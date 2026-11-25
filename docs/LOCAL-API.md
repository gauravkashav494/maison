# Local API for the EcomVision sync agent

This is the API we ask the shop software (MS Access) to provide. Our **sync agent** runs on the
same PC, or on another PC on the same network, and calls this API. Your software does not need
internet access. The agent passes data between your API and the online store.

```
Your software  <-- this API (local network) -->  EcomVision agent  <-- internet -->  Online store
```

What goes in each direction:

| Data | Direction | Endpoint |
|---|---|---|
| Product master (with variants) | your software → agent | `GET /products` |
| Stock levels, with why each one changed | your software → agent | `GET /stock` |
| Customer master | your software → agent | `GET /customers` |
| Web orders | agent → your software | `POST /orders` |
| Changes to web orders (cancelled, invoice number) | agent → your software | `POST /orders/{number}/update` |
| Order fulfilment (dispatched, tracking) | your software → agent | `GET /order-status` (recommended) |

---

## 1. General rules

- **Base URL:** for example `http://localhost:8765/ecom/v1`. Any port is fine; tell us which one.
  It may be served only to the local machine or network. It must never be exposed to the internet.
- **Authentication:** every request carries the header `X-Api-Key: <key>`. You choose the key,
  and we enter it in the agent. Answer `401` when the key is missing or wrong.
- **Format:** JSON, UTF-8, `Content-Type: application/json`.
- **Dates:** ISO 8601 with the time zone, e.g. `2026-11-23T14:05:00+05:30`.
- **Money:** a number in rupees with at most two decimals (`499.5`, not `"₹499.50"`).
- **Quantities:** whole numbers.
- **IDs:** `external_id` is your own primary key for the record (item code, party ID…). It must
  never change and never be reused.
- **Polling:** the agent calls every 1 to 5 minutes. Each call should answer within 30 seconds.
- **Errors:** use a normal HTTP status with a body like `{"error": "Item master is locked"}`.
  The agent retries on the next cycle.

### Paging and "changed since"

The product, stock and customer lists send only what has changed, a page at a time:

```
GET /products?changed_since=2026-11-23T10:00:00+05:30&page=1&per_page=500
```

- Without `changed_since`, return **everything**. The first sync uses this, and so does a daily
  full check.
- With it, return records **added or changed at or after** that time, including records made
  inactive. Please **don't delete records**: mark them inactive so the agent learns about it.
- Every list response includes `server_time`, the time on **your** PC when the response was built.
  The agent sends that value back as the next `changed_since`, so the two clocks never need to agree.
- `has_more: true` means the agent should ask for the next page.

---

## 2. Product master: `GET /products`

