package main

import (
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"encoding/pem"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
)

// fakeServer stands in for the API; the contract itself is checked by the e2e test below.
type fakeServer struct {
	orders    []map[string]any
	customers []map[string]any
	products  []map[string]any
	config    map[string]any // GET agent/config: the billing software connection set in the panel
	reject    map[string]bool
	received  map[string][]map[string]any
	acks      []map[string]any
	reports   []map[string]any
}

func (f *fakeServer) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	path := r.URL.Path
	var body map[string]any
	_ = json.NewDecoder(r.Body).Decode(&body)

	switch {
	case path == "/agent/config":
		json.NewEncoder(w).Encode(f.config)
	case path == "/agent/report":
		f.reports = append(f.reports, body)
		w.Write([]byte(`{}`))
	case path == "/changes/orders" && r.Method == "GET":
		json.NewEncoder(w).Encode(map[string]any{"data": f.orders, "has_more": false})
	case path == "/changes/customers" && r.Method == "GET" && f.customers != nil:
		json.NewEncoder(w).Encode(map[string]any{"data": f.customers, "has_more": false})
	case path == "/changes/products" && r.Method == "GET" && f.products != nil:
		json.NewEncoder(w).Encode(map[string]any{"data": f.products, "has_more": false})
	case strings.HasPrefix(path, "/changes/") && r.Method == "GET":
		w.Write([]byte(`{"data":[],"has_more":false}`))
	case strings.HasSuffix(path, "/ack"):
		for _, item := range body["items"].([]any) {
			f.acks = append(f.acks, item.(map[string]any))
		}
		w.Write([]byte(`{"results":[]}`))
	default:
		keyField := map[string]string{"/stock": "sku", "/products": "external_id", "/orders/status": "number"}[path]
		results := []map[string]any{}
		for _, raw := range body["items"].([]any) {
			item := raw.(map[string]any)
			f.received[path] = append(f.received[path], item)
			outcome := "updated"
			if f.reject[text(item[keyField])] {
				outcome = "rejected"
			}
			results = append(results, map[string]any{"key": item[keyField], "result": outcome, "error": "no"})
		}
		json.NewEncoder(w).Encode(map[string]any{"results": results})
	}
}

func webOrder(status string) map[string]any {
	return map[string]any{
		"id": float64(7), "version": float64(1), "number": "ME-1", "email": "a@b.c", "total": float64(250),
		"placed_at": "2026-11-10T10:00:00+00:00", "status": status,
		"items": []any{map[string]any{"sku": "A1", "variant_sku": nil, "qty": float64(3), "price": float64(50)}},
	}
}

func cleanRun(t *testing.T, engine *Engine, fake *fakeServer) {
	t.Helper()
	if err := engine.Run(); err != nil {
		t.Fatal(err)
	}
	last := fake.reports[len(fake.reports)-1]
	if last["ok"] != true {
		t.Fatalf("errors: %v", last["errors"])
	}
}

type fakeUpdates struct {
	binary    []byte
	signature string
	version   string
}

func (f *fakeUpdates) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if strings.HasSuffix(r.URL.Path, "/binary") {
		w.Write(f.binary)
		return
	}
	if r.URL.Query().Get("have") == f.version {
		w.Write([]byte(`{"current":true}`))
		return
	}
	json.NewEncoder(w).Encode(map[string]any{"current": false, "version": f.version, "signature": f.signature})
}

func signed(t *testing.T, key *rsa.PrivateKey, data []byte) string {
	t.Helper()
	digest := sha256.Sum256(data)
	signature, err := rsa.SignPKCS1v15(rand.Reader, key, crypto.SHA256, digest[:])
	if err != nil {
		t.Fatal(err)
	}
	return base64.StdEncoding.EncodeToString(signature)
}

func newUpdateFixture(t *testing.T, script string) (*Updater, *fakeUpdates, *rsa.PrivateKey, string) {
	t.Helper()
	key, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	der, _ := x509.MarshalPKIXPublicKey(&key.PublicKey)
	public := pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: der})

	dir := t.TempDir()
	if err := os.WriteFile(filepath.Join(dir, workerName), []byte(script), 0o755); err != nil {
		t.Fatal(err)
	}
	updates := &fakeUpdates{}
	server := httptest.NewServer(updates)
	t.Cleanup(server.Close)
	return &Updater{API: NewAPI(server.URL, "t"), Dir: dir, Arch: "amd64", PublicKey: base64.StdEncoding.EncodeToString(public)}, updates, key, dir
}

func publish(t *testing.T, updates *fakeUpdates, key *rsa.PrivateKey, script string) {
	t.Helper()
	sum := sha256.Sum256([]byte(script))
	updates.binary = []byte(script)
	updates.version = hex.EncodeToString(sum[:])
	updates.signature = signed(t, key, []byte(script))
}

func worker(t *testing.T, dir string) string {
	t.Helper()
	data, err := os.ReadFile(filepath.Join(dir, workerName))
	if err != nil {
		t.Fatal(err)
	}
	return string(data)
}

