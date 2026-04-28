# Information Architecture & Route Map (Clause-Traceable)

Primary sources:
- [platform-specification-source-of-truth.md](platform-specification-source-of-truth.md)
- [public-facing-pages-inventory.md](public-facing-pages-inventory.md)
- [clause-traceable-rebuild-matrix.md](clause-traceable-rebuild-matrix.md)

## Top-Level Navigation (Public)
- Home `/` — clause `1.a`, `2.b`, `3.f`, `4.d`, `5.a`, `5.b`, `10.a`, `10.c`
- Directory `/directory` — clause `3.a`–`3.f`
- Events `/events` — clause `4.a`–`4.d`
- Articles `/articles` — clause `2.a`, `2.b`, `5.a`
- Classifieds `/classifieds` — clause `7.a`–`7.c`
- Advertise `/advertise` — clause `1.a`, `3.a`, `5.a`–`5.d`, `6.a`
- Account `/account` — clause `8.c`, `9.e`, `10.d`
- Basket `/basket` and Checkout `/checkout` — clause `8.a`–`8.d`, `10.d`

## Public Routes (Named)
| Path | Name | Controller Action | Clause |
|---|---|---|---|
| `/` | `home` | `HomeController::__invoke` | `1.a`, `2.b`, `3.f`, `4.d`, `5.a`, `5.b`, `10.a` |
| `/directory` | `directory.index` | `DirectoryController@index` | `3.f` |
| `/directory/{listing:slug}` | `directory.show` | `DirectoryController@show` | `3.e`, `3.f` |
| `/events` | `events.index` | `EventController@index` | `4.d` |
| `/events/{event:slug}` | `events.show` | `EventController@show` | `4.c`, `4.d` |
| `/articles` | `articles.index` | `ArticleController@index` | `2.b` |
| `/articles/{article:slug}` | `articles.show` | `ArticleController@show` | `2.b`, `5.a` |
| `/search` | `search.index` | `SearchController@index` | `2.b`, `3.f`, `4.d`, `7.b`, `10.a` |
| `/classifieds` | `classifieds.index` | `ClassifiedController@index` | `7.a`, `7.b` |
| `/classifieds/{slug}` | `classifieds.show` | `ClassifiedController@show` | `7.b` |
| `/advertise` | `advertise.index` | `AdvertiseController@index` | `3.a`, `5.a`–`5.d`, `6.a` |
| `/account` | `account.index` | `AccountController@index` | `8.c`, `9.e` |
| `/basket` | `basket.index` | `CheckoutController@basket` | `8.a` |
| `/checkout` | `checkout.index` | `CheckoutController@index` | `8.a`–`8.d`, `10.d` |
| `/404` | `error.404` | `ErrorController@notFound` | `10.a`, `10.c` |

## Auth Routes (Role-Aware Dashboard Shell)
| Path | Name | Guard | Module Examples | Clause |
|---|---|---|---|---|
| `/dashboard` | `dashboard` | `auth` | role-aware shell (writer, business, staff, support, admin) | `9.f`, `10.a` |
| `/admin` | `admin.dashboard` | `auth + role:admin` | settings, payments, invoices, KPI, audit logs | `9.a`, `9.e`, `11.e`, `11.d` |
| `/staff` | `staff.dashboard` | `auth + role:staff` | lead capture, listings, wallet, payouts | `3.d`, `9.b` |
| `/editor` | `editor.dashboard` | `auth + role:content-manager` | article moderation, publication | `2.a`, `9.b` |
| `/owner` | `owner.dashboard` | `auth + role:business-owner` | listing management, events, ads, push | `3.c`, `4.a`, `5.c`, `6.a` |

## SEO & Sitemaps
- Canonicals: ensure canonical URLs for archives and pagination — clause `10.b`
- Meta: title/description per route; OpenGraph/Twitter cards — clause `10.a`
- Sitemaps: split by content type (articles, directory, events, classifieds) — clause `2.b`, `3.f`, `4.d`, `7.b`
- Pagination: include `rel=prev/next` and canonical for page > 1 — clause `10.b`

## Breadcrumbs
- Articles: Home > Articles > Category > Post — clause `2.b`
- Directory: Home > Directory > Category > Listing — clause `3.f`
- Events: Home > Events > Category > Event — clause `4.d`
- Classifieds: Home > Classifieds > Category > Item — clause `7.b`

## Middleware
- `role:admin,editor,staff,owner` — clause `9.f`
- Geo-detection (cookie + query override) — clause `3.f`, `4.d`, `6.b`
- Rate limiting for public APIs and checkout — clause `11.d`
- Audit logging for admin actions — clause `11.d`

## APIs (Surface Summary)
- Articles: `/api/articles`, `/api/articles/{slug}` — clause `2.b`
- Directory: `/api/listings`, `/api/listings/{slug}` — clause `3.e`, `3.f`
- Events: `/api/events`, `/api/events/{slug}` — clause `4.c`, `4.d`
- Classifieds: `/api/classifieds`, `/api/classifieds/{slug}` — clause `7.b`
- Checkout: `/api/checkout`, `/api/payments/*` (PayFast webhooks) — clause `8.a`–`8.d`
- Admin: `/api/admin/*` for settings, invoices, KPIs, audit — clause `9.a`, `9.e`, `11.e`, `11.d`

## Data Dependencies (Per Route)
- Articles: categories, tags, location nodes — clause `2.b`
- Directory: listings, geo index, categories, media — clause `3.e`, `3.f`
- Events: events, organiser link to listing, geo index — clause `4.c`, `4.d`
- Classifieds: user-owned listings, moderation state — clause `7.b`, `7.c`
- Checkout: orders, payments, invoices, tokens — clause `8.a`–`8.d`
- Admin: settings, ledgers, wallets, payouts, KPIs, audit — clause `9.a`–`9.f`, `11.e`, `11.d`

## Redirects
- Old WP paths mapped to new Laravel routes (301) — clause `10.a`
- Consolidate marketplace/shop URLs per final commerce decision — clause `7.a`, `8.a`

## Dark Mode & Accessibility
- All pages include theme toggle and WCAG 2.2 AA semantics — clause `10.a`, `10.c`

## Next
- Produce the page-by-page rebuild spec with components, data, and acceptance criteria for each route — driven by clause mapping above.
