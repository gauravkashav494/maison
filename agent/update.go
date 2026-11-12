package main

import (
	"context"
	"crypto"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"encoding/pem"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"time"
)

const (
	workerName = "worker.exe"

	// A worker that finished but could not complete the sync (network or database trouble).
	// Anything else non-zero means the program itself failed.
	exitSyncFailed = 3

	crashesBeforeRollback = 3
	workerTimeout         = 30 * time.Minute
	maxPackageBytes       = 200 << 20
)

// Updater replaces the worker with new signed code. The installed service never replaces
// itself, so a bad release can always be rolled back or fixed by the next one.
type Updater struct {
	API       *API
	Dir       string
	Arch      string
	PublicKey string // base64 of the PEM public key, fixed at build time
}

type pendingUpdate struct {
	Version string `json:"version"`
	Crashes int    `json:"crashes"`
}

func (u *Updater) path(name string) string { return filepath.Join(u.Dir, name) }

func sha256File(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	sum := sha256.New()
	if _, err := io.Copy(sum, file); err != nil {
		return "", err
	}
	return hex.EncodeToString(sum.Sum(nil)), nil
}

func (u *Updater) readPending() *pendingUpdate {
	data, err := os.ReadFile(u.path("update-pending.json"))
	if err != nil {
		return nil
	}
	var pending pendingUpdate
	if json.Unmarshal(data, &pending) != nil {
		return nil
	}
	return &pending
}

func (u *Updater) writePending(pending pendingUpdate) error {
	data, _ := json.Marshal(pending)
	return os.WriteFile(u.path("update-pending.json"), data, 0o600)
}

func (u *Updater) badVersions() map[string]bool {
	bad := map[string]bool{}
	if data, err := os.ReadFile(u.path("bad-versions.json")); err == nil {
		_ = json.Unmarshal(data, &bad)
	}
	return bad
}

func (u *Updater) verify(digest []byte, signature string) error {
	pemBytes, err := base64.StdEncoding.DecodeString(u.PublicKey)
	if err != nil {
		return err
	}
	block, _ := pem.Decode(pemBytes)
	if block == nil {
		return errors.New("this build has no signing key, so it cannot accept updates")
	}
	parsed, err := x509.ParsePKIXPublicKey(block.Bytes)
	if err != nil {
		return err
	}
	key, ok := parsed.(*rsa.PublicKey)
	if !ok {
		return errors.New("the signing key is not RSA")
	}
	raw, err := base64.StdEncoding.DecodeString(signature)
	if err != nil {
		return err
	}
	return rsa.VerifyPKCS1v15(key, crypto.SHA256, digest, raw)
}

// Check installs a newer worker if the server has one. A new version waits until the previous
// update has proved itself.
func (u *Updater) Check() error {
	if u.readPending() != nil {
		return nil
	}

	have, err := sha256File(u.path(workerName))
	if err != nil {
		return err
	}

	var meta struct {
		Current   bool   `json:"current"`
		Version   string `json:"version"`
		Signature string `json:"signature"`
	}
	if err := u.API.Call("GET", fmt.Sprintf("agent/package?arch=%s&have=%s", u.Arch, have), nil, &meta); err != nil {
		return err
	}
	if meta.Current || u.badVersions()[meta.Version] {
		return nil
	}

	download := u.path(workerName + ".new")
	defer os.Remove(download)

	digest, err := u.download(download)
	if err != nil {
		return err
	}
	if hex.EncodeToString(digest) != meta.Version {
		return errors.New("the downloaded update does not match its version")
	}
	if err := u.verify(digest, meta.Signature); err != nil {
		return fmt.Errorf("refused an update with an invalid signature: %w", err)
	}

	if err := os.Rename(u.path(workerName), u.path(workerName+".old")); err != nil {
		return err
	}
	if err := os.Rename(download, u.path(workerName)); err != nil {
		_ = os.Rename(u.path(workerName+".old"), u.path(workerName))
		return err
	}
	return u.writePending(pendingUpdate{Version: meta.Version})
}

func (u *Updater) download(destination string) ([]byte, error) {
	response, err := u.API.open("GET", "agent/package/binary?arch="+u.Arch, nil)
	if err != nil {
		return nil, err
	}
	defer response.Body.Close()

	file, err := os.OpenFile(destination, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o700)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	sum := sha256.New()
	if _, err := io.Copy(io.MultiWriter(file, sum), io.LimitReader(response.Body, maxPackageBytes)); err != nil {
		return nil, err
	}
	return sum.Sum(nil), nil
}

// RecordRun settles a trial update: a worker that runs keeps it, repeated crashes undo it.
func (u *Updater) RecordRun(crashed bool) error {
	pending := u.readPending()
	if pending == nil {
		return nil
	}

	if !crashed {
		_ = os.Remove(u.path(workerName + ".old"))
		return os.Remove(u.path("update-pending.json"))
	}

	pending.Crashes++
	if pending.Crashes < crashesBeforeRollback {
		return u.writePending(*pending)
	}

	if err := os.Rename(u.path(workerName+".old"), u.path(workerName)); err != nil {
		return err
	}
	bad := u.badVersions()
	bad[pending.Version] = true
	data, _ := json.Marshal(bad)
	if err := os.WriteFile(u.path("bad-versions.json"), data, 0o600); err != nil {
		return err
	}
	return os.Remove(u.path("update-pending.json"))
}

// runCycle updates the worker if needed, then runs one sync in it.
func runCycle(ctx context.Context, updater *Updater, logf func(string, ...any)) {
	if err := updater.Check(); err != nil {
		logf("update check failed: %v", err)
	}

	runCtx, cancel := context.WithTimeout(ctx, workerTimeout)
	defer cancel()

	output, err := exec.CommandContext(runCtx, updater.path(workerName), "once").CombinedOutput()
	if len(output) > 0 {
		logf("%s", output)
	}
	if ctx.Err() != nil {
		return // the service is stopping; this says nothing about the worker
	}

	crashed := false
	if err != nil {
		var exit *exec.ExitError
		crashed = !errors.As(err, &exit) || exit.ExitCode() != exitSyncFailed
		logf("worker failed: %v", err)
	}
	if err := updater.RecordRun(crashed); err != nil {
		logf("could not record the worker's result: %v", err)
	}
}

func currentArch() string { return runtime.GOARCH }
