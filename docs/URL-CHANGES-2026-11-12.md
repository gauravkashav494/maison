# URL changes: main domain and client subdomains

**Date:** 12 November 2026
**Branch:** `feat/database`

The Super Admin panel is now the main domain itself: the dashboard is `ecomvision.test/`, the
sign-in is `ecomvision.test/login`, and screens are `ecomvision.test/clients` and so on, with no
`/admin` prefix. Every client page is reached only through the client's subdomain.

All 123 automated tests pass, including a new one that signs a Super Admin in at `/login` through
the real sign-in form. The addresses below were also checked on the local site.

## Addresses

| URL | Result |
|---|---|
| `ecomvision.test/` | Super Admin dashboard (signed-out visitors go to `/login`) |
| `ecomvision.test/login` | Super Admin sign-in |
| `ecomvision.test/clients`, `/plans`, `/appearance/templates` ... | Super Admin screens (Templates is not at `/templates`, which is the stores' app icon folder) |
| `ecomvision.test/shop` or any other store page | Goes to `/`, since there is no store on the main domain anymore |
| `ecomvision.test/store/fashion` | Goes to `fashion.ecomvision.test` |
| `fashion.ecomvision.test` | Fashion client store |
| `fashion.ecomvision.test/login` | Fashion's shopper sign-in, unchanged |
| `fashion.ecomvision.test/client/login` | Fashion's client team sign-in |
| `fashion.ecomvision.test/admin/login` | Not found, because the Super Admin is on the main domain only |
| `theme-fashion.ecomvision.test` | Public theme preview of the Fashion template, with sample content |
| `simple.ecomvision.test`, `ecomvision.test/store/simple` | Go to `theme-simple.ecomvision.test`, the template's theme preview |
| `bulk.ecomvision.test` | Not found: the Bulk store points to a template that does not exist in the code |

## Theme previews

Every template has a public sample store at `theme-<template>.ecomvision.test`, for example
`theme-fashion.ecomvision.test`. It shows the template's own sample content, never a client's data.

- Visitors can browse and use the cart. Ordering, booking, shopper sign-in and sign-up, the
  contact form and the newsletter are switched off, and a bar at the bottom says so.
- Search engines are told not to index these pages.
- Set one up or update it under Appearance → Templates (Set up theme / Preview), or run
  `php artisan themes:build` (all templates) or `php artisan themes:build fashion`.
- Each theme has its own database and is hidden from client lists, counts and totals. Client
  names cannot start with `theme-`.

On the live server, replace `ecomvision.test` with the real domain.

## Things to know

- **Bulk store:** its record points to a `bulk` template that is not in the code, so it has no theme
  preview and its address shows "not found" instead of the server error it gave before.
- **Template previews:** the Preview button on Appearance → Templates now opens the template's own
  subdomain (for example `fashion.ecomvision.test`). If a client runs that template, the visitor is
  taken to that client's store.
- **Template activation:** activating a template no longer changes anything the public sees, since
  the main domain is the admin panel. The button and its "visitors on the main domain" wording are
  still there and should be removed or reworded.
- **Exact domain match:** the Super Admin works only on the exact main domain in `APP_URL` (or
  `STORE_BASE_DOMAIN`). If the live site also uses `www.`, make sure that host redirects to the main
  domain, or the Super Admin pages will show "not found" there.
- **Path-style links removed:** the `STORE_URL_MODE` setting is gone, so the live server needs a
  wildcard DNS record and SSL certificate for the subdomains.
- **Client login still open elsewhere:** `/client/login` still opens on the main domain and on other
  clients' subdomains. Each person still only sees their own client's data. A possible next step is
  to lock each client login to its own subdomain.
