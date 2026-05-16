# Production Readiness Todo

This document tracks the work needed to make the Life platform production ready. Keep statuses current as implementation progresses.

Status legend:
- Pending: not started.
- In Progress: actively being implemented or reviewed.
- Done: implemented and verified as far as the local environment allows.
- Blocked: needs an external decision, credential, infrastructure access, or manual review.

## P0 - Launch Blockers

| Status | Item | Recommendation |
| --- | --- | --- |
| Done | PayFast callback hardening | Callback signatures are now required, amount and currency are validated when supplied, paid callbacks require transaction references, and transaction replay across different payments is rejected. |
| Done | Staff commission correctness | Default commission is aligned with the platform spec at 50%, with coverage for a staff-attributed paid order. Still decide whether future reporting should display gross, net-of-VAT, or net-after-refunds. |
| Done | Session-backed API route extraction | Dashboard JSON routes were moved out of `routes/web.php` into `routes/web_api.php` while preserving existing `/api/*` URIs and `api.*` route names. |
| Pending | Decoupled external API strategy | Move true API routes to `routes/api.php` only after choosing Sanctum, JWT, or OAuth, because current dashboard endpoints rely on browser session auth. |
| Done | Authorization policies | Listing, order, subscription, staff wallet, payout request, payment, and notification policies now centralize owner/staff/admin/editor/support access for account listing surfaces, listing-scoped events/campaigns/vouchers, dashboard JSON APIs, checkout orders, subscription renewal, account invoices, wallet pages, payout workflows, and admin finance actions. |
| Done | Remove in-app git updater | Railway is the deployment authority, so the app-level git updater service, `/dev/updates/*` routes, credential UI, and rollout controls were removed. |
| Done | Production-safe dev test runner | `/dev/tests/run` is blocked outside local/testing unless `DEV_TOOLS_ENABLED=true`; production test running also requires `DEV_TEST_RUNNER_ENABLED=true`. The admin Dev tab is hidden when developer tools are disabled. |
| In Progress | Environment hardening | Added a repeatable `php artisan production:check` command covering `APP_ENV`, `APP_DEBUG`, HTTPS URL, database, queue/cache/session drivers, queue worker, scheduler/cron, secure cookies, mail, upload storage, backups/restore drills, dev-tool flags, and PayFast production settings. Current local environment still reports launch-blocking findings because it is configured for local development. |
| In Progress | Backups and restore drills | `php artisan production:check` now requires `BACKUPS_ENABLED`, `BACKUP_PROVIDER`, retention, and a documented restore drill before accepting real payments. Remaining work: enable backups in Railway/managed database provider and complete a real restore test. |
| In Progress | Upload security | Staff/writer ID, banking, and residence documents now store on the private disk and are opened through an authenticated admin route. Public image upload validation now uses shared rules that restrict uploads to JPG, PNG, and WebP with explicit limits. Listing deletes now remove gallery photo files before database cascade. Added `php artisan uploads:orphans` to review/delete unreferenced upload files. `php artisan production:check` now requires an explicit durable upload storage decision. Remaining work: mount a Railway volume for `storage/app`, or refactor upload disks for S3-compatible object storage. |
| In Progress | Security review | Payment callbacks, role policies, and upload validation have focused hardening. Added named rate limiters for auth-sensitive posts, public form submissions, voucher redemption/consumption, PayFast callbacks, and public ad/push tracking endpoints. Remaining work: audit logs for sensitive actions, admin bootstrap launch decision, and independent security review. |

## P1 - High Priority

