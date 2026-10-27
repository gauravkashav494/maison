# Testing guide

How to try the platform by hand: the two panels, client isolation, allowances, order
fulfilment, variants, per-client databases and the Simple List storefront.

Local server: `php artisan serve --host=127.0.0.1 --port=8000` → <http://127.0.0.1:8000>
SuperAdmin panel: <http://127.0.0.1:8000/admin/login>
Client panel: <http://127.0.0.1:8000/client/login>

## Accounts

| Who | Email | Password | Panel |
|---|---|---|---|
| SuperAdmin | `admin@maisonelan.com` | `password` | `/admin` |
| Client Admin — Fashion | `fashion@maisonelan.com` | `password` | `/client` |
| Client Admin — Grocery | `grocery@maisonelan.com` | `password` | `/client` |
| Client Admin — Heritage | `heritage@maisonelan.com` | `password` | `/client` |
| Client Admin — Plumbing | `plumbing@maisonelan.com` | `password` | `/client` |
| Client Admin — Plumbing Services | `plumbing-services@maisonelan.com` | `password` | `/client` |

SuperAdmins are managed under **Platform → Super Admins**; a client's own staff under
**Team → Staff** (on `/client`, Client Admins only) or **Platform → Clients** on `/admin`.

## The automated suite

```bash
php artisan test
```

56 tests cover what is hardest to check by hand: client isolation (including two clients on
one store), the panels' access rules, order fulfilment transitions, variants and the
allowances. `tests/Feature/ClientIsolationTest.php` is the one to read first.

---

## 1. Two panels, two kinds of account

**As a Client Admin** (e.g. `grocery@maisonelan.com` at `/client`)

- The topbar shows `MANAGING Indian Grocery Store` with no way to change it.
- The sidebar has that client's modules only — no Platform group, no Clients, no Plans, no
  Templates, no Super Admins. A services store shows Services / Service areas / Bookings
  instead of Products / Orders.
- Lists contain only that client's rows.

**Hiding a menu item is not the protection.** While signed in there, type these by hand:

| URL | Expected |
|---|---|
| `/client/products/<id>/edit` for another client's product | 404 |
| `/client/clients`, `/client/plans`, `/client/users`, `/client/storefronts`, `/client/templates` | 404 |
| `/admin`, `/admin/clients` | redirect to `/admin/login` — a client session is not an admin session |
| `/client/products?switch_client=<other client id>` | ignored; still your own data |
| `/?preview_template=fashion` | stays on your own storefront |

Sign in as **Admin Staff** instead of Client Admin and `Team → Staff` disappears, and
`/client/client-users` answers 403.

**As the SuperAdmin** (`/admin`)

- The topbar has a client switcher: **All clients** plus each client.
- Choosing a client narrows every list to it; another client's ids then 404.
- `?switch_client=99999` (an id that names no client) clears the choice instead of selecting
  something else, and an inactive client cannot be selected.
- The dashboard shows platform totals: clients, stores, catalogue, customers and demand.

---

## 2. Two clients on one store — the important test

1. **Platform → Clients → New client**: name `Alpha Test`, store *Fashion Store*, save.
2. Create a second client `Beta Test` on the **same** store.
3. Give each a Client Admin (**Platform → Clients** → its Staff, or `/client` as that client).
4. Sign in as Alpha's admin, add a product, note its id from the URL.
5. Sign in as Beta's admin and open `/client/products/<that id>/edit` → **404**.
6. Beta's product list, orders, customers and reports show none of Alpha's rows.
7. Register a customer on Alpha's storefront (`/c/alpha-test` then **Create an account**), then
   try the same email and password on `/c/beta-test` → refused. Registering it there creates a
   *separate* account.
8. While signed in on Alpha's storefront, open `/c/beta-test` → you are signed out, not carried
   across.

---

## 3. Allowances

**Platform → Plans** for the defaults, **Platform → Clients → Edit** to override one client.
Blank inherits the plan; `0` means unlimited.

### Product images (50 by default)

1. Set **Product images** to `2` on a client and save.
2. As that client, edit a product and try to upload a third image → the field is rejected with
   *"This account is limited to 2 product images and is using 2."*
3. Swap images instead of adding (remove one, add one) → allowed, because the removed image
   frees its slot.
4. The dashboard shows a `Product images 2 / 2` meter, and the product list says the same.

