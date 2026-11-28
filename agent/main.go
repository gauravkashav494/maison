// EcomVision sync agent: installed once on a PC in the shop, where it reaches the shop software's
// own API (docs/LOCAL-API.md) on the local network.
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
	// EcomVision, from the setup code. Where the shop software is comes from EcomVision too
	// (localConfig), so it is set in the store's panel, not here.
	APIURL string `json:"api_url"`
	Token  string `json:"token"`
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
	version, _ := sha256File(executable)
	engine := &Engine{
		API:       NewAPI(config.APIURL, config.Token),
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
