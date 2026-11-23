# E-commerce readiness review

_Reviewed: 20 November 2026, branch `feat/database`._

What the platform already does, how sure we are that it works, and what is still needed before
a client store can take real orders and real money.

## How this was checked

- **Full test suite:** `php artisan test`: **722 tests passed (4,945 assertions), 0 failed.**
  (PHPUnit prints deprecation warnings for doc-comment metadata. These are harmless today, but
  the tests must move to attributes before upgrading to PHPUnit 12.)
- **Code read by hand:** checkout and order placement, payment status, cart and coupons, order
  confirmation, invoice access, order numbers, shipping methods, notifications.
- **Not done:** a line-by-line audit of every screen, load testing, a security penetration test,
  or testing on a real production server. Passing tests show the built features behave as their
  tests describe. They do not prove there are no bugs at all.

**Short answer:** what is built is solid and well tested. Two built features had real problems
(payment status, coupon limits; the coupon one is now fixed), and several things a live shop needs have not been built yet.

---

## 1. Already built

Status key: ✅ works, tested · ⚠️ works, has a known issue (see section 2)

### Storefront

| Feature | Status | Notes |
|---|---|---|
| Home, shop, categories, brands, collections, product pages | ✅ | Several templates: Fashion, Grocery, Heritage, Plumbing, Plumbing Services, Bulk |
| Search (page and live overlay), quick view, recently viewed | ✅ | |
| Product variants (size, colour, options) with stock per variant | ✅ | |
| Product reviews with moderation | ✅ | Rate-limited. Anyone can review, not only buyers |
| Journal / blog, CMS pages, FAQs, testimonials | ✅ | |
| Newsletter sign-up and one-click unsubscribe | ✅ | |
| Contact form | ✅ | Rate-limited |
| Delivery PIN code check | ✅ | India only |
| Wishlist | ✅ | Saved **on the device only** (localStorage). Lost on a new phone or browser |
| PWA (installable, offline page) | ✅ | |
| SEO: sitemap, robots.txt, per-page meta, SEO settings | ✅ | |

### Cart and checkout

| Feature | Status | Notes |
|---|---|---|
| Cart: add, update, remove, max quantity per order | ✅ | Cart is kept in the session, not the database |
| Stock checked again at checkout, products locked during order placement | ✅ | Two shoppers cannot both buy the last unit (`PlaceOrder::lockAndCheckStock`) |
| Unavailable lines block the order until removed | ✅ | |
| Coupons: percent, fixed, free shipping, minimum subtotal, dates, total and per-customer limits | ✅ | Limits re-checked with the coupon locked (issue 2.2, fixed) |
| Shipping methods with "free over" threshold | ✅ | Flat rates from settings, not live courier rates |
| Cash on delivery with optional COD fee | ✅ | The only payment method switched on |
| GST: inclusive/exclusive prices, per-product rate, IGST vs CGST+SGST by state | ✅ | |
| Guest checkout with WhatsApp code verification | ✅ | |
| Sales-person orders (placed for a customer at the counter) | ✅ | |
| Order and address writes wrapped in one transaction | ✅ | No half-placed orders |
| Order confirmation page | ✅ | Only the guest who placed it, or the owning customer, can see it |

### Customer accounts

| Feature | Status | Notes |
|---|---|---|
| Register, log in with password or WhatsApp code | ✅ | Rate-limited |
| Forgot / reset password (WhatsApp code, email link for past guests) | ✅ | |
| Order history and order detail | ✅ | |
| Saved addresses, profile, change password | ✅ | |
| Track order (order number + email/phone) | ✅ | |
| Cancel order before dispatch | ✅ | Stock goes back on the shelf |
| Download invoice PDF | ✅ | Owner, guest who just ordered, or a signed link. Only after dispatch |

### Admin panel (Filament)

| Feature | Status | Notes |
|---|---|---|
| Products, categories, brands, collections, menus, pages, posts, FAQs | ✅ | |
| Orders: status pipeline (confirmed → processing → dispatched → out for delivery → delivered), cancel, invoice | ✅ | Invoice number issued at dispatch |
| Stock levels, stock movement history | ✅ | |
| Customers, reviews, subscribers, contact messages, coupons, taxes | ✅ | |
| Settings pages per template, homepage builders, SEO, search listings | ✅ | |
| Staff accounts, roles and permissions per module | ✅ | |
| Activity log of every staff action | ✅ | Readable sentences, per client |
| Notification bell in the client panel | ✅ | New orders, customer cancellations, reviews awaiting approval, contact messages, bookings. Only staff who may open that module see each alert (`App\Support\StaffAlerts`) |
| Multi-client platform: subdomains, separate client databases, plans, super admin | ✅ | |