func TestSignedUpdateIsInstalled(t *testing.T) {
	updater, updates, key, dir := newUpdateFixture(t, "old")
	publish(t, updates, key, "new")

	if err := updater.Check(); err != nil {
		t.Fatal(err)
	}
	if worker(t, dir) != "new" {
		t.Fatal("not installed")
	}
	if err := updater.RecordRun(false); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(filepath.Join(dir, workerName+".old")); !os.IsNotExist(err) {
		t.Fatal("old copy kept after a good run")
	}
}

func TestUpdateSignedByAnotherKeyIsRefused(t *testing.T) {
	updater, updates, _, dir := newUpdateFixture(t, "old")
	other, _ := rsa.GenerateKey(rand.Reader, 2048)
	publish(t, updates, other, "evil")

	if err := updater.Check(); err == nil {
		t.Fatal("accepted")
	}
	if worker(t, dir) != "old" {
		t.Fatal("worker replaced")
	}
}

func TestTamperedDownloadIsRefused(t *testing.T) {
	updater, updates, key, dir := newUpdateFixture(t, "old")
	publish(t, updates, key, "good")
	updates.binary = []byte("evil")

	if err := updater.Check(); err == nil {
		t.Fatal("accepted")
	}
	if worker(t, dir) != "old" {
		t.Fatal("worker replaced")
	}
}

func TestBuildWithoutAKeyRefusesUpdates(t *testing.T) {
	updater, updates, key, dir := newUpdateFixture(t, "old")
	updater.PublicKey = ""
	publish(t, updates, key, "new")

	if err := updater.Check(); err == nil || worker(t, dir) != "old" {
		t.Fatal("accepted without a key")
	}
}

func TestRepeatedCrashesRollBackAndTheVersionIsNotRetried(t *testing.T) {
	updater, updates, key, dir := newUpdateFixture(t, "old")
	publish(t, updates, key, "broken")
	if err := updater.Check(); err != nil {
		t.Fatal(err)
	}

	for i := 0; i < crashesBeforeRollback; i++ {
		if worker(t, dir) != "broken" {
			t.Fatalf("rolled back too early, crash %d", i)
		}
		if err := updater.RecordRun(true); err != nil {
			t.Fatal(err)
		}
	}

	if worker(t, dir) != "old" {
		t.Fatal("not rolled back")
	}
	if err := updater.Check(); err != nil {
		t.Fatal(err)
	}
	if worker(t, dir) != "old" {
		t.Fatal("bad version reinstalled")
	}

	publish(t, updates, key, "fixed")
	if err := updater.Check(); err != nil {
		t.Fatal(err)
	}
	if worker(t, dir) != "fixed" {
		t.Fatal("a fixed release was not installed")
	}
}

func TestCycleTreatsSyncFailureAsNotACrash(t *testing.T) {
	script := "#!/bin/sh\nexit " + strconv.Itoa(exitSyncFailed) + "\n"
	updater, updates, key, dir := newUpdateFixture(t, "#!/bin/sh\nexit 0\n")
	publish(t, updates, key, script)

	runCycle(context.Background(), updater, func(string, ...any) {})

	if worker(t, dir) != script {
		t.Fatal("not updated")
	}
	if updater.readPending() != nil {
		t.Fatal("a network or shop software failure must settle the trial, not count as a crash")
	}
}

func TestCycleCountsACrash(t *testing.T) {
	script := "#!/bin/sh\nexit 2\n"
	updater, updates, key, _ := newUpdateFixture(t, "#!/bin/sh\nexit 0\n")
	publish(t, updates, key, script)

	runCycle(context.Background(), updater, func(string, ...any) {})

	if pending := updater.readPending(); pending == nil || pending.Crashes != 1 {
		t.Fatalf("pending = %+v", pending)
	}
}

// Runs against a real EcomVision server when EV_E2E_API and EV_E2E_TOKEN are set, with a fake
// shop software API on this side.
func TestAgainstRealServer(t *testing.T) {
	base, token := os.Getenv("EV_E2E_API"), os.Getenv("EV_E2E_TOKEN")
	if base == "" {
		t.Skip("EV_E2E_API is not set")
	}

	shop := newFakeShop()
	for _, list := range []string{"products", "customers", "stock"} {
		shop.lists[list] = [][]map[string]any{}
	}
	shopServer := httptest.NewServer(shop)
	t.Cleanup(shopServer.Close)
	engine := &Engine{API: NewAPI(base, token), Local: NewLocalAPI(shopServer.URL+"/", "k"), StatePath: filepath.Join(t.TempDir(), "state.json"), Version: "e2e"}

	if err := engine.Run(); err != nil {
		t.Fatal(err)
	}
	if shop.orders["ME-E2E"] == nil {
		t.Fatal("web order not sent to the shop software")
	}

	// A real build, signed by the real command and verified by the real updater.
	dir := t.TempDir()
	if err := os.WriteFile(filepath.Join(dir, workerName), []byte("old"), 0o755); err != nil {
		t.Fatal(err)
	}
	updater := &Updater{API: engine.API, Dir: dir, Arch: "amd64", PublicKey: os.Getenv("EV_E2E_PUBLIC_KEY")}
	if err := updater.Check(); err != nil {
		t.Fatal(err)
	}
	installed, err := sha256File(filepath.Join(dir, workerName))
	if err != nil || installed != os.Getenv("EV_E2E_BINARY_SHA") {
		t.Fatalf("installed %s, %v", installed, err)
	}
}
