package main

import (
	"database/sql"
	"strings"
)

// Row is one result row, keyed by lower-cased column name.
type Row map[string]any

// Column is a local column and its database type name.
type Column struct {
	Name string `json:"name"`
	Type string `json:"type"`
}

// Table is a local table with its columns, keyed by lower-cased name.
type Table struct {
	Name    string
	Columns map[string]Column
}

// Schema holds the local tables, keyed by lower-cased name.
type Schema map[string]*Table

// Store is everything the engine needs from the local database.
type Store interface {
	Schema() (Schema, error)
	Query(query string, args ...any) ([]Row, error)
	Exec(query string, args ...any) error
	Tx(work func() error) error
	Close() error
}

type sqlStore struct {
	db     *sql.DB
	tx     *sql.Tx
	schema func() (Schema, error)
}

func (s *sqlStore) Schema() (Schema, error) { return s.schema() }
func (s *sqlStore) Close() error            { return s.db.Close() }

func (s *sqlStore) Query(query string, args ...any) ([]Row, error) {
	var rows *sql.Rows
	var err error
	if s.tx != nil {
		rows, err = s.tx.Query(query, args...)
	} else {
		rows, err = s.db.Query(query, args...)
	}
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	names, err := rows.Columns()
	if err != nil {
		return nil, err
	}

	var result []Row
	for rows.Next() {
		values := make([]any, len(names))
		targets := make([]any, len(names))
		for i := range values {
			targets[i] = &values[i]
		}
		if err := rows.Scan(targets...); err != nil {
			return nil, err
		}

		row := Row{}
		for i, name := range names {
			if text, ok := values[i].([]byte); ok {
				values[i] = string(text)
			}
			row[strings.ToLower(name)] = values[i]
		}
		result = append(result, row)
	}
	return result, rows.Err()
}

func (s *sqlStore) Exec(query string, args ...any) error {
	var err error
	if s.tx != nil {
		_, err = s.tx.Exec(query, args...)
	} else {
		_, err = s.db.Exec(query, args...)
	}
	return err
}

func (s *sqlStore) Tx(work func() error) error {
	tx, err := s.db.Begin()
	if err != nil {
		return err
	}
	s.tx = tx
	defer func() { s.tx = nil }()

	if err := work(); err != nil {
		_ = tx.Rollback()
		return err
	}
	return tx.Commit()
}