---

## 2. Problems found in what is built

### 2.1 Online payments would be recorded as "paid" without charging anyone (critical)

`app/Services/PlaceOrder.php:93`:

```php
'payment_status' => $data['payment_method'] === 'cod' ? 'cod' : 'paid',
```

Any method other than COD is saved as **paid** at once, and no payment provider is involved.
Today this is safe only because `Order::CHECKOUT_ONLY_PAYMENT_METHODS = ['cod']` hides card, UPI,
wallet and net banking. If someone removes that line to "turn on" online payments, shoppers
will get goods without paying.

**Fix:** keep online methods switched off until a payment provider is connected (section 3.1).
Then save new orders as `pending` and mark them `paid` only from the provider's verified webhook.

### 2.2 Coupon usage limit can be exceeded (medium) ✅ fixed 20 Nov 2026

The coupon is now locked and checked again when the order is placed. A new optional "uses
per customer" limit counts the shopper's account and phone number, and cancelled orders don't
count. A code used up after it was applied no longer silently drops from the total. The order
is stopped and the shopper is told. Tests: `tests/Feature/CouponLimitTest.php`.

Original finding: `PlaceOrder::place()` raises `used_count` but never checks `usage_limit` again inside the
transaction, and does not lock the coupon row. Two shoppers who check out at the same moment can
both use the last remaining coupon. There is also **no per-customer limit**, so one person can
use a "first order" code again and again.

**Fix:** inside the transaction, lock the coupon (`lockForUpdate`), check `isUsable()` again,
then increment. Add an optional `per_customer_limit` column.

### 2.3 Smaller points

- **Reviews are not limited to buyers.** Anyone can post (moderated). Consider a "verified buyer" badge.
- **Wishlist and cart are not saved to the account**, so they do not follow the shopper across devices.
- **Estimated delivery is always "today + 5 days"** (`PlaceOrder.php`), whatever the shipping
  method or PIN code.
- **PHPUnit doc-comment metadata** must become attributes before PHPUnit 12.

---

## 3. Not built yet: needed for a real store

### 3.1 Must-have before going live

| Gap | What to build |
|---|---|
| **Payment provider** | Razorpay, Cashfree or PhonePe: pay, verify the signature, webhook, failure/retry, `pending_payment` status, reconciliation |
| **Order notifications** | There are no Mail classes. WhatsApp is used only for login codes. Send confirmation, dispatch (with tracking), delivered and cancelled messages by email and WhatsApp. Alert the store about new orders. Set up real SMTP/SES (`.env.example` has `MAIL_MAILER=log`) and a running queue worker |
| **Refunds and returns** | Return request from the account page, admin approve/reject, refund through the payment provider (full or partial), GST credit note |
| **Legal pages** | Terms, privacy, refund/return and shipping policies. Payment providers require them before approving a store |

### 3.2 Shipping

- Courier integration (Shiprocket / Delhivery): live rates, COD availability per PIN code, AWB
  creation, label printing, automatic status updates. Orders already have a `tracking_number` field.
- Product weight and dimensions for weight-based shipping.

### 3.3 Sales growth

- Wishlist and cart saved to the account
- Abandoned cart reminders (WhatsApp / email)
- "Notify me when back in stock"
- Store credit / loyalty points, gift cards
- "Frequently bought together" suggestions
- Reviews from verified buyers only, with photos

### 3.4 Running the business

- Sales reports: revenue, best sellers, COD vs prepaid, GST (GSTR-ready) export
- Payment settlement reconciliation
- Low-stock alerts to staff
- Bulk printing of labels and invoices

### 3.5 Production setup

- `APP_ENV=production`, `APP_DEBUG=false`
- HTTPS on every client subdomain (wildcard certificate)
- Supervised queue worker and scheduler cron job
- Database and storage backups
- Error monitoring (e.g. Sentry) and uptime checks
- Analytics: GA4 / Meta Pixel e-commerce events (view item, add to cart, purchase)

---

## 4. Suggested order of work

1. ~~Fix the coupon limit (2.2).~~ Done.
2. Payment provider plus `pending` payment status (2.1 + 3.1).
3. Order notifications by email and WhatsApp.
4. Refunds and returns.
5. Legal pages and production setup, then go live.
6. Courier integration, then the sales-growth features.

Every new staff action in these features (issuing a refund, creating a shipment, resending a
message) also needs its activity log line and a test in `tests/Feature/ActivityLogTest.php`
(see `.claude/rules/rules.md`).
