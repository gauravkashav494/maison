package main

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"net/url"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

// fakeShop stands in for the shop software's API (docs/LOCAL-API.md).
type fakeShop struct {
	healthy  bool
	lists    map[string][][]map[string]any // path => pages
	orders   map[string]map[string]any     // number => body received
	updates  map[string][]map[string]any   // number => updates received
	records  map[string][]map[string]any   // "products" or "customers" => bodies received
	queries  map[string][]url.Values
	badCalls int
}

func newFakeShop() *fakeShop {
	return &fakeShop{healthy: true, lists: map[string][][]map[string]any{}, orders: map[string]map[string]any{},
		updates: map[string][]map[string]any{}, records: map[string][]map[string]any{}, queries: map[string][]url.Values{}}
}

func (s *fakeShop) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Header.Get("X-Api-Key") != "k" {
		s.badCalls++
		w.WriteHeader(http.StatusUnauthorized)
		w.Write([]byte(`{"error":"bad key"}`))
		return
	}
	path := strings.TrimPrefix(r.URL.Path, "/")
	var body map[string]any
	_ = json.NewDecoder(r.Body).Decode(&body)

	switch {
	case path == "health":
		json.NewEncoder(w).Encode(map[string]any{"ok": s.healthy, "software": "Shop"})
	case r.Method == "GET":
		pages, known := s.lists[path]
		if !known {
			w.WriteHeader(http.StatusNotFound)
			w.Write([]byte(`{"error":"not here"}`))
			return
		}
		s.queries[path] = append(s.queries[path], r.URL.Query())
		page := 1
		if n := r.URL.Query().Get("page"); n == "2" {
			page = 2
		}
		items := []map[string]any{}
		if page <= len(pages) {
			items = pages[page-1]
		}
		json.NewEncoder(w).Encode(map[string]any{"items": items, "has_more": page < len(pages)})
	case r.Method == "POST" && (path == "products" || path == "customers"):
		s.records[path] = append(s.records[path], body)
		if filled(body["external_id"]) {
			w.Write([]byte(`{"result":"updated","local_id":"` + text(body["external_id"]) + `"}`))
			return
		}
		w.Write([]byte(`{"result":"created","local_id":"LOCAL-` + text(body["name"]) + `"}`))
	case path == "orders":
		number := text(body["number"])
		if _, exists := s.orders[number]; exists {
			w.Write([]byte(`{"result":"exists","local_id":"SALE-` + number + `"}`))
			return
		}
		s.orders[number] = body
		w.Write([]byte(`{"result":"created","local_id":"SALE-` + number + `"}`))
	case strings.HasPrefix(path, "orders/") && strings.HasSuffix(path, "/update"):
		number, _ := url.PathUnescape(strings.TrimSuffix(strings.TrimPrefix(path, "orders/"), "/update"))
		if _, exists := s.orders[number]; !exists {
			w.WriteHeader(http.StatusNotFound)
			w.Write([]byte(`{"error":"no such order"}`))
			return
		}
		s.updates[number] = append(s.updates[number], body)
		w.Write([]byte(`{"result":"updated"}`))
	default:
		w.WriteHeader(http.StatusNotFound)
	}
}

func newHTTPFixture(t *testing.T) (*Engine, *fakeServer, *fakeShop, *time.Time) {
	t.Helper()
	fake := &fakeServer{reject: map[string]bool{}, received: map[string][]map[string]any{}}
	server := httptest.NewServer(fake)
	t.Cleanup(server.Close)
	shop := newFakeShop()
	shopServer := httptest.NewServer(shop)
	t.Cleanup(shopServer.Close)

	clock := time.Date(2026, 11, 23, 15, 0, 0, 0, time.UTC)
	engine := &Engine{
		API: NewAPI(server.URL, "t"), Local: NewLocalAPI(shopServer.URL+"/", "k"),
		StatePath: filepath.Join(t.TempDir(), "state.json"), Version: "v",
		Now: func() time.Time { return clock },
	}
	return engine, fake, shop, &clock
}

func lastReport(t *testing.T, fake *fakeServer) map[string]any {
	t.Helper()
	if len(fake.reports) == 0 {
		t.Fatal("no report was sent")
	}
	return fake.reports[len(fake.reports)-1]
}

