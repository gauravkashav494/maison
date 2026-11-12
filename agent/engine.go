package main

import (
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"math"
	"os"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
)

const (
	batchSize = 500
	maxErrors = 50
)

// Section maps one kind of data to a local table. It comes from the server and is only data:
// every name must exist in the database's own schema before it is used in a statement, and
// every value is sent as a parameter.
type Section struct {
	Table        string            `json:"table"`
	Key          string            `json:"key"`
	Parent       string            `json:"parent"`
	SkuColumn    string            `json:"sku_column"`
	QtyColumn    string            `json:"qty_column"`
	Columns      map[string]string `json:"columns"`
	Defaults     map[string]any    `json:"defaults"`
	StatusValues map[string]string `json:"status_values"`
	Insert       bool              `json:"insert"`
}

type agentState struct {
	Schema string                       `json:"schema"`
	Sent   map[string]map[string]string `json:"sent"`
}

type result struct {
	Key    any    `json:"key"`
	Result string `json:"result"`
	Error  string `json:"error"`
}

type resultsResponse struct {
	Results []result `json:"results"`
}

// Engine runs one sync cycle.
type Engine struct {
	API       *API
	DB        Store
	StatePath string
	Version   string

	schema  Schema
	mapping map[string]*Section
	state   agentState
	errors  []string
	counts  map[string]int
}

