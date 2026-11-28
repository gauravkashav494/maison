package main

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheInstallFolderIsTheAgentsOwnOnALocalDrive(t *testing.T) {
	base := t.TempDir() // on the system drive: a fixed local disk
	current := defaultInstallDir()

	// Enter keeps the offered folder.
	if dir, err := chooseInstallDir("  ", current); err != nil || dir != current {
		t.Fatalf("blank answer: %q %v", dir, err)
	}

	// A chosen folder gets the agent's own folder inside it, never the folder itself.
	dir, err := chooseInstallDir(`"`+base+`"`, current)
	if err != nil || dir != filepath.Join(base, folderName) {
		t.Fatalf("chosen folder: %q %v", dir, err)
	}
	// Naming the agent's folder itself does not nest it twice.
	if again, err := chooseInstallDir(dir, current); err != nil || again != dir {
		t.Fatalf("agent folder named: %q %v", again, err)
	}

	// A folder of that name holding someone else's files is refused...
	foreign := filepath.Join(base, "other")
	if err := os.MkdirAll(filepath.Join(foreign, folderName), 0o700); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(foreign, folderName, "notes.txt"), []byte("x"), 0o600); err != nil {
		t.Fatal(err)
	}
	if _, err := chooseInstallDir(foreign, current); err == nil || !strings.Contains(err.Error(), "other files") {
		t.Fatalf("a folder with other files should be refused: %v", err)
	}

	// ...but an earlier install of the agent is fine to install over.
	if err := os.WriteFile(filepath.Join(foreign, folderName, "service.exe"), []byte("x"), 0o600); err != nil {
		t.Fatal(err)
	}
	if dir, err := chooseInstallDir(foreign, current); err != nil || dir != filepath.Join(foreign, folderName) {
		t.Fatalf("earlier install: %q %v", dir, err)
	}

	// Network paths are refused: the service runs as SYSTEM and cannot reach them.
	if _, err := chooseInstallDir(`\\server\share\apps`, current); err == nil {
		t.Fatal("a network path should be refused")
	}
}