func TestHTTPConnectorSendsOrdersAndReadsListsFromABookmark(t *testing.T) {
	engine, fake, shop, clock := newHTTPFixture(t)

	order := webOrder("confirmed")
	order["customer_external_id"] = "PARTY-1"
	order["shipping_name"] = "Priya"
	order["items"] = []any{map[string]any{"sku": "TEE", "variant_sku": "TEE-M", "product_external_id": "ITM-2", "qty": float64(1), "price": float64(499)}}
	fake.orders = []map[string]any{order}

	shop.lists["products"] = [][]map[string]any{
		{{"external_id": "ITM-1", "name": "Soap", "brand": "Lux", "mrp": 50, "tax_rate": 18, "hsn_code": "3401",
			"price_incl_tax": 45, "price_excl_tax": 38.135, "sku": "SOAP", "is_active": true, "unit": "pcs",
			"updated_at": "2026-11-23T11:00:00+05:30"}},
		{{"external_id": "ITM-2", "name": "Tee", "price_incl_tax": 499, "sku": "TEE", "updated_at": "2026-11-23T12:00:00+05:30",
			"variants": []any{map[string]any{"options": map[string]any{"Size": " M "}, "sku": "TEE-M", "price_excl_tax": 475.24, "is_active": true}}}},
	}
	shop.lists["stock"] = [][]map[string]any{{{"sku": "SOAP", "on_hand": 7.0, "reason": "sale", "note": "Bill 1", "updated_at": "2026-11-23T11:30:00+05:30"}}}
	shop.lists["customers"] = [][]map[string]any{{{"external_id": "PARTY-1", "name": "Priya", "phone": "9876543210", "gstin": "X", "updated_at": "2026-11-20T09:00:00+05:30"}}}
	// No GET /order-status (it is only recommended): a 404 is not an error.

	cleanRun(t, engine, fake)

	// The web order went to the shop software as the spec has it, and was linked by its local id.
	sent := shop.orders["ME-1"]
	if sent == nil {
		t.Fatal("the order was not sent to the shop software")
	}
	customer := sent["customer"].(map[string]any)
	line := sent["lines"].([]any)[0].(map[string]any)
	if customer["external_id"] != "PARTY-1" || line["sku"] != "TEE-M" || line["item_external_id"] != "ITM-2" {
		t.Fatalf("order body: %v", sent)
	}
	if len(fake.acks) != 1 || fake.acks[0]["external_id"] != "SALE-ME-1" {
		t.Fatalf("acks: %v", fake.acks)
	}

	// Both pages of products, in EcomVision's fields; visibility and unit are not sent.
	products := fake.received["/products"]
	if len(products) != 2 {
		t.Fatalf("products: %v", products)
	}
	soap := products[0]
	if soap["compare_at_price"] != 50.0 || soap["price_excl_tax"] != 38.14 || soap["hsn_code"] != "3401" || soap["is_active"] != nil || soap["unit"] != nil {
		t.Fatalf("soap: %v", soap)
	}
	variant := products[1]["variants"].([]any)[0].(map[string]any)
	if variant["sku"] != "TEE-M" || variant["price_excl_tax"] != 475.24 || variant["options"].(map[string]any)["Size"] != "M" {
		t.Fatalf("variant: %v", variant)
	}
	stock := fake.received["/stock"]
	if len(stock) != 1 || stock[0]["on_hand"] != 7.0 || stock[0]["reason"] != "sale" || stock[0]["note"] != "Bill 1" {
		t.Fatalf("stock: %v", stock)
	}
	if customers := fake.received["/customers"]; len(customers) != 1 || customers[0]["gstin"] != nil {
		t.Fatalf("customers: %v", customers)
	}
	if first := shop.queries["products"][0]; first.Has("changed_since") || first.Get("per_page") != "500" {
		t.Fatalf("the first read takes everything: %v", first)
	}

	// Next cycle: from the newest updated_at less the overlap, and nothing unchanged is resent.
	fake.orders = nil
	cleanRun(t, engine, fake)
	again := shop.queries["products"][len(shop.queries["products"])-1]
	if again.Get("changed_since") != "2026-11-23T11:55:00+05:30" {
		t.Fatalf("bookmark: %v", again)
	}
	if len(fake.received["/products"]) != 2 {
		t.Fatalf("unchanged products were sent again: %d", len(fake.received["/products"]))
	}
	if len(fake.received["/stock"]) != 2 {
		t.Fatal("stock is sent every time it is read")
	}

	// A day on, the whole list is read again.
	*clock = clock.Add(25 * time.Hour)
	cleanRun(t, engine, fake)
	if full := shop.queries["products"][len(shop.queries["products"])-1]; full.Has("changed_since") {
		t.Fatalf("the daily read should take everything: %v", full)
	}
	if shop.badCalls != 0 {
		t.Fatal("the API key was not sent")
	}
}

