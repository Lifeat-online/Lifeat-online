# Admin Module Architecture

Primary sources:
- `platform-specification-source-of-truth.md`
- `clause-traceable-rebuild-matrix.md`
- `database-schema-and-entity-relationship-plan.md`
- `billing-package-invoice-and-payout-architecture.md`

This document defines the operational backend architecture for the Laravel platform.

Goals:
- turn clause requirements into concrete admin modules
- define role boundaries
- specify primary screens and actions
- identify module dependencies
- prepare implementation sequencing

## 1. Role Model

Primary admin-facing roles:
- `super_admin`
- `content_manager`
- `sales_staff`
- `support`

Extended operational roles used in the broader platform:
- `writer`
- `business_owner`

Clause trace:
- `9.f`

## 2. Admin Shell

## 2.1 Core Admin Layout

The admin shell should provide:
- persistent sidebar navigation
- role-based menu visibility
- global search
- alert center
- current environment indicator
- theme toggle
- quick actions

Clause trace:
- `9.f`, `10.a`, `10.c`

## 2.2 Cross-Cutting Admin Features

Every admin module should support where applicable:
- filters
- pagination
- bulk actions
- export
- audit visibility
- notes/history timeline
- status badges

Clause trace:
- `9.b`, `9.c`, `9.d`, `9.e`, `11.d`

## 3. Admin Module Map

### Group A: Global Platform Control
- Dashboard
- Settings
- Roles & Permissions
- Audit Logs
- KPI / Monitoring

### Group B: Content Operations
- Articles
- Writers
- Word Ledger
- Payment Batches

### Group C: Sales And Directory Operations
- Businesses
- Business Packages
- Leads / Assisted Sales
- Events

### Group D: Advertising Operations
- Ad Inventory
- Ad Packages
- Campaigns
- Creatives
- Push Campaigns

### Group E: Commerce And Finance
- Orders
- Payments
- Invoices
- Refunds / Overrides
- Renewals

### Group F: Staff Earnings
- Staff Wallets
- Payout Requests
- Payout History

### Group G: Community Moderation
- Classifieds
- Flags
- Moderation Queue

### Group H: Support Tools
- Customer Lookup
- Account Adjustments
- Issue Notes / Timeline

## 4. Module Specifications

## 4.1 Dashboard Module

### Purpose
- give high-level operational visibility across content, revenue, campaigns, and issues

### Main Screens
- executive dashboard
- operational alerts
- expiring packages
- failed payments
- pending moderation

### Widgets
- daily revenue
- active directory packages
- active events
- ad campaign count
- push campaign count
- unpaid writer ledger amount
- pending payout requests
- failed payments

### Roles
- super_admin: full
- content_manager: content-oriented subset
- sales_staff: sales-oriented subset
- support: support-oriented subset

Clause trace:
- `9.b`, `9.c`, `9.d`, `9.e`, `11.e`

## 4.2 Settings Module

### Purpose
- central place for editable business rules

### Main Screens
- pricing settings
- billing settings
- geo defaults
- writer rate settings
- advert pricing
- push pricing

### Key Editable Values
- writer per-word rate
- directory standard price
- directory self-service price
- staff-assisted directory price
- event one-off price
- event monthly price
- advert package rates
- push fee
- default/fallback geo radius
- VAT percentage
- invoice prefix

### Roles
- super_admin only by default

Clause trace:
- `3.b`, `4.b`, `5.d`, `6.b`, `9.a`

## 4.3 Roles And Permissions Module

### Purpose
- enforce role-based access and permission boundaries

### Main Screens
- role list
- permission matrix
- user access assignment

### Roles
- super_admin only

Clause trace:
- `9.f`, `11.d`

## 4.4 Article Management Module

### Purpose
- review, edit, approve, publish, and archive content

### Main Screens
- article list
- article moderation queue
- article detail/editorial review page
- revisions history

### Key Actions
- approve
- reject
- request revision
- publish
- archive
- assign editor

### Roles
- super_admin
- content_manager
- support: read-only if needed

Clause trace:
- `2.a`, `2.b`, `9.b`, `9.f`

## 4.5 Writer Management Module

### Purpose
- manage writers and writer payouts

### Main Screens
- writer list
- writer profile
- unpaid word ledger
- payment batch export

### Key Actions
- activate/deactivate writer
- adjust per-word rate
- review article throughput
- create payment batch
- export batch
- mark batch paid

### Roles
- super_admin
- content_manager

Clause trace:
- `1.a`, `2.a`, `9.b`

## 4.6 Business Management Module

### Purpose
- manage all business listings, package status, and listing lifecycle

