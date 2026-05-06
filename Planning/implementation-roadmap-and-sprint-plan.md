# Implementation Roadmap And Sprint Plan

Primary sources:
- `platform-specification-source-of-truth.md`
- `clause-traceable-rebuild-matrix.md`
- `database-schema-and-entity-relationship-plan.md`
- `billing-package-invoice-and-payout-architecture.md`
- `admin-module-architecture.md`
- `api-surface-and-auth-strategy.md`
- `test-deployment-monitoring-and-compliance-plan.md`

This document converts the completed planning set into an executable delivery roadmap.

Goals:
- define phases and milestone gates
- group work into epics and delivery batches
- map each batch back to business clauses
- identify prerequisites, dependencies, and acceptance checkpoints
- provide a practical order for implementation

## 1. Delivery Principles

### 1.1 Build Around Revenue-Critical Loops First

The earliest implementation phases must prioritize:
- writer content production
- business directory purchase and publication
- event upsell gating
- invoicing and payment reliability
- staff commission and payout readiness

Clause trace:
- `1.a`, `2.a`, `3.a` to `3.f`, `4.a` to `4.d`, `8.a` to `8.d`

### 1.2 Prefer Vertical Slices Over Horizontal Isolation

Each phase should produce usable end-to-end outcomes, not only isolated infrastructure.

Example:
- “business purchase -> payment -> activation -> listing visible” is better than building pricing, payments, and directory UI in isolation.

Clause trace:
- `12.a`

### 1.3 Maintain Clause Traceability Throughout

Every epic, story, migration batch, and acceptance gate should reference the governing clauses.

Clause trace:
- Planning traceability rule in source-of-truth document

## 2. Roadmap Structure

Recommended structure:
- Phase 0: Foundations and migration control
- Phase 1: Content and publishing core
- Phase 2: Business directory revenue engine
- Phase 3: Event add-on, staff wallet, and finance maturity
- Phase 4: Advert and push monetisation layer
- Phase 5: Classifieds, support tools, and non-core retained flows
- Phase 6: Hardening, performance, compliance, and release readiness

## 2.1 Current Execution Status

Current delivery position in the Laravel codebase:
- Phase 0: largely delivered for roles, settings, audit logging, and scheduled automation baselines
- Phase 1: broadly delivered for writer workflow, editorial moderation, article discovery taxonomy, and writer payment operations
- Phase 2: broadly delivered for business packages, checkout, payments, invoices, entitlements, and public directory flows
- Phase 3: partially delivered with event eligibility, event commerce, renewals, finance recovery tools, support-safe finance visibility, and staff wallet/payout baseline in place, while deeper sales tooling and reporting refinements remain outstanding
- Phase 4: partially delivered with owner advert and push self-service, package handoff, entitlement activation, admin campaign controls, baseline impression/click tracking, push dispatch logging, and baseline push open counter in place, while creative approval policy depth and richer reporting remain outstanding
- Phase 5: partially delivered with classifieds moderation, account/self-service improvements, customer lookup, support notes, and support-role dashboard/access controls in place, while retained non-core public decision work remains outstanding
- Phase 6: still largely outstanding beyond the current automated test coverage baseline

## 3. Phase 0: Foundations And Migration Control

### Objective
- create the platform base required for controlled implementation

### Current Status
- largely delivered baseline

### Epics
- E0.1 Laravel project baseline hardening
- E0.2 role/permission refactor
- E0.3 settings framework
- E0.4 migration discipline and base schema utilities
- E0.5 CI, smoke tests, and environment setup

### Delivery Batches
- permissions model and policies
- settings table and settings service
- shared status enums/value objects where useful
- audit log foundation
- CI skeleton and smoke checks

### Acceptance Gate
- roles are not hard-coded
- settings are admin-editable
- CI runs tests automatically
- audit foundation exists

### Clause Trace
- `9.a`, `9.f`, `11.b`, `11.c`, `11.d`

## 4. Phase 1: Content And Publishing Core

### Objective
- enable paid writing work and public article publishing

### Current Status
- broadly delivered baseline

### Epics
- E1.1 article schema expansion
- E1.2 writer workflow
- E1.3 article moderation tools
- E1.4 public article archive/detail improvements
- E1.5 writer ledger and batch export foundation

