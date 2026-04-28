# API Surface And Auth Strategy

Primary sources:
- `platform-specification-source-of-truth.md`
- `information-architecture-and-route-map.md`
- `database-schema-and-entity-relationship-plan.md`
- `billing-package-invoice-and-payout-architecture.md`
- `admin-module-architecture.md`

This document defines the platform API strategy, authentication model, authorization boundaries, webhook handling, rate limiting, and client-domain split.

## 1. API Strategy

## 1.1 API Style

Recommended v1 approach:
- REST as the primary implementation surface
- optional GraphQL added later for advanced admin analytics or aggregated mobile clients

Reason:
- REST is faster to implement for the current staged rebuild
- easier webhook, billing, moderation, and admin CRUD integration
- cleaner fit for versioned public/admin/mobile service boundaries

Clause trace:
- `11.a`

## 1.2 Versioning

All APIs should be versioned from day one:
- `/api/v1/...`

Benefits:
- safe iteration
- backward compatibility
- explicit client contracts

Clause trace:
- `11.a`, `12.b`

## 1.3 API Domains

The API surface should be split by consumer and trust boundary:

- Public content API
- Authenticated user API
- Business/self-service API
- Staff API
- Admin API
- Finance API
- Webhook/API integration endpoints

Clause trace:
- `2.b`, `3.c`, `3.d`, `8.a` to `8.d`, `9.a` to `9.f`, `11.d`

## 2. Authentication Strategy

## 2.1 Recommended Auth Stack

Recommended model:
- session auth for first-party web Blade/admin experience
- token auth for decoupled/mobile/API clients
- Laravel Sanctum for v1 token/session hybrid implementation
- JWT or OAuth 2.0-compatible gateway path reserved for expanded external clients

Reason:
- the platform currently has server-rendered Laravel pages and admin UI
- Sanctum covers SPA/mobile token use with less operational overhead than a full OAuth server
- architecture remains compatible with future OAuth 2.0 evolution

Clause trace:
- `11.a`, `11.d`

## 2.2 Auth Modes By Client

### First-Party Web
- cookie/session auth
- CSRF protection
- server-rendered Blade pages and admin shell

### First-Party SPA / Mobile
- bearer token auth via Sanctum personal access tokens or SPA auth mode

### Webhooks / System Integrations
- signed requests
- IP validation where supported
- idempotency controls

Clause trace:
- `8.a`, `11.a`, `11.d`

## 2.3 Account Types

User identities exist once in `users`, with role-driven access:
- registered_user
- writer
- business_owner
- sales_staff
- content_manager
- support
- super_admin

Clause trace:
- `2.a`, `7.a`, `9.f`

## 3. Authorization Strategy

## 3.1 Authorization Layers

Use three layers:
- route middleware
- policy-based resource authorization
- domain rule services for package/entitlement eligibility

Examples:
- `CanManageBusiness`
- `CanModerateArticle`
- `CanApproveCreative`
- `CanViewFinance`
- `CanRequestPayout`

Clause trace:
- `3.a`, `4.a`, `9.f`, `11.d`

## 3.2 Role Boundary Summary

### Public / Unauthenticated
- read public content endpoints only

### Registered User
- manage own profile
- submit/manage own classifieds
- view own orders, invoices, subscriptions

### Writer
- submit articles
- view own article statuses
- view own word ledger and payout history if exposed

### Business Owner
- manage own business listings
- manage own events
- manage own campaigns
- access own invoices/subscriptions

### Sales Staff
- manage own leads and staff-assisted business creation
- view own staff wallet and payout requests

### Content Manager
- moderate content
- manage writers and article workflows

### Support
- inspect customer records
- inspect payments and invoices
- limited assistance tools

### Super Admin
- unrestricted platform access

Clause trace:
- `2.a`, `3.c`, `3.d`, `7.a`, `9.b`, `9.f`

## 4. API Segments

## 4.1 Public Content API

Base:
- `/api/v1/public`

Purpose:
- expose SEO/public content and discovery data

Suggested endpoints:
- `GET /api/v1/public/home`
- `GET /api/v1/public/articles`
- `GET /api/v1/public/articles/{slug}`
- `GET /api/v1/public/directory`
- `GET /api/v1/public/directory/{slug}`
- `GET /api/v1/public/events`
- `GET /api/v1/public/events/{slug}`
- `GET /api/v1/public/classifieds`
- `GET /api/v1/public/classifieds/{slug}`
- `GET /api/v1/public/search`
- `GET /api/v1/public/taxonomies/*`
- `GET /api/v1/public/locations`

Notes:
- include location-aware filtering
- include ad-slot exposure metadata only where public delivery requires it

