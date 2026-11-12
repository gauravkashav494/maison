// EcomVision sync agent: installed once on the PC that holds a client's database.
package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

// publicKeyBase64 is set at build time by build.sh.
var publicKeyBase64 string

type settings struct {
	APIURL     string `json:"api_url"`
	Token      string `json:"token"`
	Connection string `json:"connection"`
}

func loadSettings(dir string) (*settings, error) {
	data, err := os.ReadFile(filepath.Join(dir, "settings.json"))
	if err != nil {
		return nil, err
	}
	var loaded settings
	return &loaded, json.Unmarshal(data, &loaded)
}

// runOnce is what the worker does: one sync, then exit.
func runOnce() int {
	executable, err := os.Executable()
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return exitSyncFailed
	}
	dir := filepath.Dir(executable)

	config, err := loadSettings(dir)
	if err != nil {
		fmt.Fprintln(os.Stderr, "settings:", err)
		return exitSyncFailed
	}
	store, err := openStore(config.Connection)
	if err != nil {
		fmt.Fprintln(os.Stderr, "database:", err)
		return exitSyncFailed
	}
	defer store.Close()

	version, _ := sha256File(executable)
	engine := &Engine{
		API:       NewAPI(config.APIURL, config.Token),
		DB:        store,
		StatePath: filepath.Join(dir, "state.json"),
		Version:   version,
	}
	if err := engine.Run(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return exitSyncFailed
	}
	return 0
}

func main() {
	if len(os.Args) > 1 && os.Args[1] == "once" {
		os.Exit(runOnce())
	}
	if err := platformMain(os.Args[1:]); err != nil {
		fmt.Fprintln(os.Stderr, "Error:", err)
		os.Exit(1)
	}
}