### Delivery Batches
- article statuses, categories, tags, location links
- writer role/profile
- content-manager article moderation screens
- public article filters and SEO improvements
- word count ledger generation on approval/publish
- admin writer management and export flow

### Milestone Outcome
- a writer can submit an article
- content-manager can approve/publish it
- article appears publicly
- ledger entry is created for payment

### Acceptance Gate
- article submission and approval tested
- writer ledger entries are accurate and auditable
- article pages satisfy SEO/accessibility baseline

### Clause Trace
- `1.a`, `2.a`, `2.b`, `9.b`, `10.a`, `10.b`

## 5. Phase 2: Business Directory Revenue Engine

### Objective
- implement the core monetisation gateway

### Current Status
- broadly delivered baseline

### Epics
- E2.1 business directory schema completion
- E2.2 package catalogue and pricing
- E2.3 checkout and PayFast foundation
- E2.4 entitlement activation
- E2.5 public directory archive/detail
- E2.6 admin business management

### Delivery Batches
- business data expansion: contacts, hours, socials, media, geo
- package type, package price, order, payment, invoice models
- checkout UI and basket refinement
- PayFast initiation and webhook processing
- subscription and entitlement activation
- directory ranking and location filtering
- admin settings for pricing, VAT, invoice prefix
- admin business CRUD and package status tools

### Milestone Outcome
- a business can buy a directory package
- payment succeeds through PayFast
- invoice is created
- listing becomes active and publicly visible

### Acceptance Gate
- directory-first rule is enforced
- invoices email correctly
- active listing geo-ranking works
- business entitlement controls visibility and upsell eligibility

### Clause Trace
- `3.a`, `3.b`, `3.c`, `3.e`, `3.f`, `8.a`, `8.b`, `8.c`, `9.a`, `9.c`, `9.e`

## 6. Phase 3: Event Add-On, Staff Wallet, And Finance Maturity

### Objective
- implement event monetisation and staff-assisted sales economics

### Current Status
- partially delivered

### Epics
- E3.1 event package flows
- E3.2 staff-assisted business acquisition workflow
- E3.3 staff wallet and payout requests
- E3.4 finance admin maturity
- E3.5 refund, override, and extension controls

### Delivery Batches
- event package definitions and eligibility rules
- event create/publish flow tied to active business entitlement
- staff lead capture and attribution
- commission ledger creation on staff-assisted paid sales
- wallet balances and payout request flow
- finance dashboard: orders, payments, invoices, refunds, overrides
- renewal reminders and expiry alerts

### Milestone Outcome
- active business owners can buy event packages
- staff-assisted sales create commission entries
- staff can request payouts
- admin can process payouts and finance exceptions

### Acceptance Gate
- event publication blocked if business entitlement is invalid
- wallet entries are accurate and auditable
- payout flow is role-protected and logged
- finance overrides preserve invoice/payment history

### Clause Trace
- `3.d`, `4.a`, `4.b`, `4.c`, `4.d`, `8.d`, `9.c`, `9.e`

## 7. Phase 4: Advert And Push Monetisation Layer

### Objective
- implement the multi-tier ad stack and premium push offering

### Current Status
- partially delivered

### Epics
- E4.1 advert inventory model
- E4.2 ad package sales and campaign management
- E4.3 creative approval and analytics
- E4.4 push campaign purchase and delivery
- E4.5 KPI instrumentation for campaign performance

### Delivery Batches
- ad slots and package pricing
- campaign creation and linkage to entitled businesses/events
- creative upload and approval queue
- delivery rules: geo, dates, impression caps
- impression/click tracking
- push campaign composer, targeting, schedule, open logs
- ad and push admin reporting

### Milestone Outcome
- businesses with active directory packages can buy advert or push packages
- creatives are approved and delivered
- analytics are recorded and visible in admin

### Acceptance Gate
- no ad or push purchase bypasses the business-directory-first rule
- campaign delivery respects geo and schedule rules
- push open metrics are recorded

### Clause Trace
- `5.a`, `5.b`, `5.c`, `5.d`, `6.a`, `6.b`, `9.d`, `11.e`

## 8. Phase 5: Classifieds, Support Tools, And Non-Core Flows

### Objective
- complete free user acquisition loop and support/admin completeness

### Current Status
- partially delivered