### Main Screens
- business list
- business detail
- listing editor
- package status panel
- renewal reminders panel

### Key Actions
- create/edit listing
- assign owner
- assign sales staff
- activate/suspend/expire listing
- override coordinates
- extend package
- trigger renewal reminder

### Roles
- super_admin
- sales_staff
- support
- content_manager: limited access if required

Clause trace:
- `3.a`, `3.c`, `3.d`, `3.e`, `3.f`, `9.c`

## 4.7 Leads And Assisted Sales Module

### Purpose
- support staff-assisted acquisition workflow

### Main Screens
- lead inbox
- assisted sales form
- conversion pipeline
- channel attribution view

### Key Actions
- create lead
- capture via phone/WhatsApp/site visit/form
- convert lead to business listing
- attribute commission source

### Roles
- sales_staff
- super_admin

Clause trace:
- `3.d`, `9.f`

## 4.8 Event Management Module

### Purpose
- manage events and validate business eligibility

### Main Screens
- event list
- event detail
- package eligibility panel
- organiser linkage panel

### Key Actions
- create/edit event
- verify active business entitlement
- activate/suspend event
- extend or cancel event entitlement

### Roles
- super_admin
- sales_staff
- support

Clause trace:
- `4.a`, `4.b`, `4.c`, `4.d`, `9.c`

## 4.9 Ad Inventory And Package Module

### Purpose
- define sellable ad slots and pricing

### Main Screens
- inventory slot list
- slot detail
- package pricing editor

### Key Actions
- create/edit slot
- define slot dimensions and scope
- define pricing and impression rules

### Roles
- super_admin

Clause trace:
- `5.a`, `5.b`, `5.c`, `5.d`

## 4.10 Ad Campaign Module

### Purpose
- operate purchased ad campaigns

### Main Screens
- campaign list
- campaign detail
- creative approval queue
- performance view

### Key Actions
- approve creative
- pause/resume campaign
- view impressions/clicks
- override schedule

### Roles
- super_admin
- support: limited view

Clause trace:
- `5.c`, `9.d`, `11.e`

## 4.11 Push Campaign Module

### Purpose
- operate premium push promotions

### Main Screens
- push campaign list
- composer
- targeting screen
- schedule view
- opens analytics

### Key Actions
- compose
- schedule
- target by radius
- target by interest tags
- cancel
- review open rates

### Roles
- super_admin

Clause trace:
- `6.a`, `6.b`

## 4.12 Classified Moderation Module

### Purpose
- keep free classifieds safe and manageable

### Main Screens
- moderation queue
- flags view
- classified detail moderation panel

### Key Actions
- approve
- hide
- reject
- mark flagged
- clear flag

### Roles
- super_admin
- support
- content_manager if desired

Clause trace:
- `7.a`, `7.b`, `7.c`

## 4.13 Orders Module

### Purpose
- central commercial order management

### Main Screens
- order list
- order detail
- line item panel
- package lifecycle panel

### Key Actions
- inspect order status
- resend checkout link if needed
- view linked payments and invoices

### Roles
- super_admin
- support
- sales_staff: limited visibility on own/attributed sales if needed

Clause trace:
- `8.a`, `8.b`, `8.c`, `9.e`

## 4.14 Payments Module

### Purpose
- inspect payment lifecycle and failures

### Main Screens
- payment list
- failed payments
- payment detail
- webhook/attempt history

### Key Actions
- review failures
- retry workflow support
- mark manual reconciliation states where policy allows

### Roles
- super_admin
- support

Clause trace:
- `8.a`, `8.c`, `9.e`

## 4.15 Invoices Module

### Purpose
- manage customer billing records

### Main Screens
- invoice list
- invoice detail
- export center
- accounting sync queue

### Key Actions
- resend invoice email
- export CSV/XLS
- trigger accounting sync

### Roles
- super_admin
- support

Clause trace:
- `8.c`, `9.e`

## 4.16 Refunds, Overrides, And Extensions Module

### Purpose
- control finance exceptions with audit safety

### Main Screens
- refund list
- manual adjustments list
- package extension log

### Key Actions
- issue refund
- extend package
- override status
- suspend or reactivate entitlement

### Roles
- super_admin only by default

Clause trace:
- `8.d`, `9.e`, `11.d`

## 4.17 Staff Wallet Module

### Purpose
- track sales commission and payout readiness

### Main Screens
- wallet overview list
- wallet detail
- ledger entries
- attributable sales list

### Key Actions
- inspect pending vs available balance
- review sales attribution
- review commission math

### Roles
- super_admin
- sales_staff: own wallet only

Clause trace:
- `3.d`, `9.e`, `9.f`