func TestHTTPConnectorSendsTheStockGivenWithAProduct(t *testing.T) {
	engine, fake, shop, _ := newHTTPFixture(t)
	fake.reject["ITM-BAD"] = true
	shop.lists["products"] = [][]map[string]any{{
		{"external_id": "ITM-1", "name": "Soap", "price_incl_tax": 45, "on_hand": 12.0, "updated_at": "2026-11-23T11:00:00+05:30"}, // no SKU
		{"external_id": "ITM-2", "name": "Tee", "price_incl_tax": 499, "sku": "TEE", "on_hand": 99.0, "updated_at": "2026-11-23T11:00:00+05:30",
			"variants": []any{
				map[string]any{"options": map[string]any{"Size": "M"}, "sku": "TEE-M", "on_hand": 4.0},
				map[string]any{"options": map[string]any{"Size": "L"}, "sku": "TEE-L"},
			}},
		{"external_id": "ITM-3", "name": "Oil", "price_incl_tax": 200, "sku": "OIL", "updated_at": "2026-11-23T11:00:00+05:30"},
		{"external_id": "ITM-BAD", "name": "Bad", "price_incl_tax": 1, "sku": "BAD", "on_hand": 5.0, "updated_at": "2026-11-23T11:00:00+05:30"},
	}}
	// GET /stock may name the item by its own id alone.
	shop.lists["stock"] = [][]map[string]any{{{"item_external_id": "ITM-3", "on_hand": 7.0, "reason": "received", "updated_at": "2026-11-23T11:30:00+05:30"}}}
	shop.lists["customers"] = [][]map[string]any{}

	_ = engine.Run() // the rejected product is reported, not fatal

	// By the item's id: its own count, or each variant's with the variant's SKU (the item's count
	// is ignored when it has variants); no row without on_hand, none for a product EcomVision did
	// not accept. Then the /stock row.
	rows := []string{}
	for _, row := range fake.received["/stock"] {
		rows = append(rows, fmt.Sprintf("%v|%v|%v|%v", row["external_id"], row["sku"], row["on_hand"], row["reason"]))
	}
	want := []string{"ITM-1|<nil>|12|<nil>", "ITM-2|TEE-M|4|<nil>", "ITM-3|<nil>|7|received"}
	if strings.Join(rows, ",") != strings.Join(want, ",") {
		t.Fatalf("stock: %v", rows)
	}
	// on_hand is not part of the product itself.
	for _, product := range fake.received["/products"] {
		if _, has := product["on_hand"]; has {
			t.Fatalf("product: %v", product)
		}
	}
}

func TestHTTPConnectorSendsWebProductsAndCustomersBeforeOrders(t *testing.T) {
	engine, fake, shop, _ := newHTTPFixture(t)
	for _, list := range []string{"products", "customers", "stock"} {
		shop.lists[list] = [][]map[string]any{}
	}
	fake.products = []map[string]any{
		{"id": float64(1), "version": float64(1), "external_id": nil, "name": "Mug", "brand": "Acme",
			"category": "Home", "category_2": "Kitchen", "category_3": "Gifts", "category_4": nil,
			"compare_at_price": float64(599), "price_incl_tax": float64(499), "sku": "MUG", "is_active": true,
			"variants": []any{map[string]any{"options": map[string]any{"Colour": "Red"}, "sku": "MUG-R", "price_incl_tax": float64(499), "is_active": true, "stock": float64(3)}}},
		{"id": float64(2), "version": float64(3), "external_id": "ITM-7", "name": "Jug", "variants": []any{}},
	}
	fake.customers = []map[string]any{{"id": float64(5), "version": float64(1), "external_id": nil, "name": "Asha", "phone": "9876543210", "email": nil, "is_active": true}}
	fake.orders = []map[string]any{webOrder("confirmed")}

	cleanRun(t, engine, fake)

	// The new product in GET /products' field names, its categories and variants (no stock);
	// the linked one goes again with its id so the shop software updates rather than adds.
	products := shop.records["products"]
	if len(products) != 2 || products[0]["mrp"] != 599.0 || products[0]["category_3"] != "Gifts" || products[1]["external_id"] != "ITM-7" {
		t.Fatalf("products: %v", products)
	}
	variant := products[0]["variants"].([]any)[0].(map[string]any)
	if variant["sku"] != "MUG-R" || variant["stock"] != nil {
		t.Fatalf("variant: %v", variant)
	}
	if customers := shop.records["customers"]; len(customers) != 1 || customers[0]["phone"] != "9876543210" {
		t.Fatalf("customers: %v", customers)
	}

	// Each is linked by the id the shop software answered, and before the order goes.
	links := map[string]any{}
	for _, ack := range fake.acks {
		links[text(ack["id"])] = ack["external_id"]
	}
	if links["1"] != "LOCAL-Mug" || links["2"] != "ITM-7" || links["5"] != "LOCAL-Asha" {
		t.Fatalf("links: %v", links)
	}
	if text(fake.acks[len(fake.acks)-1]["id"]) != "7" {
		t.Fatalf("the order goes last: %v", fake.acks)
	}
}

