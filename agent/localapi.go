package main

// The agent's only connector: it talks to the shop software's own JSON API on the local network,
// as docs/LOCAL-API.md describes it. The software's developer builds that API; the agent never opens
// the shop's database itself.
//
//   your software  <-- this API (local network) -->  agent  <-- internet -->  EcomVision
//
// Each cycle: GET /health, then web orders out (POST /orders, POST /orders/{number}/update),
// then the lists in (GET /products, /customers, /stock, /order-status), each read from a
// bookmark: the newest updated_at received, by the shop PC's own clock, less an overlap.

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

const (
	localPageSize = 500
	localMaxPages = 1000
	// Asked for again on every read, so a record saved while the shop software was building
	// the last answer is not missed. Repeats are harmless: EcomVision matches by key.
	localOverlap = 5 * time.Minute
	// How often a list is read whole (no changed_since), to correct anything missed.
	localFullEvery = 24 * time.Hour
)

// LocalAPI talks to the shop software's API (docs/LOCAL-API.md).
type LocalAPI struct {
	BaseURL string
	Key     string
	client  *http.Client
}

// NewLocalAPI is for an address on this PC or the shop's network: no proxy.
func NewLocalAPI(baseURL, key string) *LocalAPI {
	return &LocalAPI{
		BaseURL: strings.TrimRight(baseURL, "/"),
		Key:     key,
		client:  &http.Client{Timeout: 60 * time.Second, Transport: &http.Transport{Proxy: nil}},
	}
}

// localError is an answer from the shop software that is not a success.
type localError struct {
	Method, Path string
	Status       int
	Detail       string
}

func (e *localError) Error() string {
	return fmt.Sprintf("shop software %s %s: HTTP %d: %s", e.Method, e.Path, e.Status, e.Detail)
}

func isLocalStatus(err error, status int) bool {
	var answer *localError
	return errors.As(err, &answer) && answer.Status == status
}

// Do sends one request and decodes the JSON answer into out (when given).
func (l *LocalAPI) Do(method, path string, query url.Values, body, out any) error {
	var reader io.Reader
	if body != nil {
		encoded, err := json.Marshal(body)
		if err != nil {
			return err
		}
		reader = bytes.NewReader(encoded)
	}

	address := l.BaseURL + "/" + path
	if len(query) > 0 {
		address += "?" + query.Encode()
	}
	request, err := http.NewRequest(method, address, reader)
	if err != nil {
		return err
	}
	request.Header.Set("X-Api-Key", l.Key)
	request.Header.Set("Accept", "application/json")
	if body != nil {
		request.Header.Set("Content-Type", "application/json")
	}

	response, err := l.client.Do(request)
	if err != nil {
		return fmt.Errorf("shop software %s %s: %w", method, path, err)
	}
	defer response.Body.Close()

	if response.StatusCode >= 300 {
		detail, _ := io.ReadAll(io.LimitReader(response.Body, 300))
		var answer struct {
			Error string `json:"error"`
		}
		if json.Unmarshal(detail, &answer) == nil && answer.Error != "" {
			detail = []byte(answer.Error)
		}
		return &localError{Method: method, Path: path, Status: response.StatusCode, Detail: strings.TrimSpace(string(detail))}
	}
	if out == nil {
		_, err = io.Copy(io.Discard, response.Body)
		return err
	}
	return json.NewDecoder(response.Body).Decode(out)
}

// localConfig asks EcomVision where the shop's billing software is: its API address and key, as
// set in the store's panel (Shop software sync → Billing software connection). Read at install
// and every cycle, so the installer asks for nothing but the setup code and a new address or key
// reaches the shop PC on its own.
func localConfig(api *API) (*LocalAPI, error) {
	var config struct {
		URL string `json:"local_api_url"`
		Key string `json:"local_api_key"`
	}
	if err := api.Call("GET", "agent/config", nil, &config); err != nil {
		return nil, err
	}
	if strings.TrimSpace(config.URL) == "" || strings.TrimSpace(config.Key) == "" {
		return nil, errors.New("the billing software's API key is not set yet: in your store's panel, open Shop software sync > Billing software connection and enter the address and key from the software's developer")
	}
	return NewLocalAPI(config.URL, config.Key), nil
}