## 4.18 Payout Requests Module

### Purpose
- process staff payout lifecycle

### Main Screens
- payout request queue
- payout detail
- paid history

### Key Actions
- approve
- reject
- mark paid
- attach notes/reference numbers

### Roles
- super_admin
- sales_staff: request and view own items

Clause trace:
- `3.d`, `9.e`, `9.f`

## 4.19 Audit Log Module

### Purpose
- support compliance, investigations, and traceability

### Main Screens
- audit list
- subject timeline
- actor timeline

### Key Actions
- filter by actor/action/entity/date

### Roles
- super_admin
- restricted support access if policy allows

Clause trace:
- `11.d`

## 4.20 KPI And Monitoring Module

### Purpose
- expose operational and business metrics

### Main Screens
- KPI dashboard
- payments/revenue trends
- failed payment trends
- uptime/error summaries
- campaign performance summaries

### Roles
- super_admin

Clause trace:
- `11.e`, `9.d`, `9.e`

## 5. Role Access Summary

| Module | Super-Admin | Content-Manager | Sales-Staff | Support |
|---|---|---|---|---|
| Dashboard | Full | Partial | Partial | Partial |
| Settings | Full | None | None | None |
| Roles & Permissions | Full | None | None | None |
| Articles | Full | Full | None | Read-only optional |
| Writers | Full | Full | None | None |
| Businesses | Full | Limited optional | Full/owned scope | Support scope |
| Leads / Assisted Sales | Full | None | Full | None |
| Events | Full | None | Full/owned scope | Support scope |
| Ad Inventory | Full | None | None | None |
| Ad Campaigns | Full | None | None | Read-only/support scope |
| Push Campaigns | Full | None | None | None |
| Classified Moderation | Full | Optional | None | Full moderation scope |
| Orders | Full | None | Limited optional | Full support scope |
| Payments | Full | None | None | Full support scope |
| Invoices | Full | None | None | Full support scope |
| Refunds / Overrides | Full | None | None | None |
| Staff Wallet | Full | None | Own wallet | None |
| Payout Requests | Full | None | Own requests | None |
| Audit Logs | Full | None | None | Restricted optional |
| KPI / Monitoring | Full | None | None | None |

Clause trace:
- `9.f`

## 6. Key Inter-Module Dependencies

- Settings drives package pricing, VAT, invoice prefix, geo defaults, and writer rates.
- Businesses depends on Orders, Payments, Subscriptions, and Entitlements.
- Events depends on Businesses and active business entitlement.
- Ad Campaigns depends on Businesses, Packages, Payments, Creative Approval, and Geo targeting.
- Push Campaigns depends on Businesses/Events, Packages, and targeting logic.
- Writer Management depends on Articles and Word Ledger.
- Staff Wallet depends on paid staff-assisted sales from Orders/Payments.
- Payout Requests depends on Staff Wallet balances and admin actions.
- Refunds/Overrides affect Orders, Payments, Invoices, and Entitlements.

Clause trace:
- `2.a`, `3.a` to `3.f`, `4.a` to `4.d`, `5.c`, `6.b`, `8.a` to `8.d`, `9.a` to `9.f`

## 7. Navigation Recommendation

Recommended sidebar sections:
- Overview
- Content
- Directory
- Events
- Advertisements
- Classifieds
- Finance
- Staff Earnings
- Settings
- Security & Audit

Role-based hiding:
- only show relevant sections per role

Clause trace:
- `9.f`, `10.a`

## 8. Build Order Recommendation

### Phase 1
- Settings
- Articles
- Writers
- Businesses
- Events
- Orders
- Payments
- Invoices

Reason:
- supports core publishing and revenue engine first

Clause trace:
- `1.a`, `2.a`, `3.a` to `3.f`, `4.a` to `4.d`, `8.a` to `8.d`

### Phase 2
- Staff Wallet
- Payout Requests
- Classified Moderation
- Refunds / Overrides

Clause trace:
- `3.d`, `7.c`, `8.d`, `9.e`

### Phase 3
- Ad Inventory
- Ad Campaigns
- Push Campaigns
- KPI / Monitoring
- Audit expansion

Clause trace:
- `5.a` to `5.d`, `6.a`, `6.b`, `11.d`, `11.e`

## 9. Open Design Decisions

- Whether sales staff should see only their own businesses/leads or also team-level views
- Whether support can initiate package extensions or only recommend them
- Whether content managers can moderate classifieds or that remains support-only
- Whether advert approvals require one-step approval or maker-checker review
- Whether payout approval and payout marking must be separated for internal controls

These should be resolved before final admin policy and authorization implementation.
