# Implementation Status And Next Steps

Primary sources:
- `platform-specification-source-of-truth.md`
- `clause-traceable-rebuild-matrix.md`
- `public-page-by-page-rebuild-spec.md`
- `implementation-roadmap-and-sprint-plan.md`

This document supplements the original planning set by recording what has already been implemented in the Laravel codebase and what should be tackled next.

It does not replace the original planning documents. Those remain the source of truth for scope, clause traceability, and phase ordering. This file exists to keep execution progress visible inside the `Planning` folder.

## 1. Current Status Summary

### 1.1 Planning Coverage

The planning folder already covers:
- public-page scope and inventory
- clause-traceable rebuild decisions
- architecture and schema direction
- implementation roadmap and phase order

The main gap that this file closes:
- a practical implementation-status view of what is done now
- a practical list of what should be built next

### 1.2 Delivery Status Snapshot

Implemented and working in the Laravel application:
- Phase 0 foundations: roles/permissions support, editable settings, audit logging
- Phase 1 writer workflow foundations and writer payment foundations
- Phase 2 and 3 commercial foundations for packages, checkout, invoices, payments, renewals, finance operations, notification logging, reminders, and finance drill-downs
- public homepage, business directory, events, articles, search, and classifieds discovery pages
- public writer/staff signup flow with admin review, approval onboarding, access email, resend, cooldown, contact-state filters, and queue summary
- advertiser acquisition funnel (advertise-with-us, add-listing start flow)
- legal pages (terms and conditions, privacy policy), about page, and custom 404 page
- customer support lookup and internal notes
- classifieds submission and admin moderation
- admin ad campaign and push campaign management (list, detail, approve, pause, resume, manual dispatch, delivery history)

Still outstanding:
- ad campaign impression/click tracking and serving analytics (E4.3)
- push open-metric logging (E4.4 / E4.5)
- Phase 6: test coverage expansion, monitoring and alerting, load testing, security hardening, pen-test, and documentation

## 2. Implemented Public Pages

### 2.1 Completed Public Front-End Pages

The following public pages have been rebuilt in Laravel:
- homepage
- business directory archive
- business directory detail
- events archive
- event detail
- writer/staff signup page
- writer/staff signup confirmation page
- article detail page
- article archive pages by category, tag, location, and author
- unified public search results page
- advertise-with-us page
- add-listing / advertiser acquisition funnel start
- login/account/public self-service entry refinement
- legal pages (terms and conditions, privacy policy)
- classifieds archive and detail pages

Clause trace:
- homepage: `1.a`, `2.b`, `3.f`, `4.d`, `5.a`, `5.b`, `10.a`, `10.c`
- directory archive/detail: `3.a`, `3.e`, `3.f`, `5.b`
- events archive/detail: `4.a`, `4.c`, `4.d`
- writer/staff signup: `1.a`, `2.a`, `3.d`, `9.b`, `9.f`
- article/search/author/legal: `1.a`, `2.b`, `3.a`, `10.a`, `10.c`
- advertise/acquisition: `3.b`, `3.c`, `3.f`
- classifieds: `6.a`

### 2.2 Public Front-End Pages Added Since Last Check

Additional public pages now implemented:
- about page (`/about`) — platform description, live stats, three audience CTAs, contact entry point
- custom 404 error page — friendly not-found page with nav links back to key discovery surfaces

Later-phase public work still outstanding:
- services decision path
- vouchers and any retained non-core public commerce flows

## 3. Implemented Admin And Workflow Slices

### 3.1 Writer And Editorial Foundations

Implemented:
- writer role foundations
- article writer workflow support
- article revision and moderation support
- word-count ledger support
- writer payment batch foundations and reporting support

Clause trace:
- `1.a`, `2.a`, `2.b`, `9.b`

### 3.2 Directory, Checkout, Finance, And Renewal Foundations

