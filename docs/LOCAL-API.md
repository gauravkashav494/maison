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
| Product master (with variants and current stock) | your software → agent | `GET /products` |
| Stock levels, with why each one changed | your software → agent | `GET /stock` |
| Customer master | your software → agent | `GET /customers` |
| Web orders | agent → your software | `POST /orders` |
| Changes to web orders (cancelled, invoice number) | agent → your software | `POST /orders/{number}/update` |
| Products added on the online store | agent → your software | `POST /products` |
| Customers who signed up online | agent → your software | `POST /customers` |
| Order fulfilment (dispatched, tracking) | your software → agent | `GET /order-status` (recommended) |

The agent makes every call: every 5 minutes it reads your lists and sends you what changed online.
Your software only answers; it never needs to call the agent.

---

## 1. General rules

- **Base URL:** please use `http://localhost:8765/ecom/v1` (port 8765, path `/ecom/v1`), the address
  the online store fills in by default. If you must use another, tell the shop: they enter it in
  the store's panel along with the API key.
  It may be served only to the local machine or network. It must never be exposed to the internet.
- **Authentication:** every request carries the header `X-Api-Key: <key>`, your software's own
  key (see "Your API key" below). Answer `401` when the key is missing or wrong.
- **Format:** JSON, UTF-8, `Content-Type: application/json`.
- **Dates:** ISO 8601 with the time zone, e.g. `2026-11-23T14:05:00+05:30`.
- **Money:** a number in rupees with at most two decimals (`499.5`, not `"₹499.50"`).
- **Quantities:** whole numbers.
- **IDs:** `external_id` is your own primary key for the record (item code, party ID…). It must
  never change and never be reused.
- **Polling:** the agent calls every 5 minutes. Each call should answer within 30 seconds.
- **Errors:** use a normal HTTP status with a body like `{"error": "Item master is locked"}`.
  The agent retries on the next cycle.

### Your API key

Your API checks a key on every call, so only our agent can use it. **Each shop's copy of your
software has its own key**, made by your software, not one key for every shop.

1. **Make it once, when your software is installed or first started.** A random string of at
   least 32 characters from a secure random generator, never a name, date or shop code:

   | Language | One line |
   |---|---|
   | C# / .NET | `Convert.ToHexString(RandomNumberGenerator.GetBytes(32))` |
   | Python | `secrets.token_hex(32)` |
   | PHP | `bin2hex(random_bytes(32))` |
   | Node.js | `require('crypto').randomBytes(32).toString('hex')` |
   | MS Access (VBA) | `Mid(CreateObject("Scriptlet.TypeLib").GUID, 2, 36) & Mid(CreateObject("Scriptlet.TypeLib").GUID, 2, 36)` (two random GUIDs) |

