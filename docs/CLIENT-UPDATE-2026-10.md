# Platform update — October 2026

The seven requested changes, what was built for each, and how to deploy them.

Written for the team deploying this release and for the client who asked for the changes.

---

## 1. Summary

| # | Requirement | Status |
|---|---|---|
| 1 | Separate SuperAdmin users, clients and customers | Done — three tables, three guards, two panels |
| 2 | Move the database from PostgreSQL to MySQL, keeping the data | Done — verified row-for-row, dump supplied |
| 3 | A separate database per **client**, with verified isolation | Done — four layers of isolation, 16 tests |
| 4 | A simple image-free storefront template | Done — "Simple List", reuses the shared cart and checkout |
| 5 | Manual order management: dispatch, delivered, cancelled, with filters | Done — with transition rules and an audit trail |
| 6 | 50 product images per client, configurable | Done — per plan and per client, enforced server-side |
| 7 | Flexible product variants | Done — any attributes, per-variant price/SKU/stock, saved with the order |

The defect behind requirement 3 — two clients assigned the same store seeing each other's
data — is fixed and covered by automated tests, including the exact case of two clients on one
store.

Nothing was removed from the storefronts or the admin except the per-**store** database
commands and limits, which the per-**client** ones replace (requirement 3 asked for the
isolation to be keyed on the client, not the store).

---

## 2. What changed, requirement by requirement

### 1. Three kinds of account

| Who | Table | Guard | Signs in at |
|---|---|---|---|
| SuperAdmin (platform operator) | `users` | `admin` | `/admin` |
| Client Admin / Admin Staff | `client_users` | `client` | `/client` |
| Customer (shopper) | `customers` | `web` | the storefront |

* `users` now holds SuperAdmins only. The five Store Owner accounts were moved to
  `client_users` as Client Admins, keeping their name, email and password.
* Customers are in the platform database with a `client_id`, so the same email can shop with
  two clients as two separate accounts. Each guard has its own password-reset table.
* `/admin` manages clients, stores, plans and SuperAdmins. `/client` manages one client's
  catalogue, orders, content, customers and staff. A session on one panel is not a session on
  the other.

### 2. MySQL

* `DB_CONNECTION=mysql` (`mariadb` locally, same schema and queries).
* The data was copied with `php artisan db:transfer --from=pgsql --to=mysql` and verified:
  **25 tables, 894 rows, zero mismatches**, with JSON, booleans and dates checked by hand.
* `database/maison_mysql.sql` is the dump to restore elsewhere.
  `database/maison_pgsql.dump` is kept as the pre-migration backup.
* PostgreSQL and SQLite still work; the test suite runs on SQLite in memory.

### 3. A database per client, and isolation that does not depend on it

The client — not the store — is the tenant. Isolation is applied in four places at once:

1. **Connection** — `CLIENT_ISOLATION=database` gives each client its own database
   (`client_<slug>`), created and filled by `php artisan clients:provision`.
2. **Column** — every content row carries `client_id`; a global scope filters every query,
   including Livewire table updates, form loads, bulk actions and exports.
3. **Policies** — every record check compares the row's client with the request's client, so
   an id guessed from a URL is refused as well as invisible.
4. **Resolution** — the client is only ever chosen server-side: from the signed-in staff
   account's own row, from the host or entry link for a storefront request, or from a
   SuperAdmin's choice, which is validated against the `clients` table first. A client id in a
   request is never trusted, and a client's staff cannot switch client at all.

Also: unique keys (slugs, SKUs, coupon codes, order numbers, menu locations) are now unique
per client, customers' credentials are matched together with the client, reset tokens are
stored per client, and a shopper's session is dropped if the visitor moves to another client's
storefront. Connections do not leak between requests: the client is selected once per request
before anything queries, and console work selects a client through `forClient()`, which always
restores the previous selection.

### 4. The Simple List storefront

A new template for clients who sell from a price list: no imagery anywhere.

* The list is the page: one row per product with name, code (SKU), price, stock, a quantity
  box and **Add**. Several rows can be ticked and added together.
* Search by name or code; a category picker narrows the list.
* A product with real choices shows **Choose options** and links to a compact detail page.
* The cart, coupons, delivery methods, checkout, order tracking and account pages are the
  shared ones, so there is no second ordering flow to maintain.
* On a phone each row becomes a labelled block, so nothing is cut off.

### 5. Order management

* **Dispatch**, **Delivered** and **Cancel** actions (and the full pipeline under *Update
  status*). No shipping integration is involved; carrier and tracking are optional free text.
* Transitions are validated in one place (`Order::allowedTransitions()`): the pipeline only
  moves forward, a delivered or cancelled order is finished, and cancellation is possible up
  to delivery. The buttons hide themselves for a move that is not allowed and the action
  checks again before writing, so a stale page cannot force an invalid change.
