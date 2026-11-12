//go:build windows

package main

import (
	"bufio"
	"context"
	"database/sql"
	"encoding/base64"
	"errors"
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
	"unsafe"

	_ "github.com/alexbrainman/odbc"
	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/registry"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/mgr"
	"golang.org/x/term"
)

const (
	serviceName  = "EcomVisionSyncAgent"
	serviceTitle = "EcomVision Sync Agent"
	syncEvery    = 5 * time.Minute
	logLimit     = 1 << 20
)

func installDir() string {
	return filepath.Join(os.Getenv("ProgramData"), "EcomVisionAgent")
}

func platformMain(args []string) error {
	isService, err := svc.IsWindowsService()
	if err != nil {
		return err
	}
	if isService {
		return svc.Run(serviceName, &service{})
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
	dir := installDir()
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
	path := filepath.Join(installDir(), "agent.log")
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

func driverNames(key string) []string {
	opened, err := registry.OpenKey(registry.LOCAL_MACHINE, key, registry.QUERY_VALUE)
	if err != nil {
		return nil
	}
	defer opened.Close()
	names, _ := opened.ReadValueNames(-1)
	return names
}

func findDriver(pattern *regexp.Regexp) string {
	matches := []string{}
	for _, name := range driverNames(`SOFTWARE\ODBC\ODBCINST.INI\ODBC Drivers`) {
		if pattern.MatchString(name) {
			matches = append(matches, name)
		}
	}
	sort.Slice(matches, func(i, j int) bool {
		a, b := strings.HasPrefix(matches[i], "ODBC"), strings.HasPrefix(matches[j], "ODBC")
		if a != b {
			return a
		}
		return matches[i] > matches[j]
	})
	if len(matches) == 0 {
		return ""
	}
	return matches[0]
}

// missingDriver explains a missing driver, including when this is the wrong build for it.
func missingDriver(pattern *regexp.Regexp, install string) error {
	for _, name := range driverNames(`SOFTWARE\WOW6432Node\ODBC\ODBCINST.INI\ODBC Drivers`) {
		if currentArch() == "amd64" && pattern.MatchString(name) {
			return errors.New("the database driver on this PC is 32-bit. Run ecomvision-agent-386.exe instead")
		}
	}
	return errors.New("no database driver found. Install " + install + ", then run this again")
}

var (
	accessDriverPattern = regexp.MustCompile(`^Microsoft Access Driver \(\*\.mdb, \*\.accdb\)$`)
	sqlDriverPattern    = regexp.MustCompile(`^(ODBC Driver \d+ for SQL Server|SQL Server Native Client [\d.]+|SQL Server)$`)
)

const (
	accessInstall = `"Microsoft Access Database Engine 2016 Redistributable"`
	sqlInstall    = `"ODBC Driver 18 for SQL Server"`
	searchLimit   = 10 * time.Minute
)

func fixedDrives() []string {
	mask, err := windows.GetLogicalDrives()
	if err != nil {
		return nil
	}
	drives := []string{}
	for i := 0; i < 26; i++ {
		if mask&(1<<uint(i)) == 0 {
			continue
		}
		root := string(rune('A'+i)) + `:\`
		if windows.GetDriveType(wide(root)) == windows.DRIVE_FIXED {
			drives = append(drives, root)
		}
	}
	return drives
}

// sqlInstances lists SQL Server installations on this PC, in both 32-bit and 64-bit registry views.
func sqlInstances() []string {
	seen := map[string]bool{}
	servers := []string{}
	for _, key := range []string{`SOFTWARE\Microsoft\Microsoft SQL Server\Instance Names\SQL`, `SOFTWARE\WOW6432Node\Microsoft\Microsoft SQL Server\Instance Names\SQL`} {
		for _, name := range driverNames(key) {
			server := `localhost\` + name
			if strings.EqualFold(name, "MSSQLSERVER") {
				server = "localhost"
			}
			if !seen[server] {
				seen[server] = true
				servers = append(servers, server)
			}
		}
	}
	sort.Strings(servers)
	return servers
}

func accessConnection(driver, file string) string {
	return fmt.Sprintf("Driver={%s};Dbq=%s;", driver, file)
}

// pick asks which database to use. The empty string means the person wants to type it.
func pick(kind string, options []candidate) string {
	fmt.Printf("\nFound more than one possible %s:\n", kind)
	for i, option := range options {
		fmt.Printf("%d) %s", i+1, option.Name)
		if !option.Modified.IsZero() {
			fmt.Printf("  (changed %s)", option.Modified.Format("2 Jan 2006 15:04"))
		}
		fmt.Printf("  [%d tables]\n", len(option.Tables))
	}
	fmt.Println("0) None of these, I will type it")

	choice, err := strconv.Atoi(ask("Enter a number"))
	if err != nil || choice < 1 || choice > len(options) {
		return ""
	}
	return options[choice-1].Name
}

func askAccessPath(driver string) (string, error) {
	file := strings.Trim(ask("Full path to the database file"), `" `)
	if _, err := os.Stat(file); err != nil {
		return "", fmt.Errorf("file not found: %s", file)
	}
	return accessConnection(driver, file), nil
}

func chooseAccess(driver string, usable []candidate, required []string) (string, error) {
	auto, options := choose(usable, required)
	if auto != nil {
		fmt.Println("Found the database:", auto.Name)
		return accessConnection(driver, auto.Name), nil
	}
	if len(options) == 0 {
		return askAccessPath(driver)
	}
	if len(options) == 1 {
		fmt.Println("\nFound:", options[0].Name)
		if strings.EqualFold(ask("Is this the shop's database? (y/n)"), "y") {
			return accessConnection(driver, options[0].Name), nil
		}
		return askAccessPath(driver)
	}
	if chosen := pick("database file", options); chosen != "" {
		return accessConnection(driver, chosen), nil
	}
	return askAccessPath(driver)
}

func sqlConnection(driver, server, database, user, password string) string {
	return fmt.Sprintf("Driver={%s};Server=%s;Database=%s;Uid=%s;Pwd={%s};TrustServerCertificate=yes;",
		driver, server, database, user, strings.ReplaceAll(password, "}", "}}"))
}

func chooseSQLServer(driver string, instances, required []string) (string, error) {
	server := ""
	switch len(instances) {
	case 0:
		server = ask(`Server name (for example localhost\SQLEXPRESS)`)
	case 1:
		server = instances[0]
		fmt.Println("Found SQL Server:", server)
	default:
		options := make([]candidate, len(instances))
		for i, instance := range instances {
			options[i] = candidate{Name: instance}
		}
		if server = pick("SQL Server", options); server == "" {
			server = ask(`Server name (for example localhost\SQLEXPRESS)`)
		}
	}

	user := ask("SQL login")
	fmt.Print("Password: ")
	secret, err := term.ReadPassword(int(os.Stdin.Fd()))
	fmt.Println()
	if err != nil {
		return "", err
	}
	password := string(secret)

	names, err := sqlDatabases(sqlConnection(driver, server, "master", user, password))
	if err != nil {
		return "", fmt.Errorf("could not sign in to SQL Server: %w", err)
	}

	found := make([]candidate, len(names))
	for i, name := range names {
		found[i] = candidate{Name: name}
	}
	usable := inspectCandidates(found, func(name string) (Schema, error) {
		return readSchema(sqlConnection(driver, server, name, user, password))
	})

	auto, options := choose(usable, required)
	database := ""
	switch {
	case auto != nil:
		database = auto.Name
		fmt.Println("Found the database:", database)
	case len(options) == 1:
		database = options[0].Name
		fmt.Println("Found the database:", database)
	case len(options) > 1:
		database = pick("database", options)
	}
	if database == "" {
		database = ask("Database name")
	}
	return sqlConnection(driver, server, database, user, password), nil
}

func sqlDatabases(connection string) ([]string, error) {
	store, err := openStore(connection)
	if err != nil {
		return nil, err
	}
	defer store.Close()

	rows, err := store.Query("SELECT name FROM sys.databases WHERE database_id > 4")
	if err != nil {
		return nil, err
	}
	names := make([]string, 0, len(rows))
	for _, row := range rows {
		names = append(names, text(row["name"]))
	}
	return names, nil
}

// askConnection finds the shop's database, asking only about what it cannot settle itself.
func askConnection(api *API) (string, error) {
	var config struct {
		Mapping map[string]*Section `json:"mapping"`
	}
	_ = api.Call("GET", "agent/config", nil, &config) // no mapping yet is normal
	required := requiredTables(config.Mapping)

	fmt.Println("\nSearching this PC for the shop's database. This can take a few minutes...")
	files := findAccessFiles(fixedDrives(), time.Now().Add(searchLimit))
	instances := sqlInstances()

	accessDriver := findDriver(accessDriverPattern)
	sqlDriver := findDriver(sqlDriverPattern)

	if len(files) > 0 && accessDriver == "" && len(instances) == 0 {
		return "", missingDriver(accessDriverPattern, accessInstall)
	}

	usable := []candidate{}
	if accessDriver != "" {
		usable = inspectCandidates(files, func(file string) (Schema, error) {
			return readSchema(accessConnection(accessDriver, file))
		})
	}

	// The mapping already points at one Access database: nothing to ask.
	if auto, _ := choose(usable, required); auto != nil {
		return chooseAccess(accessDriver, usable, required)
	}

	switch {
	case len(usable) > 0 && len(instances) == 0:
		return chooseAccess(accessDriver, usable, required)
	case len(usable) == 0 && len(instances) > 0:
		if sqlDriver == "" {
			return "", missingDriver(sqlDriverPattern, sqlInstall)
		}
		return chooseSQLServer(sqlDriver, instances, required)
	}

	if len(usable) == 0 {
		fmt.Println("No database was found automatically.")
	} else {
		fmt.Println("This PC has both Access files and SQL Server.")
	}
	fmt.Println("\n1) MS Access file (.accdb or .mdb)\n2) MS SQL Server")
	switch ask("Which database does this shop use? (1 or 2)") {
	case "1":
		if accessDriver == "" {
			return "", missingDriver(accessDriverPattern, accessInstall)
		}
		return chooseAccess(accessDriver, usable, required)
	case "2":
		if sqlDriver == "" {
			return "", missingDriver(sqlDriverPattern, sqlInstall)
		}
		return chooseSQLServer(sqlDriver, instances, required)
	}
	return "", errors.New("please enter 1 or 2")
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

func setup() error {
	apiURL, token, err := decodeSetupCode(ask("Paste the setup code"))
	if err != nil {
		return err
	}

	api := NewAPI(apiURL, token)
	var ping struct {
		Client string `json:"client"`
	}
	if err := api.Call("GET", "ping", nil, &ping); err != nil {
		return fmt.Errorf("could not reach EcomVision: %w", err)
	}
	fmt.Println("Connected to EcomVision as client:", ping.Client)

	connection, err := askConnection(api)
	if err != nil {
		return err
	}
	store, err := openStore(connection)
	if err != nil {
		return fmt.Errorf("could not open the database: %w", err)
	}
	schema, err := store.Schema()
	store.Close()
	if err != nil {
		return fmt.Errorf("could not read the database's tables: %w", err)
	}
	fmt.Printf("Database connection OK. Found %d tables.\n", len(schema))

	manager, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer manager.Disconnect()
	if err := removeService(manager); err != nil {
		return err
	}

	dir := installDir()
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	if err := secureFolder(dir); err != nil {
		return fmt.Errorf("could not secure the agent folder: %w", err)
	}

	encoded := fmt.Sprintf(`{"api_url":%q,"token":%q,"connection":%q}`, apiURL, token, connection)
	if err := os.WriteFile(filepath.Join(dir, "settings.json"), []byte(encoded), 0o600); err != nil {
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
		Description: "Keeps this shop's database in sync with EcomVision.",
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

	fmt.Println("\nInstalled. The agent now starts with Windows, syncs every 5 minutes and updates itself.")
	fmt.Println("You do not need to run this file again.")
	return nil
}

func uninstall() error {
	manager, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer manager.Disconnect()
	if err := removeService(manager); err != nil {
		return err
	}
	if err := os.RemoveAll(installDir()); err != nil {
		return err
	}
	fmt.Println("The agent has been removed.")
	return nil
}

// ---- Database ---------------------------------------------------------------------------

func openStore(connection string) (Store, error) {
	db, err := sql.Open("odbc", connection)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(1)
	if err := db.Ping(); err != nil {
		db.Close()
		return nil, err
	}
	return &sqlStore{db: db, schema: func() (Schema, error) { return readSchema(connection) }}, nil
}

// database/sql has no way to list tables, so the two catalog calls go straight to ODBC.
var (
	odbc32           = windows.NewLazySystemDLL("odbc32.dll")
	sqlAllocHandle   = odbc32.NewProc("SQLAllocHandle")
	sqlFreeHandle    = odbc32.NewProc("SQLFreeHandle")
	sqlSetEnvAttr    = odbc32.NewProc("SQLSetEnvAttr")
	sqlDriverConnect = odbc32.NewProc("SQLDriverConnectW")
	sqlDisconnect    = odbc32.NewProc("SQLDisconnect")
	sqlTables        = odbc32.NewProc("SQLTablesW")
	sqlColumns       = odbc32.NewProc("SQLColumnsW")
	sqlFetch         = odbc32.NewProc("SQLFetch")
	sqlGetData       = odbc32.NewProc("SQLGetData")
)

const (
	handleEnv     = 1
	handleDbc     = 2
	handleStmt    = 3
	attrVersion   = 200
	odbcVersion3  = 3
	nullTerm      = ^uintptr(2) // SQL_NTS (-3)
	charTypeWide  = ^uintptr(7) // SQL_C_WCHAR (-8)
	returnNoData  = 100
	returnMaxGood = 1
)

func ok(code uintptr) bool {
	return int16(code) >= 0 && int16(code) <= returnMaxGood
}

func wide(text string) *uint16 {
	pointer, _ := windows.UTF16PtrFromString(text)
	return pointer
}

func allocHandle(kind, parent uintptr) (uintptr, error) {
	var handle uintptr
	code, _, _ := sqlAllocHandle.Call(kind, parent, uintptr(unsafe.Pointer(&handle)))
	if !ok(code) {
		return 0, fmt.Errorf("ODBC could not allocate a handle (%d)", int16(code))
	}
	return handle, nil
}

func readResults(statement uintptr, columns []uintptr, use func([]string)) error {
	buffer := make([]uint16, 512)
	for {
		code, _, _ := sqlFetch.Call(statement)
		if int16(code) == returnNoData {
			return nil
		}
		if !ok(code) {
			return fmt.Errorf("ODBC fetch failed (%d)", int16(code))
		}

		values := make([]string, len(columns))
		for i, column := range columns {
			var length int // SQLLEN: the size of a pointer, as int is on both builds
			code, _, _ := sqlGetData.Call(statement, column, charTypeWide,
				uintptr(unsafe.Pointer(&buffer[0])), uintptr(len(buffer)*2), uintptr(unsafe.Pointer(&length)))
			if !ok(code) {
				return fmt.Errorf("ODBC read failed (%d)", int16(code))
			}
			if length >= 0 {
				values[i] = windows.UTF16ToString(buffer)
			}
		}
		use(values)
	}
}

func readSchema(connection string) (Schema, error) {
	env, err := allocHandle(handleEnv, 0)
	if err != nil {
		return nil, err
	}
	defer sqlFreeHandle.Call(handleEnv, env)
	sqlSetEnvAttr.Call(env, attrVersion, odbcVersion3, 0)

	dbc, err := allocHandle(handleDbc, env)
	if err != nil {
		return nil, err
	}
	defer sqlFreeHandle.Call(handleDbc, dbc)

	var outLength int16
	code, _, _ := sqlDriverConnect.Call(dbc, 0, uintptr(unsafe.Pointer(wide(connection))), nullTerm, 0, 0, uintptr(unsafe.Pointer(&outLength)), 0)
	if !ok(code) {
		return nil, fmt.Errorf("ODBC could not connect (%d)", int16(code))
	}
	defer sqlDisconnect.Call(dbc)

	statement, err := allocHandle(handleStmt, dbc)
	if err != nil {
		return nil, err
	}
	code, _, _ = sqlTables.Call(statement, 0, 0, 0, 0, 0, 0, uintptr(unsafe.Pointer(wide("TABLE,VIEW"))), nullTerm)
	if !ok(code) {
		sqlFreeHandle.Call(handleStmt, statement)
		return nil, fmt.Errorf("ODBC could not list tables (%d)", int16(code))
	}

	schema := Schema{}
	err = readResults(statement, []uintptr{2, 3}, func(row []string) {
		owner, name := strings.ToLower(row[0]), row[1]
		if owner == "sys" || owner == "information_schema" || strings.HasPrefix(name, "MSys") {
			return
		}
		schema[strings.ToLower(name)] = &Table{Name: name, Columns: map[string]Column{}}
	})
	sqlFreeHandle.Call(handleStmt, statement)
	if err != nil {
		return nil, err
	}

	for _, table := range schema {
		statement, err := allocHandle(handleStmt, dbc)
		if err != nil {
			return nil, err
		}
		code, _, _ := sqlColumns.Call(statement, 0, 0, 0, 0, uintptr(unsafe.Pointer(wide(table.Name))), nullTerm, 0, 0)
		if !ok(code) {
			sqlFreeHandle.Call(handleStmt, statement)
			return nil, fmt.Errorf("ODBC could not list the columns of %s (%d)", table.Name, int16(code))
		}

		current := table
		err = readResults(statement, []uintptr{3, 4, 6}, func(row []string) {
			if strings.EqualFold(row[0], current.Name) { // the name is a pattern: _ matches any character
				current.Columns[strings.ToLower(row[1])] = Column{Name: row[1], Type: row[2]}
			}
		})
		sqlFreeHandle.Call(handleStmt, statement)
		if err != nil {
			return nil, err
		}
	}
	return schema, nil
}