// ---- The cycle ------------------------------------------------------------------------------

func (e *Engine) localCycle(message *string) error {
	var health struct {
		OK bool `json:"ok"`
	}
	if err := e.Local.Do("GET", "health", nil, nil, &health); err != nil || !health.OK {
		// Closed or busy: nothing is lost, the next cycle picks up from the same bookmarks.
		*message = "The shop software did not answer; will try again next cycle."
		if err == nil {
			err = errors.New("shop software GET health: ok is not true")
		}
		return err
	}

	// Products and customers made on the website first, so the orders after them can name their
	// local ids; then orders, so the stock read below already has them taken off.
	e.step("products_out", func() error { return e.feed("products", e.localProductOut) })
	e.step("customers_out", func() error { return e.feed("customers", e.localCustomerOut) })
	e.step("orders", func() error { return e.feed("orders", e.localOrder) })
	for _, name := range []string{"products", "customers", "stock"} {
		e.step(name, func() error { return e.localList(name, name, e.listHandler(name)) })
	}
	// Recommended in the spec, not required: a shop software without it answers 404.
	e.step("order_status", func() error {
		err := e.localList("order_status", "order-status", e.listHandler("order_status"))
		if isLocalStatus(err, http.StatusNotFound) {
			return nil
		}
		return err
	})

	return e.saveState()
}

// listHandler sends a page of one of the shop software's lists to EcomVision.
func (e *Engine) listHandler(name string) func([]map[string]any) error {
	switch name {
	case "products":
		return func(items []map[string]any) error {
			if err := e.sendChanged("products", "products", "external_id", mapEach(items, localProduct)); err != nil {
				return err
			}
			// An item's own on_hand (its opening stock, or its stock now) goes in as a stock push,
			// only once EcomVision has the product, so a new item doesn't sit at 0 until /stock.
			return e.sendBatches("stock", "stock", "sku", e.productStock(items), map[string]string{})
		}
	case "customers":
		return func(items []map[string]any) error {
			return e.sendChanged("customers", "customers", "external_id", mapEach(items, localCustomer))
		}
	case "stock":
		// Not checked against what was sent: the same count with a new reason is still news, and
		// a repeated row changes nothing on EcomVision.
		return func(items []map[string]any) error {
			return e.sendBatches("stock", "stock", "sku", mapEach(items, localStock), map[string]string{})
		}
	default: // order_status
		return func(items []map[string]any) error {
			return e.sendChanged("order_status", "orders/status", "number", mapEach(items, localOrderStatus))
		}
	}
}

func (e *Engine) now() time.Time {
	if e.Now != nil {
		return e.Now()
	}
	return time.Now()
}

// localList reads a list page by page from its bookmark, hands each page on, and moves the
// bookmark to the newest updated_at once every page has been handled.
func (e *Engine) localList(name, path string, handle func([]map[string]any) error) error {
	full := true
	if last, err := time.Parse(time.RFC3339, e.state.Full[name]); err == nil {
		full = e.now().Sub(last) >= localFullEvery
	}

	newest, _ := parseLocalTime(e.state.Since[name])
	query := url.Values{"per_page": {strconv.Itoa(localPageSize)}}
	if !full && !newest.IsZero() {
		query.Set("changed_since", newest.Add(-localOverlap).Format(time.RFC3339))
	}

	for page := 1; page <= localMaxPages; page++ {
		query.Set("page", strconv.Itoa(page))
		var answer struct {
			Items   []map[string]any `json:"items"`
			HasMore bool             `json:"has_more"`
		}
		if err := e.Local.Do("GET", path, query, nil, &answer); err != nil {
			return err
		}
		e.counts[name+".read"] += len(answer.Items)
		if len(answer.Items) > 0 {
			if err := handle(answer.Items); err != nil {
				return err
			}
		}
		for _, item := range answer.Items {
			if at, ok := parseLocalTime(text(item["updated_at"])); ok && at.After(newest) {
				newest = at
			}
		}
		if !answer.HasMore || len(answer.Items) == 0 {
			break
		}
	}

	if !newest.IsZero() {
		e.state.Since[name] = newest.Format(time.RFC3339)
	}
	if full {
		e.state.Full[name] = e.now().UTC().Format(time.RFC3339)
	}
	return nil
}

