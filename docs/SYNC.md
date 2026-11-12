# Two-way sync with a client's local database

How EcomVision exchanges products, stock, orders and customers with a client's local system
(MS Access or MS SQL Server), and what the agent on the shop PC has to do.

---

## 1. How it fits together

```
Shop PC (behind the router)                     EcomVision server
┌───────────────────────────┐   outbound HTTPS  ┌──────────────────────┐
│ Local DB  <->  Sync agent │ ----------------> │ /api/sync/v1/*       │
└───────────────────────────┘                   └──────────────────────┘
```

The agent always starts the connection, so no router port is opened and the local database is
never exposed. The API does not depend on which local database is used. Only the agent's
connector does.

| Local database | Agent connects through |
|---|---|
| SQL Server or SQL Server Express | ODBC Driver for SQL Server |
| Access tables linked to SQL Server | SQL Server directly; Access is left alone |
| Access file only (.accdb / .mdb) | Access Database Engine (ODBC, Windows only) |

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
| Product | SKU, name, price, compare-at price, stock | slug, description, images, category, SEO, visibility |
| Order | fulfilment status, carrier, tracking number | the order as placed |
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
| `GET ping` | Check the token and read the server time |
| `GET changes/{products,orders,customers}?limit=100&after_id=0` | Records waiting for the agent (`limit` up to 500) |
| `POST changes/{products,orders,customers}/ack` | Confirm records were taken |
| `POST products` | Create or update products |
| `POST stock` | Set stock (and optionally price) by SKU |
| `POST customers` | Add local customers, link existing ones |
| `POST orders/status` | Send fulfilment updates |

### Sending changes

Each POST takes `{"items": [...]}`, up to 500 items.

| Endpoint | Item fields (**required**) |
|---|---|
| `products` | **external_id**, **name**, **price**, sku, compare_at_price |
| `stock` | **sku**, **on_hand**, price |
| `customers` | **external_id**, **name**, **email**, phone |
| `orders/status` | **number**, status, carrier, tracking_number, note |
| `changes/*/ack` | **id**, **version**, external_id |

`external_id` is the record's primary key in the local database. Prices and quantities are whole
numbers; prices are whole rupees, so the agent decides how to round a decimal price.

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
{"data": [{"id": 12, "number": "ME-261110-AB12", "status": "confirmed", "version": 1, "items": []}],
 "has_more": false}
```

A record stays in the feed until it is acknowledged with the `version` that was received, and
comes back if it changes on EcomVision afterwards. Acknowledge only after the local database has
committed. To step past a record the agent cannot process yet, pass its id as `after_id`.

Passwords are never sent. Customer addresses are not in the customer feed; each order carries its
own shipping address.

---

## 4. The agent's cycle

Run these **in this order**, every one to five minutes:

1. `GET changes/orders`, write them to the local database, reducing local stock, then `ack` with
   the local id as `external_id`.
2. `GET changes/customers` and `GET changes/products`, write, then `ack`.
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

For a product sold by variant, send each variant's SKU. The parent SKU is refused, and the
product's total is recalculated from its variants.

---

## 5. Going live

```bash
php artisan migrate
php artisan clients:provision --no-seed   # adds the columns to each client database
php artisan sync:token <client-slug>
```

Orders that exist when the migrations run are treated as already taken, so they are not sent and
do not reduce stock. Existing products and customers **are** sent on the first cycle so that the
agent can link them: match on SKU and email, and return the local id as `external_id`.

---

## 6. The agent: install once

The agent is a compiled Windows program written in Go (`agent/`). It needs no PowerShell, .NET or
other runtime. It installs itself as a Windows service that starts with Windows and syncs every
five minutes.

| What changed | What to do | Reinstall? |
|---|---|---|
| Table or column added, renamed or remapped | `php artisan sync:agent <slug> --map=file.json` | No |
| Agent logic | edit `agent/`, build, sign, deploy (below) | No |
| Database moved or its password changed | edit `C:\ProgramData\EcomVisionAgent\settings.json` on that PC | No |

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

```bash
agent/build.sh
php artisan sync:agent-sign ~/ecomvision-agent.pem
# copy resources/agent/dist to the live server
```

Agents pick it up on their next cycle.

### Installing at a shop

```bash
php artisan sync:agent <slug> --setup-code --url=https://your-live-domain
```

Copy `ecomvision-agent-amd64.exe` to the shop PC and double-click it. It asks for Administrator
rights and the setup code, searches the PC's fixed drives for the database, tests the connection,
and installs the service.

Finding the database: backups, front-end files and files that cannot be opened are ignored. If the
client's mapping is already set and identifies exactly one database, setup uses it without asking.
So when many shops run the same software, set the mapping first. Otherwise setup asks the person
to confirm or choose. SQL Server is detected, but its login must be typed. Files on network drives
are not searched, and the service could not read them anyway.

If the PC only has a 32-bit database driver it says so; run `ecomvision-agent-386.exe` instead.
To remove the agent, run the program from a command prompt with `uninstall`.

Access files need the *Microsoft Access Database Engine 2016 Redistributable*. Because the service
runs as SYSTEM, the Access file must be on a local disk, and SQL Server needs a SQL login.

### Mapping a client

Until a mapping is set, the agent only reports the local tables.

```bash
php artisan sync:agent <slug> --schema   # tables and columns found on the PC
php artisan sync:agent <slug> --map=acme.json
php artisan sync:agent <slug>            # last seen, version, counts, errors
```

Keys inside `columns` are API fields and values are local columns. Include only the sections the
client needs.

```json
{
  "products": {"table": "Items", "columns": {"external_id": "ItemID", "sku": "Code", "name": "Title", "price": "Rate"}},
  "stock": {"table": "Items", "columns": {"sku": "Code", "on_hand": "Qty"}},
  "customers": {"table": "Parties", "columns": {"external_id": "PartyID", "name": "Name", "email": "Email", "phone": "Mobile"}},
  "order_status": {
    "table": "Sales",
    "columns": {"number": "WebNo", "status": "State", "carrier": "Courier", "tracking_number": "Awb"},
    "status_values": {"Dispatched": "shipped", "Done": "delivered", "Void": "cancelled"}
  },
  "orders_in": {
    "table": "Sales", "key": "SaleID",
    "columns": {"number": "WebNo", "email": "Email", "total": "Amount", "placed_at": "SaleDate", "status": "State"},
    "defaults": {"Source": "WEB"},
    "status_values": {"Dispatched": "shipped", "Done": "delivered", "Void": "cancelled"}
  },
  "order_items_in": {"table": "SaleLines", "parent": "SaleID", "columns": {"sku": "Code", "qty": "Qty", "price": "Rate"}},
  "stock_on_import": {"table": "Items", "sku_column": "Code", "qty_column": "Qty"},
  "customers_in": {"table": "Parties", "key": "PartyID", "insert": true, "columns": {"name": "Name", "email": "Email", "phone": "Mobile"}},
  "products_in": {"table": "Items", "key": "ItemID", "insert": false, "columns": {"sku": "Code", "name": "Title", "price": "Rate"}}
}
```

Names are checked against the local schema, and the mapping cannot contain SQL. A web order that
is cancelled after import is added back to local stock only if `orders_in` maps `status`.

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
* If a shop's IT needs an exclusion, it is one folder, `C:\ProgramData\EcomVisionAgent`, one
  service, `EcomVisionSyncAgent`, and outbound HTTPS to your domain only.