```json
{
  "server_time": "2026-11-23T14:05:00+05:30",
  "prices_include_tax": true,
  "page": 1,
  "has_more": false,
  "items": [
    {
      "external_id": "ITM-1001",
      "sku": "SOAP-LUX-100",
      "name": "Lux Soap 100 g",
      "price": 45.00,
      "mrp": 50.00,
      "tax_rate": 18,
      "category": "Bath & Body",
      "brand": "Lux",
      "unit": "pcs",
      "is_active": true,
      "updated_at": "2026-11-23T11:42:10+05:30",
      "variants": []
    },
    {
      "external_id": "ITM-2040",
      "sku": "TEE",
      "name": "Cotton T-Shirt",
      "price": 499.00,
      "mrp": 699.00,
      "tax_rate": 5,
      "category": "T-Shirts",
      "brand": "Acme",
      "unit": "pcs",
      "is_active": true,
      "updated_at": "2026-11-23T13:10:00+05:30",
      "variants": [
        {"sku": "TEE-BLK-M", "options": {"Colour": "Black", "Size": "M"}, "price": 499.00, "is_active": true},
        {"sku": "TEE-BLK-L", "options": {"Colour": "Black", "Size": "L"}, "price": 549.00, "is_active": true}
      ]
    }
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `external_id` | yes | Your item's primary key |
| `sku` | yes | Item code or barcode, unique across all items **and** variants. The agent matches products, stock and order lines by it. |
| `name` | yes | |
| `price` | yes | Selling price |
| `prices_include_tax` | yes | At the top of the response: `true` if `price` includes GST, `false` if GST is added on top |
| `mrp` | no | Shown as the struck-out price when it is higher than `price` |
| `tax_rate` | recommended | GST %: 0, 5, 12, 18, 28 or 40 |
| `category`, `brand` | no | Names, not IDs |
| `unit` | no | pcs, kg, box… |
| `is_active` | yes | `false` for discontinued items |
| `updated_at` | yes | Last change to any field above, variants included (a stock change alone doesn't count here: it goes in `/stock`) |
| `variants` | no | Only for items sold in sizes or colours. Each variant has its own unique `sku`, 1 to 5 `options` and an optional `price` (the item's price when left out) |

Stock is not part of this list. It comes from `GET /stock` (section 3). Images and descriptions are
managed on the online store, so they aren't needed here.

---

## 3. Stock levels: `GET /stock`

The quantity in hand of every item and variant, with why it last changed. It works like the
**Inventory → Stock levels** screen on the online store, where staff adjust stock as stock
received, a customer return, damaged or lost, or a stock count. The reason you send is shown the
same way in the store's stock history.

Same paging and `changed_since` as section 1: list each SKU whose stock changed at or after that
time.

```json
{
  "server_time": "2026-11-23T14:05:00+05:30",
  "page": 1,
  "has_more": false,
  "items": [
    {"sku": "SOAP-LUX-100", "item_external_id": "ITM-1001", "on_hand": 120, "reason": "received", "note": "GRN-2231", "updated_at": "2026-11-23T11:42:10+05:30"},
    {"sku": "TEE-BLK-M", "item_external_id": "ITM-2040", "on_hand": 8, "reason": "sale", "note": "Bill 55012", "updated_at": "2026-11-23T13:10:00+05:30"},
    {"sku": "TEE-BLK-L", "item_external_id": "ITM-2040", "on_hand": 0, "reason": "count", "note": "Monthly stock count", "updated_at": "2026-11-23T13:40:00+05:30"},
    {"sku": "MUG-CER-01", "item_external_id": "ITM-3100", "on_hand": 14, "reason": "damaged", "note": "2 broken in transit", "updated_at": "2026-11-23T13:55:00+05:30"},
    {"sku": "BAG-JUTE-L", "item_external_id": "ITM-3205", "on_hand": 6, "reason": "returned", "note": "Credit note CN-118", "updated_at": "2026-11-23T14:01:00+05:30"}
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `sku` | yes | One row per SKU you sell: the item's SKU, or for an item with variants each **variant's** SKU (not the item's) |
| `item_external_id` | no | The item's `external_id`, to help us check |
| `on_hand` | yes | The **full quantity in hand now**: a whole number, never negative. Not the change |
| `reason` | recommended | Why it last changed (table below) |
| `note` | no | Up to 255 characters, e.g. the GRN, bill or credit-note number. Shown beside the change |
| `updated_at` | yes | When the stock last changed |

**Reasons** (the same as the online store's Adjust stock screen, plus counter sales):

| `reason` | Use it for | Shown online as |
|---|---|---|
| `sale` | A sale at the counter or a bill in your software | Sold in shop |
| `received` | A purchase or goods received | Stock received |
| `returned` | Goods a customer brought back | Customer return |
| `damaged` | Damaged, expired, lost or written off | Damaged or lost |
| `count` | A physical stock count or a manual correction | Stock count |
| empty or left out | Anything else | From local system |

Any other value is refused, so send `null` rather than a reason of your own.

**Stock rules:**

- **`on_hand` is always the whole quantity.** The online store works out the difference from the
  last level it had and records that, with your reason. So a missed or repeated row never makes the
  stock wrong.
- **Several changes between two calls:** send the latest level with the reason of the latest change.
  The store records one line for the total difference. If you can, keep the call interval short.
- **Reduce stock as soon as you import a web order**, in the same transaction (section 5). `on_hand`
  must already exclude web orders you have accepted, or the same stock is sold twice. Leave `reason`
  empty for this change: the online store already recorded that sale.
- **Put stock back for a cancelled web order** (section 6), also with `reason` empty.
- Without `changed_since`, return every SKU. The agent does this daily to correct any drift.

---

## 4. Customer master: `GET /customers`

Same paging and `changed_since` as section 1.

```json
{
  "server_time": "2026-11-23T14:05:00+05:30",
  "page": 1,
  "has_more": false,
  "items": [
    {
      "external_id": "PARTY-311",
      "name": "Priya Sharma",
      "phone": "9876543210",
      "email": "priya@example.com",
      "gstin": null,
      "city": "Pune",
      "is_active": true,
      "updated_at": "2026-11-20T09:15:00+05:30"
    }
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `external_id` | yes | Your party or customer primary key |
| `name` | yes | |
| `phone` | phone or email | 10-digit Indian mobile preferred |
| `email` | phone or email | |
| `gstin`, `city` | no | |
| `is_active` | yes | |
| `updated_at` | yes | |

Customers are matched with online-store accounts by phone, or else by email.

---

## 5. Web orders: `POST /orders`

The agent sends each new online order, one per request. Please save it as a sale or order in your
software and **reduce stock** for its lines, in one transaction.

```json
{
  "number": "EFS-261123-K7QM",
  "placed_at": "2026-11-23T13:58:41+05:30",
  "status": "confirmed",
  "customer": {
    "external_id": "PARTY-311",
    "name": "Priya Sharma",
    "phone": "9876543210",
    "email": "priya@example.com"
  },
  "shipping_address": {
    "name": "Priya Sharma",
    "line1": "Flat 4, Green Park",
    "line2": "Baner Road",
    "city": "Pune",
    "state": "Maharashtra",
    "postal_code": "411045",
    "country": "India"
  },
  "payment_method": "cod",
  "payment_status": "pending",
  "prices_include_tax": true,
  "subtotal": 1044.00,
  "discount": 0.00,
  "coupon_code": null,
  "shipping_cost": 0.00,
  "tax": 101.65,
  "total": 1044.00,
  "notes": "Please call before delivery",
  "lines": [
    {"sku": "TEE-BLK-L", "item_external_id": "ITM-2040", "name": "Cotton T-Shirt (Black / L)", "qty": 1, "price": 549.00, "tax_rate": 5, "tax": 26.14, "total": 549.00},
    {"sku": "SOAP-LUX-100", "item_external_id": "ITM-1001", "name": "Lux Soap 100 g", "qty": 11, "price": 45.00, "tax_rate": 18, "tax": 75.51, "total": 495.00}
  ]
}
```

- `number` is the online order number, e.g. `EFS-261123-K7QM`: the store's prefix (2 to 5 letters
  or digits, set in the store's settings, otherwise taken from its name), the date as YYMMDD, and 4
  random characters. It is at most 20 characters long and unique within the store. Keep it as
  text, and use it to find the order in `POST /orders/{number}/update` and `GET /order-status`.
- `customer.external_id` is filled in when the shopper is already in your customer master.
  Otherwise it is `null`. Create the customer, or use a "Web customer" account, as you prefer.
- `payment_method` is `cod`, `upi`, `card`, `netbanking` or `wallet`.
- Each line's `sku` is one you sent in `/products` (a variant SKU for variant items).

**Your answer:**

```json
{"result": "created", "local_id": "SALE-55012"}
```

- **Sending the same `number` again must not create a second order.** If the connection drops,
  the agent sends the order again. Answer `{"result": "exists", "local_id": "SALE-55012"}`.
- If the order can't be saved (unknown SKU, database locked…), answer a 4xx/5xx status with
  `{"error": "..."}`. The agent will try again next cycle.

---

## 6. Changes to a web order: `POST /orders/{number}/update`

The agent sends this when something changes on the online store after the order was sent to you:
the customer cancels, the address is corrected, or the tax invoice number is issued on dispatch.

```json
{
  "status": "cancelled",
  "cancellation_reason": "Customer changed mind",
  "invoice_number": null,
  "shipping_address": {"name": "Priya Sharma", "line1": "Flat 4, Green Park", "line2": "Baner Road", "city": "Pune", "state": "Maharashtra", "postal_code": "411045", "country": "India"},
  "payment_status": "pending"
}
```

- `status` is one of `confirmed`, `processing`, `shipped`, `out_for_delivery`, `delivered`,
  `cancelled`.
- **When an order becomes `cancelled`, put its stock back, once only.** The same update may arrive
  twice.
- Answer `{"result": "updated"}`, or `{"result": "unchanged"}` if nothing was different.
- Answer `404` if you don't have the order. The agent will send it through `POST /orders` first.

---

## 7. Order fulfilment: `GET /order-status` (recommended)

When orders are packed and dispatched from your software, list the web orders whose status changed,
so the customer sees tracking online. It uses the same `changed_since` and `server_time` as
section 1.

```json
{
  "server_time": "2026-11-23T17:30:00+05:30",
  "items": [
    {
      "number": "EFS-261123-K7QM",
      "status": "shipped",
      "carrier": "BlueDart",
      "tracking_number": "BD123456789",
      "note": "Packed in 1 box",
      "updated_at": "2026-11-23T17:20:00+05:30"
    }
  ]
}
```

- Use the statuses listed in section 6. Status moves forward only: confirmed → processing →
  shipped → out_for_delivery → delivered, or → cancelled before dispatch.
- Include only orders that came from the online store (those received through `POST /orders`).

---

## 8. Health check: `GET /health`

```json
{"ok": true, "software": "ShopMaster", "version": "4.2.1", "server_time": "2026-11-23T14:05:00+05:30"}
```

The agent calls this first on every cycle. If it fails, the agent skips the cycle and tries again
later, so nothing is lost while your software is closed.

---

## 9. Checklist before going live

- [ ] Every item and variant has a unique `sku`, and every record has a fixed `external_id`.
- [ ] `/products`, `/stock` and `/customers` support `changed_since` and paging, and report
      `server_time`.
- [ ] `/stock` has one row per SKU (each variant's own SKU), `on_hand` is the full quantity,
      `reason` is one of `sale`, `received`, `returned`, `damaged`, `count` or empty, and `on_hand`
      already excludes imported web orders.
- [ ] `POST /orders` is safe to repeat (same `number` → `exists`) and reduces stock in the same
      transaction.
- [ ] A cancelled order restocks exactly once.
- [ ] The API key is required on every call, and the API isn't reachable from the internet.
- [ ] Send us a sample response from each GET endpoint using your real data, so we can test before
      the shop goes live.