2. **Save it where your software can read it again.** For software built on MS Access, a settings
   table in your own database is simplest, e.g. `tblSettings` with the row
   `EcomSyncApiKey = 9f3c1a7e…`, which your API reads to check calls and your settings screen
   shows. A settings file readable only by Administrators and your API's Windows service (e.g.
   under `C:\ProgramData\<your software>\`) is safer still. It stays the same until someone
   changes it.
3. **Show it in your software's settings**, with a Copy button and a "Make a new key" button, e.g.
   "EcomVision sync: API key". The shop copies it into its online store's panel (Shop software
   sync → Billing software connection). Nobody has to contact you for it.
4. **Check it on every call**: compare the `X-Api-Key` header with the saved key and answer `401`
   if it is missing or different.
5. **If it ever leaks**, "Make a new key" in your software, and the shop enters the new one in its
   panel. The agent picks it up on its next sync; nothing is reinstalled.

Never put the key in your program itself (it would be the same in every shop), in a shared or
network folder, or in an email.

### "Changed since"

The product, stock and customer lists send only what has changed, all of it in one answer:

```
GET /products?changed_since=2026-11-23T10:00:00+05:30
```

```json
{"items": [ ... ]}
```

- Without `changed_since`, return **everything**. The first sync uses this, and so does a daily
  full check.
- With it, return records **added or changed at or after** that time, including records made
  inactive. Please **don't delete records**: mark them inactive so the agent learns about it.
- **Every record carries `updated_at`**, the time it was last added or changed by **your** PC's
  clock. Set it on every change, including a stock change and making a record inactive.
- The agent keeps the newest `updated_at` it has received, and asks next time for
  `changed_since` = that time **minus 5 minutes**. It never uses its own clock, so the two PCs'
  clocks don't need to agree. The overlap catches anything saved while you were reading, so the
  same record may come again a few times; that is expected and harmless.
- `items` is the only field the answer needs. The agent also adds `page` and `per_page` to the
  address; you can ignore them. Paging is only for a list too big to build within 30 seconds:
  then honour `page` and `per_page` (500) and add `"has_more": true` to every page but the last.
  Never send `"has_more": true` while ignoring `page`: the agent would get the same page again.

---

## 2. Product master: `GET /products`

```json
{
  "items": [
    {
      "external_id": "1001",
      "name": "Lux Soap 100 g",
      "brand": "Lux",
      "category": "Bath & Body",
      "category_2": "Soaps",
      "is_active": true,
      "mrp": 50.00,
      "tax_rate": 18,
      "hsn_code": "3401",
      "price_incl_tax": 45.00,
      "price_excl_tax": 38.14,
      "sku": "SOAP-LUX-100",
      "unit": "pcs",
      "on_hand": 120,
      "variants": [],
      "updated_at": "2026-11-23T11:42:10+05:30"
    },
    {
      "external_id": "2040",
      "name": "Cotton T-Shirt",
      "brand": "Acme",
      "category": "Men",
      "category_2": "T-Shirts",
      "category_3": "Sale",
      "is_active": true,
      "mrp": 699.00,
      "tax_rate": 5,
      "hsn_code": "6109",
      "price_incl_tax": 499.00,
      "price_excl_tax": 475.24,
      "sku": "TEE",
      "unit": "pcs",
      "variants": [
        {"options": {"Colour": "Black", "Size": "M"}, "sku": "TEE-BLK-M", "price_incl_tax": 499.00, "price_excl_tax": 475.24, "is_active": true, "on_hand": 8},
        {"options": {"Colour": "Black", "Size": "L"}, "sku": "TEE-BLK-L", "price_incl_tax": 549.00, "price_excl_tax": 522.86, "is_active": true, "on_hand": 10}
      ],
      "updated_at": "2026-11-23T13:10:00+05:30"
    }
  ]
}
```

The fields follow the online store's product form, so each one is easy to find there.

| Field | Required | On the product form | Notes |
|---|---|---|---|
| `external_id` | yes | (not shown: the link to your record) | Your item's primary key |
| `name` | yes | Details → Name | |
| `brand` | no | Details → Brand | A name, not an ID |
| `category` | yes when any category is sent | Details → Category | The item's category, a name, not an ID: `Men` |
| `category_2` | no | Details → Child category | A child category of `category`: `Shirts` under `Men`. Becomes the item's main category |
| `category_3` | no | Details → Category tag 1 | Any other category the item is also listed under: `Sale`. Independent of the others |
| `category_4` | no | Details → Category tag 2 | One more category the item is also listed under: `Winter Wear`. Independent of the others |
| `is_active` | yes | Details → Visible on storefront | `false` for discontinued items |
| `mrp` | no | Pricing → MRP | Including GST, as printed on the pack. Shown as the struck-out price when it is higher than the selling price |
| `tax_rate` | recommended | Pricing → Tax | GST %: 0, 5, 12, 18, 28 or 40 |
| `hsn_code` | recommended | Pricing → HSN code | GST classification of the goods: 4, 6 or 8 digits, e.g. `6109` or `61091000`. Printed on the tax invoice |
| `price_incl_tax` and/or `price_excl_tax` | yes, at least one | Pricing → Price incl. tax / Price excl. tax | Selling price, as your software holds it: `price_incl_tax` including GST, `price_excl_tax` before GST, or **both** if you keep both. The online store converts with the item's `tax_rate`, so you never need to know how the store shows prices (see the notes below the table) |
| `sku` | no | Pricing → SKU / Barcode | Item code or barcode, if your software has one; unique across all items **and** variants when given. Items, their stock and order lines are matched by `external_id`, so an item needs no SKU |
| `unit` | no | (not on the form) | pcs, kg, box… |
| `on_hand` | recommended | Initial stock (new product) / Current stock | The **current quantity in hand**: a whole number, never negative. For a new item this is its opening stock, so it goes online with the right quantity straight away. For an item with variants, give it on each variant instead (the item's own is then ignored). See the stock notes below the table |
| `variants` | no | Pricing → Variants | Only for items sold in sizes or colours. Each variant, in the form's column order: 1 to 5 `options`, its own unique `sku` (required: a variant is matched by it), an optional `hsn_code` (only when the variant is different goods; the item's code when left out), an optional `price_incl_tax` and/or `price_excl_tax` (the item's price when left out), `is_active` (Available) and `on_hand` (its own initial / current stock) |
| `updated_at` | yes | (not shown) | Last change to any field above, variants included (a stock change alone doesn't count here: it goes in `/stock`) |

**Prices.** Send the prices your software keeps for each item and variant:

```json
{"tax_rate": 18, "price_incl_tax": 45.00, "sku": "SOAP-LUX-100"}
{"tax_rate": 5, "price_excl_tax": 200.00, "sku": "OIL-MUST-1L"}
{"tax_rate": 5, "price_incl_tax": 499.00, "price_excl_tax": 475.24, "sku": "TEE"}
```

- **Only one of them:** the online store works out the other from `tax_rate`.
- **Both:** both are kept as you send them. If they don't agree with the tax rate (499.00 vs
  475.24 at 5% agree; 100 vs 90 at 18% don't), the online store keeps the one it sells by and
  works out the other.
- Use two decimals, as on your bill. Don't send a price for a variant that sells at the item's
  price.

**Categories.** These are the four category boxes on the product form:

- `category` (required whenever you send any category): the item's category, e.g. `Men`.
- `category_2`: a child category of `category`, e.g. `Shirts` under `Men`. Send it when the
  category has children and the item belongs in one. The online store finds it under `category`
  and creates it there if it doesn't have it yet. The item's main category is `category_2` when
  given, else `category`.
- `category_3` and `category_4`: two more categories the item is also listed under, e.g. `Sale` or
  `Winter Wear`. Each is independent: any category, not tied to `category` or to each other.

The online store finds each by name and creates any it doesn't have. Categories are read only
while the item has no category online; after that, staff choose them on the online store.

**Stock.** `on_hand` here is the quantity in hand now, the same number `GET /stock` gives for that
item, with the same rules (section 3): the full quantity, never the change, already less the web
orders you have imported. It is shown in the store's stock history as "From local system".

- **Send it with every item**, so a new item arrives with its opening stock and not 0.
- `GET /stock` is still needed: it carries the **reason** for each change (sold, received, damaged…)
  and is the only place a stock-only change is read, since that doesn't change the item's
  `updated_at`. When both give the same item in the same cycle, `/stock` is applied last.

Images and descriptions are managed on the online store, so they aren't needed here.

---

## 3. Stock levels: `GET /stock`

The quantity in hand of every item and variant, with why it last changed. It works like the
**Inventory → Stock levels** screen on the online store, where staff adjust stock as stock
received, a customer return, damaged or lost, or a stock count. The reason you send is shown the
same way in the store's stock history.

Same `changed_since` as section 1: list each item (or variant) whose stock changed at
or after that time.

```json
{
  "items": [
    {"item_external_id": "1001", "on_hand": 120, "reason": "received", "note": "GRN-2231", "updated_at": "2026-11-23T11:42:10+05:30"},
    {"item_external_id": "2040", "sku": "TEE-BLK-M", "on_hand": 8, "reason": "sale", "note": "Bill 55012", "updated_at": "2026-11-23T13:10:00+05:30"},
    {"item_external_id": "2040", "sku": "TEE-BLK-L", "on_hand": 0, "reason": "count", "note": "Monthly stock count", "updated_at": "2026-11-23T13:40:00+05:30"},
    {"item_external_id": "3100", "on_hand": 14, "reason": "damaged", "note": "2 broken in transit", "updated_at": "2026-11-23T13:55:00+05:30"},
    {"item_external_id": "3205", "on_hand": 6, "reason": "returned", "note": "Credit note CN-118", "updated_at": "2026-11-23T14:01:00+05:30"}
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `item_external_id` | yes | The item's `external_id` (your product id). Stock is matched by it. One row per item, or for an item with variants one row per **variant** |
| `sku` | for a variant | The **variant's** SKU, so we know which variant. Not needed for an item without variants |
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
- Without `changed_since`, return every item and variant. The agent does this daily to correct any drift.

---

## 4. Customer master: `GET /customers`

Same `changed_since` as section 1.

```json
{
  "items": [
    {
      "external_id": "311",
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
    "external_id": "311",
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
  "coupon_type": null,
  "coupon_value": null,
  "shipping_cost": 0.00,
  "tax": 101.65,
  "total": 1044.00,
  "notes": "Please call before delivery",
  "lines": [
    {"item_external_id": "2040", "sku": "TEE-BLK-L", "name": "Cotton T-Shirt (Black / L)", "hsn_code": "6109", "qty": 1, "price": 549.00, "tax_rate": 5, "tax": 26.14, "total": 549.00},
    {"item_external_id": "1001", "sku": "SOAP-LUX-100", "name": "Lux Soap 100 g", "hsn_code": "3401", "qty": 11, "price": 45.00, "tax_rate": 18, "tax": 75.51, "total": 495.00}
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
- `discount` is the promo code's saving. When a code was used, `coupon_code` names it and
  `coupon_type` (`percent` or `fixed`) with `coupon_value` give its offer, e.g. `percent` / `10` for
  10% off. All three are `null` without a code.
- Each line's `hsn_code` is the item's (or the variant's) HSN code at the time of the order.
- `prices_include_tax` says how this order's prices are given: `true` when each line's `price` and`
  `total` include GST, `false` when GST (`tax`) is added on top. It is the online store's setting, so
  it can differ from how your software holds prices.
- Match each line to your item by `item_external_id`, and to the variant by `sku` for variant items. `sku` is empty for an item you sent without one.

**Your answer:**

```json
{"result": "created", "local_id": "55012"}
```

- **Sending the same `number` again must not create a second order.** If the connection drops,
  the agent sends the order again. Answer `{"result": "exists", "local_id": "55012"}`.
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

## 7. Products added on the online store: `POST /products`

Staff can also add an item on the online store. The agent sends each such item, one per request,
so your software has it too. It sends the item again when a variant is added, removed or given a
new SKU or options on the online store.

```json
{
  "external_id": null,
  "name": "Ceramic Mug 350 ml",
  "brand": "Acme",
  "category": "Home",
  "category_2": "Kitchen",
  "category_3": "Gifts",
  "category_4": null,
  "is_active": true,
  "mrp": 599.00,
  "tax_rate": 12,
  "hsn_code": "6912",
  "price_incl_tax": 499.00,
  "price_excl_tax": 445.54,
  "sku": "MUG-CER-350",
  "variants": [
    {"options": {"Colour": "Red"}, "sku": "MUG-CER-350-R", "hsn_code": null, "price_incl_tax": 499.00, "price_excl_tax": 445.54, "is_active": true},
    {"options": {"Colour": "Blue"}, "sku": "MUG-CER-350-B", "hsn_code": null, "price_incl_tax": 499.00, "price_excl_tax": 445.54, "is_active": true}
  ]
}
```

The fields are the ones `GET /products` uses (section 2). `external_id` is your id for the item
once you have it, and `null` for an item you haven't got yet. `sku` may be `null`. Stock is not
sent: the item starts at 0 in your software, and the online store takes your count from
`GET /stock` afterwards.

**What to do:**

1. Find the item: by `external_id` when it is given, else by `sku`.
2. **Not found:** add it, with its categories (create any you don't have) and variants.
   Answer `{"result": "created", "local_id": "4120"}`.
3. **Found:** add any variants you don't have yet and update the options of those you have. Keep
   your own name, prices and stock: those are yours. Answer
   `{"result": "updated", "local_id": "4120"}`.

`local_id` is your item's id, the `external_id` you give it in `GET /products`. It is required:
the online store links the item by it. The same item may arrive twice (if your answer was lost),
so finding it first means it is never added twice.

---

## 8. Customers who signed up online: `POST /customers`

The agent sends each customer who registers on the online store, one per request, and again when
they change their name, email or phone there.

```json
{"external_id": null, "name": "Asha Verma", "email": "asha@example.com", "phone": "9876500011", "is_active": true}
```

`email` or `phone` may be `null`, but not both. **What to do:**

1. Find the customer: by `external_id` when it is given, else by `phone`, else by `email`.
2. **Not found:** add them. Answer `{"result": "created", "local_id": "512"}`.
3. **Found:** update their name, email and phone (the online account is theirs to change), and
   answer `{"result": "updated", "local_id": "512"}`.

`local_id` is your customer's id, the `external_id` in `GET /customers`, and is required.

The agent sends new products and customers before new orders, so a web order's
`customer.external_id` and each line's `item_external_id` are usually already your ids.

---

## 9. Order fulfilment: `GET /order-status` (recommended)

When orders are packed and dispatched from your software, list the web orders whose status changed,
so the customer sees tracking online. It uses the same `changed_since` and `updated_at` as
section 1.

```json
{
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

## 10. Health check: `GET /health`

```json
{"ok": true, "software": "ShopMaster", "version": "4.2.1", "server_time": "2026-11-23T14:05:00+05:30"}
```

`server_time` is optional: your PC's time, so we can warn the shop when its clock is far off.
The agent calls this first on every cycle. If it fails, the agent skips the cycle and tries again
later, so nothing is lost while your software is closed.

---

## 11. Checklist before going live

- [ ] Every record has a fixed `external_id`, and every variant has a unique `sku`.
- [ ] `/products`, `/stock`, `/customers` and `/order-status` support `changed_since` and answer `{"items": [...]}` with the whole list,
      and every record's `updated_at` changes whenever the record does.
- [ ] `/products` gives `on_hand` on every item without variants and on every variant.
- [ ] `/stock` has one row per item by `item_external_id` (plus the variant's `sku` for each variant), `on_hand` is the full quantity,
      `reason` is one of `sale`, `received`, `returned`, `damaged`, `count` or empty, and `on_hand`
      already excludes imported web orders.
- [ ] `POST /orders` is safe to repeat (same `number` → `exists`) and reduces stock in the same
      transaction.
- [ ] A cancelled order restocks exactly once.
- [ ] `POST /products` and `POST /customers` find the record first (by `external_id`, then SKU, or phone and email), so a repeat never adds it twice, and always answer its `local_id`.
- [ ] `/products` gives `category` whenever it gives any category, `category_2` only as a child of `category`, and `category_3` / `category_4` as any other categories the item is listed under.
- [ ] Each shop's copy has its own random API key, shown in your software's settings with "Make a new key";
      it is required on every call, and the API isn't reachable from the internet.
- [ ] Send us a sample response from each GET endpoint using your real data, so we can test before
      the shop goes live.