| Status | Item | Recommendation |
| --- | --- | --- |
| Done | Checkout/payment integration tests | Covered success, failure, retry, duplicate same-payment callback, replayed transaction reference, bad signature, missing signature, wrong amount, wrong currency, inactive package, expired package price, and unauthorized checkout. |
| In Progress | Role-boundary tests | Listing/order/invoice/wallet/payout boundaries now have focused coverage. Continue covering admin finance, writer, councillor, moderation, and support surfaces. |
| In Progress | Staff wallet tests | Commission credit, duplicate commission prevention, core wallet/payout authorization, payout paid debit behavior, and full-refund commission reversal are covered. Continue covering manual adjustments, payout export/reconciliation edge cases, and stronger database-level ledger immutability. |
| In Progress | Subscription lifecycle tests | Activation, expiry sweep, public visibility after expiry, renewal checkout, auto-renewal order creation, renewal payment reminders, extension, suspension, refund suspension, entitlement state sync, and idempotent renewal order creation now have focused coverage. Remaining work: multi-package edge cases for advert and push renewals plus production provider payment edge cases. |
| Done | Campaign entitlement tests | Advert approval now requires both active business-directory entitlement and active advert package entitlement. Push dispatch already requires active business-directory and push package entitlements. Added focused coverage for advert approval blocks/success and push dispatch entitlement blocks. |
| In Progress | Queue setup | `php artisan production:check` now requires `QUEUE_WORKER_ENABLED=true` and documents `QUEUE_WORKER_COMMAND`. Remaining work: configure a Railway worker service running `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and monitor failed jobs. |
| In Progress | Scheduler setup | `php artisan production:check` now requires `SCHEDULER_ENABLED=true` and documents `SCHEDULER_COMMAND`. `schedule:list` coverage verifies subscription reminders, renewal orders, payment reminders, expiry sweeps, and push dispatch jobs are registered. Remaining work: configure a Railway scheduler service running `php artisan schedule:work` or a once-per-minute cron running `php artisan schedule:run`. |
| In Progress | Sensitive audit coverage | Settings, finance payment/subscription actions, payout requests, campaigns, classifieds, civic fault moderation, writer applications, support notes, bulk operations, and writer payment batch creation/mark-paid now have focused audit coverage. Remaining work: review role changes, listing ownership transfers, and any future launch-only admin actions. |
| Done | Project identity cleanup | Renamed lingering `jims-pos` package metadata and updated README stack/version/deployment drift. |
| Done | Release checklist | Added Railway production checklist covering migrations, queues, scheduler, storage, env validation, payments, and release checks. |

## P2 - Operational Readiness

| Status | Item | Recommendation |
| --- | --- | --- |
| Pending | Monitoring | Add uptime, HTTP error, queue failure, failed payment, failed mail, slow page, and disk/storage monitoring. |
| Pending | KPI dashboard | Track revenue, active listings, expiring subscriptions, failed payments, approval queues, writer payouts, and staff wallet liabilities. |
| Pending | Error tracking | Add Sentry, Bugsnag, or equivalent. |
| Pending | Structured logs | Add structured context for payments, callbacks, subscriptions, campaign dispatch, voucher redemption, and finance actions. |
| Pending | Database index review | Review listings, geo queries, subscriptions, orders, payments, campaigns, vouchers, and search-heavy pages. |
| Pending | Performance testing | Test home, directory, search, listing detail, checkout, admin finance, and campaign pages. |
| Pending | Load test evidence | Validate toward 1,000 concurrent users and sub-500 ms p95 response time. |
| Pending | Image optimization | Add responsive image sizes, WebP/AVIF generation, lazy loading, and production media processing. Upload limits and orphan cleanup are now covered by shared validation rules and `php artisan uploads:orphans`. |
| Pending | Cache strategy | Cache settings, package prices, category lists, public pages, and geo-heavy discovery where safe. |
| Pending | CDN/static assets | Configure CDN strategy for static assets and public media. |

## P3 - Product Completeness

| Status | Item | Recommendation |
| --- | --- | --- |
| Pending | Campaign analytics | Add daily impressions, clicks, CTR, push opens, ranking, and exports. |
| Pending | Push tracking quality | Add tokenized opens and provider delivery receipts if a real push provider is used. |
| Pending | Retained flow decisions | Decide launch scope for vouchers, services, shop/transport remnants, and remove or harden anything deferred. |
| Pending | Pricing authority | Ensure package prices and admin settings cannot drift silently. |
| Pending | Customer billing polish | Improve invoices, payment attempts, renewal status, and package expiry warnings. |
| Pending | Owner onboarding | Polish listing creation, checkout, activation, and next-step messaging. |
| Pending | Writer onboarding | Polish application status, approval emails, article workflow, and payment expectations. |
| Pending | Support workflows | Decide on impersonation, escalation markers, timeline completeness, and note controls. |
| Pending | Finance exports | Harden CSV exports for accounting use and large datasets. |
| Pending | Moderation workflows | Round out moderation for classifieds, vouchers, reviews, articles, and civic fault reports. |

## P4 - Compliance And Trust

| Status | Item | Recommendation |
| --- | --- | --- |
| Pending | POPIA/GDPR review | Confirm consent, retention, access/export/delete, privacy notices, and processor list. |
| Pending | PCI scope review | Document SAQ-A assumptions and confirm the app never stores card data. |
| Pending | Legal copy review | Align Terms and Privacy copy with actual launch behavior. |
| Pending | Cookie/analytics consent | Add consent flows if analytics or tracking cookies are introduced. |
| Pending | Data retention policy | Define retention for applications, compliance documents, logs, failed payments, and audit records. |
| Pending | Admin handbook | Document normal admin and support operations. |
| Pending | Incident runbook | Cover payment failures, data leaks, rollback, queue outages, and provider downtime. |
| Pending | External security assessment | Schedule pen test or independent security review before serious public launch. |
| Pending | Dependency scanning | Add vulnerability scanning for PHP and Node dependencies. |
| Pending | CI/CD hardening | Add tests, build, deployment gates, migration strategy, rollback, and branch protection. |

## P5 - Valuable Refactors

| Status | Item | Recommendation |
| --- | --- | --- |
| Pending | Form Requests | Move complex validation out of controllers. |
| Pending | Status enums/constants | Replace repeated string statuses with enums or model constants. |
| Pending | Domain events | Introduce events/listeners for payment paid, subscription activated, payout paid, and campaign dispatched. |
| Pending | API documentation | Document public/mobile/partner API surfaces. |
| Pending | Browser E2E tests | Cover checkout, listing creation, staff-assisted sale, voucher redemption, and admin approvals. |
| Pending | Accessibility pass | Test keyboard navigation, focus states, labels, contrast, and tap targets for WCAG 2.2 AA. |
| Pending | SEO pass | Add metadata, canonical URLs, schema.org, sitemap, robots, and Open Graph checks. |
| Pending | Search tuning | Improve relevance and filters as content grows. |
| Pending | Admin UX consistency | Normalize admin layouts, actions, empty states, and filtering patterns. |
| Pending | Seed data separation | Keep test/demo data separate from production seed data. |

## Verification Log

- Done: `npm.cmd run build` passed after the first implementation pass.
- Done: PHP syntax checks passed for changed controller, services, route files, and feature tests using `C:\php\php.exe -l`.
- Done: `npm.cmd run build`, `git diff --check`, and PHP syntax checks passed after the dev-tools production gate pass.
- Done: Removed in-app git updater and verified no remaining references to `UpdateUtilityService`, `/dev/updates/*`, updater credentials, or app-level git rollout controls.
- Done: `npm.cmd run build`, `git diff --check`, and PHP syntax checks passed after removing the in-app updater and refreshing project identity metadata.
- Done: Added listing/order/subscription policies and verified syntax/build/diff checks for the authorization policy pass.
- Done: Staff wallet and payout policies were added and wired into account/admin controllers; PHP syntax checks, `npm.cmd run build`, and `git diff --check` passed for this slice.
- Done: Admin finance policies were added for order, payment, subscription, and notification surfaces; PHP syntax checks, `npm.cmd run build`, and `git diff --check` passed for this slice.
- Done: Herd PHP 8.4.10 was located at `C:\Users\Phoenix\AppData\Roaming\Local\lightning-services\php-8.4.10+0\bin\win64\php.exe`; `artisan route:list --except-vendor` loads 305 routes.
- Done: `WalletPolicyAuthorizationTest` passed with PHP 8.4.10 when `mbstring`, `openssl`, `pdo_sqlite`, and `sqlite3` were loaded explicitly.
- Done: `FinancePolicyAuthorizationTest` passed with PHP 8.4.10 when `mbstring`, `openssl`, `pdo_sqlite`, and `sqlite3` were loaded explicitly.
- Done: Added `php artisan production:check`, updated `.env.example`, and added it to the Railway checklist.
- Done: `ProductionReadinessCommandTest` passed with PHP 8.4.10.
- Current finding: local `php artisan production:check` reports 13 errors and 8 warnings, as expected for local settings (`APP_ENV=local`, SQLite, sandbox PayFast, local mail/storage, no production upload volume, no Railway worker/scheduler flags, and no backup/restore-drill variables).
- Done: Writer/staff application verification documents were moved from public storage to private storage behind an admin-only document route.
- Done: `StaffSignupPageTest` and `AdminWriterApplicationReviewTest` passed with PHP 8.4.10 after the private document storage change.
- Done: Focused verification suite passed: 23 tests, 95 assertions covering production readiness, authorization policies, and private upload handling.
- Done: `artisan route:list --except-vendor` now loads 306 routes after adding the private writer-application document route.
- Done: Added shared upload validation rules and applied them to listing, event, article, classified, civic fault, advert creative, and writer profile-photo upload surfaces.
- Done: `UploadRulesTest`, `ClassifiedModerationTest`, `StaffSignupPageTest`, and `AccountPageTest` passed with PHP 8.4.10 after normalizing public image validation.
- Done: Added `php artisan uploads:orphans` for public/private upload orphan detection and optional deletion.
- Done: Listing deletion now removes gallery photo files before related `listing_photos` rows are cascaded.
- Done: `UploadOrphansCommandTest` and `test_owner_can_manage_listing_gallery_photos` passed with PHP 8.4.10 after orphan cleanup changes.
- Done: Final upload-security focused verification passed with PHP 8.4.10: 41 tests, 300 assertions across upload rules, classified moderation, staff signup, account listings, admin writer applications, production checks, wallet/finance authorization, and orphan upload scanning.
- Done: `npm.cmd run build` passed after the upload-security/orphan cleanup slice.
- Done: `git diff --check` passed after the upload-security/orphan cleanup slice; Git only reported existing CRLF normalization warnings.
- Done: `artisan route:list --except-vendor` loads 306 routes with PHP 8.4.10 after the upload-security/orphan cleanup slice.
- Done: Extended checkout/payment feature coverage for unauthorized checkout, inactive packages, expired package prices, currency mismatch, and duplicate same-payment PayFast callbacks.
- Done: `tests\Feature\BusinessDirectoryRevenueTest.php` passed with PHP 8.4.10: 19 tests, 80 assertions.
- Done: Added production readiness checks for durable upload storage strategy, automated backup enablement, backup provider documentation, retention, restore drill completion, and last restore drill date.
- Done: Updated `.env.example`, README, and Railway production checklist with Railway upload volume and backup/restore drill requirements.
- Done: `ProductionReadinessCommandTest` passed with PHP 8.4.10 after the backup/storage readiness checks: 1 test, 6 assertions.
- Done: Focused production readiness/payment/upload verification passed with PHP 8.4.10 after the backup/storage readiness slice: 21 tests, 92 assertions.
- Done: `git diff --check` passed after the backup/storage readiness slice; Git only reported existing CRLF normalization warnings.
- Done: Added named route rate limiters for auth-sensitive POSTs, public form submissions, voucher redemption/consumption, PayFast callbacks, and public ad/push tracking endpoints.
- Done: `SecurityRateLimitingTest` passed with PHP 8.4.10: 2 tests, 57 assertions.
- Done: Focused security-adjacent regression suite passed with PHP 8.4.10: 40 tests, 219 assertions across security throttles, auth, password reset, checkout/payment, vouchers, staff signup, and classified submissions.
- Done: `artisan route:list --except-vendor` still loads 306 routes after adding named throttles.
- Done: Added audit logs for writer payment batch creation and mark-paid actions, including actor, subject, status, amount/count, and affected ledger IDs.
- Done: `WriterPaymentBatchTest` passed with PHP 8.4.10 after the audit update: 2 tests, 13 assertions.
- Done: Focused audit-related regression suite passed with PHP 8.4.10: 31 tests, 164 assertions across settings, finance, writer applications, civic fault moderation, bulk operations, customer support timelines, and writer payments.
- Done: Added production readiness checks for Railway queue worker and scheduler/cron enablement plus documented worker/scheduler commands.
- Done: Updated `.env.example`, README, and Railway production checklist with worker command `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and scheduler command `php artisan schedule:work`.
- Done: `ProductionReadinessCommandTest` passed with PHP 8.4.10 after queue/scheduler checks and scheduler registration coverage: 2 tests, 14 assertions.
- Done: `npm.cmd run build`, `git diff --check`, and `artisan schedule:list` passed after the queue/scheduler readiness slice; `schedule:list` shows the five expected production recurring jobs.
- Done: Made staff commission crediting idempotent for the same payment.
- Done: Full refunds now reverse available staff commission once through an adjustment ledger entry.
- Done: Added `StaffWalletLifecycleTest` for duplicate commission prevention, payout paid debit behavior, and full-refund commission reversal.
- Done: Staff wallet lifecycle focused tests passed with PHP 8.4.10: 3 tests, 19 assertions.
- Done: Focused staff wallet and finance regression suite passed with PHP 8.4.10: 19 tests, 62 assertions.
- Done: Added `SubscriptionLifecycleTest` for extension, suspension, expiry, entitlement sync, listing state sync, and idempotent renewal order creation.
- Done: `SubscriptionLifecycleTest` passed with PHP 8.4.10: 4 tests, 21 assertions.
- Done: Focused subscription, finance, and checkout regression suite passed with PHP 8.4.10: 43 tests, 177 assertions.
- Done: Hardened admin advert approval so campaigns cannot become active without both an active listing business-directory entitlement and an active advert package entitlement.
- Done: Added `CampaignEntitlementTest` for advert approval blocks/success and push dispatch entitlement blocks.
- Done: `CampaignEntitlementTest` passed with PHP 8.4.10: 5 tests, 12 assertions.
- Done: Focused campaign, checkout, and subscription regression suite passed with PHP 8.4.10: 44 tests, 212 assertions.
- Done: Removed the stale `favicon.png` reference and hid the admin test-runner panel whenever `DEV_TEST_RUNNER_ENABLED` is off in production, preventing expected 403s from appearing as dashboard console noise.
- Done: `DevToolsAccessTest` passed with PHP 8.4.10 after the console-cleanup fix: 4 tests, 9 assertions.
