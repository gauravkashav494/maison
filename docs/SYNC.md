# Two-way sync with a client's shop software

How EcomVision exchanges products, stock, orders and customers with a client's local shop
(billing) software, and what the agent on the shop PC does.

---

## 1. How it fits together

```
Shop network (behind the router)                               EcomVision server
┌─────────────────────────────────────────────┐ outbound HTTPS ┌──────────────────┐
│ Shop software's API  <-- local -->  Agent   │ -------------> │ /api/sync/v1/*   │
└─────────────────────────────────────────────┘                └──────────────────┘
```

The agent always starts the connection, so no router port is opened and the shop's data is never
exposed. It reaches that data **only through the shop software's own API**, which the software's
developer builds to [LOCAL-API.md](LOCAL-API.md). The agent never opens the shop's database, so
the software keeps its own rules (stock taken off when it imports a web order, say) and can change
its tables freely.

Every client has its own token and its own agent. The token alone decides whose data a request
reaches; a client id in a request is ignored.

```bash
php artisan sync:token acme --name=shop-pc   # shown once; only its hash is stored
php artisan sync:token acme --revoke         # revokes all of acme's tokens
```

Send it on every request as `Authorization: Bearer evs_…`. Use HTTPS only.

---

## 2. Who owns which field

| Data | Local system owns | EcomVision owns |
|---|---|---|
| Product | SKU, name, price, compare-at price, stock; each variant's SKU, options and price; the main category, brand, tax and HSN code (a variant's HSN code too) while the product has none | slug, description, images, category, brand, tax and HSN code once set, other categories, collections, SEO, visibility, variant images and on-sale flags |
| Order | fulfilment status, carrier, tracking number | the order as placed, its tax invoice number |
| Customer | adds customers EcomVision does not have; fills a blank phone | name, email, phone and active flag of an existing customer |

A field sent for the other side's ownership is ignored. An owned field edited on the other side
is overwritten the next time the owner sends that record, so a price edited in the EcomVision
panel lasts only until the agent next sends that product.

A product created from the local system starts **hidden**, with stock 0, until someone adds its
images and text and switches it on.

---

## 3. Endpoints

All are under `/api/sync/v1`. Limit: 600 requests a minute.

| Method and path | Purpose |
|---|---|
| `GET ping` | Check the token, read the server time and `prices_include_tax` |
| `GET changes/{products,orders,customers}?limit=100&after_id=0` | Records waiting for the agent (`limit` up to 500) |
| `POST changes/{products,orders,customers}/ack` | Confirm records were taken |
| `POST products` | Create or update products |
| `POST stock` | Set stock (and optionally price) by the product's `external_id`, a variant's SKU, or a product SKU |
| `POST customers` | Add local customers, link existing ones |
| `POST orders/status` | Send fulfilment updates |

### Sending changes

Each POST takes `{"items": [...]}`, up to 500 items.