func TestTheBillingSoftwareConnectionComesFromTheStorePanel(t *testing.T) {
	engine, fake, shop, _ := newHTTPFixture(t)
	for _, list := range []string{"products", "customers", "stock"} {
		shop.lists[list] = [][]map[string]any{}
	}
	// As the installed agent runs: it knows only EcomVision, not where the shop software is.
	address := engine.Local.BaseURL
	engine.Local = nil

	// Not set in the panel yet: the cycle says so, and reads nothing.
	fake.config = map[string]any{"local_api_url": address, "local_api_key": nil}
	if err := engine.Run(); err == nil {
		t.Fatal("a cycle without a billing software key should report a failure")
	}
	if report := lastReport(t, fake); report["ok"] != false || !strings.Contains(text(report["message"]), "not set in the store's panel") {
		t.Fatalf("report: %v", report)
	}
	if len(shop.queries["products"]) != 0 {
		t.Fatal("nothing is read without a connection")
	}

	// Set: the cycle reaches the shop software with that address and key.
	fake.config = map[string]any{"local_api_url": address, "local_api_key": "k"}
	cleanRun(t, engine, fake)
	if len(shop.queries["products"]) != 1 || shop.badCalls != 0 {
		t.Fatalf("products read %d times, %d calls with a wrong key", len(shop.queries["products"]), shop.badCalls)
	}
}

func TestHTTPConnectorUpdatesResendsAndSkipsWebOrders(t *testing.T) {
	engine, fake, shop, _ := newHTTPFixture(t)
	for _, list := range []string{"products", "customers", "stock"} {
		shop.lists[list] = [][]map[string]any{}
	}
	shop.orders["W-1"] = map[string]any{"number": "W-1"}

	known := webOrder("shipped")
	known["number"], known["external_id"], known["invoice_number"] = "W-1", "SALE-W-1", "INV-7"
	lost := webOrder("confirmed") // linked once, but the shop software no longer has it
	lost["id"], lost["number"], lost["external_id"] = float64(8), "W-2", "SALE-OLD"
	cancelled := webOrder("cancelled") // never reached the shop software
	cancelled["id"], cancelled["number"] = float64(9), "W-3"
	fake.orders = []map[string]any{known, lost, cancelled}

	cleanRun(t, engine, fake)

	if updates := shop.updates["W-1"]; len(updates) != 1 || updates[0]["status"] != "shipped" || updates[0]["invoice_number"] != "INV-7" {
		t.Fatalf("update of W-1: %v", shop.updates)
	}
	if shop.orders["W-2"] == nil {
		t.Fatal("an order the shop software lost is sent whole again")
	}
	if shop.orders["W-3"] != nil {
		t.Fatal("a cancelled order it never had is not sent")
	}
	links := map[string]any{}
	for _, ack := range fake.acks {
		links[text(ack["id"])] = ack["external_id"]
	}
	if links["7"] != "SALE-W-1" || links["8"] != "SALE-W-2" || links["9"] != nil {
		t.Fatalf("links: %v", links)
	}
}

func TestHTTPConnectorWaitsWhileTheShopSoftwareIsDown(t *testing.T) {
	engine, fake, shop, _ := newHTTPFixture(t)
	shop.healthy = false
	shop.lists["products"] = [][]map[string]any{{{"external_id": "ITM-1", "name": "Soap", "price_incl_tax": 45}}}
	fake.orders = []map[string]any{webOrder("confirmed")}

	if err := engine.Run(); err == nil {
		t.Fatal("a cycle without the shop software should report a failure")
	}
	if report := lastReport(t, fake); report["ok"] != false || !strings.Contains(text(report["message"]), "did not answer") {
		t.Fatalf("report: %v", report)
	}
	if len(shop.queries["products"]) != 0 || len(fake.acks) != 0 || len(shop.orders) != 0 {
		t.Fatal("nothing is read, sent or acknowledged while the shop software is down")
	}
}