func sortedKeys[V any](values map[string]V) []string {
	keys := make([]string, 0, len(values))
	for key := range values {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	return keys
}

func (e *Engine) fail(format string, args ...any) {
	if len(e.errors) < maxErrors {
		text := fmt.Sprintf(format, args...)
		if len(text) > 900 {
			text = text[:900]
		}
		e.errors = append(e.errors, text)
	}
}

func (e *Engine) step(name string, work func() error) {
	if err := work(); err != nil {
		e.fail("%s: %v", name, err)
	}
}

// Run performs one cycle and reports the outcome to the server.
func (e *Engine) Run() error {
	e.counts = map[string]int{}
	message := "Sync complete."

	err := e.cycle(&message)
	if err != nil {
		e.fail("%v", err)
		message = "Sync stopped early."
	}

	reportErrors := e.errors
	if reportErrors == nil {
		reportErrors = []string{}
	}
	reportErr := e.API.Call("POST", "agent/report", map[string]any{
		"agent_version": e.Version,
		"ok":            len(e.errors) == 0,
		"message":       message,
		"counts":        e.counts,
		"errors":        reportErrors,
	}, nil)
	if err != nil {
		return err
	}
	return reportErr
}

func (e *Engine) cycle(message *string) error {
	e.readState()

	schema, err := e.DB.Schema()
	if err != nil {
		return fmt.Errorf("reading local tables: %w", err)
	}
	e.schema = schema
	if err := e.sendSchema(); err != nil {
		return err
	}

	var config struct {
		Mapping map[string]*Section `json:"mapping"`
	}
	if err := e.API.Call("GET", "agent/config", nil, &config); err != nil {
		return err
	}
	e.mapping = config.Mapping

	if len(e.mapping) == 0 {
		*message = "Waiting for a mapping; local tables have been reported."
		return e.saveState()
	}

	// Orders first, so that the stock count sent below already includes them.
	e.step("orders", e.syncOrders)
	e.step("customers_in", func() error { return e.syncLinked("customers", "customers_in", "email", true) })
	e.step("products_in", func() error { return e.syncLinked("products", "products_in", "sku", false) })
	e.step("products", func() error { return e.push("products", "products", "external_id", false, nil) })
	e.step("customers", func() error { return e.push("customers", "customers", "external_id", false, nil) })
	// Always sent in full: the server subtracts unconfirmed orders, so a resend corrects drift.
	e.step("stock", func() error { return e.push("stock", "stock", "sku", true, nil) })
	e.step("order_status", e.pushOrderStatus)

	return e.saveState()
}

// ---- State ------------------------------------------------------------------------------

func (e *Engine) readState() {
	e.state = agentState{Sent: map[string]map[string]string{}}
	if data, err := os.ReadFile(e.StatePath); err == nil {
		_ = json.Unmarshal(data, &e.state)
	}
	if e.state.Sent == nil {
		e.state.Sent = map[string]map[string]string{}
	}
}

func (e *Engine) saveState() error {
	data, err := json.Marshal(e.state)
	if err != nil {
		return err
	}
	temporary := e.StatePath + ".new"
	if err := os.WriteFile(temporary, data, 0o600); err != nil {
		return err
	}
	return os.Rename(temporary, e.StatePath)
}

func hashText(text string) string {
	sum := sha256.Sum256([]byte(text))
	return base64.StdEncoding.EncodeToString(sum[:])
}

func (e *Engine) sendSchema() error {
	tables := map[string][]Column{}
	for _, table := range e.schema {
		columns := make([]Column, 0, len(table.Columns))
		for _, key := range sortedKeys(table.Columns) {
			columns = append(columns, table.Columns[key])
		}
		tables[table.Name] = columns
	}

	encoded, err := json.Marshal(tables)
	if err != nil {
		return err
	}
	hash := hashText(string(encoded))
	if e.state.Schema == hash {
		return nil
	}

	if err := e.API.Call("POST", "agent/schema", map[string]any{"tables": tables}, nil); err != nil {
		return err
	}
	e.state.Schema = hash
	return nil
}

// ---- Mapping and values -----------------------------------------------------------------

func quote(name string) (string, error) {
	if name == "" || strings.ContainsAny(name, "[]") {
		return "", fmt.Errorf("unsafe name %q", name)
	}
	return "[" + name + "]", nil
}

func mustQuote(name string) string {
	quoted, err := quote(name)
	if err != nil {
		panic(err) // section() has already checked every name
	}
	return quoted
}

// section returns a mapped section, or nil if it is unmapped or names something missing.
func (e *Engine) section(name string) *Section {
	section := e.mapping[name]
	if section == nil {
		return nil
	}

	table := e.schema[strings.ToLower(section.Table)]
	if table == nil {
		e.fail("%s: table %q does not exist locally", name, section.Table)
		return nil
	}

	names := []string{}
	for _, column := range section.Columns {
		names = append(names, column)
	}
	for column := range section.Defaults {
		names = append(names, column)
	}
	for _, column := range []string{section.Key, section.Parent, section.SkuColumn, section.QtyColumn} {
		if column != "" {
			names = append(names, column)
		}
	}

	for _, column := range names {
		_, known := table.Columns[strings.ToLower(column)]
		if _, err := quote(column); err != nil || !known {
			e.fail("%s: column %q does not exist in %q", name, column, section.Table)
			return nil
		}
	}
	if _, err := quote(section.Table); err != nil {
		e.fail("%s: %v", name, err)
		return nil
	}
	return section
}

func filled(value any) bool {
	return value != nil && strings.TrimSpace(text(value)) != ""
}

func text(value any) string {
	switch v := value.(type) {
	case nil:
		return ""
	case string:
		return v
	case []byte:
		return string(v)
	case float64:
		return strconv.FormatFloat(v, 'f', -1, 64)
	case float32:
		return strconv.FormatFloat(float64(v), 'f', -1, 32)
	case time.Time:
		return v.Format(time.RFC3339)
	default:
		return fmt.Sprint(v)
	}
}

func number(value any) (float64, error) {
	switch v := value.(type) {
	case float64:
		return v, nil
	case float32:
		return float64(v), nil
	case int64:
		return float64(v), nil
	case int32:
		return float64(v), nil
	case int:
		return float64(v), nil
	default:
		return strconv.ParseFloat(strings.TrimSpace(text(value)), 64)
	}
}

var (
	textTypes    = regexp.MustCompile(`CHAR|TEXT|MEMO|GUID`)
	dateTypes    = regexp.MustCompile(`DATE|TIME`)
	numericTypes = regexp.MustCompile(`INT|COUNTER|LONG|BYTE|DECIMAL|NUMERIC|DOUBLE|FLOAT|REAL|SINGLE|MONEY|CURRENCY`)
)

// columnValue converts a value to what the column's type expects.
func (e *Engine) columnValue(table, column string, value any) (any, error) {
	if !filled(value) {
		return nil, nil
	}

	kind := strings.ToUpper(e.schema[strings.ToLower(table)].Columns[strings.ToLower(column)].Type)
	switch {
	case textTypes.MatchString(kind):
		return text(value), nil
	case dateTypes.MatchString(kind) && !strings.Contains(kind, "STAMP"):
		if date, ok := value.(time.Time); ok {
			return date, nil
		}
		date, err := time.Parse(time.RFC3339, text(value))
		if err != nil {
			return nil, fmt.Errorf("%s: %w", column, err)
		}
		return date.Local(), nil
	case numericTypes.MatchString(kind):
		parsed, err := number(value)
		if err != nil {
			return nil, fmt.Errorf("%s: %w", column, err)
		}
		if parsed == math.Trunc(parsed) && math.Abs(parsed) <= math.MaxInt32 {
			return int64(parsed), nil
		}
		return parsed, nil
	default:
		return text(value), nil
	}
}

func (e *Engine) addRow(section *Section, values map[string]any, extra map[string]any) error {
	columns := []string{}
	args := []any{}

	add := func(column string, value any) error {
		converted, err := e.columnValue(section.Table, column, value)
		if err != nil {
			return err
		}
		columns = append(columns, mustQuote(column))
		args = append(args, converted)
		return nil
	}

	for _, column := range sortedKeys(extra) {
		if err := add(column, extra[column]); err != nil {
			return err
		}
	}
	for _, field := range sortedKeys(section.Columns) {
		if value, ok := values[field]; ok && value != nil {
			if err := add(section.Columns[field], value); err != nil {
				return err
			}
		}
	}
	for _, column := range sortedKeys(section.Defaults) {
		if err := add(column, section.Defaults[column]); err != nil {
			return err
		}
	}

	marks := strings.TrimSuffix(strings.Repeat("?, ", len(columns)), ", ")
	return e.DB.Exec(fmt.Sprintf("INSERT INTO %s (%s) VALUES (%s)", mustQuote(section.Table), strings.Join(columns, ", "), marks), args...)
}

func (e *Engine) localKey(section *Section, column string, value any) (any, error) {
	converted, err := e.columnValue(section.Table, column, value)
	if err != nil {
		return nil, err
	}
	rows, err := e.DB.Query(
		fmt.Sprintf("SELECT %s AS k FROM %s WHERE %s = ?", mustQuote(section.Key), mustQuote(section.Table), mustQuote(column)),
		converted,
	)
	if err != nil || len(rows) == 0 {
		return nil, err
	}
	return rows[0]["k"], nil
}

func (e *Engine) updateRow(section *Section, changes map[string]any, key any) error {
	if len(changes) == 0 {
		return nil
	}

	assignments := []string{}
	args := []any{}
	for _, column := range sortedKeys(changes) {
		converted, err := e.columnValue(section.Table, column, changes[column])
		if err != nil {
			return err
		}
		assignments = append(assignments, mustQuote(column)+" = ?")
		args = append(args, converted)
	}

	convertedKey, err := e.columnValue(section.Table, section.Key, key)
	if err != nil {
		return err
	}
	args = append(args, convertedKey)
	return e.DB.Exec(fmt.Sprintf("UPDATE %s SET %s WHERE %s = ?", mustQuote(section.Table), strings.Join(assignments, ", "), mustQuote(section.Key)), args...)
}

// ---- Local to EcomVision ----------------------------------------------------------------

func apiValue(field string, value any) (any, error) {
	if !filled(value) {
		return nil, nil
	}
	switch field {
	case "price", "compare_at_price", "on_hand":
		parsed, err := number(value)
		if err != nil {
			return nil, fmt.Errorf("%s: %w", field, err)
		}
		return int64(math.Max(0, math.Round(parsed))), nil
	default:
		return strings.TrimSpace(text(value)), nil
	}
}

func (e *Engine) localItems(name string, section *Section) ([]map[string]any, error) {
	seen := map[string]bool{}
	columns := []string{}
	for _, field := range sortedKeys(section.Columns) {
		column := section.Columns[field]
		if !seen[strings.ToLower(column)] {
			seen[strings.ToLower(column)] = true
			columns = append(columns, mustQuote(column))
		}
	}

	rows, err := e.DB.Query(fmt.Sprintf("SELECT %s FROM %s", strings.Join(columns, ", "), mustQuote(section.Table)))
	if err != nil {
		return nil, err
	}

	items := make([]map[string]any, 0, len(rows))
	for _, row := range rows {
		item := map[string]any{}
		bad := false
		for field, column := range section.Columns {
			value, err := apiValue(field, row[strings.ToLower(column)])
			if err != nil {
				e.fail("%s: %v", name, err)
				bad = true
				break
			}
			if value != nil {
				item[field] = value
			}
		}
		if !bad {
			items = append(items, item)
		}
	}
	return items, nil
}

func itemHash(item map[string]any) string {
	parts := make([]string, 0, len(item))
	for _, key := range sortedKeys(item) {
		parts = append(parts, key+"="+text(item[key]))
	}
	return hashText(strings.Join(parts, "\n"))
}

// push sends local rows. Only rows the server accepted are remembered, so the rest are retried.
func (e *Engine) push(name, endpoint, keyField string, always bool, shape func(map[string]any) bool) error {
	section := e.section(name)
	if section == nil {
		return nil
	}
	items, err := e.localItems(name, section)
	if err != nil {
		return err
	}

	sent := e.state.Sent[name]
	if sent == nil {
		sent = map[string]string{}
		e.state.Sent[name] = sent
	}

	pending := []map[string]any{}
	for _, item := range items {
		if shape != nil && !shape(item) {
			continue
		}
		key, ok := item[keyField]
		if ok && (always || sent[text(key)] != itemHash(item)) {
			pending = append(pending, item)
		}
	}

	for start := 0; start < len(pending); start += batchSize {
		end := start + batchSize
		if end > len(pending) {
			end = len(pending)
		}
		batch := pending[start:end]

		var response resultsResponse
		if err := e.API.Call("POST", endpoint, map[string]any{"items": batch}, &response); err != nil {
			return err
		}

		accepted := map[string]bool{}
		for _, outcome := range response.Results {
			e.counts[name+"."+outcome.Result]++
			if outcome.Result == "rejected" {
				e.fail("%s %s: %s", name, text(outcome.Key), outcome.Error)
			} else {
				accepted[text(outcome.Key)] = true
			}
		}
		for _, item := range batch {
			if key := text(item[keyField]); accepted[key] {
				sent[key] = itemHash(item)
			}
		}
	}
	return nil
}

func (e *Engine) pushOrderStatus() error {
	section := e.mapping["order_status"]
	values := map[string]string{}
	if section != nil {
		for local, api := range section.StatusValues {
			values[strings.ToLower(local)] = api
		}
	}

	return e.push("order_status", "orders/status", "number", false, func(item map[string]any) bool {
		if status, ok := item["status"]; ok && len(values) > 0 {
			if translated, known := values[strings.ToLower(text(status))]; known {
				item["status"] = translated
			} else {
				delete(item, "status")
			}
		}
		return len(item) > 1 // a number alone says nothing
	})
}

// ---- EcomVision to local ----------------------------------------------------------------

func (e *Engine) feed(resource string, handle func(map[string]any) (any, error)) error {
	after := int64(0)
	for {
		var page struct {
			Data    []map[string]any `json:"data"`
			HasMore bool             `json:"has_more"`
		}
		if err := e.API.Call("GET", fmt.Sprintf("changes/%s?limit=100&after_id=%d", resource, after), nil, &page); err != nil {
			return err
		}

		acks := []map[string]any{}
		for _, row := range page.Data {
			id, _ := number(row["id"])
			version, _ := number(row["version"])
			after = int64(id)

			external, err := handle(row)
			if err != nil {
				e.fail("%s %d: %v", resource, int64(id), err)
				continue
			}

			ack := map[string]any{"id": int64(id), "version": int64(version)}
			if filled(external) {
				ack["external_id"] = text(external)
			}
			acks = append(acks, ack)
			e.counts[resource+".received"]++
		}

		if len(acks) > 0 {
			var response resultsResponse
			if err := e.API.Call("POST", "changes/"+resource+"/ack", map[string]any{"items": acks}, &response); err != nil {
				return err
			}
			for _, outcome := range response.Results {
				if outcome.Result == "rejected" {
					e.fail("%s ack %s: %s", resource, text(outcome.Key), outcome.Error)
				}
			}
		}

		if !page.HasMore {
			return nil
		}
	}
}

func localStatus(section *Section, status string) string {
	for _, local := range sortedKeys(section.StatusValues) {
		if section.StatusValues[local] == status {
			return local
		}
	}
	return status
}

func lineSku(line map[string]any) string {
	if filled(line["variant_sku"]) {
		return text(line["variant_sku"])
	}
	return text(line["sku"])
}

func orderLines(order map[string]any) []map[string]any {
	raw, _ := order["items"].([]any)
	lines := make([]map[string]any, 0, len(raw))
	for _, entry := range raw {
		if line, ok := entry.(map[string]any); ok {
			lines = append(lines, line)
		}
	}
	return lines
}

func (e *Engine) changeStock(section *Section, sku string, change int64) error {
	if sku == "" {
		return nil
	}
	skuValue, err := e.columnValue(section.Table, section.SkuColumn, sku)
	if err != nil {
		return err
	}
	quantity := mustQuote(section.QtyColumn)
	return e.DB.Exec(fmt.Sprintf("UPDATE %s SET %s = %s + ? WHERE %s = ?", mustQuote(section.Table), quantity, quantity, mustQuote(section.SkuColumn)), change, skuValue)
}

func (e *Engine) importOrder(order map[string]any, header, lines, stock *Section) error {
	number := header.Columns["number"]
	values := map[string]any{}
	for field, value := range order {
		values[field] = value
	}
	values["status"] = localStatus(header, text(order["status"]))

	return e.DB.Tx(func() error {
		if err := e.addRow(header, values, nil); err != nil {
			return err
		}
		key, err := e.localKey(header, number, order["number"])
		if err != nil {
			return err
		}

		for _, line := range orderLines(order) {
			if lines != nil {
				lineValues := map[string]any{}
				for field, value := range line {
					lineValues[field] = value
				}
				lineValues["sku"] = lineSku(line)
				if err := e.addRow(lines, lineValues, map[string]any{lines.Parent: key}); err != nil {
					return err
				}
			}
			if stock != nil {
				qty, err := quantity(line)
				if err != nil {
					return err
				}
				if err := e.changeStock(stock, lineSku(line), -qty); err != nil {
					return err
				}
			}
		}
		return nil
	})
}

func quantity(line map[string]any) (int64, error) {
	parsed, err := number(line["qty"])
	return int64(parsed), err
}

func (e *Engine) updateOrder(order map[string]any, key any, header, stock *Section) error {
	status := text(order["status"])
	changes := map[string]any{}
	for _, field := range []string{"status", "payment_status", "carrier", "tracking_number"} {
		column := header.Columns[field]
		switch {
		case column == "":
		case field == "status":
			changes[column] = localStatus(header, status)
		case filled(order[field]):
			changes[column] = order[field]
		}
	}

	return e.DB.Tx(func() error {
		statusColumn := header.Columns["status"]
		if stock != nil && status == "cancelled" && statusColumn != "" {
			converted, err := e.columnValue(header.Table, header.Key, key)
			if err != nil {
				return err
			}
			rows, err := e.DB.Query(
				fmt.Sprintf("SELECT %s AS s FROM %s WHERE %s = ?", mustQuote(statusColumn), mustQuote(header.Table), mustQuote(header.Key)),
				converted,
			)
			if err != nil {
				return err
			}

			// Status is written in this same transaction, so a retry cannot restock twice.
			if len(rows) > 0 && text(rows[0]["s"]) != localStatus(header, "cancelled") {
				for _, line := range orderLines(order) {
					qty, err := quantity(line)
					if err != nil {
						return err
					}
					if err := e.changeStock(stock, lineSku(line), qty); err != nil {
						return err
					}
				}
			}
		}
		return e.updateRow(header, changes, key)
	})
}

func (e *Engine) syncOrders() error {
	header := e.section("orders_in")
	if header == nil {
		return nil
	}
	lines := e.section("order_items_in")
	stock := e.section("stock_on_import")
	number := header.Columns["number"]
	if number == "" || header.Key == "" {
		return fmt.Errorf("orders_in needs a key and a number column")
	}

	return e.feed("orders", func(order map[string]any) (any, error) {
		key, err := e.localKey(header, number, order["number"])
		if err != nil {
			return nil, err
		}

		if key == nil {
			// Never reached the local system, so nothing to record or take off stock.
			if text(order["status"]) == "cancelled" {
				return nil, nil
			}
			if err := e.importOrder(order, header, lines, stock); err != nil {
				return nil, err
			}
			return e.localKey(header, number, order["number"])
		}

		return key, e.updateOrder(order, key, header, stock)
	})
}

func (e *Engine) syncLinked(resource, name, matchField string, updateExisting bool) error {
	section := e.section(name)
	if section == nil {
		return nil
	}
	if section.Key == "" || section.Columns[matchField] == "" {
		return fmt.Errorf("%s needs a key and a %s column", name, matchField)
	}

	return e.feed(resource, func(row map[string]any) (any, error) {
		if filled(row["external_id"]) {
			if updateExisting {
				changes := map[string]any{}
				for field, column := range section.Columns {
					if filled(row[field]) {
						changes[column] = row[field]
					}
				}
				if err := e.updateRow(section, changes, row["external_id"]); err != nil {
					return nil, err
				}
			}
			return row["external_id"], nil
		}

		if !filled(row[matchField]) {
			return nil, nil
		}
		column := section.Columns[matchField]
		key, err := e.localKey(section, column, row[matchField])
		if err != nil || key != nil {
			return key, err
		}

		if !section.Insert {
			return nil, nil
		}
		if err := e.addRow(section, row, nil); err != nil {
			return nil, err
		}
		return e.localKey(section, column, row[matchField])
	})
}
