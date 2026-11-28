package main

import (
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"math"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"
)

const (
	batchSize = 500
	maxErrors = 50
)

type agentState struct {
	Sent map[string]map[string]string `json:"sent"`
	// Per local list, the newest updated_at received (the shop PC's clock) and when the agent last
	// read the whole list.
	Since map[string]string `json:"since,omitempty"`
	Full  map[string]string `json:"full,omitempty"`
}

type result struct {
	Key    any    `json:"key"`
	Result string `json:"result"`
	Error  string `json:"error"`
}

type resultsResponse struct {
	Results []result `json:"results"`
}

// Engine runs one sync cycle between EcomVision (API) and the shop software's own API (Local,
// docs/LOCAL-API.md). Local is read from EcomVision at the start of the cycle when not set.
type Engine struct {
	API       *API
	Local     *LocalAPI
	StatePath string
	Version   string
	Now       func() time.Time // the agent's clock, for the daily full read; time.Now when nil

	state  agentState
	errors []string
	counts map[string]int
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
	e.errors = nil
	message := "Sync complete."

	err := e.cycle(&message)
	if err != nil {
		e.fail("%v", err)
		// Unless the cycle said why itself (the shop software did not answer, say).
		if message == "Sync complete." {
			message = "Sync stopped early."
		}
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
	if e.Local == nil {
		local, err := localConfig(e.API)
		if err != nil {
			*message = "The billing software's connection is not set in the store's panel."
			return err
		}
		e.Local = local
	}
	return e.localCycle(message)
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
	if e.state.Since == nil {
		e.state.Since = map[string]string{}
	}
	if e.state.Full == nil {
		e.state.Full = map[string]string{}
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

// ---- Values -------------------------------------------------------------------------------

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

// ---- Local to EcomVision ----------------------------------------------------------------

func apiValue(field string, value any) (any, error) {
	if !filled(value) {
		return nil, nil
	}
	switch field {
	case "price", "price_incl_tax", "price_excl_tax", "compare_at_price", "tax_rate":
		// Rupees and paise (the server keeps two decimals), never below zero.
		parsed, err := number(value)
		if err != nil {
			return nil, fmt.Errorf("%s: %w", field, err)
		}
		return math.Max(0, math.Round(parsed*100)/100), nil
	case "on_hand":
		parsed, err := number(value)
		if err != nil {
			return nil, fmt.Errorf("%s: %w", field, err)
		}
		return int64(math.Max(0, math.Round(parsed))), nil
	default:
		return strings.TrimSpace(text(value)), nil
	}
}

func itemHash(item map[string]any) string {
	parts := make([]string, 0, len(item))
	for _, key := range sortedKeys(item) {
		parts = append(parts, key+"="+text(item[key]))
	}
	return hashText(strings.Join(parts, "\n"))
}

// sendBatches posts items to EcomVision in batches and remembers, in sent, each one it accepted.
func (e *Engine) sendBatches(name, endpoint, keyField string, pending []map[string]any, sent map[string]string) error {
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
