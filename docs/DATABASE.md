# Database, clients and allowances

How the platform stores its data: MySQL, the three kinds of account, a database per client,
and the allowances a client is held to.

---

## 1. Accounts

Three separate tables, three separate guards. No account can act outside its own area.

| Who | Table | Guard | Signs in at | Sees |
|---|---|---|---|---|
| **SuperAdmin** (platform operator) | `users` | `admin` | `/admin` | everything: clients, stores, plans, every client's data |
| **Client Admin / Admin Staff** | `client_users` | `client` | `/client` | one client's own data, nothing else |
| **Customer** (shopper) | `customers` | `web` | the storefront | their own orders and addresses |

* `users` holds **nothing but** SuperAdmins. A client's staff and its shoppers are separate
  records, so a client account cannot gain platform access by a flag being flipped.
* Customers live in the platform database and carry a `client_id`: the same email address
  shopping with two clients is two accounts, and neither client can see the other's.
* Each guard has its own password-reset table, so a token issued to a shopper cannot reset a
  staff account that happens to use the same address.

---

## 2. Clients

A **client** is the tenant — the business renting the platform. It is assigned one **store**
(the template it runs), and it owns its own content, staff, customers and allowances.

Two clients may run the same store. They share nothing: not products, not orders, not
customers. That is enforced in four places at once, so no single mistake exposes data:

1. **Connection** — with `CLIENT_ISOLATION=database` the client's content models resolve to
   that client's own database.
2. **Column** — every content row carries `client_id`, and a global scope filters by it.
3. **Policies** — every record check compares the row's client with the request's client.
4. **Resolution** — the client is chosen server-side only: from the signed-in staff account's
   own row, from the host/entry link for a storefront request, or from a SuperAdmin's choice,
   which is validated against the `clients` table before it is used. A client id in a request
   is never trusted.

Set up under **Platform → Clients**: name, slug (fixed — the database and address are named
after it), store, plan, limits, custom domain.

### Public address

Every client is reachable on its own address as soon as it is created:

| Form | Example | Needs |
|---|---|---|
| Subdomain | `https://acme.yourdomain.com` | a wildcard DNS record and certificate |
| Custom domain | `https://shop.acme.com` | the client's DNS pointed at this server |
| Entry link | `https://yourdomain.com/c/acme` | nothing — it remembers the client for that visitor |

`STORE_URL_MODE=subdomain|path` decides which form the admin shows for sharing; all of them
keep working. A shopper's session is dropped when the visitor moves to another client's
storefront, so a session never follows them across clients.

---

## 3. MySQL

The application runs on **MySQL** (`DB_CONNECTION=mysql`; `mariadb` locally when XAMPP's
MariaDB is used — same schema, same queries). PostgreSQL and SQLite still work, because the
queries are driver-agnostic, and the test suite runs on SQLite in memory.

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maison
DB_USERNAME=maison
DB_PASSWORD=…
```

### The dump

`database/maison_mysql.sql` is a `mysqldump` of the working database (schema + data: clients,
their staff, customers, products, categories, pages, menus, services, orders). Restore it:

```bash
mysql -u root -e "create database maison character set utf8mb4 collate utf8mb4_unicode_ci"
mysql -u root -e "create user 'maison'@'localhost' identified by '…'; grant all on *.* to 'maison'@'localhost'"
mysql -u maison -p maison < database/maison_mysql.sql
```

Then `php artisan migrate` (a no-op if the dump is current) and `php artisan optimize:clear`.
The `maison` user needs `CREATE DATABASE` if you intend to use per-client databases.

`database/maison_pgsql.dump` is the previous PostgreSQL dump, kept for reference. To move
data between drivers use the transfer command rather than hand-editing a dump:

```bash
php artisan migrate --database=mysql        # create the schema first
php artisan db:transfer --from=pgsql --to=mysql
```

It copies every table, converts values to the target column types, suspends foreign keys
during the load (or loads parents-first when it may not) and resets PostgreSQL sequences.

---

## 4. A database per client

`config/clients.php` decides how far apart the clients' data is kept:

| `CLIENT_ISOLATION` | What happens |
|---|---|
| `shared` | One database. Rows carry `client_id` and every query is scoped by it. No setup. |
| `database` | One database per client: `client_acme`, `client_globex`, … Content in the client's own database; platform data (super admins, clients, their staff, customers, storefronts, settings) stays in the main one. |
| `schema` | PostgreSQL only: one schema per client inside one database. |

This installation runs `database`: `CLIENT_ISOLATION=database` in `.env`, one database per
client on the same MySQL server, created by `clients:provision`.

Whichever is used, `client_id` is still written and still filtered, so isolation never rests
on the connection alone.

```bash
php artisan clients:provision                 # every client
php artisan clients:provision acme            # one client
php artisan clients:provision acme --fresh    # rebuild it from scratch
php artisan clients:provision acme --no-seed  # schema only, no content
php artisan clients:provision acme --copy     # move its existing rows out of the main database
```

For each client the command creates the database (or schema), builds the content schema from
`database/migrations/client`, and fills it with that store's own content by running the
seeders its template declares — then stamps every row with the client id. Nothing is copied
from another client.

`--copy` is the migration path for an installation that started in `shared` mode: instead of
seeding, the client's existing rows (matched on `client_id`) are copied into its own database.
The rows in the main database are left in place; delete them only once the move is verified.
They are then dead weight rather than a second source of truth — nothing reads them while
isolation is `database` — and they are what a switch back to `shared` would fall back to.

Re-running is safe: the schema step applies only missing migrations, the seeders update in
place rather than duplicate, and the stamping only fills blanks.

### Changing the content schema

Content tables live in two places: the ordinary migrations build them in the main database,
and `database/migrations/client` builds them inside each client database. A change to a
content table therefore needs a migration in **both** — see
`database/migrations/client/0001_01_01_000002_add_variants_and_fulfilment.php` for the shape.
Then `php artisan migrate` and `php artisan clients:provision --no-seed`.

### Unique keys

Slugs, SKUs, coupon codes, order numbers and menu locations are unique **per client**
(`unique(client_id, slug)` and so on), so two clients can both have a "shirts" category or a
`SALE10` coupon. Inside a per-client database the composite key behaves exactly like a
single-column one.

---

## 5. Allowances

Set per plan (**Platform → Plans**) and overridable per client (**Platform → Clients →
Edit**). Blank inherits the plan, `0` means unlimited.

| Allowance | Default | Enforced by |
|---|---|---|
| **Product images** | 50 per client | a validation rule on the image field (`WithinClientImageLimit`), counted from the products themselves |
| **Maximum products** | unlimited | `ProductPolicy::create` — the button disappears *and* `/client/products/create` answers 403 |
| **Storage (MB)** | unlimited | a validation rule on every upload field (`WithinClientStorage`) |

* Images are counted as **distinct files** across the client's products, including variant
  images, so re-using one photo on two products costs one image and listing it twice on one
  product costs nothing extra.
* Replacing images frees their slots in the same check, so swapping pictures never trips the
  limit.
* Storage is measured from the disk, so deleting media frees it immediately. Uploads land in
  `storage/app/public/clients/<slug>/…`.
* All three are shown on the client's dashboard (the usage widget) and on the product list.

---

## 6. Plans and pricing

Plans carry the allowances a client inherits. Billing is **not** implemented: a plan's price
is a label (`₹X` by default), so a paid tier can be introduced later by changing a number and
adding a payment flow — nothing else in the platform depends on it.