Clause trace:
- `2.b`, `3.f`, `4.d`, `7.b`, `10.a`

## 4.2 Auth API

Base:
- `/api/v1/auth`

Suggested endpoints:
- `POST /login`
- `POST /logout`
- `POST /register`
- `POST /forgot-password`
- `POST /reset-password`
- `GET /me`
- `POST /token`
- `DELETE /token/{id}`

Notes:
- registration may branch by future onboarding mode, but should not create unapproved elevated roles directly

Clause trace:
- `2.a`, `7.a`, `9.f`, `11.d`

## 4.3 Account API

Base:
- `/api/v1/account`

Suggested endpoints:
- `GET /profile`
- `PATCH /profile`
- `GET /subscriptions`
- `GET /orders`
- `GET /orders/{id}`
- `GET /invoices`
- `GET /invoices/{id}`
- `GET /notifications`

Clause trace:
- `8.c`, `9.e`, `10.d`

## 4.4 Writer API

Base:
- `/api/v1/writer`

Suggested endpoints:
- `GET /articles`
- `POST /articles`
- `GET /articles/{id}`
- `PATCH /articles/{id}`
- `GET /ledger`
- `GET /payment-batches`

Rules:
- writers manage only their own submissions
- publishing approval remains admin/content-managed

Clause trace:
- `1.a`, `2.a`, `9.b`

## 4.5 Business Owner API

Base:
- `/api/v1/owner`

Suggested endpoints:
- `GET /businesses`
- `POST /businesses`
- `GET /businesses/{id}`
- `PATCH /businesses/{id}`
- `GET /businesses/{id}/subscription`
- `GET /events`
- `POST /events`
- `PATCH /events/{id}`
- `GET /campaigns`
- `POST /campaigns`
- `GET /push-campaigns`
- `POST /push-campaigns`

Rules:
- self-service access only for owned/assigned entities
- event/campaign creation blocked if no active business entitlement

Clause trace:
- `3.c`, `4.a`, `5.c`, `6.a`

## 4.6 Sales Staff API

Base:
- `/api/v1/staff`

Suggested endpoints:
- `GET /leads`
- `POST /leads`
- `PATCH /leads/{id}`
- `POST /businesses`
- `PATCH /businesses/{id}`
- `GET /wallet`
- `GET /wallet/ledger`
- `POST /payout-requests`
- `GET /payout-requests`

Rules:
- scoped to own attributed leads, businesses, wallet, and payout requests

Clause trace:
- `3.d`, `9.f`

## 4.7 Classifieds API

Base:
- `/api/v1/classifieds`

Suggested endpoints:
- `GET /mine`
- `POST /`
- `GET /{id}`
- `PATCH /{id}`
- `DELETE /{id}`
- `POST /{id}/images`
- `DELETE /{id}/images/{imageId}`

Rules:
- free listings
- user scope only
- publication subject to moderation

Clause trace:
- `7.a`, `7.b`, `7.c`

## 4.8 Admin API

Base:
- `/api/v1/admin`

Primary subdomains:
- `/settings`
- `/roles`
- `/users`
- `/writers`
- `/articles`
- `/businesses`
- `/events`
- `/classifieds`
- `/orders`
- `/payments`
- `/invoices`
- `/refunds`
- `/wallets`
- `/payout-requests`
- `/ad-slots`
- `/ad-packages`
- `/campaigns`
- `/push-campaigns`
- `/audit-logs`
- `/kpis`

Clause trace:
- `9.a` to `9.f`, `11.d`, `11.e`

## 4.9 Finance API

Base:
- `/api/v1/admin/finance`

Suggested endpoints:
- `GET /dashboard`
- `GET /orders`
- `GET /payments`
- `GET /invoices`
- `POST /refunds`
- `POST /overrides`
- `POST /extensions`
- `GET /exports`

Clause trace:
- `8.c`, `8.d`, `9.e`

## 5. Webhook Strategy

## 5.1 PayFast Webhooks

Endpoint example:
- `POST /api/v1/webhooks/payfast/itn`

Requirements:
- signature verification
- request source validation
- idempotency key or transaction deduplication
- minimal response time
- enqueue downstream processing where appropriate

Clause trace:
- `8.a`, `8.c`, `11.d`

## 5.2 Internal Event Hooks

Internal domain events should be emitted for:
- payment succeeded
- payment failed
- invoice issued
- subscription activated
- subscription expired
- staff commission credited
- payout paid
- article approved
- campaign approved

Reason:
- keeps modules decoupled while preserving a clean operational audit trail

Clause trace:
- `2.a`, `3.d`, `8.c`, `9.d`, `11.e`

## 5.3 Accounting Sync Endpoints

If external accounting sync is implemented:
- outbound sync jobs preferred
- inbound callback endpoints must be signed and restricted