### Epics
- E5.1 classifieds submission and moderation
- E5.2 support tooling
- E5.3 account/history improvements
- E5.4 evaluate retained services/shop/voucher/transport flows

### Delivery Batches
- classifieds schema completion and image limits
- submission/editing UX
- moderation queue and flags
- support customer lookup and issue notes
- account area for invoices, subscriptions, submissions
- review/decision on retained non-core WordPress flows

### Milestone Outcome
- users can post classifieds for free
- moderators can approve/hide/flag items
- support can inspect customer journeys cleanly

### Acceptance Gate
- classifieds never bypass moderation state rules
- support access is constrained and auditable

### Clause Trace
- `7.a`, `7.b`, `7.c`, `9.f`

## 9. Phase 6: Hardening, Performance, Compliance, Release Readiness

### Objective
- bring the platform to production-ready operational standard

### Current Status
- largely outstanding

### Epics
- E6.1 test coverage expansion
- E6.2 load and performance validation
- E6.3 monitoring and alerting completion
- E6.4 security hardening and pen-test
- E6.5 documentation and training deliverables

### Delivery Batches
- E2E suites for checkout and revenue-critical flows
- webhook integration tests
- load testing for 1,000 concurrent users
- structured logs, alerting, KPI dashboards
- DAST/SAST/dependency scans
- penetration testing and remediation
- admin handbook, API docs, runbooks, recordings

### Milestone Outcome
- system meets operational acceptance criteria

### Acceptance Gate
- blue-green deploy and rollback verified
- monitoring active
- compliance evidence assembled
- training materials complete

### Clause Trace
- `11.b`, `11.c`, `11.d`, `11.e`, `12.b`, `12.c`, `12.d`, `12.e`

## 10. Suggested Epic Backlog

## Epic List

| Epic ID | Epic | Phase | Priority | Current Status | Clause Trace |
|---|---|---|---|---|---|
| E0.1 | Permissions and policy refactor | 0 | P1 | Delivered baseline | `9.f`, `11.d` |
| E0.2 | Settings framework | 0 | P1 | Delivered baseline | `9.a` |
| E0.3 | CI and smoke foundation | 0 | P1 | Partial | `11.b`, `11.c` |
| E1.1 | Writer submission workflow | 1 | P1 | Delivered baseline | `1.a`, `2.a` |
| E1.2 | Article moderation and publishing | 1 | P1 | Delivered baseline | `2.a`, `2.b`, `9.b` |
| E1.3 | Writer ledger and batch export | 1 | P1 | Delivered baseline | `2.a`, `9.b` |
| E2.1 | Business directory schema and public pages | 2 | P1 | Delivered baseline | `3.e`, `3.f` |
| E2.2 | Checkout, PayFast, invoice, entitlement flow | 2 | P1 | Delivered baseline | `8.a` to `8.d` |
| E2.3 | Business pricing and package admin | 2 | P1 | Partial | `3.b`, `3.c`, `9.a` |
| E3.1 | Event package and eligibility system | 3 | P1 | Delivered baseline | `4.a` to `4.d` |
| E3.2 | Staff-assisted sales and wallet | 3 | P1 | Delivered baseline | `3.d` |
| E3.3 | Finance exceptions and renewals | 3 | P1 | Delivered baseline | `8.d`, `9.c`, `9.e` |
| E4.1 | Ad inventory and packages | 4 | P1 | Partial | `5.a` to `5.d` |
| E4.2 | Push notification monetisation | 4 | P1 | Partial | `6.a`, `6.b` |
| E5.1 | Classifieds and moderation | 5 | P2 | Delivered baseline | `7.a` to `7.c` |
| E5.2 | Support workflows and customer lookup | 5 | P2 | Delivered baseline | `9.f` |
| E6.1 | Performance, monitoring, security hardening | 6 | P1 | Partial | `11.b` to `11.e`, `12.c`, `12.d` |
| E6.2 | Documentation and training completion | 6 | P1 | Partial | `12.b`, `12.e` |

## 11. Suggested Sprint Sequence

This sprint sequence remains useful as the original planned delivery order.

Execution reality has now diverged from the original sequence because Phase 4 and Phase 5 baseline work has already been delivered before some Phase 3 and Phase 6 items were completed.

Assumption:
- 2-week sprints
- small-to-medium team
- architecture and product oversight available continuously