| Endpoint | Item fields (**required**) |
|---|---|
| `products` | **external_id**, **name**, brand, category, category_2, category_3, category_4, compare_at_price, tax_rate, hsn_code, **a price** (`price`, or `price_incl_tax` and/or `price_excl_tax`), sku, variants (in the product form's order) |
| `stock` | **external_id** and/or **sku** (a variant needs its sku), **on_hand**, a price (`price`, `price_incl_tax` or `price_excl_tax`), reason, note |
| `customers` | **external_id**, **name**, **email or phone** (at least one) |
| `orders/status` | **number**, status, carrier, tracking_number, note |
| `changes/*/ack` | **id**, **version**, external_id |

On `products`, `brand`, the categories, `compare_at_price`, `tax_rate`, `hsn_code` and `variants` are
optional. Fields follow the product form (Details, then Pricing & Variants), and the feed sends them
in the same order:

```json
{"external_id": "ITM-88", "name": "Tee", "brand": "Acme", "category": "Men", "category_2": "T-Shirts",
 "compare_at_price": 699, "tax_rate": 5, "hsn_code": "6109", "price_incl_tax": 499.50, "sku": "TEE",
 "variants": [{"options": {"Color": "Black", "Size": "M"}, "sku": "TEE-BLK-M"},
              {"options": {"Color": "Black", "Size": "L"}, "sku": "TEE-BLK-L", "hsn_code": "61091000", "price_incl_tax": 549}]}
```

* The categories are names, as the product form's category boxes pick them. `category` (Men) and
  `category_2`, a child of it (Shirts), make the main category: `category_2` when given, else
  `category`. `category_3` and `category_4` are the two category tags, any other categories the
  product is also listed under (Sale, Winter Wear), independent of the rest. Names are matched
  without regard to case (`category_2` under `category`; elsewhere a top-level category wins a
  tie), and created out of the menu when missing: `category_2` under `category`, the others at the
  top. Any of the others without `category` rejects the row. They are applied only while the
  product has no category; after that the panel owns them.
* `brand` is a name, linked to the store's brand of that name (created when missing). Applied only
  while the product has no brand.
* `tax_rate` is the GST percentage (0, 5, 12, 18, 28…). It picks the store's tax with that rate,
  creating "GST 18%" when there is none. Applied only while the product has no tax.
* `hsn_code` is the GST classification of the goods: 4, 6 or 8 digits (`6109` or `6109 10 00`;
  spaces are dropped). Anything else rejects the row. Applied only while the product has none. A
  variant may carry its own `hsn_code` for different goods (another material, say), applied
  while that variant has none; without one it uses the product's.
* `variants` (up to 200, 1–5 options each) are matched by SKU: new SKUs are added with stock 0,
  known ones get their options and price updated, and a variant no longer sent is left alone.
  Option names and values are matched to the product's existing spelling. A variant without
  `price` sells at the product's price. A variant SKU that another product already uses is
  rejected. Stock still arrives through `stock`, per variant SKU.

`external_id` is the record's primary key in the shop software. Quantities are whole numbers.
Prices are rupees and paise (at most two decimals, e.g. `499.50`); the agent rounds to the paisa.

**Which price to send.** Send the price the local system holds: `price_incl_tax`, `price_excl_tax`,
or both when it keeps both; or `price` alone, in the store's own form. `price` with either of the
others, or no price at all on `products`, rejects the row. Variants take the same fields.

| Field | Means | Saved as |
|---|---|---|
| `price_incl_tax` | Including GST (most Indian shops) | Converted to the store's form with the product's tax; may come with `price_excl_tax` |
| `price_excl_tax` | Before GST | Converted the same way |
| `price` | The store's own form: including tax when `ping` says `"prices_include_tax": true`, before tax when `false` (Site settings → Payments & tax) | As it is |

The rate is the product's tax on EcomVision, or the `tax_rate` sent with a product that has no tax
yet; with no tax both prices are the same. Each figure sent is kept to the paisa in its own field
(`price_incl_tax` 45 stays 45); one not sent is worked out. When both are sent, the one in the
store's own form becomes `price`, what shoppers pay, and the other is kept while it agrees with it
and the tax to the paisa, else worked out from it (a different rate on EcomVision, say).

```json
{"external_id": "ITM-1001", "sku": "SOAP-LUX-100", "name": "Lux Soap", "price_incl_tax": 45, "tax_rate": 18}
{"external_id": "ITM-2040", "sku": "TEE", "name": "Tee", "price_incl_tax": 499, "price_excl_tax": 475.24, "tax_rate": 5}
```

**Customers** are matched by `external_id`, then email, then phone (`98765 43210`,
`+91 98765 43210` and `098765 43210` are the same number). A customer may have no email, as in the
admin. A matched customer that already has another `external_id` is rejected.

**Stock** pushed through `stock` is written to the stock history (Inventory → Stock movements),
by "Sync agent", with the reason the local system gives, as on the Adjust stock screen:

| `reason` | In the stock history |
|---|---|
| `sale` | Sold in shop (a counter sale) |
| `received` | Stock received |
| `returned` | Customer return |
| `damaged` | Damaged or lost |
| `count` | Stock count |
| left out | From local system |

`note` (up to 255 characters, e.g. a GRN or bill number) is shown beside it. `on_hand` is always
the full quantity, never a difference, so the stock is right even when a reason is wrong or a
change was missed; the history line records the difference from the previous level. An unknown
`reason` rejects the row.

```json
{"items": [{"external_id": "ITM-1001", "on_hand": 120, "reason": "received", "note": "GRN-2231"},
           {"external_id": "ITM-2040", "sku": "TEE-BLK-M", "on_hand": 8, "reason": "sale"}]}
```

A valid request answers 200 with one result per item. A bad row does not stop the others:

```json
{"results": [
  {"key": "L2", "result": "created", "id": 41},
  {"key": "L1", "result": "rejected", "error": "The price field is required."}
]}
```

`result` is `created`, `updated`, `unchanged` or `rejected`. Validation failures are listed first.
Sending the same batch twice is safe, so retry freely after a network failure.

### Receiving changes

```json
{"data": [{"id": 12, "number": "EFS-261110-K7QM", "status": "confirmed", "version": 1, "items": []}],
 "has_more": false}
```

A record stays in the feed until it is acknowledged with the `version` that was received, and
comes back if it changes on EcomVision afterwards. Acknowledge only after the shop software has
committed. To step past a record the agent cannot process yet, pass its id as `after_id`.

Passwords are never sent. Customer addresses are not in the customer feed; each order carries its
own shipping address. A customer's or order's `email` may be empty.

Orders carry their tax as charged: `tax`, `tax_rate`, `tax_label`, `prices_include_tax`, and per
line `tax_rate`, `tax` and `hsn_code`. A promo code comes with its offer as it stood when the
order was placed: `coupon_code`, `coupon_type` (`percent` or `fixed`) and `coupon_value`,
beside the `discount` it gave. Once dispatched they also carry `invoice_number` and `invoiced_at`.
When the local system dispatches an order through `orders/status`, the result includes the new
`invoice_number`, and the order comes through the feed once more carrying it. An order placed by a
sales person at the counter names them in `salesperson`. Its invoice, like any other order's, is
issued when it is dispatched. Once the local system has the shopper or a line's product,
`customer_external_id` and each line's `product_external_id` give their local ids (null before).

Products carry `brand`, `tax_rate`, `hsn_code` (and each variant its own, else the product's), `price_incl_tax`, `price_excl_tax` (each variant its own too), the category
boxes as the form shows them (`category`, the top category; `category_2`, the main category under
it, or null; `category_3` and `category_4`, its first two other categories, or null), and every
category it is listed under in `categories`.

A product created in the panel is in the products feed (with its categories and variants) so
the local system can create it; acknowledge it with the local item id as `external_id`. It comes
back when a variant is added, removed or given a new SKU or options, but not when only stock,
price, images or availability change. While a client has an active sync token, the panel requires
a SKU on every variant (a product's own SKU stays optional: products are matched by `external_id`).

---

## 4. The agent's cycle

In this order, every five minutes (section 6 has the detail):

1. `GET changes/products` and `GET changes/customers`: send products and customers made or changed
   on the website to the shop software, then `ack` with its local id as `external_id`.
2. `GET changes/orders`, send each to the shop software, which saves it and reduces its own stock,
   then `ack` with the local id as `external_id`.
3. Send local changes to `POST products` and `POST customers`.
4. Read current quantities and send `POST stock`.
5. Send fulfilment changes to `POST orders/status`.

Order matters for stock. `on_hand` is the local count, and EcomVision subtracts orders the agent
has not acknowledged yet:

```
stock shown online = on_hand - quantities in unacknowledged, uncancelled orders
```

So the local system **must reduce its own stock when it imports an order**. If it does not, every
web sale reappears as available on the next stock push.

A row names the product by `external_id` (the local item id), by `sku`, or both. A product
without variants needs only its `external_id`, so it needs no SKU. For a product sold by variant,
send each variant's SKU (with the `external_id` or alone); the product alone or its own SKU is
refused, and the product's total is recalculated from its variants.

---

## 5. Going live

```bash
php artisan migrate
php artisan clients:provision --no-seed   # adds the columns to each client database
php artisan sync:token <client-slug>
```

Orders that exist when the migrations run are treated as already taken, so they are not sent and
do not reduce stock. Products and customers the shop software already has are matched on the
first cycle by its `external_id`; a product that EcomVision already had with the same SKU is linked
to it.

---

## 6. The agent: install once

The agent is a compiled Windows program written in Go (`agent/`). It needs no PowerShell, .NET or
other runtime. It installs itself as a Windows service that starts with Windows and syncs every
five minutes.

| What changed | What to do | Reinstall? |
|---|---|---|
| Agent logic | edit `agent/`, build, sign, deploy (below) | No |
| The billing software's API moved or its key changed | change **Billing software connection** on the client's Shop software sync page; the agent reads it every cycle | No |

The installed service never replaces itself. It runs a separate copy, `worker.exe`, and updates
only that. A new version is checked against its signature first. If it crashes three cycles
running, the previous version is restored and that release is not tried again, so a bad release
cannot strand a shop. Publishing a newer release is delivered as normal.

### First-time setup

Build with a currently supported version of Go.

```bash
php artisan migrate
php artisan clients:provision --no-seed
php artisan sync:agent-sign ~/ecomvision-agent.pem --generate   # once ever; back this key up
agent/build.sh
# optional: Authenticode-sign the two .exe files here (section 7)
php artisan sync:agent-sign ~/ecomvision-agent.pem
```

Builds in `resources/agent/dist` are not in git. Copy that folder to the live server, and put
`resources/agent/signing.pub` there too.

### Releasing a change

Only a change under `agent/` needs this; server code is deployed as usual. One command, from the
project folder (needs Go, Go 1.20, PHP and Inno Setup 6):

```
powershell -ExecutionPolicy Bypass -File agent\release.ps1 -Version 1.2.1
# copy resources\agent\dist to the live server
```

It builds four agents: 64-bit and 32-bit for Windows 10 and 11 with the current Go, and again for
Windows 7 and 8.1 with Go 1.20, the last Go that runs there (install it once:
`go install golang.org/dl/go1.20.14@latest`, then `go1.20.14 download`). It signs them with
`%USERPROFILE%\ecomvision-agent.pem` (or `-Key`), checks the server accepts every signature, then
builds the four installers (`EcomVisionSyncAgent-Setup-x64.exe`, `-x86.exe`, `-x64-win7.exe`,
`-x86-win7.exe`, from `agent/installer/agent.iss`), which carry the signed builds; it stops at the
first failure and lists the files to copy.

The Windows 7 / 8.1 agents are built with `-X main.buildChannel=-win7`, so they ask for updates as
`amd64-win7` / `386-win7` and only ever get a Windows 7 build: the normal builds need Windows 10
and would not start there. The normal installers refuse Windows older than 10 with Windows' own
message; the Windows 7 ones run from Windows 7 SP1. Go 1.20 gets no more security fixes, and nor
does Windows 7: prefer a Windows 10 PC for the agent where the shop has one.

What reaches shops already installed: a change to the sync itself (`engine.go`, `localapi.go`,
`api.go`) arrives on each agent's next cycle by self-update. A change to the service, updater or
setup (`platform_windows.go`, `update.go`, `main.go`) reaches a shop only when it runs the new
installer; new installs get everything. The steps can also be run one by one: `agent/build.sh`,
`php artisan sync:agent-sign <key>`, `agent/build-installer.ps1 -Version <v>`.

### Installing at a shop

In the admin panel, open **Clients →** the client **→ Shop software sync**:

* **Billing software connection** holds the billing software's API address (standard:
  `http://localhost:8765/ecom/v1`) and key, from its developer. Set it before installing: the
  agent reads it at install and every cycle (`GET agent/config`), so the installer never asks for
  it and a change needs no reinstall. The key is stored encrypted and never shown in the log.
* **Create agent setup code** makes the code to paste into the installer, shown once.
* **Download the agent** offers four installers: 64-bit or 32-bit for **Windows 10 or 11**
  (`EcomVisionSyncAgent-Setup-x64.exe`, `-x86.exe`) and for **Windows 7 or 8.1** (`-x64-win7.exe`,
  `-x86-win7.exe`). The one the browser's computer needs is marked Recommended; the 32-bit ones
  also run on 64-bit Windows.
* **Revoke sync access** stops that client's agent at once; other clients are not affected.

The client's own **Client Admin** can do the same from their panel, under **Settings → Shop
software sync**, which also walks them through installing the agent step by step. Other staff
don't see that page: a setup code is a password to the store's data. The clients list's **Shop
sync** column shows every client's agent at a glance.

The client page's **Shop software sync** section shows whether the agent is syncing, its last
sync, last result, counts and errors, and its version. Each of these actions is in the activity
log. The same can be done on the server with `php artisan sync:agent <slug> --setup-code`,
`php artisan sync:agent <slug>` and `php artisan sync:token <slug> --revoke`.

Copy the installer to a PC on the shop's network (usually the one running the shop software)
and run it. Its wizard asks only for the setup code. When Next is pressed it checks the code,
reads the billing software connection from the store, and checks that the billing software answers
there (the agent's `check-code`), saying what to fix if not. It installs the agent into Program Files, and the agent sets up its service in `C:\ProgramData\EcomVisionAgent`
(`settings.json` there holds only `api_url` and `token`). It is
removed from Windows' Apps & features, which stops and deletes the service too. The installer is
not Authenticode-signed, so SmartScreen may warn: More info → Run anyway.

Without the installer, the agent program alone sets itself up the same way in a console window:
run it and paste the setup code.

**Install folder.** Pressing Enter keeps `C:\ProgramData\EcomVisionAgent`, or, when reinstalling,
the folder the agent is in now. A chosen folder must be on a fixed local drive (not a network or
mapped drive, which the service cannot reach), and the agent always goes into its own
`EcomVisionAgent` folder inside it: `D:\Apps` becomes `D:\Apps\EcomVisionAgent`. Setup locks that
folder to SYSTEM and Administrators, so it refuses one that already holds other files. Running
setup again with a new folder moves the agent: its sync state goes along and the old folder is
removed. Uninstall finds the folder from the Windows service.

To try the agent before the shop software has its API, run the demo shop software in
`tools/demo-shop` (its README says how) and set the client's **Billing software connection** to
`http://localhost:8765/ecom/v1` and `demo-key`.

On a 32-bit Windows PC, run `ecomvision-agent-386.exe` instead. To remove the agent, run the
program from a command prompt with `uninstall`.

### What the agent does each cycle

`agent/localapi.go`, against the API in [LOCAL-API.md](LOCAL-API.md):

1. `GET /health`. If it fails, or `ok` is not `true`, the cycle stops and reports "The shop software
   did not answer". Nothing is read, sent or acknowledged, so nothing is lost.
2. **Website products and customers out**, from `changes/products` and `changes/customers`: each
   goes to `POST /products` or `POST /customers` and is acknowledged with the `local_id` it answers
   (a missing `local_id` is an error, and the record is sent again next cycle). They go before
   orders, so an order's customer and lines already carry the shop software's ids.
3. **Web orders out**, from `changes/orders`. A new order goes to `POST /orders` and is acknowledged
   with the `local_id` it answers. A linked order (it has an `external_id`) goes to
   `POST /orders/{number}/update`. If that answers 404, the order is sent whole again, unless it is
   cancelled. A cancelled order that never reached the shop software is not sent at all.
4. **Lists in**: `GET /products`, `/customers` and `/stock`, page by page (`per_page=500`), then
   `GET /order-status` (optional: a 404 means the shop software does not have it). Products and
   customers are sent only when they differ from what EcomVision last accepted. An item's
   `on_hand` (each variant's, for an item with variants) is sent as a stock push right after its
   product is accepted, so a new item starts with its opening stock. `/stock` is sent as read, with
   its `reason` and `note`, and is applied last.

Each list keeps a bookmark in `state.json`: the newest `updated_at` received, by the shop PC's
clock. The next read asks for `changed_since` = bookmark minus 5 minutes, so a record saved during
the last read is not missed; repeats are harmless. The agent's own clock only decides when a list
is read whole again: the first time, then once a day.

Field names: `mrp` becomes `compare_at_price`. `is_active` and `unit` are not sent, because whether
a product shows online is EcomVision's. A customer's `gstin` and `city` stay local.

---

## 7. Security and antivirus

Installed agents run code from the server as SYSTEM, so an update is accepted only with a valid
RSA signature. Keep the private key off the web server: anyone holding it can run code on every
installed PC. If it is lost, agents can no longer be updated and every shop needs a reinstall.

Antivirus may flag an unsigned program that updates itself. To reduce this:

* Authenticode-sign both `.exe` files with a code-signing certificate in your company's name.
  Do it **after** `agent/build.sh` and **before** `sync:agent-sign`, because the update signature
  covers the final file.
* Never compress the program with a packer such as UPX.
* If a shop's IT needs an exclusion, it is one folder (the install folder,
  `C:\ProgramData\EcomVisionAgent` unless chosen otherwise), one service, `EcomVisionSyncAgent`,
  and outbound HTTPS to your domain only.