Clause trace:
- `9.e`, `11.d`

## 6. Authorization Scopes

Recommended token scopes:
- `public.read`
- `account.read`
- `account.write`
- `writer.read`
- `writer.write`
- `business.read`
- `business.write`
- `staff.read`
- `staff.write`
- `classifieds.write`
- `finance.read`
- `finance.write`
- `admin.read`
- `admin.write`
- `audit.read`

Notes:
- first-party web sessions may not need explicit scopes
- token clients should use both scopes and policies

Clause trace:
- `9.f`, `11.a`, `11.d`

## 7. Rate Limiting Strategy

## 7.1 Public API Limits

Apply stricter limits to:
- public search
- public listing/archive APIs
- content detail endpoints if abused

Suggested model:
- IP-based plus optional device/session fingerprinting

Clause trace:
- `11.d`

## 7.2 Auth Limits

Apply aggressive limits to:
- login
- password reset
- token creation

Clause trace:
- `11.d`

## 7.3 Checkout / Finance Limits

Apply sensitive limits and fraud controls to:
- checkout init
- payment retry
- payout request creation
- refund endpoints

Clause trace:
- `8.c`, `8.d`, `11.d`

## 7.4 Admin Limits

Admin endpoints should use:
- authenticated identity-based throttles
- action-sensitive limits for export, resend, refund, and override endpoints

Clause trace:
- `9.e`, `11.d`

## 8. Data Serialization Strategy

## 8.1 Public Response Shape

Public APIs should expose:
- stable slugs
- pagination metadata
- normalized filter metadata
- location context where relevant
- media URLs in optimized formats

Clause trace:
- `2.b`, `3.f`, `4.d`, `10.b`

## 8.2 Admin Response Shape

Admin APIs should expose:
- richer operational status values
- notes/history blocks
- audit metadata
- linked financial objects
- export/sync state

Clause trace:
- `9.a` to `9.f`, `11.d`

## 8.3 Error Format

Use a consistent machine-readable error envelope:
- `code`
- `message`
- `details`
- `trace_id`

Benefits:
- easier support workflows
- better logging and client handling

Clause trace:
- `11.d`, `11.e`

## 9. Security Controls

## 9.1 API Security Baseline

All API traffic should enforce:
- HTTPS only
- CSRF on session-backed routes
- token hashing/storage best practice
- request validation at boundary
- permission checks at controller and policy layers
- audit logs for sensitive actions

Clause trace:
- `11.d`

## 9.2 Sensitive Endpoint Controls

Extra controls required for:
- payment webhooks
- refunds
- manual overrides
- package extensions
- payout approval and payout mark-paid
- role assignment

Recommended controls:
- maker-checker where policy requires
- reason fields
- audit entries
- optional step-up confirmation for highest-risk actions

Clause trace:
- `8.d`, `9.e`, `9.f`, `11.d`

## 9.3 Privacy And Compliance

The API must support:
- least-privilege data exposure
- data export/delete workflows where legally required
- redaction of sensitive payment/provider payloads
- encrypted data at rest for sensitive fields

Clause trace:
- `11.d`

## 10. Recommended Client Boundaries

## 10.1 Blade/Web App

Use:
- session auth
- server-rendered pages for initial rebuild
- API calls only where needed for async admin UX

## 10.2 Mobile App Or SPA

Use:
- `/api/v1/*`
- token-based auth
- same domain rules and scopes

## 10.3 External Integrations

Use:
- dedicated signed endpoints
- narrow scopes
- explicit allowlist and audit trail

Clause trace:
- `11.a`, `11.d`

## 11. Suggested Endpoint Build Order

### Phase 1
- auth
- public content/directory/events/search
- account orders/invoices
- checkout init
- PayFast webhook

Clause trace:
- `2.b`, `3.f`, `4.d`, `8.a` to `8.d`

### Phase 2
- writer endpoints
- business owner self-service endpoints
- staff wallet/payout endpoints
- classifieds endpoints

Clause trace:
- `2.a`, `3.c`, `3.d`, `7.a` to `7.c`

### Phase 3
- admin finance
- ad campaign
- push campaign
- KPI and audit APIs

Clause trace:
- `5.a` to `5.d`, `6.a`, `6.b`, `9.e`, `11.e`

## 12. Open Design Decisions

- Whether to adopt Sanctum only for v1 or implement Passport/OAuth earlier
- Whether GraphQL is needed for admin dashboards in v1
- Whether mobile clients ship in the first release or later
- Whether support users may perform write operations on finance objects or remain mostly read-only
- Whether maker-checker approval is required for refunds, role changes, and payout completion

These decisions should be resolved before full API implementation begins.
