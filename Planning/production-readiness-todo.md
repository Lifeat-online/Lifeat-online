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
| Done | PayFast callback hardening | Callback signatures are now required, amount and currency are validated when supplied, paid callbacks require transaction references, transaction replay across different payments is rejected, and late failed/cancelled callbacks cannot roll back already-paid orders. |
| Done | Staff commission correctness | Default commission is aligned with the platform spec at 50%, with coverage for a staff-attributed paid order. Still decide whether future reporting should display gross, net-of-VAT, or net-after-refunds. |
| Done | Session-backed API route extraction | Dashboard JSON routes were moved out of `routes/web.php` into `routes/web_api.php` while preserving existing `/api/*` URIs and `api.*` route names. |
| Pending | Decoupled external API strategy | Move true API routes to `routes/api.php` only after choosing Sanctum, JWT, or OAuth, because current dashboard endpoints rely on browser session auth. |
| Done | Authorization policies | Listing, order, subscription, staff wallet, payout request, payment, and notification policies now centralize owner/staff/admin/editor/support access for account listing surfaces, listing-scoped events/campaigns/vouchers, dashboard JSON APIs, checkout orders, subscription renewal, account invoices, wallet pages, payout workflows, and admin finance actions. |
| Done | Remove in-app git updater | Coolify/git deployment is the deployment authority, so the app-level git updater service, `/dev/updates/*` routes, credential UI, and rollout controls were removed. |
| Done | Production-safe dev test runner | `/dev/tests/run` is blocked outside local/testing unless `DEV_TOOLS_ENABLED=true`; production test running also requires `DEV_TEST_RUNNER_ENABLED=true`. The admin Dev tab is hidden when developer tools are disabled. |
| In Progress | Environment hardening | Added a repeatable `php artisan production:check` command covering `APP_ENV`, `APP_DEBUG`, HTTPS URL, database, queue/cache/session drivers, queue worker, scheduler/cron, secure cookies, mail, upload storage, backups/restore drills, dev-tool flags, and PayFast production settings. Current local environment still reports launch-blocking findings because it is configured for local development. |
| In Progress | Backups and restore drills | `php artisan production:check` now requires `BACKUPS_ENABLED`, `BACKUP_PROVIDER`, retention, and a documented restore drill before accepting real payments. Remaining work: enable backups in Hetzner/Coolify or the managed database provider and complete a real restore test. |
| In Progress | Upload security | Staff/writer ID, banking, and residence documents now store on the private disk and are opened through an authenticated admin route. Public image upload validation now uses shared rules that restrict uploads to JPG, PNG, and WebP with explicit limits. Listing deletes now remove gallery photo files before database cascade. Added `php artisan uploads:orphans` to review/delete unreferenced upload files. `php artisan production:check` now requires an explicit durable upload storage decision. Current direction: mount a Hetzner/Coolify durable volume at `/app/storage/app` with `UPLOAD_STORAGE_BACKEND=mounted_volume` and `UPLOAD_STORAGE_MOUNT_PATH=/app/storage/app`; S3-compatible object storage remains a later option. |
| In Progress | Security review | Payment callbacks, role policies, campaign report/export access, Dev dashboard/platform-push access, listing ownership transfer auditing, shell admin provisioning audit logs, and upload validation have focused hardening. Added named rate limiters for auth-sensitive posts, public form submissions, voucher redemption/consumption, PayFast callbacks, and public ad/push tracking endpoints. The browser admin-bootstrap route is absent in the current worktree; first-admin provisioning is handled from the deployment shell. Remaining work: first-admin credential-handoff runbook review and independent security review. |

## P1 - High Priority

