# Current API Surface

Last updated: 2026-06-05

This document records the JSON/API-style endpoints currently implemented in the Life@ Laravel app. It documents the current production surface; it does not replace the future external API strategy in `Planning/api-surface-and-auth-strategy.md`.

## Current Boundary

- `routes/api.php` contains voucher-oriented JSON endpoints.
- `routes/web_api.php` contains session-backed dashboard JSON endpoints that intentionally keep `/api/*` URIs for Blade dashboards.
- Public web routes also expose a few JSON utility endpoints for health checks, maps, civic fault map data, advert tracking, push-open tracking, and Jimmy/Ask Life.
- A decoupled external API strategy is still pending. Do not move browser-session dashboard endpoints into a token-only API stack until Sanctum, JWT, or OAuth is chosen.

## Authentication Model

- Public read endpoints are unauthenticated unless a route below says otherwise.
- Authenticated endpoints currently use the application auth guard and existing role middleware/policies.
- Session-backed dashboard endpoints rely on browser cookies and CSRF protection from the web middleware stack.
- Public tracking and utility endpoints are rate-limited where called out below.
- Future mobile/partner clients need a separate token strategy before these surfaces can be treated as a formal external API.

## Voucher JSON API

Defined in `routes/api.php`.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/api/vouchers` | Public | Paginated active voucher discovery. Supports `q`, `category_id`, `sort`, and `listing` query filters. |
| GET | `/api/vouchers/{listing}/{voucher}` | Public | Voucher detail payload with active state and remaining uses. Uses listing/voucher slugs. |
| POST | `/api/vouchers/{listing}/{voucher}/redeem` | Authenticated | Claims a voucher for the current user. Returns validation errors as JSON with HTTP 422. |
| GET | `/api/me/vouchers` | Authenticated | Paginated voucher redemptions for the current user. |
| POST | `/api/voucher-redemptions/{code}/consume` | Authenticated owner/staff/admin | Consumes a claimed voucher code. Listing owners can consume their own voucher redemptions; staff/admin can consume any valid code. |
| GET | `/api/listings/{listing}/vouchers` | Authenticated listing owner | Paginated owner view of vouchers for a listing. |
| POST | `/api/listings/{listing}/vouchers` | Authenticated listing owner | Creates a listing voucher. |
| GET | `/api/listings/{listing}/vouchers/stats` | Authenticated listing owner | Returns voucher totals, published count, claimed count, and consumed count. |
| PUT | `/api/listings/{listing}/vouchers/{voucher}` | Authenticated listing owner | Updates a voucher belonging to the listing. |
| DELETE | `/api/listings/{listing}/vouchers/{voucher}` | Authenticated listing owner | Deletes a voucher belonging to the listing. |

## Session-Backed Dashboard JSON

Defined in `routes/web_api.php`. These endpoints are mounted under `/api/*`, but they are intentionally part of the web/session application surface.

| Method | Path | Auth/Role | Purpose |
| --- | --- | --- | --- |
| POST | `/api/push-subscriptions` | Public/session optional, throttle `30,1` | Stores or refreshes a browser push subscription. Attaches `user_id` when a user is signed in. |
| DELETE | `/api/push-subscriptions` | Public/session optional, throttle `30,1` | Revokes a browser push subscription by endpoint. |
| GET | `/api/client/advertising/listings` | Authenticated | Current user's advertising listings. |
| GET | `/api/client/advertising/listings/{listing}` | Authenticated | Owner advertising summary for one listing. |
| PUT | `/api/client/advertising/listings/{listing}/integrations/{type}` | Authenticated | Updates an owner-managed marketing integration. |
| GET | `/api/staff/advertising/businesses` | Staff/Admin | Staff business advertising list. |
| GET | `/api/staff/advertising/businesses/{listing}` | Staff/Admin | Staff advertising summary for a business listing. |
| PUT | `/api/staff/advertising/ad-campaigns/{adCampaign}` | Staff/Admin | Updates an advert campaign through the staff advertising dashboard. |
| PUT | `/api/staff/advertising/push-campaigns/{pushCampaign}` | Staff/Admin | Updates a push campaign through the staff advertising dashboard. |
| PUT | `/api/staff/advertising/businesses/{listing}/integrations/{type}` | Staff/Admin | Updates a staff-managed marketing integration. |
| GET | `/api/admin/metrics` | Admin/Editor/Staff/Support | Operational/admin KPI JSON. |
| GET | `/api/admin/audit-logs` | Admin/Editor/Support | Audit log index JSON. |
| GET/POST/PUT/DELETE | `/api/admin/listings...` | Admin/Editor/Staff | Admin listing JSON CRUD and bulk action endpoints. |
| GET/POST/PUT/DELETE | `/api/admin/events...` | Admin/Editor/Staff | Admin event JSON CRUD and bulk action endpoints. |
| GET/POST/PUT/DELETE | `/api/admin/articles...` | Admin/Editor | Admin article JSON CRUD and bulk action endpoints. |
| GET/POST/PUT/DELETE | `/api/admin/vouchers...` | Admin/Editor/Staff | Admin voucher JSON CRUD and bulk action endpoints. |
| GET/POST/PUT/DELETE | `/api/admin/integrations...` | Admin/Editor/Staff | Admin marketing integration JSON CRUD and bulk action endpoints. |
| GET/POST | `/api/admin/campaigns/ads...` | Admin/Editor/Staff read; Admin/Editor mutate | Admin advert campaign list/detail plus approve, pause, resume, and bulk actions. |
| GET/POST | `/api/admin/campaigns/push...` | Admin/Editor/Staff read; Admin/Editor mutate | Admin push campaign list/detail plus dispatch and bulk actions. |
| GET/POST/PUT/DELETE | `/api/admin/councillors...` | Admin | Councillor JSON CRUD and bulk action endpoints. |
| GET/POST/PUT | `/api/admin/fault-reports...` | Admin/Editor | Civic fault report review, moderation, update, and bulk endpoints. |

## Public JSON And Utility Endpoints

Defined in public web routes and served through the web middleware stack.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| GET | `/health` | Public, throttle `60,1` | Health JSON for database, storage, disk, queue, payment, and notification degradation signals. |
| POST | `/ask-life` | Public, throttle `20,1` | Jimmy/Ask Life answer endpoint. |
| POST | `/ask-life/feedback` | Public, throttle `30,1` | Feedback for Ask Life responses. |
| POST | `/ask-life/speak` | Public, throttle `12,1` | Voice response endpoint for Ask Life. |
| GET | `/faults/data/faults` | Public | Civic fault map data. |
| GET | `/faults/data/councillors` | Public | Councillor map/reference data. |
| GET | `/maps/places/autocomplete` | Public, throttle `60,1` | Address autocomplete proxy. |
| GET | `/maps/places/details` | Public, throttle `60,1` | Place details proxy. |
| GET | `/maps/places/reverse` | Public, throttle `60,1` | Reverse geocoding proxy. |
| GET | `/ads/{adCampaign}/i` | Public, throttle `public-tracking` | Advert impression tracking. |
| GET | `/ads/{adCampaign}/click` | Public, throttle `public-tracking` | Advert click tracking and redirect. |
| GET | `/push/{pushCampaign}/open` | Public, throttle `public-tracking` | Push open tracking. |

## Operational Notes

- Keep route ownership explicit: browser dashboard JSON belongs in `routes/web_api.php`; true external/mobile/partner API routes belong in `routes/api.php` after the auth strategy is chosen.
- JSON mutation endpoints should keep using policies, role middleware, audit logs, and rate limits that match the equivalent Blade workflow.
- Public proxy/tracking endpoints must not expose provider secrets, request bodies, signatures, API keys, tokens, or private user data in responses or logs.
- When a mobile/partner API is introduced, document its auth scheme, token lifetime, rate limits, pagination format, error envelope, and versioning policy before exposing it publicly.
