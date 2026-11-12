package main

import (
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"database/sql"
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

	_ "modernc.org/sqlite"
)

// openTestStore is a real SQL database: the engine's statements run for real, using the same
// [name] quoting and ? parameters as Access and SQL Server.
func openTestStore(t *testing.T, ddl string) *sqlStore {
	t.Helper()
	db, err := sql.Open("sqlite", filepath.Join(t.TempDir(), "local.db"))
	if err != nil {
		t.Fatal(err)
	}
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { db.Close() })
	if _, err := db.Exec(ddl); err != nil {
		t.Fatal(err)
	}

	return &sqlStore{db: db, schema: func() (Schema, error) {
		rows, err := db.Query(`SELECT m.name, p.name, p.type FROM sqlite_master m, pragma_table_info(m.name) p WHERE m.type = 'table'`)
		if err != nil {
			return nil, err
		}
		defer rows.Close()
		schema := Schema{}
		for rows.Next() {
			var table, column, kind string
			if err := rows.Scan(&table, &column, &kind); err != nil {
				return nil, err
			}
			if schema[strings.ToLower(table)] == nil {
				schema[strings.ToLower(table)] = &Table{Name: table, Columns: map[string]Column{}}
			}
			schema[strings.ToLower(table)].Columns[strings.ToLower(column)] = Column{Name: column, Type: kind}
		}
		return schema, rows.Err()
	}}
}

const shopDDL = `
CREATE TABLE Items (ItemID INTEGER PRIMARY KEY, Code VARCHAR(20), Title VARCHAR(50), Rate DECIMAL(10,2), Qty INTEGER);
CREATE TABLE Sales (SaleID INTEGER PRIMARY KEY, WebNo VARCHAR(30), Email VARCHAR(50), Amount DECIMAL(10,2), SaleDate DATETIME, State VARCHAR(20), Source VARCHAR(10));
CREATE TABLE SaleLines (LineID INTEGER PRIMARY KEY, SaleID INTEGER, Code VARCHAR(20), Qty INTEGER, Rate DECIMAL(10,2));
INSERT INTO Items (ItemID, Code, Title, Rate, Qty) VALUES (1, 'A1', 'Tea', 99.6, 10);
`

const shopMapping = `{
 "products": {"table":"Items","columns":{"external_id":"ItemID","sku":"Code","name":"Title","price":"Rate"}},
 "stock": {"table":"items","columns":{"sku":"code","on_hand":"qty"}},
 "orders_in": {"table":"Sales","key":"SaleID","columns":{"number":"WebNo","email":"Email","total":"Amount","placed_at":"SaleDate","status":"State"},
   "defaults":{"Source":"WEB"},"status_values":{"Void":"cancelled","New":"confirmed"}},
 "order_items_in": {"table":"SaleLines","parent":"SaleID","columns":{"sku":"Code","qty":"Qty","price":"Rate"}},
 "stock_on_import": {"table":"Items","sku_column":"Code","qty_column":"Qty"},
 "order_status": {"table":"Sales","columns":{"number":"WebNo","status":"State"},"status_values":{"Shipped":"shipped"}}
}`

// fakeServer stands in for the API; the contract itself is checked by the e2e test below.
type fakeServer struct {
	mapping  string
	orders   []map[string]any
	reject   map[string]bool
	received map[string][]map[string]any
	acks     []map[string]any
	reports  []map[string]any
	schemas  int
}

