//go:build windows

package main

import (
	"bufio"
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/mgr"
)

const (
	serviceName  = "EcomVisionSyncAgent"
	serviceTitle = "EcomVision Sync Agent"
	syncEvery    = 5 * time.Minute
	logLimit     = 1 << 20
)

// folderName is the agent's own folder: setup always installs into a folder of this name, so it
// never takes over (and locks) a folder holding anything else.
const folderName = "EcomVisionAgent"

func defaultInstallDir() string {
	return filepath.Join(os.Getenv("ProgramData"), folderName)
}

// runningDir is the folder of the running program: the installed service and worker live in the
// install folder, wherever setup put it.
func runningDir() string {
	executable, err := os.Executable()
	if err != nil {
		return defaultInstallDir()
	}
	return filepath.Dir(executable)
}

// installedDir is the folder of the installed service, read from Windows ("" when none is installed).
func installedDir(manager *mgr.Mgr) string {
	existing, err := manager.OpenService(serviceName)
	if err != nil {
		return ""
	}
	defer existing.Close()
	config, err := existing.Config()
	if err != nil {
		return ""
	}
	binary := strings.Trim(config.BinaryPathName, `" `)
	if binary == "" {
		return ""
	}
	return filepath.Dir(binary)
}

// askInstallDir asks where to install, offering current (the existing install's folder, or the
// default). The answer is a local fixed drive; the agent goes into its own EcomVisionAgent folder
// there, which must be new, empty or already the agent's.
func askInstallDir(current string) (string, error) {
	fmt.Printf("\nInstall folder (press Enter for %s)\n", current)
	return chooseInstallDir(ask("Folder"), current)
}

