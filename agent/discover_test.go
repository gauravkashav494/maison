package main

import (
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func put(t *testing.T, root, name string, age time.Duration) string {
	t.Helper()
	path := filepath.Join(root, filepath.FromSlash(name))
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte("x"), 0o644); err != nil {
		t.Fatal(err)
	}
	when := time.Now().Add(-age)
	if err := os.Chtimes(path, when, when); err != nil {
		t.Fatal(err)
	}
	return path
}

func TestFindsAccessFilesNewestFirstAndSkipsSystemFolders(t *testing.T) {
	root := t.TempDir()
	old := put(t, root, "Shop/old.mdb", 48*time.Hour)
	live := put(t, root, "Program Files (x86)/Pos/Data/live.ACCDB", time.Hour)
	put(t, root, "Windows/system/skip.mdb", 0)
	put(t, root, "Program Files/Microsoft Office/template.accdb", 0)
	put(t, root, "Shop/notes.txt", 0)
	put(t, root, "Shop/security.mdw", 0)

	found := findAccessFiles([]string{root}, time.Now().Add(time.Minute))

	if len(found) != 2 || found[0].Name != live || found[1].Name != old {
		t.Fatalf("found = %+v", found)
	}
}

func TestSearchStopsAtTheDeadline(t *testing.T) {
	root := t.TempDir()
	put(t, root, "Shop/live.mdb", 0)
	if found := findAccessFiles([]string{root}, time.Now().Add(-time.Second)); len(found) != 0 {
		t.Fatalf("found = %+v", found)
	}
}

func TestInspectDropsFilesThatCannotBeReadOrHaveNoTables(t *testing.T) {
	found := []candidate{{Name: "good"}, {Name: "locked"}, {Name: "frontend"}}
	usable := inspectCandidates(found, func(name string) (Schema, error) {
		switch name {
		case "good":
			return Schema{"items": &Table{Name: "Items"}}, nil
		case "locked":
			return nil, errors.New("password")
		}
		return Schema{}, nil
	})
	if len(usable) != 1 || usable[0].Name != "good" || !usable[0].Tables["items"] {
		t.Fatalf("usable = %+v", usable)
	}
}

func tables(names ...string) map[string]bool {
	result := map[string]bool{}
	for _, name := range names {
		result[name] = true
	}
	return result
}

func TestAMappingIdentifyingOneDatabaseIsChosenAlone(t *testing.T) {
	usable := []candidate{
		{Name: `C:\Other\accounts.mdb`, Tables: tables("ledger")},
		{Name: `D:\Pos\shop.mdb`, Tables: tables("items", "sales", "extra")},
	}
	auto, _ := choose(usable, []string{"items", "sales"})
	if auto == nil || auto.Name != `D:\Pos\shop.mdb` {
		t.Fatalf("auto = %+v", auto)
	}
}

func TestABackupOfTheSameDatabaseDoesNotBlockTheChoice(t *testing.T) {
	usable := []candidate{
		{Name: `C:\Pos\Backup\shop_backup.mdb`, Tables: tables("items")},
		{Name: `C:\Pos\shop_old.mdb`, Tables: tables("items")},
		{Name: `C:\Pos\shop.mdb`, Tables: tables("items")},
	}
	auto, _ := choose(usable, []string{"items"})
	if auto == nil || auto.Name != `C:\Pos\shop.mdb` {
		t.Fatalf("auto = %+v", auto)
	}
}

func TestSeveralMatchesAreOfferedNotGuessed(t *testing.T) {
	usable := []candidate{{Name: "a.mdb", Tables: tables("items")}, {Name: "b.mdb", Tables: tables("items")}}
	auto, options := choose(usable, []string{"items"})
	if auto != nil || len(options) != 2 {
		t.Fatalf("auto = %+v, options = %+v", auto, options)
	}
}

func TestWithoutAMappingNothingIsChosenAlone(t *testing.T) {
	auto, options := choose([]candidate{{Name: "only.mdb", Tables: tables("items")}}, nil)
	if auto != nil || len(options) != 1 {
		t.Fatalf("auto = %+v, options = %+v", auto, options)
	}
}

func TestAMappingThatMatchesNothingStillOffersWhatWasFound(t *testing.T) {
	auto, options := choose([]candidate{{Name: "a.mdb", Tables: tables("x")}}, []string{"items"})
	if auto != nil || len(options) != 1 {
		t.Fatalf("auto = %+v, options = %+v", auto, options)
	}
}

func TestOnlyBackupsAreStillOffered(t *testing.T) {
	_, options := choose([]candidate{{Name: "shop_backup.mdb", Tables: tables("x")}}, nil)
	if len(options) != 1 || !strings.Contains(options[0].Name, "backup") {
		t.Fatalf("options = %+v", options)
	}
}

func TestRequiredTablesComeFromTheMapping(t *testing.T) {
	got := requiredTables(map[string]*Section{"stock": {Table: "Items"}, "products": {Table: "items"}, "orders_in": {Table: "Sales"}})
	if strings.Join(got, ",") != "items,sales" {
		t.Fatalf("got = %v", got)
	}
}
