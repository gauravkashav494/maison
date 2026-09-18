# Maison Élan — Laravel storefront + CMS

Laravel 12 · Filament 5 admin · Blade + Alpine.js · Tailwind CSS v4 · SQLite (dev) / MySQL (prod)

## Run locally

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate   # first time only
php artisan migrate --seed                          # creates the catalogue, menus, pages and admin user
php artisan storage:link                            # exposes uploaded media
npm run build                                       # or `npm run dev` while working on CSS/JS
php artisan serve                                   # http://127.0.0.1:8000
```

> Node is managed by nvm-windows on this machine. If `npm` is not found, run `nvm use 22.23.2`
> in an elevated terminal once, or call `C:\Users\pc2\AppData\Roaming\nvm\v22.23.2\npm.cmd`.

**Admin panel:** http://127.0.0.1:8000/admin — `admin@maisonelan.com` / `password` (change this in Users or via tinker).

## What the CMS controls

| Admin section | Storefront effect |
|---|---|
| **Catalogue → Products** | Name, slug, category, SKU, short + rich description, materials, care, images (drag to reorder; 1st = main, 2nd = hover), colours, sizes, price / compare-at price, stock, New / Best-seller flags, rating, sort order, SEO tab |
| **Catalogue → Categories** | Name, parent, tagline, image, homepage-grid toggle, SEO |
| **Catalogue → Collections** | Name, season, description, story (rich text), card + hero image, product picker, Featured flag, SEO |
| **Content → Navigation** | Every menu: header (with mega-menu columns + image tiles), footer columns, legal links. Drag to reorder. |
| **Content → Pages** | Every informational page with a template picker: About (story sections, values, sustainability, founders), Contact, FAQ, Size guide (tables), Care guide (per material), Gift cards, Careers (open roles), Store locator, Cookie policy, Legal documents (auto table of contents) |
| **Content → Journal** | Stories with cover image, category, publish date, lead-story flag, SEO |
| **Sales → Orders** | Every order from checkout: items, totals, address, payment; update status (Confirmed → Processing → Shipped → Out for delivery → Delivered), carrier + tracking number, notes — each change appears on the customer's tracking timeline |
| **Sales → Customers** | Registered customers, their orders, staff flag (admin access) |
| **Catalogue → Reviews** | Moderate customer reviews (approve/reject); product rating and review count update automatically |
| **Content → FAQs / Boutiques** | Categorised FAQ accordion; store locator entries with hours, contact and map link |
| **Marketing → Promo codes** | Percentage / fixed / free-shipping coupons with minimum order, usage limits and validity dates |
| **Marketing → Messages** | Contact-form submissions |
| **Marketing → Subscribers** | Newsletter sign-ups from the homepage and footer |
| **Settings → Site settings → Checkout** | Shipping methods (cost, free-over threshold, ETA), enabled payment methods, COD fee, tax rate, gift-card values |
| **Settings → Site settings** | Brand/wordmark, announcement bar, free-shipping threshold, contact + social, payment badges, **SEO defaults** (title suffix, default description/OG image, Search Console verification, GA4 id, extra robots rules) |
| **Appearance → Templates** | Choose the live storefront template (Fashion / Indian Grocery); preview, activate, install demo content |
| **Appearance → Fashion · Homepage** | Every homepage section: hero, ticker, category/arrivals headings, editorial story, featured collection, fragrance spotlight, journal heading, brand promises, newsletter |

**Storefront pages:** home, shop (filters: category, sub-category, size, colour, price, collection, brand, material, rating, availability, sale; sort; grid/list; mobile filter sheet), collections + campaign detail, product (zoom, lightbox, video, reviews, complete the look, recently viewed, sticky mobile add-to-bag, Buy now), search, cart (promo codes, free-shipping progress, recommendations), checkout (contact, address, delivery method, payment: cards/UPI/wallets/net banking/COD), order confirmation, order tracking, customer account (login, register, forgot/reset password, dashboard, orders, order detail, wishlist, addresses, profile), journal, and all CMS pages above. Cookie consent banner with preference controls.

SEO plumbing that runs automatically: per-page `<title>`/description/canonical/robots, Open Graph + Twitter cards, JSON-LD (Organization, WebSite search action, Product with offers/ratings), `/sitemap.xml`, `/robots.txt`.

## Storefront templates (multi-template)

The backend (catalogue, pages, orders, customers, cart, checkout, SEO, admin) is shared; the *storefront design* is a template. Two ship with the project:

| Template | Id | Views | Assets |
|---|---|---|---|
| **Fashion** (Maison Élan editorial) | `fashion` | `resources/views/` (base views, also the fallback) | `resources/css/app.css`, `resources/js/app.js` |
| **Indian Grocery** (quick-commerce style) | `grocery` | `resources/views/templates/grocery/` | `resources/templates/grocery/{css,js}/app.js` |

**Switching:** Admin → *Appearance → Templates* — preview a template for your own session, or *Activate* it for visitors (confirmation required). Every URL stays the same; nothing is deleted when you switch. CLI equivalents: `php artisan template:list`, `template:install grocery`, `template:activate grocery`.

**How it works:** `App\Templates\TemplateManager` reads the active template from the `appearance` settings group. The `ResolveTemplate` middleware prepends the template’s view folder, so `view(shop.product)` resolves to the template’s copy (falling back to the base views), and registers a catalogue visibility scope (`products/categories/collections.template`: `null` = every template). Each template class (`app/Templates/*`) declares its menu locations, settings groups, defaults, admin pages and demo seeder.

**Per-template admin:** *Appearance → Fashion · Homepage / Navigation* and *Grocery · Settings / Homepage / Navigation*. Products, categories and collections have a *Visible in* option; products also have a *Grocery* tab (veg/non-veg mark, shelf life, origin, ingredients, storage, max qty per order). Pack sizes are the product *Sizes* (e.g. `500 g`, `1 kg`).

**Adding a template:** create `app/Templates/<Name>/<Name>Template.php` (extend `App\Templates\Template`), register it in `config/templates.php`, add its views under `resources/views/templates/<id>/` (same view names as the base), its Vite entries in `vite.config.js`, and optionally a seeder + Filament settings page.
## Switching to MySQL

Edit `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maison_elan
DB_USERNAME=root
DB_PASSWORD=
```

then `php artisan migrate --seed`.

## Structure

```
app/
  Filament/            admin resources (Products, Categories, Collections, Menus, Pages, Posts, Subscribers), settings pages, shared Fields helper
  Http/Controllers/Storefront/   Home, Shop, Catalog (collections/products/search/pages/journal), Cart (session bag + newsletter), Seo (sitemap/robots)
  Models/              Product, Category, Collection, Menu, MenuItem, Page, Post, Setting, Subscriber (+ HasSeo trait)
  Services/Cart.php    session-backed bag
  Support/             Media (URL/storage resolver), Seo (meta value object)
  helpers.php          money(), setting(), emph()
app/Templates/        template registry (Template, TemplateManager, Fashion/, Grocery/ + HomeComposer, visibility scope)
resources/
  css/app.css          Fashion design tokens + components (Tailwind v4)
  js/app.js            Fashion Alpine stores (ui, cart, wishlist, recent) and components
  views/               Fashion views: layouts/app, partials (header, mega-menu, drawers…), components, home/sections, shop/*, pages/*
  views/templates/grocery/   Indian Grocery views (same names, own layout/partials/components)
  templates/grocery/   Indian Grocery css + js bundle
database/seeders/      Catalog, Menu, Content, Settings seeders
```

The original Next.js prototype is kept at `D:\ecommerce-next` for reference.

## Notes for going live

- **Payments** — card / UPI / wallet / net-banking options are designed and validated, and orders are created with `payment_status = paid`, but no gateway is connected yet. Wire Razorpay/Stripe into `CartController::placeOrder()` before launch. Cash on delivery works as-is.
- **Email** — order confirmations and password-reset mails use Laravel's mailer; set `MAIL_*` in `.env` (dev uses the `log` driver, so reset links appear in `storage/logs/laravel.log`).
- **Uploads** — admin uploads go to `storage/app/public`; keep `php artisan storage:link` in your deploy script.
- Demo promo codes: `WELCOME10`, `ELAN2000`, `FREESHIP`.
