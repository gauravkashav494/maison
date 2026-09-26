# Database, stores and limits

Three things are described here: the per-store limits, moving the application to PostgreSQL,
and giving each store its own database.

---

## 1. Store limits

Each store can be capped by the super admin under **Platform → Stores → Edit**:

| Limit | Meaning | Blank means |
|---|---|---|
| **Maximum products** | How many products the store may hold | unlimited |
| **Storage allowance (MB)** | How much uploaded media the store may keep | unlimited |

* Products are counted per store. When the limit is reached the "New product" button disappears,
  `/admin/products/create` answers **403**, and the list explains why — for owners *and* for the
  super admin working in that store, so nobody can slip past it by typing a URL.
* Storage is measured from the disk, so deleting media frees it up immediately. Admin uploads land
  in `storage/app/public/stores/<slug>/…`, and an upload that would exceed the allowance is
  rejected by a validation rule on the field itself.
* Both figures are shown on the store owner's dashboard and in the Stores table.

---

## 2. PostgreSQL

The application runs on **PostgreSQL** (`DB_CONNECTION=pgsql`, defaults in `config/database.php`).
MySQL and SQLite still work — the queries are driver-agnostic — but Postgres is what local
development and production use.

### The dump

`database/maison_pgsql.dump` is a `pg_dump` custom-format dump of the working database
(schema + data: products, categories, pages, menus, services, stores, users with their roles).
`database/maison_pgsql.sql` is the same thing as plain SQL if you prefer to read or edit it.

Restore it on another machine:

```bash
createdb -U postgres -O maison maison          # database + owner, once
pg_restore -h 127.0.0.1 -U maison -d maison --no-owner --clean --if-exists database/maison_pgsql.dump
# …or with the plain file:
psql -h 127.0.0.1 -U maison -d maison -f database/maison_pgsql.sql
```

`.env` on that machine:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=maison
DB_USERNAME=maison
DB_PASSWORD=…
```

Then `php artisan migrate` (no-op if the dump is current) and `php artisan optimize:clear`.
PHP needs the `pdo_pgsql` extension enabled in `php.ini`.

To produce a fresh dump after changing data:

```bash
pg_dump -h 127.0.0.1 -U maison -d maison -Fc -f database/maison_pgsql.dump
pg_dump -h 127.0.0.1 -U maison -d maison     -f database/maison_pgsql.sql
```

### Local server on this machine

PostgreSQL 17.2 is installed as portable binaries (no Windows service, no admin rights):

| | |
|---|---|
| Binaries | `D:\pgsql-portable\pgsql\bin` |
| Data directory | `D:\pgsql-portable\data` |
| Log | `D:\pgsql-portable\server.log` |
| Superuser | `postgres` / `maison_local_pw` |
| Application role | `maison` / `maison`, owner of database `maison` |

It does **not** start automatically after a reboot:

```bash
"D:\pgsql-portable\pgsql\bin\pg_ctl" -D "D:/pgsql-portable/data" -l "D:/pgsql-portable/server.log" start
"D:\pgsql-portable\pgsql\bin\pg_ctl" -D "D:/pgsql-portable/data" status
"D:\pgsql-portable\pgsql\bin\pg_ctl" -D "D:/pgsql-portable/data" stop
```

Removing it is deleting `D:\pgsql-portable` and re-commenting the two `extension=…pgsql` lines
in `php.ini`.

### Moving data from another database

`db:transfer` copies every table between any two configured connections — that is how the data
moved from the old SQLite/MySQL database into PostgreSQL:

```bash
php artisan migrate --database=pgsql                      # build the schema
php artisan db:transfer --from=mysql --to=pgsql --pretend # row counts only
php artisan db:transfer --from=mysql --to=pgsql
```

It loads tables parents-first, converts values to the target column types (booleans, JSON) and
resets the identity sequences, so inserts continue after the highest imported id. If the target
role is a superuser it suspends foreign keys instead of ordering the tables.

The old MySQL export, `database/maison_elan_data.sql`, is still there for a MySQL target.

## 3. A separate database per store

Controlled by `STORE_ISOLATION` in `.env` (see `config/stores.php`):

| Mode | What it does | Works on |
|---|---|---|
| `shared` *(default)* | One database. Rows carry a `template` column and every query is scoped by it. | all |
| `database` | One database per store: `store_fashion`, `store_grocery`, … | PostgreSQL, MySQL, SQLite |
| `schema` | One PostgreSQL schema per store inside the same database. | PostgreSQL |

**What lives where**

* **Store database** — products, categories, collections, coupons, orders, order items, reviews,
  pages, posts, FAQs, menus, menu items, messages, subscribers, services, service areas,
  testimonials, projects, bookings/quotes.
* **Main database** — users (so one customer account works across the stores), stores, settings,
  store locations, sessions, cache, jobs.

**Building the stores**

```bash
# .env
STORE_ISOLATION=database        # or: schema
STORE_DB_PREFIX=store_          # database/schema name prefix

php artisan stores:provision            # every store
php artisan stores:provision grocery    # one store
php artisan stores:provision --fresh    # rebuild a store from scratch
php artisan stores:provision --no-seed  # schema only
```

`stores:provision` creates the database (or schema), builds the store schema from
`database/migrations/tenant`, and fills it with that store's own content by running the seeders
its template declares — its categories, products or services, pages, navigation and settings.
A store database is therefore created from the store's own definition, not copied from another
database, and it contains only store tables: there is no `users`, `settings` or `sessions` table
inside it and no foreign key pointing at the main database.

Adding a store later is the same command with its slug. Changes to store tables belong in
`database/migrations/tenant`; `stores:provision` applies them to every store, and the ordinary
`php artisan migrate` applies them to the main database.
**What changes in the admin**

* The super admin always works inside one store (the "All stores" option disappears, because a
  cross-store query cannot span databases). The store switcher still moves between them, and the
  platform totals add the stores up.
* The template tabs on the resource lists disappear for the same reason.
* The customers table hides its per-user order count, which would need a cross-database join.
* Store owners see no difference at all.

**Going back** is just `STORE_ISOLATION=shared` — the main database still has its own copy of the
store tables. Content written while isolation was on lives in the store databases; bring it back
with `php artisan db:transfer --from=store_grocery --to=pgsql` if you need it.

---

## Backups

* `shared`: one database to dump.
* `database` / `schema`: dump the main database **and** each `store_*` database (or schema); a store
  can be restored on its own, which is the main reason for running this mode.
* `php artisan db:transfer --from=<a> --to=<b>` also works as a copy tool between any two
  configured connections, e.g. cloning production into a staging database.