// parseLocalTime reads an updated_at: ISO 8601 with its zone, as the spec asks; a time with no
// zone is read in the agent's own (the same PC, or one in the same shop).
func parseLocalTime(value string) (time.Time, bool) {
	value = strings.TrimSpace(value)
	if value == "" {
		return time.Time{}, false
	}
	for _, layout := range []string{time.RFC3339Nano, "2006-01-02T15:04:05", "2006-01-02 15:04:05"} {
		var parsed time.Time
		var err error
		if strings.Contains(layout, "Z07") {
			parsed, err = time.Parse(layout, value)
		} else {
			parsed, err = time.ParseInLocation(layout, value, time.Local)
		}
		if err == nil {
			return parsed, true
		}
	}
	return time.Time{}, false
}

// sendChanged sends only items that differ from what EcomVision last accepted for that key.
func (e *Engine) sendChanged(name, endpoint, keyField string, items []map[string]any) error {
	sent := e.state.Sent[name]
	if sent == nil {
		sent = map[string]string{}
		e.state.Sent[name] = sent
	}
	pending := []map[string]any{}
	for _, item := range items {
		if key := text(item[keyField]); key != "" && sent[key] != itemHash(item) {
			pending = append(pending, item)
		}
	}
	return e.sendBatches(name, endpoint, keyField, pending, sent)
}

// ---- Local fields to EcomVision's --------------------------------------------------------------

func mapEach(items []map[string]any, shape func(map[string]any) map[string]any) []map[string]any {
	out := make([]map[string]any, 0, len(items))
	for _, item := range items {
		if shaped := shape(item); shaped != nil {
			out = append(out, shaped)
		}
	}
	return out
}

// copyFields puts each named local field, when filled, under EcomVision's name for it, with
// money and quantities rounded as the sync API expects.
func copyFields(into, from map[string]any, fields map[string]string) {
	for local, field := range fields {
		if value, err := apiValue(field, from[local]); err == nil && value != nil {
			into[field] = value
		}
	}
}

// localProduct is GET /products' item as POST /api/sync/v1/products takes it. is_active and
// unit are not sent: whether a product shows online is EcomVision's.
func localProduct(item map[string]any) map[string]any {
	if !filled(item["external_id"]) {
		return nil
	}
	out := map[string]any{}
	copyFields(out, item, map[string]string{
		"external_id": "external_id", "name": "name", "brand": "brand", "category": "category",
		"category_2": "category_2", "category_3": "category_3", "category_4": "category_4",
		"mrp": "compare_at_price", "tax_rate": "tax_rate", "hsn_code": "hsn_code",
		"price_incl_tax": "price_incl_tax", "price_excl_tax": "price_excl_tax", "sku": "sku",
	})

	raw, _ := item["variants"].([]any)
	variants := []map[string]any{}
	for _, entry := range raw {
		local, ok := entry.(map[string]any)
		if !ok || !filled(local["sku"]) {
			continue
		}
		options := map[string]any{}
		if given, ok := local["options"].(map[string]any); ok {
			for name, value := range given {
				if filled(value) {
					options[strings.TrimSpace(name)] = strings.TrimSpace(text(value))
				}
			}
		}
		variant := map[string]any{"options": options}
		copyFields(variant, local, map[string]string{
			"sku": "sku", "hsn_code": "hsn_code", "price_incl_tax": "price_incl_tax", "price_excl_tax": "price_excl_tax",
		})
		variants = append(variants, variant)
	}
	if len(variants) > 0 {
		out["variants"] = variants
	}
	return out
}