// chooseInstallDir turns the answer to the install-folder question into the folder to use.
func chooseInstallDir(answer, current string) (string, error) {
	answer = strings.Trim(answer, `" `)
	if answer == "" {
		return current, nil
	}

	dir, err := filepath.Abs(answer)
	if err != nil {
		return "", err
	}
	volume := filepath.VolumeName(dir)
	if volume == "" || strings.HasPrefix(volume, `\\`) {
		return "", errors.New("choose a folder on a drive of this PC, e.g. D:\\Apps (not a network path)")
	}
	root, _ := windows.UTF16PtrFromString(volume + `\`)
	if windows.GetDriveType(root) != windows.DRIVE_FIXED {
		// The service runs as SYSTEM: it cannot see mapped network drives, and a removable one may be gone.
		return "", fmt.Errorf("%s is not a fixed local drive; the agent runs as a Windows service and needs one", volume)
	}
	if !strings.EqualFold(filepath.Base(dir), folderName) {
		dir = filepath.Join(dir, folderName)
	}

	if !strings.EqualFold(dir, current) {
		entries, err := os.ReadDir(dir)
		if err == nil && len(entries) > 0 && !isAgentFolder(dir) {
			return "", fmt.Errorf("%s already holds other files; choose another folder", dir)
		}
	}
	return dir, nil
}

// isAgentFolder is true for a folder an earlier install of the agent made.
func isAgentFolder(dir string) bool {
	_, err := os.Stat(filepath.Join(dir, "service.exe"))
	return err == nil
}

func platformMain(args []string) error {
	isService, err := svc.IsWindowsService()
	if err != nil {
		return err
	}
	if isService {
		return svc.Run(serviceName, &service{})
	}

	// The installer's commands (installer/agent.iss): no questions, no "press Enter", and the
	// outcome in --message-file for the wizard to show. The checks need no Administrator rights.
	if len(args) > 0 {
		switch args[0] {
		case "check-code", "install":
			return installerCommand(args[0], args[1:])
		case "uninstall":
			if len(args) > 1 && args[1] == "--quiet" {
				return uninstall()
			}
		}
	}

	if !windows.GetCurrentProcessToken().IsElevated() {
		return relaunchElevated(args)
	}

	if len(args) > 0 && args[0] == "uninstall" {
		err = uninstall()
	} else {
		err = setup()
	}
	if err != nil {
		fmt.Println("\nSetup failed:", err)
	}
	fmt.Print("\nPress Enter to close this window.")
	_, _ = bufio.NewReader(os.Stdin).ReadString('\n')
	return nil
}

func relaunchElevated(args []string) error {
	executable, err := os.Executable()
	if err != nil {
		return err
	}
	verb, _ := windows.UTF16PtrFromString("runas")
	file, _ := windows.UTF16PtrFromString(executable)
	parameters, _ := windows.UTF16PtrFromString(strings.Join(args, " "))
	return windows.ShellExecute(0, verb, file, parameters, nil, windows.SW_NORMAL)
}

// ---- Service ----------------------------------------------------------------------------

type service struct{}

func (*service) Execute(_ []string, requests <-chan svc.ChangeRequest, status chan<- svc.Status) (bool, uint32) {
	status <- svc.Status{State: svc.StartPending}

	logf := openLog()
	ctx, cancel := context.WithCancel(context.Background())
	var running sync.WaitGroup
	running.Add(1)
	go func() {
		defer running.Done()
		serviceLoop(ctx, logf)
	}()

	status <- svc.Status{State: svc.Running, Accepts: svc.AcceptStop | svc.AcceptShutdown}
	for request := range requests {
		switch request.Cmd {
		case svc.Interrogate:
			status <- request.CurrentStatus
		case svc.Stop, svc.Shutdown:
			status <- svc.Status{State: svc.StopPending}
			cancel()
			running.Wait()
			return false, 0
		}
	}
	cancel()
	running.Wait()
	return false, 0
}

func serviceLoop(ctx context.Context, logf func(string, ...any)) {
	dir := runningDir()
	timer := time.NewTimer(0)
	defer timer.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-timer.C:
		}

		if config, err := loadSettings(dir); err != nil {
			logf("settings: %v", err)
		} else {
			runCycle(ctx, &Updater{API: NewAPI(config.APIURL, config.Token), Dir: dir, Arch: currentArch(), PublicKey: publicKeyBase64}, logf)
		}
		timer.Reset(syncEvery)
	}
}

func openLog() func(string, ...any) {
	path := filepath.Join(runningDir(), "agent.log")
	return func(format string, args ...any) {
		if info, err := os.Stat(path); err == nil && info.Size() > logLimit {
			_ = os.Rename(path, path+".old")
		}
		file, err := os.OpenFile(path, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o600)
		if err != nil {
			return
		}
		defer file.Close()
		log.New(file, "", log.LstdFlags).Printf(format, args...)
	}
}

// ---- Setup ------------------------------------------------------------------------------

var input = bufio.NewReader(os.Stdin)

func ask(prompt string) string {
	fmt.Print(prompt + ": ")
	line, _ := input.ReadString('\n')
	return strings.TrimSpace(line)
}

func decodeSetupCode(code string) (apiURL, token string, err error) {
	raw, err := base64.RawURLEncoding.DecodeString(strings.TrimPrefix(code, "EV1."))
	parts := strings.SplitN(string(raw), "\n", 2)
	if err != nil || !strings.HasPrefix(code, "EV1.") || len(parts) != 2 || !strings.HasPrefix(parts[0], "https://") {
		return "", "", errors.New("that setup code is not valid")
	}
	return parts[0], parts[1], nil
}

// checkSetupCode is everything the setup needs to know before installing, from the setup code
// alone: whose store it is, where the billing software is (set in the store's panel), and that
// the billing software answers there. Used by the console setup and the installer (check-code).
// summary is one line for the person: the store and the software it reached.
func checkSetupCode(code string) (apiURL, token, summary string, err error) {
	apiURL, token, err = decodeSetupCode(strings.TrimSpace(code))
	if err != nil {
		return "", "", "", err
	}
	api := NewAPI(apiURL, token)
	var ping struct {
		Client string `json:"client"`
	}
	if err := api.Call("GET", "ping", nil, &ping); err != nil {
		return "", "", "", fmt.Errorf("could not reach your online store with this code: %w", err)
	}

	local, err := localConfig(api)
	if err != nil {
		return "", "", "", err
	}
	software, err := checkLocalAPI(local)
	if err != nil {
		return "", "", "", err
	}
	return apiURL, token, fmt.Sprintf("online store %s and billing software %s", ping.Client, software), nil
}

// checkLocalAPI checks the billing software's API with GET /health and names the software. The
// address and key come from the store's panel, so each failure says what to fix there.
func checkLocalAPI(local *LocalAPI) (string, error) {
	where := "in your store's panel (Shop software sync > Billing software connection)"
	parsed, err := url.Parse(local.BaseURL)
	if err != nil || (parsed.Scheme != "http" && parsed.Scheme != "https") || parsed.Host == "" {
		return "", fmt.Errorf("the billing software's address %q is not a full address; correct it %s", local.BaseURL, where)
	}

	var health struct {
		OK       bool   `json:"ok"`
		Software string `json:"software"`
		Version  string `json:"version"`
	}
	if err := local.Do("GET", "health", nil, nil, &health); err != nil {
		var answer *localError
		switch {
		case isLocalStatus(err, http.StatusUnauthorized) || isLocalStatus(err, http.StatusForbidden):
			return "", fmt.Errorf("the billing software refused the API key; check it with the software's developer and correct it %s", where)
		case isLocalStatus(err, http.StatusNotFound):
			return "", fmt.Errorf("something answers at %s, but not the billing software's API; check the address %s", local.BaseURL, where)
		case errors.As(err, &answer):
			return "", fmt.Errorf("the billing software answered with an error: %s", answer.Detail)
		default:
			return "", fmt.Errorf("nothing answered at %s. Check that the billing software is running on this PC (or on the shop's network), and that the address %s is right", local.BaseURL, where)
		}
	}
	if !health.OK {
		return "", errors.New(`the billing software answered, but its health check did not say "ok": true`)
	}
	return strings.TrimSpace(health.Software + " " + health.Version), nil
}

func secureFolder(dir string) error {
	descriptor, err := windows.SecurityDescriptorFromString("D:PAI(A;OICI;FA;;;SY)(A;OICI;FA;;;BA)")
	if err != nil {
		return err
	}
	dacl, _, err := descriptor.DACL()
	if err != nil {
		return err
	}
	return windows.SetNamedSecurityInfo(dir, windows.SE_FILE_OBJECT,
		windows.DACL_SECURITY_INFORMATION|windows.PROTECTED_DACL_SECURITY_INFORMATION, nil, nil, dacl, nil)
}

func copyFile(from, to string) error {
	source, err := os.Open(from)
	if err != nil {
		return err
	}
	defer source.Close()
	target, err := os.Create(to)
	if err != nil {
		return err
	}
	defer target.Close()
	_, err = io.Copy(target, source)
	return err
}

func removeService(manager *mgr.Mgr) error {
	existing, err := manager.OpenService(serviceName)
	if err != nil {
		return nil // not installed
	}
	_, _ = existing.Control(svc.Stop)
	for i := 0; i < 60; i++ {
		state, err := existing.Query()
		if err != nil || state.State == svc.Stopped {
			break
		}
		time.Sleep(time.Second)
	}
	err = existing.Delete()
	existing.Close()
	if err != nil {
		return err
	}

	for i := 0; i < 20; i++ {
		probe, err := manager.OpenService(serviceName)
		if err != nil {
			return nil
		}
		probe.Close()
		time.Sleep(500 * time.Millisecond)
	}
	return errors.New("the old service is still being removed. Restart the PC and try again")
}

// installerCommand runs one step for the installer and writes one line to --message-file: what
// to show the person, or why it failed. The exit code says which: 0 is success. The setup code is
// all it needs: the billing software's address and key come from the store's panel.
//
//	check-code --code EV1.… --message-file F
//	install    --code EV1.… [--dir D] --message-file F
func installerCommand(name string, args []string) error {
	flags := flag.NewFlagSet(name, flag.ContinueOnError)
	code := flags.String("code", "", "setup code")
	dir := flags.String("dir", "", "install folder; empty keeps the default or the current one")
	messageFile := flags.String("message-file", "", "where to write the outcome")
	if err := flags.Parse(args); err != nil {
		return err
	}

	var message string
	var err error
	switch name {
	case "check-code":
		_, _, message, err = checkSetupCode(*code)
	case "install":
		var server, token string
		if server, token, _, err = checkSetupCode(*code); err == nil {
			err = install(settings{APIURL: server, Token: token}, dir)
			message = "installed"
		}
	}
	if err != nil {
		message = err.Error()
	}
	if *messageFile != "" {
		_ = os.WriteFile(*messageFile, []byte(message), 0o600)
	}
	if err != nil {
		os.Exit(1)
	}
	return nil
}

// setup is the console setup: run the program by itself and paste the setup code.
func setup() error {
	apiURL, token, summary, err := checkSetupCode(ask("Paste the setup code"))
	if err != nil {
		return err
	}
	fmt.Println("Connected to", summary)
	return install(settings{APIURL: apiURL, Token: token}, nil)
}

// install puts the service in place with these settings. folder is nil to ask for the install
// folder, or the installer's answer ("" for the default, or where the agent is now).
func install(config settings, folder *string) error {
	encoded, _ := json.Marshal(config)
	settingsJSON := string(encoded)

	manager, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer manager.Disconnect()

	// A reinstall offers the folder the agent is in now.
	previous := installedDir(manager)
	current := previous
	if current == "" {
		current = defaultInstallDir()
	}
	var dir string
	if folder == nil {
		dir, err = askInstallDir(current)
	} else {
		dir, err = chooseInstallDir(*folder, current)
	}
	if err != nil {
		return err
	}

	if err := removeService(manager); err != nil {
		return err
	}
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	if err := secureFolder(dir); err != nil {
		return fmt.Errorf("could not secure the agent folder: %w", err)
	}
	// Moving to another folder: the sync bookmarks go along, so nothing is read or sent twice.
	moved := previous != "" && !strings.EqualFold(previous, dir) && isAgentFolder(previous)
	if moved {
		if _, err := os.Stat(filepath.Join(previous, "state.json")); err == nil {
			if err := copyFile(filepath.Join(previous, "state.json"), filepath.Join(dir, "state.json")); err != nil {
				return fmt.Errorf("could not move the sync state: %w", err)
			}
		}
	}

	if err := os.WriteFile(filepath.Join(dir, "settings.json"), []byte(settingsJSON), 0o600); err != nil {
		return err
	}

	executable, err := os.Executable()
	if err != nil {
		return err
	}
	servicePath := filepath.Join(dir, "service.exe")
	for _, target := range []string{servicePath, filepath.Join(dir, workerName)} {
		if !strings.EqualFold(target, executable) {
			if err := copyFile(executable, target); err != nil {
				return err
			}
		}
	}

	created, err := manager.CreateService(serviceName, servicePath, mgr.Config{
		DisplayName: serviceTitle,
		Description: "Keeps this shop's software in sync with EcomVision.",
		StartType:   mgr.StartAutomatic,
	})
	if err != nil {
		return err
	}
	defer created.Close()
	_ = created.SetRecoveryActions([]mgr.RecoveryAction{{Type: mgr.ServiceRestart, Delay: time.Minute}}, 86400)
	if err := created.Start(); err != nil {
		return err
	}
	if moved && !strings.EqualFold(filepath.Dir(executable), previous) {
		// Only the agent's own old folder, and only once the new install is running.
		if err := os.RemoveAll(previous); err != nil {
			fmt.Println("Could not remove the old folder", previous, "- delete it by hand:", err)
		}
	}

	fmt.Println("\nInstalled in", dir)
	fmt.Println("The agent now starts with Windows, syncs every 5 minutes and updates itself.")
	fmt.Println("You do not need to run this file again.")
	return nil
}

func uninstall() error {
	manager, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer manager.Disconnect()

	// Wherever setup put it: Windows knows from the service.
	dir := installedDir(manager)
	if dir == "" {
		dir = defaultInstallDir()
	}
	if err := removeService(manager); err != nil {
		return err
	}
	// Only a folder that is the agent's own is deleted.
	if strings.EqualFold(filepath.Base(dir), folderName) && isAgentFolder(dir) {
		if err := os.RemoveAll(dir); err != nil {
			return err
		}
	}
	fmt.Println("The agent has been removed from", dir)
	return nil
}