Implemented:
- packages, orders, payments, invoices, subscriptions, and entitlement foundations
- checkout flow and PayFast-style payment foundations
- invoice sending and notification logging
- renewal reminders and renewal-order automation
- finance exports, reconciliation, refund tracking, and finance drill-down pages
- finance audit timelines, notification detail, resend, resend protection, failed notification visibility, and pagination/filtering

Clause trace:
- `3.a`, `3.b`, `3.c`, `3.e`, `3.f`
- `4.a`, `4.b`, `4.c`, `4.d`
- `8.a`, `8.b`, `8.c`, `8.d`
- `9.c`, `9.e`

### 3.3 Writer And Staff Application Flow

Implemented:
- public `/staff-signup` application form
- `WriterApplication` data model and migrations
- username support on users
- file uploads for profile and compliance documents
- admin queue and detail pages for writer applications
- approval decisions with notes and audit logging
- approval onboarding into real platform accounts
- safe role assignment for approved writer or staff applicants
- approval email with password setup/reset link
- manual resend of access email
- resend cooldown protection
- access email history on the application detail page
- queue-level access summary showing last-send and cooldown state

Clause trace:
- `1.a`, `2.a`, `3.d`, `9.b`, `9.f`

### 3.4 Owner Monetisation Self-Service And Push Delivery Baseline

Implemented:
- owner-facing listing workspace for editing, gallery management, review responses, events, advert campaigns, and push campaigns
- listing-scoped event, advert, and push package purchase handoff through checkout
- entitlement activation for business, event, advert, and push packages
- push campaign delivery baseline with manual owner dispatch, scheduled due-campaign dispatch, push-channel notification logs, and owner delivery history
- admin visibility for push delivery logs through the existing notification reporting screens

Clause trace:
- `3.a`, `3.b`, `3.c`, `3.f`
- `4.a`, `4.b`, `4.c`, `4.d`
- `5.a`, `5.b`, `5.c`
- `6.a`, `6.b`
- `8.a`, `8.b`, `8.c`
- `9.c`, `9.d`, `9.e`

### 3.5 Content Discovery, Search, And Acquisition

Implemented:
- unified search interface merging listings, events, articles, and classifieds
- article archive routing and layout including authors, categories, tags, and locations
- advertise-with-us public entry point
- add-listing funnel creating draft listings and handing off to checkout

Clause trace:
- `1.a`, `2.b`, `3.a`, `3.b`, `3.c`, `3.f`, `10.a`

### 3.6 Support And Classifieds Groundwork

Implemented:
- global customer lookup tool for admin/support staff
- comprehensive customer timeline showing orders, payments, subscriptions, content, and application history
- support internal notes logging
- classifieds submission flow for registered users
- classifieds admin moderation queue and review tool

Clause trace:
- `6.a`, `7.a`, `7.b`, `7.c`

### 3.8 Ad Campaign And Push Campaign Admin Module

Implemented:
- admin ad campaign list with status and keyword filters
- ad campaign detail view with full creative and scheduling information
- admin approve (ready → active), pause (active → paused), and resume (paused → active) actions with audit logging
- admin push campaign list with status, sent/unsent, and keyword filters
- push campaign detail view with full message and audience details
- admin manual dispatch action for active/scheduled push campaigns with audit logging
- push delivery history table on each campaign detail page linking back to individual notification logs
- admin dashboard updated with ad campaigns awaiting approval and active ad campaign counts
- admin dashboard header updated with Ad Campaigns and Push Campaigns quick-links

Clause trace:
- `4.a`, `5.a`, `5.b`, `5.c`, `8.a`, `9.d`

### 3.7 Writer Application Queue Contact Filters

Implemented:
- `contact` filter parameter on the writer applications queue index (`needs_contact`, `recently_contacted`)
- `needs_contact` filter surfaces approved applications that have never received an access email
- `recently_contacted` filter surfaces applications where the access email was sent within the last 7 days
- contact-state counts displayed in both the filter dropdown and as clickable quick-filter pills on the queue
- per-row Contact State badge column: amber for "Needs access email", green for "Recently contacted", grey for older contacts