// productStock is the stock GET /products gives, as stock rows matched by the item's own id: each
// variant's on_hand (with the variant's SKU) for an item sold in variants, else the item's own, so
// an item needs no SKU. Only for products EcomVision has accepted. No reason, so the stock history
// shows "From local system". A GET /stock row for the same item, read later in the cycle, wins.
func (e *Engine) productStock(items []map[string]any) []map[string]any {
	accepted := e.state.Sent["products"]
	out := []map[string]any{}
	add := func(id, sku, onHand any) {
		if stock := localStock(map[string]any{"item_external_id": id, "sku": sku, "on_hand": onHand}); stock != nil && stock["on_hand"] != nil {
			out = append(out, stock)
		}
	}
	for _, item := range items {
		id := item["external_id"]
		if _, ok := accepted[text(id)]; !ok || !filled(id) {
			continue
		}
		variants, _ := item["variants"].([]any)
		withSku := 0
		for _, entry := range variants {
			if variant, ok := entry.(map[string]any); ok && filled(variant["sku"]) {
				withSku++
				add(id, variant["sku"], variant["on_hand"])
			}
		}
		if withSku == 0 {
			add(id, nil, item["on_hand"])
		}
	}
	return out
}

func localCustomer(item map[string]any) map[string]any {
	if !filled(item["external_id"]) {
		return nil
	}
	out := map[string]any{}
	copyFields(out, item, map[string]string{"external_id": "external_id", "name": "name", "email": "email", "phone": "phone"})
	return out
}

// localStock is a GET /stock row as POST /api/sync/v1/stock takes it: the item by its own id
// (item_external_id), its SKU, or both; a variant by its SKU.
func localStock(item map[string]any) map[string]any {
	if !filled(item["sku"]) && !filled(item["item_external_id"]) {
		return nil
	}
	out := map[string]any{}
	copyFields(out, item, map[string]string{
		"item_external_id": "external_id", "sku": "sku", "on_hand": "on_hand", "reason": "reason", "note": "note",
	})
	return out
}

func localOrderStatus(item map[string]any) map[string]any {
	if !filled(item["number"]) {
		return nil
	}
	out := map[string]any{}
	copyFields(out, item, map[string]string{
		"number": "number", "status": "status", "carrier": "carrier", "tracking_number": "tracking_number", "note": "note",
	})
	if len(out) < 2 {
		return nil // a number alone says nothing
	}
	return out
}

// ---- Web orders to the shop software ----------------------------------------------------------

func shippingAddress(order map[string]any) map[string]any {
	return map[string]any{
		"name": order["shipping_name"], "line1": order["shipping_line1"], "line2": order["shipping_line2"],
		"city": order["shipping_city"], "state": order["shipping_state"], "postal_code": order["shipping_postal_code"],
		"country": order["shipping_country"],
	}
}

// localOrderBody is a feed order as POST /orders takes it (docs/LOCAL-API.md, section 5).
func localOrderBody(order map[string]any) map[string]any {
	lines := []map[string]any{}
	for _, line := range orderLines(order) {
		lines = append(lines, map[string]any{
			"sku": lineSku(line), "item_external_id": line["product_external_id"], "name": line["name"],
			"hsn_code": line["hsn_code"], "qty": line["qty"], "price": line["price"], "tax_rate": line["tax_rate"],
			"tax": line["tax"], "total": line["total"],
		})
	}
	return map[string]any{
		"number": order["number"], "placed_at": order["placed_at"], "status": order["status"],
		"customer": map[string]any{
			"external_id": order["customer_external_id"], "name": order["shipping_name"],
			"phone": order["phone"], "email": order["email"],
		},
		"shipping_address": shippingAddress(order),
		"payment_method":   order["payment_method"], "payment_status": order["payment_status"],
		"prices_include_tax": order["prices_include_tax"],
		"subtotal":           order["subtotal"], "discount": order["discount"],
		"coupon_code": order["coupon_code"], "coupon_type": order["coupon_type"], "coupon_value": order["coupon_value"],
		"shipping_cost": order["shipping_cost"], "tax": order["tax"], "total": order["total"],
		"notes": order["notes"], "lines": lines,
	}
}

// ---- Products and customers made on the website, to the shop software ------------------------