* Every change records the status, the time, the note and **who made it**, and sets
  `dispatched_at`, `delivered_at` or `cancelled_at` (with the reason). The order view shows
  the trail.
* Only an account the policy lets edit the order may change its status — checked per order, so
  another client's order cannot be touched.
* Filters: status (including *Cancelled*), one exact date, a date range with either end open,
  payment method. Each shows as a chip, and **Reset filters** clears them.

### 6. 50 product images per client

* The default is 50, held on the plan and overridable per client. Blank inherits the plan;
  `0` means unlimited.
* Enforced on the back end by a validation rule on the image field itself, so uploading one
  file at a time or posting the field directly cannot get past it.
* Counted as **distinct files** across the client's products, including variant images: one
  photo on two products costs one image, and images a save removes free their slots.
* Shown on the client dashboard and on the product list.
* Products and media storage are capped the same way (also per client).

### 7. Product variants

* **Enable per product** — "Sell this product by variant".
* **Flexible attributes** — the button is now **Add Variant**: name the attribute (Size,
  Colour, Material, Style, or anything) and give it values; add, edit and remove them freely.
  A Colour attribute also takes swatch colours.
* **Per-variant price, SKU and stock** — each combination can override the product's; a blank
  field inherits it. A combination can be switched off.
* **Saved with the order** — the customer's choice is validated against the product, priced
  from the matching variant and written to the order line (`options`, `variant_sku`), with the
  variant's own stock reduced.
* **Existing colour data is preserved** — the colour and size lists are still there, still
  editable, and are read as a Colour and a Size attribute. Nothing changes for an existing
  product until variants are switched on for it.

---

## 3. Schema changes

New tables:

| Table | Purpose |
|---|---|
| `clients` | the tenants: name, slug, store, plan, domain, database, limits, active |
| `client_users` | Client Admin / Admin Staff accounts, unique email per client |
| `customers` | shoppers, unique email per client |
| `plans` | allowances a client inherits, with a price label |
| `customer_password_reset_tokens`, `client_user_password_reset_tokens` | one broker per guard |

Changed columns:

| Table | Added |
|---|---|
| 18 content tables (`products`, `categories`, `collections`, `coupons`, `orders`, `reviews`, `pages`, `posts`, `faqs`, `menus`, `menu_items`, `contact_messages`, `subscribers`, `services`, `service_areas`, `testimonials`, `projects`, `service_requests`) | `client_id` (indexed) |
| `orders`, `reviews`, `service_requests`, `addresses` | `customer_id` (indexed); the old `user_id` foreign key dropped, the column kept so historic rows stay readable |
| `products` | `has_variants`, `variant_attributes`, `variants` |
| `order_items` | `options`, `variant_sku` |
| `orders` | `dispatched_at`, `delivered_at`, `cancelled_at`, `cancellation_reason` |

Unique keys now include `client_id`: `products.slug`, `products.sku`, `categories.slug`,
`collections.slug`, `coupons.code`, `pages.(storefront_template,slug)`,
`posts.(template,slug)`, `menus.location`, `services.(template,slug)`,
`service_areas.(template,slug)`, `projects.(template,slug)`, `subscribers.email`,
`orders.number`, `service_requests.reference`.

Migrations (in order): `2026_10_27_100000_create_client_accounts`,
`…100100_add_client_scoping_to_content`, `…100200_backfill_clients_and_customers`,
`…110000_add_product_variants`, `…120000_add_order_fulfilment_fields`,
`…130000_scope_unique_keys_to_client`. The backfill is idempotent and deletes nothing before
its replacement exists.

`database/migrations/client/` builds the same content schema inside a per-client database. A
change to a content table needs a migration in both places.

---

## 4. Configuration

`.env`:

```
DB_CONNECTION=mysql          # mariadb locally
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maison
DB_USERNAME=maison
DB_PASSWORD=…

CLIENT_ISOLATION=database    # a database per client; or shared (one database), schema (PostgreSQL)
CLIENT_DB_PREFIX=client_     # client_<slug>
CLIENT_IMAGE_LIMIT=50        # platform default allowance
# CLIENT_CONNECTION=mysql    # only if the client databases live on another connection

STORE_BASE_DOMAIN=yourdomain.com
STORE_URL_MODE=subdomain     # or path, for /c/<client> entry links
```

No credentials are in the repository: every value above is read from `.env`, and the committed
dump contains no plaintext passwords (staff passwords are bcrypt hashes, as before).

New config files: `config/clients.php` (isolation, prefix, default image limit, the list of
client-owned tables). `config/stores.php` is removed — it is superseded by it.

---

## 5. Deployment

Take a backup first; the backfill migration rewrites account rows.