## Sprint 1
- permissions refactor
- settings framework
- CI baseline
- audit log base

Gate:
- platform base is safe for larger development

## Sprint 2
- writer profiles
- article statuses
- article moderation queue
- public article filters

Gate:
- writer-to-publication loop works

## Sprint 3
- word ledger
- writer payment batch export
- article SEO improvements
- account/article ownership flows

Gate:
- paid writing workflow is operational

## Sprint 4
- business schema completion
- directory archive/detail enrichment
- package catalogue and pricing models

Gate:
- business listing model is ready for commerce integration

## Sprint 5
- checkout shell
- order/payment/invoice flow
- PayFast integration
- entitlement activation

Gate:
- business purchase -> payment -> activation works end to end

## Sprint 6
- admin business management
- pricing settings UI
- expiry alerts and reminder jobs
- support finance visibility

Gate:
- directory revenue engine is administratively manageable

## Sprint 7
- event package purchase
- event eligibility validation
- event publication/admin controls

Gate:
- event upsell loop works safely

## Sprint 8
- staff-assisted lead flow
- staff attribution
- wallet ledger
- payout request flow

Gate:
- staff commission model is operational

## Sprint 9
- refund/override/extension tools
- finance dashboard expansion
- invoice export/sync prep

Gate:
- finance control model is usable

## Sprint 10
- ad inventory and ad packages
- campaign model
- creative approval flow

Gate:
- first ad monetisation tier works

## Sprint 11
- push campaign module
- geo targeting for campaigns
- campaign analytics basics

Gate:
- premium push tier works

## Sprint 12
- classifieds submission
- moderation queue
- support tooling

Gate:
- free classifieds loop works with moderation

## Sprint 13
- E2E suite expansion
- monitoring stack
- KPI dashboards
- structured alerts

Gate:
- production observability is operational

## Sprint 14
- load tests
- security testing
- pen-test remediation
- rollout readiness

Gate:
- release readiness achieved

## 12. Milestone Gates

### Milestone A: Publishing Core
- writer submits article
- manager approves
- article published
- ledger created

### Milestone B: Directory Revenue Core
- business package purchased
- payment verified
- invoice issued
- listing active and public

### Milestone C: Event And Staff Economics
- event purchase gated by active business
- staff sale creates wallet credit
- payout request can be completed

### Milestone D: Monetisation Expansion
- ad campaigns and push campaigns can be sold and operated

### Milestone E: Production Readiness
- monitoring, load testing, pen-test, training, and documentation complete

## 13. Migration Batch Plan

### Batch 1
- roles, permissions, settings, audit foundation

### Batch 2
- article and writer ledger expansion

### Batch 3
- business model completion
- package catalogue

### Batch 4
- orders, payments, invoices, subscriptions, entitlements

### Batch 5
- event package and event eligibility tables

### Batch 6
- staff wallet and payout tables

### Batch 7
- ad inventory, campaigns, push campaign tables

### Batch 8
- classifieds moderation completion

Rule:
- preserve expand/migrate/contract discipline for rollback safety

Clause trace:
- `11.c`

## 14. Definition Of Done

A roadmap item is only done when:
- code is merged
- relevant tests exist and pass
- migrations are applied safely
- admin/public UX is wired end to end
- auditability is preserved for sensitive actions
- documentation is updated if the change affects operations

Clause trace:
- `11.b`, `11.c`, `11.d`, `12.b`

## 15. Suggested Immediate Execution Start

The original recommended execution start was:

1. permissions/settings/audit foundation
2. writer workflow + ledger
3. business package + checkout + entitlement
4. business admin + expiry/reminder handling

Reason:
- this sequence delivers the first commercially useful release fastest

Clause trace:
- `1.a`, `2.a`, `3.a` to `3.f`, `8.a` to `8.d`, `9.a`, `9.b`, `9.c`, `9.e`, `9.f`

## 16. Open Delivery Decisions

- exact team composition and parallel work capacity
- whether sprints are strictly 2 weeks or milestone-driven
- whether retained non-core WordPress flows are formally deferred or rebuilt
- what advert inventory/creative approval model should be used for the next Phase 4 slice
- what push delivery provider and open-tracking strategy should be adopted
- when staff wallet and payout flows should move ahead of the remaining campaign analytics work

These should be confirmed before the remaining later-phase work is completed.
