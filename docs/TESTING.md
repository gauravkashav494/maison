# Testing guide

How to try the platform features by hand: roles and store access, per-store limits, the
per-store websites, separate databases and the PostgreSQL move.

Local server: `php artisan serve --host=127.0.0.1 --port=8000` → <http://127.0.0.1:8000>
Admin: <http://127.0.0.1:8000/admin/login>

## Accounts

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@maisonelan.com` | `password` |
| Fashion owner | `fashion@maisonelan.com` | `password` |
| Grocery owner | `grocery@maisonelan.com` | `password` |
| Heritage owner | `heritage@maisonelan.com` | `password` |
| Plumbing owner | `plumbing@maisonelan.com` | `password` |
| Plumbing Services owner | `plumbing-services@maisonelan.com` | `password` |

Manage them under **Platform → Users** (super admin only). Changing a user's role to *Store Owner*
reveals the **Assigned store** field; that single field is what the whole admin scopes itself by.

---

## 1. Roles and store access

**As a store owner** (e.g. `grocery@maisonelan.com`)

- The topbar shows `MANAGING Indian Grocery Store` with no way to change it.
- The sidebar has only that store's modules — no Platform group, no Templates, no Site settings,
  and no other store's settings pages. A services store shows Services / Service areas /
  Bookings instead of Products / Orders.
- Lists contain only that store's rows and have no template tabs.

**The important part — hiding a menu item is not the protection.** While logged in as the grocery
owner, type these URLs by hand; each must be rejected:

| URL | Expected |
|---|---|
| `/admin/products/1/edit` (a Fashion product) | 404 |
| `/admin/pages/55/edit` (a Plumbing Services page) | 404 |
| `/admin/users` | 403 |
| `/admin/storefronts` | 403 |
| `/admin/templates` | 403 |
| `/admin/site-settings` | 403 |
| `/admin/plumbing-settings` | 403 |
| `/?preview_template=fashion` | stays on the grocery site |

Also try to escalate: open your own user record (`/admin/users/<id>/edit`) → 403. On any record
you *can* edit, the **Visible in** field is locked, so content cannot be moved to another store.

**As the super admin**

- The topbar has a store switcher: **All stores** plus each store.
- Choosing a store narrows every list to it; the ids above then 404 until you switch back.
- Everything is reachable again on **All stores**, and the dashboard shows platform totals
  (stores, users, catalogue, leads & bookings).

---

## 2. Store limits

**Platform → Stores → <store> → Edit → Plan limits.** Blank means unlimited.

### Product limit

1. Set **Maximum products** on Fashion Store to `19` (its current count) and save.
2. Open **Products** — the *New product* button is gone and the heading explains the limit.
3. Type `/admin/products/create` in the address bar → **403**.
4. Log in as `fashion@maisonelan.com` → the dashboard shows a `Products 19 / 19` meter.
5. Raise the limit to `25` → the button returns and the meter updates.

### Storage allowance

1. Set **Storage allowance (MB)** to `1`.
2. Edit any product in that store and upload an image larger than 1 MB → the field rejects it and
   names the store's current usage.
3. Upload a small image → it is accepted and lands in `storage/app/public/stores/<slug>/…`.
4. The **Storage** column in Platform → Stores and the dashboard meter both follow the folder, so
   deleting media frees the space immediately.

Limits apply to everyone working in that store, including the super admin — only the limit itself
is a super-admin setting.

---

## 3. One website per store

Every store is reachable at the same time; the template activated under **Appearance → Templates**
is only what the main domain serves by default.

| Store | Subdomain (local) | Entry link (no DNS needed) |
|---|---|---|
| Fashion | <http://fashion.localhost:8000> | `/store/fashion` |
| Indian Grocery | <http://grocery.localhost:8000> | `/store/grocery` |
| Heritage Grocery | <http://heritage.localhost:8000> | `/store/heritage` |
| Plumbing | <http://plumbing.localhost:8000> | `/store/plumbing` |
| Plumbing Services | <http://plumbing-services.localhost:8000> | `/store/plumbing-services` |

Check that each host shows its own products/pages, that the cart and checkout work there, and that
a service-only URL such as `/shop` 404s on the Plumbing Services host. Store owners see their own
address (with a Copy link button) on their dashboard.

Which address is shown to share is set by `STORE_URL_MODE` in `.env` (`path` or `subdomain`);
both always work. Subdomains need a wildcard DNS record and certificate — until then use the
entry links, e.g. `https://ecommerce.petrovision.in/store/grocery`.

---

## 4. A separate database per store

Off by default (`STORE_ISOLATION=shared`). To try it:

```bash
# .env
STORE_ISOLATION=database

php artisan stores:provision      # once per store; creates the database and its tables
php artisan stores:split          # copies each store's existing rows into it
php artisan optimize:clear
```

What to check:

- `database/store_*.sqlite` (locally) or `store_*` databases on the server hold only that store's rows.
- A product created in one store appears in that store's database only.
- A grocery product URL 404s on the fashion host.
- Users, stores and settings still live in the main database, so one customer account works everywhere.
- In the admin the super admin now always works inside a selected store: the "All stores" option and
  the template tabs are gone (a query cannot span databases), and platform totals add the stores up.
- Store owners notice no difference.

Set `STORE_ISOLATION=shared` to go back. Content written while isolated stays in the store
databases, so avoid switching back and forth with real data — see `docs/DATABASE.md`.

---

## 5. PostgreSQL

The application already runs on PostgreSQL locally (server in `D:\pgsql-portable`, database
`maison`). Start it with
`"D:\pgsql-portable\pgsql\bin\pg_ctl" -D "D:/pgsql-portable/data" -l "D:/pgsql-portable/server.log" start`
if the machine has been rebooted.

To rebuild it from the dump:

```bash
pg_restore -h 127.0.0.1 -U maison -d maison --no-owner --clean --if-exists database/maison_pgsql.dump
php artisan optimize:clear
```

Then confirm: storefront search still matches regardless of letter case (PostgreSQL LIKE is
case-sensitive, the app switches to ILIKE), product filters by size/colour/diet still work (JSON
columns), the discount filters on the shop page work, and creating a record in the admin succeeds
(identity sequences are reset by the transfer).

---

## 6. After deploying

```bash
git pull
php artisan migrate
npm run build
php artisan optimize:clear
```

Optional: `php artisan db:seed --class=StoreOwnerSeeder` creates one demo owner per store.
Import the bundled data with `mysql -u user -p <database> < database/maison_elan_data.sql` —
it empties each table first, so back up anything you want to keep.

---

## Regression checklist

Quick pass after any change to roles, stores or the database layer:

1. Each of the five storefronts loads its home, listing, detail, cart/booking and contact pages.
2. A booking on Plumbing Services and an order on a catalogue store both complete.
3. Super admin: every admin module opens, the store switcher works.
4. Each owner: only their store's modules, and the URL tests in section 1 are all rejected.
5. Limits: a store at its product limit blocks creation; an oversized upload is rejected.
