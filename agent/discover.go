package main

import (
	"io/fs"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"time"
)

const maxInspected = 25

// candidate is a database that setup found on this PC.
type candidate struct {
	Name     string // file path, or database name on SQL Server
	Modified time.Time
	Tables   map[string]bool
}

var (
	skippedFolders = map[string]bool{
		"windows": true, "$recycle.bin": true, "system volume information": true, "recovery": true,
		"perflogs": true, "node_modules": true, ".git": true, "windowsapps": true, "microsoft office": true,
	}
	backupNames = regexp.MustCompile(`(?i)back[-_ ]?up|\bbak\b|\bold\b|\bcopy\b|\barchive\b`)
)

// findAccessFiles lists Access files under roots, most recently changed first, because the
// live database is the one in daily use.
func findAccessFiles(roots []string, deadline time.Time) []candidate {
	found := []candidate{}
	for _, root := range roots {
		_ = filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
			if err != nil {
				return nil // a folder this account cannot read
			}
			if time.Now().After(deadline) {
				return filepath.SkipDir
			}
			if entry.IsDir() {
				if path != root && skippedFolders[strings.ToLower(entry.Name())] {
					return filepath.SkipDir
				}
				return nil
			}

			extension := strings.ToLower(filepath.Ext(path))
			if extension != ".accdb" && extension != ".mdb" {
				return nil
			}
			if info, err := entry.Info(); err == nil && info.Size() > 0 {
				found = append(found, candidate{Name: path, Modified: info.ModTime()})
			}
			return nil
		})
	}

	sort.SliceStable(found, func(i, j int) bool { return found[i].Modified.After(found[j].Modified) })
	return found
}

// inspectCandidates keeps databases that open and hold real tables. Front-end files only hold
// links, so they drop out here too.
func inspectCandidates(found []candidate, inspect func(string) (Schema, error)) []candidate {
	if len(found) > maxInspected {
		found = found[:maxInspected]
	}

	usable := []candidate{}
	for _, item := range found {
		schema, err := inspect(item.Name)
		if err != nil || len(schema) == 0 {
			continue
		}
		item.Tables = map[string]bool{}
		for name := range schema {
			item.Tables[name] = true
		}
		usable = append(usable, item)
	}
	return usable
}

func (c candidate) hasAll(tables []string) bool {
	for _, table := range tables {
		if !c.Tables[table] {
			return false
		}
	}
	return true
}

// choose returns the database to use without asking, or else the options to offer. It only
// decides alone when the client's mapping identifies exactly one database.
func choose(usable []candidate, required []string) (*candidate, []candidate) {
	options := usable

	if len(required) > 0 {
		matching := []candidate{}
		for _, item := range usable {
			if item.hasAll(required) {
				matching = append(matching, item)
			}
		}
		if len(matching) > 0 {
			options = withoutBackups(matching)
			if len(options) == 1 {
				return &options[0], options
			}
			return nil, options
		}
	}
	return nil, withoutBackups(options)
}

func withoutBackups(items []candidate) []candidate {
	kept := []candidate{}
	for _, item := range items {
		if !backupNames.MatchString(strings.NewReplacer("_", " ", "-", " ").Replace(item.Name)) {
			kept = append(kept, item)
		}
	}
	if len(kept) == 0 {
		return items
	}
	return kept
}

// requiredTables lists the tables the client's mapping uses, which identify its database.
func requiredTables(mapping map[string]*Section) []string {
	seen := map[string]bool{}
	tables := []string{}
	for _, section := range mapping {
		if section == nil {
			continue
		}
		name := strings.ToLower(section.Table)
		if name != "" && !seen[name] {
			seen[name] = true
			tables = append(tables, name)
		}
	}
	sort.Strings(tables)
	return tables
}