// localProductBody is a products-feed record as POST /products takes it (docs/LOCAL-API.md), in
// GET /products' own field names, so the shop software reads both the same way.
func localProductBody(product map[string]any) map[string]any {
	variants := []map[string]any{}
	raw, _ := product["variants"].([]any)
	for _, entry := range raw {
		if variant, ok := entry.(map[string]any); ok {
			variants = append(variants, map[string]any{
				"options": variant["options"], "sku": variant["sku"], "hsn_code": variant["hsn_code"],
				"price_incl_tax": variant["price_incl_tax"], "price_excl_tax": variant["price_excl_tax"],
				"is_active": variant["is_active"],
			})
		}
	}
	return map[string]any{
		"external_id": product["external_id"], "name": product["name"], "brand": product["brand"],
		"category": product["category"], "category_2": product["category_2"],
		"category_3": product["category_3"], "category_4": product["category_4"],
		"is_active": product["is_active"], "mrp": product["compare_at_price"], "tax_rate": product["tax_rate"],
		"hsn_code": product["hsn_code"], "price_incl_tax": product["price_incl_tax"],
		"price_excl_tax": product["price_excl_tax"], "sku": product["sku"], "variants": variants,
	}
}

func localCustomerBody(customer map[string]any) map[string]any {
	return map[string]any{
		"external_id": customer["external_id"], "name": customer["name"], "email": customer["email"],
		"phone": customer["phone"], "is_active": customer["is_active"],
	}
}

// localProductOut and localCustomerOut hand one record made or changed on the website to the shop
// software, which adds it or brings its own up to date, and return its local id for the ack.
func (e *Engine) localProductOut(product map[string]any) (any, error) {
	return e.localRecordOut("products", localProductBody(product))
}

func (e *Engine) localCustomerOut(customer map[string]any) (any, error) {
	return e.localRecordOut("customers", localCustomerBody(customer))
}

// localRecordOut posts one record and returns the id the shop software keeps it under. Sending
// again is safe: the spec has the shop software find it by external_id (then SKU, or phone and
// email) and answer "updated" instead of adding it twice.
func (e *Engine) localRecordOut(path string, body map[string]any) (any, error) {
	var answer struct {
		Result  string `json:"result"`
		LocalID any    `json:"local_id"`
	}
	if err := e.Local.Do("POST", path, nil, body, &answer); err != nil {
		return nil, err
	}
	if !filled(answer.LocalID) {
		return nil, fmt.Errorf("shop software POST /%s gave no local_id", path)
	}
	e.counts[path+"_out."+answer.Result]++
	return text(answer.LocalID), nil
}

// localOrderUpdate is what POST /orders/{number}/update takes (section 6).
func localOrderUpdate(order map[string]any) map[string]any {
	return map[string]any{
		"status": order["status"], "cancellation_reason": order["cancellation_reason"],
		"invoice_number": order["invoice_number"], "shipping_address": shippingAddress(order),
		"payment_status": order["payment_status"],
	}
}

// localOrder hands one web order to the shop software and returns its local id, which the ack
// stores as the order's external_id. An order the shop software has gets the update; one it has
// not (never sent, or lost there: 404) is sent whole, except a cancelled one, which it never
// needs. Sending again is safe: the spec has POST /orders answer "exists" for a known number.
func (e *Engine) localOrder(order map[string]any) (any, error) {
	number := text(order["number"])
	if number == "" {
		return nil, errors.New("the order has no number")
	}
	linked := order["external_id"]
	update := "orders/" + url.PathEscape(number) + "/update"

	if filled(linked) {
		err := e.Local.Do("POST", update, nil, localOrderUpdate(order), nil)
		if err == nil {
			return linked, nil
		}
		if !isLocalStatus(err, http.StatusNotFound) {
			return nil, err
		}
	}
	if text(order["status"]) == "cancelled" {
		return linked, nil
	}

	var created struct {
		Result  string `json:"result"`
		LocalID any    `json:"local_id"`
	}
	if err := e.Local.Do("POST", "orders", nil, localOrderBody(order), &created); err != nil {
		return nil, err
	}
	// Already there (an earlier send whose answer was lost): bring it up to date as well.
	if created.Result == "exists" {
		if err := e.Local.Do("POST", update, nil, localOrderUpdate(order), nil); err != nil && !isLocalStatus(err, http.StatusNotFound) {
			return nil, err
		}
	}
	e.counts["orders."+created.Result]++
	if filled(created.LocalID) {
		return text(created.LocalID), nil
	}
	return number, nil // no id of its own: the number finds the order there
}