Clause trace:
- `9.b`, `9.f`

### 3.8 What Still Remains In This Workflow

Remaining optional refinements for the writer/staff application flow:
- possible applicant-facing status updates if the business wants them
- optional dedicated invite/password-set copy refinement
- possible reporting or export views for application conversion

## 4. Suggested Immediate Next Work

### 4.1 Recommended Next Slice

The three most actionable next targets in priority order:

**Option A — Staff wallet and payout flows (E3.2)**
The biggest remaining functional gap with direct revenue impact. Staff-assisted sales exist in concept but commission accrual, wallet balances, and payout requests are not yet implemented.
- staff commission ledger on staff-assisted paid sales
- wallet balance view for staff users
- payout request submission by staff
- admin payout approval and marking paid
- audit logging throughout
Clause trace: `3.d`, `8.d`, `9.c`, `9.e`

**Option B — Ad campaign serving analytics (E4.3 / E4.5)**
Closes the remaining Phase 4 gap: impression/click counters on served ad campaigns, and push open-metric logging.
- increment impression and click counters when an ad is served or clicked
- push open-tracking endpoint (pixel or redirect)
- admin campaign performance summary (impressions, clicks, open rate)
Clause trace: `5.c`, `5.d`, `6.b`, `9.d`, `11.e`

**Option C — Phase 6 test coverage and hardening (E6.1)**
Expand the automated test suite with webhook integration tests and E2E flows for the revenue-critical paths, before moving into load testing and security review.
- PayFast webhook idempotency integration tests
- checkout E2E (package selection → payment → entitlement activation)
- subscription expiry and renewal E2E
- role-boundary and permission-denial security tests
Clause trace: `8.a`, `8.c`, `11.b`, `11.d`

### 4.2 Recommended Order

Recommended sequence:
1. **Option A** (staff wallet) — highest business value, last major unfinished functional area
2. **Option B** (ad serving analytics) — closes Phase 4 fully, required before load testing ad surfaces
3. **Option C** (Phase 6 hardening) — runs alongside and after A and B as the platform approaches release

## 5. Remaining Major Planned Areas

The following areas are still in the planning set and not yet fully delivered:

**Phase 3 outstanding:**
- E3.2: staff wallet and payout flows — **COMPLETE** (`3.d`)

**Phase 4 outstanding:**
- E4.3 / E4.5: ad impression/click tracking and push open metrics — **COMPLETE** (`5.c`, `5.d`, `6.b`, `9.d`)

**Phase 5 outstanding:**
- retained non-core flows: services decision path and vouchers (deferred decision)

**Phase 6 outstanding:**
- E6.1: test coverage expansion (E2E, webhook integration, security boundary tests)
- E6.2: load and performance validation (1,000 concurrent users, p95 budgets)
- E6.3: monitoring and alerting stack (uptime, queue health, KPI dashboards)
- E6.4: security hardening (DAST, SAST, dependency scans, pen-test)
- E6.5: documentation and training (admin handbook, runbooks, release checklist, training recordings)

## 6. Documentation Rule Going Forward

When a major implementation slice lands, the `Planning` folder should be updated in one of these ways:
- update this status document with completed work and next steps
- update the relevant planning document if the implementation changes the intended scope or sequence

Minimum update triggers:
- new public page or full public flow goes live
- new admin module or workflow becomes operational
- roadmap priority changes
- a previously planned gap is closed

## 7. Current Bottom Line

The planning folder now contains:
- original scope and architectural planning
- clause-traceable page/module matrices
- phase roadmap
- this implementation-status document for actual progress tracking

Current overall position:
- the Laravel rebuild has moved well beyond planning in the writer, finance, directory, events, classifieds, support, and staff-signup areas
- the public content, search, and acquisition surfaces are now implemented and live
- the next major value comes from finishing the remaining minor gaps in phase 5 and pushing into phase 6 (hardening, analytics, and release readiness)