func (f *fakeServer) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	path := r.URL.Path
	var body map[string]any
	_ = json.NewDecoder(r.Body).Decode(&body)

	switch {
	case path == "/agent/config":
		w.Write([]byte(`{"mapping":` + f.mapping + `}`))
	case path == "/agent/schema":
		f.schemas++
		w.Write([]byte(`{}`))
	case path == "/agent/report":
		f.reports = append(f.reports, body)
		w.Write([]byte(`{}`))
	case path == "/changes/orders" && r.Method == "GET":
		json.NewEncoder(w).Encode(map[string]any{"data": f.orders, "has_more": false})
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

func newFixture(t *testing.T, ddl, mapping string) (*Engine, *fakeServer, *sqlStore) {
	t.Helper()
	fake := &fakeServer{mapping: mapping, reject: map[string]bool{}, received: map[string][]map[string]any{}}
	server := httptest.NewServer(fake)
	t.Cleanup(server.Close)
	store := openTestStore(t, ddl)
	return &Engine{API: NewAPI(server.URL, "t"), DB: store, StatePath: filepath.Join(t.TempDir(), "state.json"), Version: "v"}, fake, store
}

func webOrder(status string) map[string]any {
	return map[string]any{
		"id": float64(7), "version": float64(1), "number": "ME-1", "email": "a@b.c", "total": float64(250),
		"placed_at": "2026-11-10T10:00:00+00:00", "status": status,
		"items": []any{map[string]any{"sku": "A1", "variant_sku": nil, "qty": float64(3), "price": float64(50)}},
	}
}

func queryInt(t *testing.T, store *sqlStore, query string) int {
	t.Helper()
	var value int
	if err := store.db.QueryRow(query).Scan(&value); err != nil {
		t.Fatal(err)
	}
	return value
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

func TestOrderIsImportedOnceAndTakenOffStockBeforeStockIsSent(t *testing.T) {
	engine, fake, store := newFixture(t, shopDDL, shopMapping)
	fake.orders = []map[string]any{webOrder("confirmed")}

	cleanRun(t, engine, fake)

	if got := queryInt(t, store, `SELECT Qty FROM Items WHERE Code='A1'`); got != 7 {
		t.Fatalf("local qty = %d", got)
	}
	if got := queryInt(t, store, `SELECT COUNT(*) FROM SaleLines l JOIN Sales s ON s.SaleID=l.SaleID WHERE s.WebNo='ME-1' AND s.Source='WEB' AND s.State='New' AND l.Code='A1' AND l.Qty=3`); got != 1 {
		t.Fatal("order or its line was not written with its parent key")
	}
	if len(fake.acks) != 1 || text(fake.acks[0]["external_id"]) != "1" {
		t.Fatalf("acks = %v", fake.acks)
	}
	stock := fake.received["/stock"]
	if len(stock) != 1 || stock[0]["on_hand"] != float64(7) {
		t.Fatalf("stock sent = %v", stock)
	}

	// The ack was lost and the order comes again: it must not be imported twice.
	cleanRun(t, engine, fake)
	if got := queryInt(t, store, `SELECT Qty FROM Items WHERE Code='A1'`); got != 7 {
		t.Fatalf("stock reduced twice: %d", got)
	}
	if got := queryInt(t, store, `SELECT COUNT(*) FROM Sales`); got != 1 {
		t.Fatalf("order imported twice: %d", got)
	}
}

func TestCancelledOrderRestocksOnce(t *testing.T) {
	engine, fake, store := newFixture(t, shopDDL, shopMapping)
	fake.orders = []map[string]any{webOrder("confirmed")}
	cleanRun(t, engine, fake)

	fake.orders = []map[string]any{webOrder("cancelled")}
	cleanRun(t, engine, fake)
	cleanRun(t, engine, fake)

	if got := queryInt(t, store, `SELECT Qty FROM Items WHERE Code='A1'`); got != 10 {
		t.Fatalf("qty = %d", got)
	}
	if got := queryInt(t, store, `SELECT COUNT(*) FROM Sales WHERE State='Void'`); got != 1 {
		t.Fatal("status not translated")
	}
}

func TestCancelledOrderNeverImportedIsSkippedButConfirmed(t *testing.T) {
	engine, fake, store := newFixture(t, shopDDL, shopMapping)
	fake.orders = []map[string]any{webOrder("cancelled")}
	cleanRun(t, engine, fake)

	if got := queryInt(t, store, `SELECT COUNT(*) FROM Sales`); got != 0 {
		t.Fatal("cancelled order imported")
	}
	if len(fake.acks) != 1 {
		t.Fatal("not acknowledged")
	}
}

func TestFailedImportRollsBackAndIsNotAcknowledged(t *testing.T) {
	ddl := shopDDL + `CREATE TRIGGER no_lines BEFORE INSERT ON SaleLines BEGIN SELECT RAISE(ABORT, 'locked'); END;`
	engine, fake, store := newFixture(t, ddl, shopMapping)
	fake.orders = []map[string]any{webOrder("confirmed")}

	if err := engine.Run(); err != nil {
		t.Fatal(err)
	}

	if got := queryInt(t, store, `SELECT COUNT(*) FROM Sales`); got != 0 {
		t.Fatal("header survived a failed import")
	}
	if len(fake.acks) != 0 {
		t.Fatal("failed import was acknowledged")
	}
	if fake.reports[0]["ok"] != false {
		t.Fatal("failure was not reported")
	}
}

func TestOnlyChangedProductsAreSentAndRejectedOnesRetried(t *testing.T) {
	engine, fake, store := newFixture(t, shopDDL+`INSERT INTO Items VALUES (2,'B2','Rice',10,1);`, shopMapping)
	fake.reject["2"] = true

	cleanRun2 := func() {
		if err := engine.Run(); err != nil {
			t.Fatal(err)
		}
	}
	cleanRun2()
	if got := len(fake.received["/products"]); got != 2 {
		t.Fatalf("first run sent %d", got)
	}
	if fake.received["/products"][0]["price"] != float64(100) {
		t.Fatalf("price not rounded: %v", fake.received["/products"][0])
	}

	cleanRun2() // only the rejected one comes again
	if got := len(fake.received["/products"]); got != 3 {
		t.Fatalf("second run total %d", got)
	}

	delete(fake.reject, "2")
	cleanRun2() // accepted now
	cleanRun2() // and nothing is due
	if got := len(fake.received["/products"]); got != 4 {
		t.Fatalf("after accept total %d", got)
	}

	if _, err := store.db.Exec(`UPDATE Items SET Rate=200 WHERE Code='A1'`); err != nil {
		t.Fatal(err)
	}
	cleanRun2()
	if got := len(fake.received["/products"]); got != 5 {
		t.Fatalf("after change total %d", got)
	}
	if got := len(fake.received["/stock"]); got != 10 {
		t.Fatalf("stock is always sent in full; got %d", got)
	}
}

func TestStatusIsTranslatedAndUnknownValuesAreNotSent(t *testing.T) {
	ddl := shopDDL + `INSERT INTO Sales (WebNo, State) VALUES ('ME-2','Shipped'),('ME-3','Packing'),(NULL,'Shipped');`
	engine, fake, _ := newFixture(t, ddl, shopMapping)
	cleanRun(t, engine, fake)

	sent := fake.received["/orders/status"]
	if len(sent) != 1 || sent[0]["number"] != "ME-2" || sent[0]["status"] != "shipped" {
		t.Fatalf("sent = %v", sent)
	}
}

func TestNoMappingOnlyReportsTablesAndSchemaGoesOnce(t *testing.T) {
	engine, fake, store := newFixture(t, shopDDL, `null`)

	cleanRun(t, engine, fake)
	cleanRun(t, engine, fake)
	if fake.schemas != 1 {
		t.Fatalf("schema sent %d times", fake.schemas)
	}
	if fake.reports[0]["message"] != "Waiting for a mapping; local tables have been reported." {
		t.Fatalf("message = %v", fake.reports[0]["message"])
	}

	if _, err := store.db.Exec(`CREATE TABLE Extra (Id INTEGER)`); err != nil {
		t.Fatal(err)
	}
	cleanRun(t, engine, fake)
	if fake.schemas != 2 {
		t.Fatal("a new table was not reported")
	}
}

func TestMappingCannotInjectOrUseUnknownNames(t *testing.T) {
	for _, mapping := range []string{
		`{"stock":{"table":"Items; DROP TABLE Items","columns":{"sku":"Code"}}}`,
		`{"stock":{"table":"Items","columns":{"sku":"Code] FROM Items; DROP TABLE Items; --"}}}`,
		`{"stock":{"table":"Items","columns":{"sku":"Nope"}}}`,
	} {
		engine, fake, store := newFixture(t, shopDDL, mapping)
		if err := engine.Run(); err != nil {
			t.Fatal(err)
		}
		if fake.reports[0]["ok"] != false {
			t.Fatalf("not reported: %s", mapping)
		}
		if got := queryInt(t, store, `SELECT COUNT(*) FROM Items`); got != 1 {
			t.Fatal("table damaged")
		}
		if len(fake.received["/stock"]) != 0 {
			t.Fatal("data sent for a bad mapping")
		}
	}
}

// ---- Updates ----------------------------------------------------------------------------

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
		t.Fatal("a network or database failure must settle the trial, not count as a crash")
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

// Runs against a real EcomVision server when EV_E2E_API and EV_E2E_TOKEN are set.
func TestAgainstRealServer(t *testing.T) {
	base, token := os.Getenv("EV_E2E_API"), os.Getenv("EV_E2E_TOKEN")
	if base == "" {
		t.Skip("EV_E2E_API is not set")
	}

	store := openTestStore(t, shopDDL)
	engine := &Engine{API: NewAPI(base, token), DB: store, StatePath: filepath.Join(t.TempDir(), "state.json"), Version: "e2e"}

	if err := engine.Run(); err != nil {
		t.Fatal(err)
	}
	if got := queryInt(t, store, `SELECT Qty FROM Items WHERE Code='A1'`); got != 8 {
		t.Fatalf("local qty after web order of 2 = %d", got)
	}
	if got := queryInt(t, store, `SELECT COUNT(*) FROM Sales WHERE WebNo='ME-E2E' AND Source='WEB'`); got != 1 {
		t.Fatal("web order not imported")
	}
	if got := queryInt(t, store, `SELECT COUNT(*) FROM SaleLines l JOIN Sales s ON s.SaleID=l.SaleID WHERE l.Code='A1' AND l.Qty=2`); got != 1 {
		t.Fatal("web order line not imported")
	}

	if _, err := store.db.Exec(`UPDATE Sales SET State='Shipped' WHERE WebNo='ME-E2E'`); err != nil {
		t.Fatal(err)
	}
	if err := engine.Run(); err != nil {
		t.Fatal(err)
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