| Status | Item | Recommendation |
| --- | --- | --- |
| Done | Checkout/payment integration tests | Covered success, failure, retry, duplicate same-payment callback, replayed transaction reference, bad signature, missing signature, wrong amount, wrong currency, inactive package, expired package price, and unauthorized checkout. |
| Done | Role-boundary tests | Listing/order/invoice/wallet/payout boundaries, payout export access, listing ownership transfer controls, campaign report/export access, Dev dashboard/platform-push visibility, admin finance read/write boundaries, writer-only workspaces, councillor workspaces, civic/classified moderation, writer administration, and support-denied management/API surfaces now have focused coverage. |
| Done | Staff wallet tests | Commission credit, duplicate commission prevention, core wallet/payout authorization, admin-only manual adjustments, over-debit protection, filtered payout reconciliation export, payout paid debit behavior, full-refund commission reversal, and database-level ledger immutability are covered. |
| Done | Subscription lifecycle tests | Activation, expiry sweep, public visibility after expiry, renewal checkout, manual browser renewal order/payment flow, renewal authorization, auto-renewal order creation, renewal payment reminders, extension, suspension, refund suspension, entitlement state sync, idempotent renewal order creation, paid advert/push renewal activation, and late failure-after-paid PayFast callback handling now have focused local coverage. Revisit provider-specific settlement/reversal cases if the production PayFast flow exposes additional terminal states. |
| Done | Campaign entitlement tests | Advert approval now requires both active business-directory entitlement and active advert package entitlement. Push dispatch already requires active business-directory and push package entitlements. Added focused coverage for advert approval blocks/success and push dispatch entitlement blocks. |
| In Progress | Queue setup | `php artisan production:check` now requires `QUEUE_WORKER_ENABLED=true`, documents `QUEUE_WORKER_COMMAND`, and warns if a dedicated auto-translation queue is configured without a matching worker command. Remaining work: configure a Coolify worker process/service running `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and monitor failed jobs. |
| In Progress | Scheduler setup | `php artisan production:check` now requires `SCHEDULER_ENABLED=true` and documents `SCHEDULER_COMMAND`. `schedule:list` coverage verifies subscription reminders, renewal orders, payment reminders, expiry sweeps, and push dispatch jobs are registered. Remaining work: configure a Coolify scheduler process/service running `php artisan schedule:work` or a once-per-minute cron running `php artisan schedule:run`. |
| Done | Sensitive audit coverage | Settings, finance payment/subscription actions, payout requests, staff wallet manual adjustments, campaigns, classifieds, civic fault moderation, writer applications, support notes, bulk operations, writer payment batch creation/mark-paid, listing ownership transfers, and shell admin account creation/promotion now have focused audit coverage. Future launch-only admin actions should add audit logs before release. |
| Done | Project identity cleanup | Renamed lingering `jims-pos` package metadata and updated README stack/version/deployment drift. |
| Done | Release checklist | Added Hetzner/Coolify production checklist covering migrations, queues, scheduler, storage, env validation, payments, and release checks. |

## P2 - Operational Readiness

| Status | Item | Recommendation |
| --- | --- | --- |
| In Progress | Monitoring | Added public `/health` JSON and `php artisan monitoring:health` checks for database reachability, storage writability, disk headroom, failed queue jobs, failed payments/stale pending orders, and failed/stale notifications. Remaining work: wire external uptime checks, HTTP error alerts, slow-page monitoring, and alert routing in the production observability provider. |
| Done | KPI dashboard | Admin overview and the live metrics JSON now track paid revenue, active listings, expiring subscriptions, failed payments/stale orders, approval queues, writer payout exposure, staff wallet liability, and active payout requests. |
| Done | Error tracking | Added a provider-agnostic `ErrorTracker` wired into Laravel exception reporting, with webhook/log drivers, redacted payloads, ignored HTTP statuses, no request-body capture, env knobs, and `production:check` gates. Production still needs `ERROR_TRACKING_ENABLED=true` and a real external webhook destination before launch. |
| Done | Structured logs | Added `lifeat.operational.*` structured log events with redaction for sensitive keys across payment status changes, PayFast initiation/callback received/paid/rejected/failed/ignored branches, subscription lifecycle and renewal-order actions, push campaign dispatch/rejection, voucher claim/consume/rejection, and admin finance actions. External log shipping and alert routing remain part of Monitoring/Error tracking. |
| Done | Database index review | Added production-readiness composite indexes for public listing/event/article/classified/voucher discovery, geo-heavy listing/event filters, subscription expiry/renewal sweeps, orders, payments, invoices, campaigns, voucher redemptions, notification attention queues, reviews, and category lookup. Remaining performance work should use production query plans and load-test evidence to tune beyond this baseline. |
| Pending | Performance testing | Test home, directory, search, listing detail, checkout, admin finance, and campaign pages. |
| Pending | Load test evidence | Validate toward 1,000 concurrent users and sub-500 ms p95 response time. |
| Pending | Image optimization | Add responsive image sizes, WebP/AVIF generation, lazy loading, and production media processing. Upload limits and orphan cleanup are now covered by shared validation rules and `php artisan uploads:orphans`. |
| Done | Cache strategy | Added a versioned Laravel read-cache baseline for settings, active package catalogues/current prices, category/tag/location filter lists, public discovery stats, and popular listing/event locations with model-driven invalidation and configurable TTLs. Full rendered-page caching remains a later traffic/load-test decision. |
| Pending | CDN/static assets | Configure CDN strategy for static assets and public media. |

## P3 - Product Completeness

| Status | Item | Recommendation |
| --- | --- | --- |
| In Progress | Campaign analytics | Campaign tracking event logs now capture advert impressions, advert clicks, and push opens; admin campaign lists can rank by performance; detail pages show daily reporting and recent tracking events; the Campaign Report screen now provides aggregate totals, daily trend rows, top campaign rankings, and CSV exports. Remaining work: final attribution model decisions for conversion handoff and campaign ROI reporting. |
| In Progress | Push tracking quality | Push open tracking now supports tokenized open-event dedupe and recent open logs. Remaining work: per-recipient token issuance for the eventual provider flow and provider-side delivery receipt integration if a real push provider exposes it. |
| Pending | Retained flow decisions | Decide launch scope for vouchers, services, shop/transport remnants, and remove or harden anything deferred. |
| Done | Pricing authority | Admin package price edits now create auditable price versions instead of mutating the active price row in place. Price changes require an authority note, close the previous effective price, create a new `package_prices` row, and write `package_price.versioned` audit logs. The Add Listing public price signal now reads from the same package catalogue/current-price source used by checkout instead of legacy `pricing.*` settings. |
| Done | Customer billing polish | Account invoice pages now surface latest payment status, pending/failed payment actions, renewal-order context, and payment attempt history without exposing raw PayFast payloads. Checkout order pages now show a customer-safe payment handoff and attempt timeline instead of the old callback simulator. Listing workspaces and listing indexes now warn when packages are nearing expiry or need renewal, with direct renewal/package-selection actions. |
| Done | Owner onboarding | Added a shared listing launch checklist for owner-managed listings, covering starter creation, profile basics, checkout/payment, public activation, and growth-tool next steps. The checklist appears on package selection, order handoff, and listing workspace pages, and starter redirects now point owners to package/order activation. |
| Done | Writer onboarding | Added writer onboarding checklist guidance across writer submissions, article forms, and earnings pages; improved post-application next-step messaging; expanded approval email copy with workspace and payout timing; and aligned admin review wording with optional banking proof and payout-later handling. |
| Pending | Support workflows | Decide on impersonation, escalation markers, timeline completeness, and note controls. |
| In Progress | Finance exports | Finance CSV exports and filtered payout reconciliation CSV exports are available. Remaining work: harden export coverage for large datasets, accounting-specific schemas, and any final accountant-requested columns. |
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
| Done | Dependency scanning | Added a GitHub Actions dependency scan baseline for Composer and npm lockfiles using `composer audit --locked` and `npm audit --audit-level=high`. Review and triage scan findings before release. |
| In Progress | CI/CD hardening | Added `.github/workflows/release-readiness.yml` to run PHP tests, Laravel route/cache/view smoke checks, frontend builds, and dependency audits on pull requests, pushes to `master`/`main`, and manual dispatch. Remaining work: configure branch protection, deployment environment approvals, migration/rollback drills, and hosting-provider release gates. |

## P5 - Valuable Refactors

| Status | Item | Recommendation |
| --- | --- | --- |
| In Progress | Form Requests | Owner-facing listing profile, listing photo, review response, event, advert campaign, push campaign, voucher, payout request, checkout-start, writer-application, civic fault, and mall vendor registration validation now live in dedicated Form Request classes. Remaining work: continue extracting admin, API, transport, AI, auth, and smaller validation blocks from controllers. |
| Pending | Status enums/constants | Replace repeated string statuses with enums or model constants. |
| Done | Domain events | Added first-class `PaymentPaid`, `SubscriptionActivated`, `PayoutPaid`, and `PushCampaignDispatched` events plus a shared `RecordRevenueLifecycleEvent` listener that emits structured lifecycle logs. Events now fire from the existing payment-paid hook, subscription activation service, payout mark-paid flow, and push dispatch service. |
| Done | API documentation | Added `Planning/current-api-surface.md` documenting the implemented voucher JSON API, session-backed dashboard JSON routes, public utility/tracking JSON endpoints, current auth boundaries, and the remaining mobile/partner token-auth strategy gap. |
| Pending | Browser E2E tests | Cover checkout, listing creation, staff-assisted sale, voucher redemption, and admin approvals. |
| Pending | Accessibility pass | Test keyboard navigation, focus states, labels, contrast, and tap targets for WCAG 2.2 AA. |
| Done | SEO pass | Public layout now emits default meta descriptions, canonical URLs, Open Graph/Twitter tags, and Organization/WebSite schema. Article, directory listing, and event detail pages add page-specific canonical URLs and NewsArticle, LocalBusiness, and Event JSON-LD. Added dynamic `/sitemap.xml` covering static public routes plus public listings, events, articles, vouchers, and classifieds, and `robots.txt` now points crawlers to the sitemap. |
| Done | Search tuning | Public search now tokenizes queries, matches related categories/tags/locations/linked listings, applies article location filtering through `LocationNode`, and ranks exact/title/field matches ahead of weaker matches while keeping the grouped result surface. |
| Pending | Admin UX consistency | Normalize admin layouts, actions, empty states, and filtering patterns. |
| Done | Seed data separation | `DatabaseSeeder` now loads production-safe reference data by default, while demo/test users and the mall demo store require explicit `SEED_DEMO_USERS=true` or `MALL_SEED_DEMO=true` in local/testing environments. The demo user seed path is idempotent. |

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
- Done: Added `php artisan production:check`, updated `.env.example`, and added it to the production checklist.
- Done: `ProductionReadinessCommandTest` passed with PHP 8.4.10.
- Current finding: local `php artisan production:check` reports launch blockers as expected for local settings (`APP_ENV=local`, SQLite, sandbox PayFast, local mail/storage, no production mounted upload volume, no production worker/scheduler flags, and no backup/restore-drill variables).
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
- Done: Updated `.env.example`, README, and launch checklist with durable mounted upload volume and backup/restore drill requirements.
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
- Done: Added production readiness checks for queue worker and scheduler/cron enablement plus documented worker/scheduler commands.
- Done: Updated `.env.example`, README, and production checklist with worker command `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and scheduler command `php artisan schedule:work`.
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
- Done: Added advert and push renewal payment coverage to `SubscriptionLifecycleTest`, proving PayFast-paid renewal orders reactivate campaign subscriptions, entitlements, and operational campaign state.
- Done: `SubscriptionLifecycleTest` passed with PHP 8.4.10 after the advert/push renewal coverage: 6 tests, 37 assertions.
- Done: Campaign Report now uses the same `admin,editor` role boundary as its CSV exports, preventing support/staff access to campaign performance reporting.
- Done: `CampaignAnalyticsTest` passed with PHP 8.4.10 after campaign report role-boundary coverage: 5 tests, 66 assertions.
- Done: Dev dashboard visibility now matches the Dev endpoint ownership gate: `dev`, `developer`, and `super_admin` users can see Dev controls, while regular admins/support cannot.
- Done: `AdminSupportAccessTest` passed with PHP 8.4.10 after aligning the Dev dashboard gate: 6 tests, 53 assertions.
- Done: Focused campaign/security regression suite passed with PHP 8.4.10 after the report and Dev dashboard boundary updates: 43 tests, 245 assertions.
- Done: Hardened admin advert approval so campaigns cannot become active without both an active listing business-directory entitlement and an active advert package entitlement.
- Done: Added `CampaignEntitlementTest` for advert approval blocks/success and push dispatch entitlement blocks.
- Done: `CampaignEntitlementTest` passed with PHP 8.4.10: 5 tests, 12 assertions.
- Done: Focused campaign, checkout, and subscription regression suite passed with PHP 8.4.10: 44 tests, 212 assertions.
- Done: Removed the stale `favicon.png` reference and hid the admin test-runner panel whenever `DEV_TEST_RUNNER_ENABLED` is off in production, preventing expected 403s from appearing as dashboard console noise.
- Done: `DevToolsAccessTest` passed with PHP 8.4.10 after the console-cleanup fix: 4 tests, 9 assertions.
- Done: Admin listing ownership transfers are now explicit, limited to admin/editor users, cascade ownership to listing-scoped events/ad campaigns/push campaigns, and write a dedicated `listing.ownership_transferred` audit log.
- Done: Dev/platform push routes now match the visible Dev-owner Platform Push panel, preventing the create-page form from leading Dev users into a 403.
- Done: `AdminListingOwnershipTransferTest` passed with PHP 8.4.10: 3 tests, 19 assertions.
- Done: Focused admin listing/campaign/push regression suite passed with PHP 8.4.10 after the ownership-transfer and Dev push-route updates: 26 tests, 156 assertions.
- Done: Expanded admin access-control regression suite passed with PHP 8.4.10 after the ownership-transfer and Dev push-route updates: 55 tests, 314 assertions.
- Done: Added admin-only staff wallet manual adjustments with required reason, ledger entries, audit logging, and over-debit protection.
- Done: `StaffWalletLifecycleTest` and `WalletPolicyAuthorizationTest` passed with PHP 8.4.10 after wallet adjustment coverage: 12 tests, 51 assertions.
- Done: Focused finance/wallet regression suite passed with PHP 8.4.10 after wallet adjustment coverage: 41 tests, 164 assertions.
- Done: Added filtered payout reconciliation CSV export with status/wallet filters, ledger debit totals, bank/payment references, wallet totals, and admin/editor-only access.
- Done: `WalletPolicyAuthorizationTest` passed with PHP 8.4.10 after payout export coverage: 7 tests, 21 assertions.
- Done: Focused finance/wallet regression suite passed with PHP 8.4.10 after payout export coverage: 42 tests, 175 assertions.
- Done: PayFast callback rejection regression passed with PHP 8.4.10 after verifying the current callback exception import state: 3 tests, 8 assertions.
- Done: Enforced staff wallet ledger entries as append-only through Eloquent model guards and database triggers for SQLite, MySQL/MariaDB, and Postgres.
- Done: `StaffWalletLifecycleTest` passed with PHP 8.4.10 after ledger immutability coverage: 8 tests, 47 assertions.
- Done: PayFast failed/cancelled callbacks are now ignored once the payment or order is already paid, preventing late provider failures from rolling back successful checkouts.
- Done: `BusinessDirectoryRevenueTest` and `SubscriptionLifecycleTest` passed with PHP 8.4.10 after late PayFast failure coverage: 27 tests, 135 assertions.
- Done: Added focused role-boundary coverage for writer-only routes, admin writer application/payment routes, councillor workspaces, civic fault moderation, classified moderation, support-denied management JSON APIs, and staff-denied article moderation JSON reads.
- Done: `AdminRoleBoundaryTest`, `AdminApiSmokeTest`, `AdminDashboardMetricsTest`, `AdminSupportAccessTest`, `ClassifiedModerationTest`, `AdminCivicFaultWorkflowTest`, `AdminWriterApplicationReviewTest`, `WriterPaymentBatchTest`, and `WriterArticleWorkflowTest` passed with PHP 8.4.10 after the role-boundary API hardening: 36 tests, 246 assertions.
- Done: `artisan route:list --except-vendor` loads 436 routes after the role-boundary API hardening.
- Done: `php artisan admin:create` now writes audit logs for shell-based admin creation and existing-user promotion without storing passwords in audit JSON.
- Done: `AdminCreateCommandTest`, `AdminWriterApplicationReviewTest`, `AdminSettingsTest`, `AdminCustomerLookupTest`, `WriterPaymentBatchTest`, `AdminCivicFaultWorkflowTest`, `ClassifiedModerationTest`, and `AdminBulkOperationsTest` passed with PHP 8.4.10 after shell admin provisioning audit coverage: 31 tests, 188 assertions.
- Done: Manual subscription renewals now carry the renewing subscription through the browser checkout form and create idempotent `renewed_subscription_id` orders through `SubscriptionRenewalService` instead of looking like fresh purchases.
- Done: `BusinessDirectoryRevenueTest`, `SubscriptionAutomationTest`, `SubscriptionLifecycleTest`, and `AccountPageTest` passed with PHP 8.4.10 after the manual renewal browser-flow coverage and account advert fixture update: 54 tests, 378 assertions.
- Done: Added a richer monitoring health surface at `/health` plus `php artisan monitoring:health`, covering database, storage, disk capacity, failed jobs, failed/stale payment work, and failed/stale notification work without exposing secrets.
- Done: `HealthCheckTest` passed with PHP 8.4.10 after the monitoring baseline: 4 tests, 45 assertions. Local health currently reports `degraded` because host disk free space is below the 15% warning threshold, which is an expected monitoring signal rather than a test failure.
- Done: Added shared operational KPI reporting to the admin overview and `admin.metrics` JSON for paid revenue, active listings, expiring subscriptions, failed payments/stale orders, approval queues, writer payout exposure, staff wallet liability, and active payout requests.
- Done: `AdminDashboardMetricsTest` passed with PHP 8.4.10 after the KPI dashboard baseline: 4 tests, 94 assertions.
- Done: Added `2026_06_05_000001_add_production_readiness_indexes.php` with composite indexes for the reviewed launch surfaces: listings, events, articles, classifieds, subscriptions, orders, payments, invoices, campaigns, vouchers, voucher redemptions, notification logs, reviews, and categories.
- Done: `ProductionReadinessIndexesTest` passed with PHP 8.4.10 after the database index review baseline: 1 test, 27 assertions.
- Done: Focused production-readiness regression passed with PHP 8.4.10 after the database index review baseline: 9 tests, 166 assertions across index migration, monitoring health, and admin dashboard KPI coverage.
- Done: `artisan route:list --except-vendor` loads 437 routes and `git diff --check` passed after the database index review baseline; Git only reported existing CRLF normalization warnings.
- Done: Added `App\Support\Logging\OperationalLog` and structured `lifeat.operational.*` events for payments, callbacks, subscriptions, push campaign dispatch, voucher redemption, and finance actions, with recursive redaction for signatures, tokens, passphrases, API keys, credentials, and secrets.
- Done: `OperationalStructuredLoggingTest` passed with PHP 8.4.10 after the structured logging baseline: 5 tests, 16 assertions.
- Done: Focused payment/subscription/campaign/voucher/finance regression passed with PHP 8.4.10 after the structured logging baseline: 46 tests, 201 assertions.
- Done: Added `App\Support\Caching\PublicReadCache`, configurable `LIFEAT_*_CACHE_TTL` values, and model invalidation for settings, package catalogue/prices, category/tag/location references, public stats, and popular listing/event locations.
- Done: `PublicReadCacheStrategyTest` passed with PHP 8.4.10 after the cache-strategy baseline: 4 tests, 10 assertions.
- Done: Focused public discovery/checkout regression passed with PHP 8.4.10 after the cache-strategy baseline: 31 tests, 167 assertions across cache strategy, advertise, directory, search, event detail, directory detail, and business-directory checkout coverage.
- Done: Added configurable exception error tracking with redaction, webhook/log drivers, ignored HTTP statuses, and `production:check` coverage for `ERROR_TRACKING_ENABLED`, driver choice, webhook URL, and sample rate.
- Done: `ErrorTrackingTest` and `ProductionReadinessCommandTest` passed with PHP 8.4.10 after the error-tracking baseline: 5 tests, 23 assertions.
- Done: Added `.github/workflows/release-readiness.yml` with PHP tests, Laravel route/config/route/view cache smoke checks, frontend build, `composer audit --locked`, and `npm audit --audit-level=high` gates for pull requests, pushes to `master`/`main`, and manual dispatch.
- Done: Full PHPUnit passed with bundled PHP 8.4.10 plus `mbstring`, `openssl`, `fileinfo`, `gd`, `curl`, `pdo_sqlite`, `sqlite3`, and `memory_limit=512M`: 390 tests, 2518 assertions.
- Done: `npm.cmd run build`, Laravel `config:cache`, `route:cache`, `view:cache` smoke checks, and `git diff --check` passed after the release-readiness workflow baseline.
- Done: Extracted owner-facing listing, listing photo, review response, event, advert campaign, push campaign, and voucher validation into `App\Http\Requests\Account` Form Request classes, including scoped event validation for advert/push campaigns.
- Done: Focused account/campaign/voucher regression passed after the Form Request slice: 47 tests, 367 assertions.
- Done: Added `Planning/current-api-surface.md` to document current JSON/API routes, auth boundaries, role requirements, and the explicit separation between session-backed dashboard JSON and future token-auth external APIs.
- Done: Added domain events for paid payments, activated subscriptions, paid payouts, and dispatched push campaigns, with `RecordRevenueLifecycleEvent` structured-log listener registration.
- Done: `RevenueDomainEventsTest` passed with PHP 8.4.10 after the domain-event baseline: 3 tests, 6 assertions.
- Done: Focused revenue lifecycle regression passed after the domain-event baseline: 82 tests, 365 assertions across payment activation, subscription lifecycle, renewal automation, staff wallets, payout authorization, campaign entitlements, structured logging, and campaign dispatch.
- Done: Added auditable package price versioning so admin amount/currency/VAT changes require a pricing authority note, expire the old active price, create a new active price row, and keep public Add Listing pricing aligned to package prices instead of legacy pricing settings.
- Done: `AdminPackagePricingTest` passed with PHP 8.4.10 after the pricing authority baseline: 2 tests, 23 assertions.
- Done: Added customer billing polish across invoices, checkout orders, payment attempts, renewal-order context, and listing expiry warnings while removing customer-visible PayFast callback simulation details from checkout.
- Done: Focused billing/customer regression passed with PHP 8.4.10 after the customer billing polish slice: 34 tests, 283 assertions across `AccountPageTest` and `BusinessDirectoryRevenueTest`.
- Done: Added SEO metadata, canonical URLs, Open Graph/Twitter tags, schema.org JSON-LD, dynamic `/sitemap.xml`, and a robots sitemap pointer across the public surface.
- Done: `SeoSurfaceTest` passed with PHP 8.4.10 after the SEO baseline: 4 tests, 25 assertions.
- Done: Tuned public search relevance and filtering across listings, events, articles, and classifieds, including article location-node filtering and taxonomy-aware keyword matching.
- Done: `SearchPageTest` passed with PHP 8.4.10 after the search-tuning baseline: 3 tests, 20 assertions.
- Done: Added owner onboarding checklist guidance across add-listing, checkout, order handoff, and listing workspace states.
- Done: `AddListingPageTest`, `BusinessDirectoryRevenueTest`, and `AccountPageTest` passed with PHP 8.4.10 after the owner-onboarding baseline: 37 tests, 316 assertions.
- Done: Added writer onboarding checklist guidance across application submission, approval notification, writer article workflow, and writer earnings/payout expectations.
- Done: `StaffSignupPageTest`, `AdminWriterApplicationReviewTest`, and `WriterArticleWorkflowTest` passed with PHP 8.4.10 after the writer-onboarding baseline: 21 tests, 119 assertions.
- Done: Separated production-safe reference seed data from demo/test seed data using explicit local/testing-only seeder flags.
- Done: `ReferenceDataSeederTest` passed with PHP 8.4.10 after the seed-data separation baseline: 2 tests, 12 assertions.