### Products

1. Set **Maximum products** to the client's current count.
2. **Products** → the *New product* button is gone and the heading explains the limit.
3. Type `/client/products/create` → **403**.

### Storage

1. Set **Storage allowance (MB)** to `1`.
2. Upload a larger image → rejected by the field with the amount used.

---

## 4. Orders: dispatch, delivered, cancel

**Sales → Orders** on either panel.

| Action | Shown when | Records |
|---|---|---|
| **Dispatch** | the order has not been dispatched, delivered or cancelled | status, `dispatched_at`, carrier/tracking if entered |
| **Delivered** | the order is not already delivered or cancelled | status, `delivered_at` |
| **Cancel** | the order is not delivered or cancelled | status, `cancelled_at`, the reason (required) |
| **Update status** | any move is still allowed | the chosen step |

Checks to make by hand:

1. Dispatch an order → the badge becomes *Dispatched* with the date beneath it; the **View**
   page timeline shows who did it and when.
2. Mark it delivered → the Dispatch, Delivered and Cancel buttons all disappear: a delivered
   order is finished.
3. Cancel a different order with a reason → the badge turns red and shows the date; nothing
   further is offered.
4. Select several orders → **Mark as dispatched** in the bulk menu; any order that cannot make
   that move is listed as left unchanged.

### Filters

**Filter** (top right of the list):

- **Status** — including *Cancelled*.
- **Placed on** — one exact day.
- **Placed from / Placed until** — a range; either end may be left open.
- Each applied filter shows as a chip, and **Reset filters** clears them all.

---

## 5. Product variants

**Products → Edit → Media & Variants.**

1. Under **Variant attributes**, press **Add Variant**: name it `Material`, values
   `Linen, Silk`. Add another named `Size` with `S, M, L`. A *Colour* attribute also takes
   swatch colours.
2. Switch **Sell this product by variant** on, then add **Combinations**: for each one fill in
   *Chosen values* (e.g. `Size → M`, `Material → Linen`) and, where it differs, its own SKU,
   price and stock. Leave a field blank to inherit the product's.
3. On the storefront the product page offers every attribute; the price and stock follow the
   combination chosen.
4. Add to bag → the bag line shows `Size: M / Material: Linen`, priced from the variant.
5. Place the order → **Orders → View** shows the same options and the variant's SKU on the
   line, and the variant's own stock has gone down.
6. Try to force it: post an option the product does not offer, or a combination with no
   variant → **422**, nothing is added.
7. Existing products are untouched: their old colour and size lists still work and are read as
   a *Colour* and a *Size* attribute, with the product's single price, until variants are
   switched on for them.

---

## 6. A database per client

This installation already runs this way — `CLIENT_ISOLATION=database` in `.env`, one database
per client — so the checks below describe the live setup rather than a change to make:

```bash
php artisan clients:provision                 # creates client_<slug> for every client
php artisan clients:provision acme --copy     # move an existing client's rows into its database
```

1. Check the databases exist and hold 21 tables each.
2. Edit a product as client A; client B's identically-seeded product is unchanged.
3. `Platform → Clients` shows each client's database name (toggle the column on).
4. Everything else behaves exactly as in shared mode — the application reads the client's own
   database and the `client_id` scope still applies.
5. After a `--copy`, the client's rows are still in the main database as well; nothing reads
   them while isolation is `database`. Delete them only once the move has been verified, and
   keep the pre-switch dump (`storage/backups/`) until then.

---

## 7. The Simple List storefront (no images)

Assign a client to the **Simple List** store, then open its address (or `/c/<slug>`).

1. The home page *is* the list: a row per product with name, code, price, stock, a quantity
   box and **Add**.
2. No imagery anywhere — the list, the order panel and the order page contain no `<img>`.
3. Set a quantity and press **Add** → the order panel opens with the line.
4. Tick several rows → the bar at the bottom offers **Add selected to order**.
5. Search by name or code; **Clear search** returns to the full list.
6. A product with real choices (two sizes) shows **Choose options** instead of a quantity box
   and links to its page, where the options are picked.
7. The order page, checkout, confirmation and account pages are the shared ones — the same
   coupons, delivery methods, payment methods and order tracking as every other template.
8. Narrow the window to a phone width: each row becomes a labelled block with nothing cut off.