```bash
# 1. Backup
mysqldump -u maison -p --single-transaction maison > backup-before-client-update.sql

# 2. Code and dependencies
git pull && composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Schema and data
php artisan migrate --force          # includes the account and content backfill
php artisan optimize:clear

# 4. A database per client (CLIENT_ISOLATION=database in .env; the user needs CREATE DATABASE)
php artisan clients:provision                  # creates and builds every client's database
php artisan clients:provision <slug> --copy    # ...or move an existing client's rows across

# 5. Check
php artisan test
```

After the migration:

1. Sign in at `/admin` as the SuperAdmin and open **Platform → Clients**: one client per
   existing store, each with its former owner as Client Admin and the 50-image allowance.
2. Each former owner now signs in at `/client` with the same email and password.
3. Give each client its address: a wildcard DNS record plus certificate for
   `*.yourdomain.com`, or a custom domain per client, or leave `STORE_URL_MODE=path` and share
   the `/c/<slug>` entry links, which need no DNS.

Rolling back: restore the backup. `migrate:rollback` reverses the schema, but the account
backfill is deliberately not reversible — it would destroy the accounts it created.

---

## 6. Test results

```
php artisan test
Tests:  65 passed (336 assertions)
```

| Suite | What it proves |
|---|---|
| `ClientIsolationTest` (16) | two clients on one store cannot reach each other's data — context, scope, policies, both panels over HTTP, storefront accounts, orders |
| `ClientDatabaseIsolationTest` (8) | the same, with a database per client: each client gets its own database, a query reaches only the selected one, the row scope still holds inside it, the connection is restored afterwards, and both panels and the storefront read the right database |
| `AdminPanelAccessTest` (5) | what the operator can reach, that switching client narrows everything and only accepts a real client, and that a client's staff cannot switch at all |
| `OrderFulfilmentTest` (9) | allowed and refused transitions, the audit trail with the actor, the cancellation reason, and the date/status filters |
| `ProductVariantTest` (10) | attributes, resolution, rejection of invalid combinations at the endpoint, per-variant pricing, and the options saved on the order |
| `ClientAllowanceTest` (8) | the 50-image default, per-plan and per-client overrides, distinct counting, the rejection message, that a catalogue already over the limit can still be edited, and the product cap |
| `SimpleTemplateTest` (7) | the list's contents, that no page renders an image, search, and ordering from a row |
| `ExampleTest` (2) | the storefront renders on an empty install |

Checked by hand as well, with `CLIENT_ISOLATION=database` live: all seven clients were
provisioned into their own databases (`client_fashion`, `client_grocery`, `client_heritage`,
`client_heritage_two`, `client_orderlist`, `client_plumbing`, `client_plumbing_services`),
every copied table matched the row counts it had in the main database, each storefront still
renders and orders correctly, both panels load, and renaming a product in `client_heritage_two`
left `client_heritage` — the same store — and the main database untouched.

`docs/TESTING.md` lists the manual checks step by step.

---

## 7. Assumptions and what is not included

* **Billing is not implemented.** Plans carry allowances and a price *label* (`₹X`). No
  payment gateway, invoice or subscription logic was added, as agreed.
* **One store per client.** A client runs one template; a business needing two storefronts is
  two clients. Nothing in the schema prevents more later.
* **Shared-mode seeding.** `clients:provision` requires `CLIENT_ISOLATION=database` or
  `schema`. In shared mode a new client starts with an empty catalogue rather than a copy of
  someone else's content, which is the safe default.
* **Customers are shared in the platform database** (scoped by `client_id`), as agreed, so one
  person's accounts with different clients stay separate while the platform keeps one place to
  look after shoppers.
* **Dispatch is the pipeline's "shipped" step**, relabelled *Dispatched* so the admin and the
  customer-facing timeline use the client's wording without changing stored data.
* **The entry-link cookie is not encrypted.** It holds a public slug and grants nothing — the
  client is still resolved and the data still scoped server-side on every request — and
  leaving it readable keeps a shared link working across deployments.
* **An allowance never locks a client out.** A catalogue that arrives over its image
  allowance (seeded or imported content, or a limit lowered afterwards) can still be edited;
  only a save that would *add* to the count past the limit is refused. Two of the demo
  clients are in that state today: grocery holds 67 images and plumbing 50, against the
  default of 50, so they can edit and remove but not add until their limit is raised.
* **Per-variant images are not offered in the admin.** Price, SKU and stock vary per
  combination, as asked; the galleries show the product-level images. Variant images that
  arrive through an import are still counted against the allowance.
* **Store locations (the boutique locator)** stay a platform module: they carry no client,
  so they are managed on `/admin` only.
* **Per-store database commands were removed** (`stores:provision`, `config/stores.php`,
  `StoreQuota`): per-client ones replace them. Store-level product and storage limits remain
  on the store as defaults for a new client.
