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
| **Settings → Homepage** | Every homepage section: hero, ticker, category/arrivals headings, editorial story, featured collection, fragrance spotlight, journal heading, brand promises, newsletter |

**Storefront pages:** home, shop (filters: category, sub-category, size, colour, price, collection, brand, material, rating, availability, sale; sort; grid/list; mobile filter sheet), collections + campaign detail, product (zoom, lightbox, video, reviews, complete the look, recently viewed, sticky mobile add-to-bag, Buy now), search, cart (promo codes, free-shipping progress, recommendations), checkout (contact, address, delivery method, payment: cards/UPI/wallets/net banking/COD), order confirmation, order tracking, customer account (login, register, forgot/reset password, dashboard, orders, order detail, wishlist, addresses, profile), journal, and all CMS pages above. Cookie consent banner with preference controls.

SEO plumbing that runs automatically: per-page `<title>`/description/canonical/robots, Open Graph + Twitter cards, JSON-LD (Organization, WebSite search action, Product with offers/ratings), `/sitemap.xml`, `/robots.txt`.

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
resources/
  css/app.css          design tokens + components (Tailwind v4)
  js/app.js            Alpine stores (ui, cart, wishlist, recent) and components
  views/               layouts/app, partials (header, mega-menu, drawers…), components (product-card, section-header…), home/sections, shop/*, pages/*
database/seeders/      Catalog, Menu, Content, Settings seeders
```

The original Next.js prototype is kept at `D:\ecommerce-next` for reference.

## Notes for going live

- **Payments** — card / UPI / wallet / net-banking options are designed and validated, and orders are created with `payment_status = paid`, but no gateway is connected yet. Wire Razorpay/Stripe into `CartController::placeOrder()` before launch. Cash on delivery works as-is.
- **Email** — order confirmations and password-reset mails use Laravel's mailer; set `MAIL_*` in `.env` (dev uses the `log` driver, so reset links appear in `storage/logs/laravel.log`).
- **Uploads** — admin uploads go to `storage/app/public`; keep `php artisan storage:link` in your deploy script.
- Demo promo codes: `WELCOME10`, `ELAN2000`, `FREESHIP`.
