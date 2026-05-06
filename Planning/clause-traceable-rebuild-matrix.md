# Clause-Traceable Rebuild Matrix

This document converts the source-of-truth platform specification and the verified WordPress public-page inventory into a rebuild matrix that can guide implementation.

Primary sources:
- `platform-specification-source-of-truth.md`
- `public-facing-pages-inventory.md`

## Matrix Legend

Decision meanings:
- `Rebuild` = create as a dedicated Laravel feature/page/module.
- `Merge` = combine multiple WordPress pages into a cleaner Laravel flow.
- `Defer` = keep in scope, but plan after the core revenue engine is working.
- `Review` = requires discovery before implementation because the WordPress purpose is unclear or duplicated.

Priority meanings:
- `P1` = required for the business model and first production release.
- `P2` = important for the broader platform, but after core revenue/content flows.
- `P3` = lower priority or dependent on previous modules.

## 0. Current Laravel Execution Snapshot

The matrix below remains valid as a scope-and-traceability planning tool, but execution has progressed materially beyond the point where every row is only theoretical.

Already live in Laravel at baseline level:
- homepage, directory archive/detail, events archive/detail
- article archive/detail plus author/category/tag/location archive surfaces
- unified search
- advertise-with-us and add-listing acquisition funnel
- contact, terms, privacy, writer/staff signup, public auth entry, and account hub
- classifieds public/archive/detail, submission/editing, and admin moderation
- customer lookup, support notes, support-role dashboard/access controls, and account history tools
- owner listing, event, advert, and push self-service flows
- checkout, payments, invoices, renewals, finance recovery tooling, and push dispatch logging baseline

Partially live:
- advert and push monetisation operations beyond self-service purchase, including approval, delivery analytics, and open-rate tracking
- staff-assisted sales economics beyond applicant onboarding, especially wallet and payout flows
- retained non-core public flows still awaiting product decisions

## 1. Public Page Rebuild Matrix

| WordPress Page / View Type | Laravel Target | Decision | Priority | Reason | Clause Trace |
|---|---|---|---|---|---|
| Front page / homepage | Location-aware home page with news, directory highlights, events, ads, and CTAs | Rebuild | P1 | The site needs a public front door for content, discovery, and monetisation | `1.a`, `2.b`, `3.f`, `4.d`, `5.a`, `5.b`, `10.a`, `10.c` |
| Article single page | SEO article detail page with ads and writer attribution | Rebuild | P1 | Paid content production depends on article publishing and monetisation | `1.a`, `2.a`, `2.b`, `5.a`, `5.c` |
| Article category archive | Article archive by category | Rebuild | P1 | Public content discovery must support category filtering | `2.b` |
| Article tag archive | Article archive by tag | Rebuild | P1 | Public content discovery must support tag filtering | `2.b` |
| Article location archive/filter | Article archive filtered by location | Rebuild | P1 | The specification explicitly requires location filtering on published articles | `2.b` |
| Author archive | Public author / writer page | Rebuild | P2 | Supports writer identity and content discovery, but not core revenue first | `2.a`, `2.b` |
| Search results page | Unified public search results with content-type filters | Rebuild | P1 | Users must discover businesses, events, content, classifieds, and services efficiently | `2.b`, `3.f`, `4.d`, `7.b`, `10.a` |
| About us | Corporate information page | Rebuild | P2 | Supports trust and public information | `10.a` |
| Terms and conditions | Legal terms page | Rebuild | P1 | Required for payments and compliance | `8.a`, `11.d` |
| Business directory archive | Geo-ranked business directory | Rebuild | P1 | Primary revenue gateway and core commercial directory experience | `3.a`, `3.b`, `3.c`, `3.d`, `3.f` |
| Business directory single page | Business detail page with contact data, media, hours, social links, and rich content | Rebuild | P1 | Required to fulfill listing value and advertiser exposure | `3.e`, `3.f`, `5.b` |
| Business category archive | Directory archive filtered by category | Rebuild | P1 | Essential directory navigation pattern | `3.e`, `3.f` |
| Business tag archive | Directory archive filtered by tags | Rebuild | P2 | Useful discovery enhancement, but secondary to core category/location | `3.f` |
| Add listing page | Business listing purchase + onboarding flow | Rebuild | P1 | Mandatory first step for advertisers starts here | `3.a`, `3.b`, `3.c`, `3.d`, `8.a`, `8.b`, `10.d` |
| Advertise with us | Advertising package landing page | Rebuild | P1 | Needed to explain directory-first advertising model and package upsell | `1.a`, `3.a`, `5.a`, `5.b`, `5.c`, `6.a` |
| Events archive | Geo-ranked event listing page | Rebuild | P1 | Core paid add-on for businesses with active directory packages | `4.a`, `4.b`, `4.d` |
| Event single page | Event detail page with organiser linkage and ticket info | Rebuild | P1 | Needed to sell and expose events | `4.c`, `4.d` |
| Event category archive | Event archive filtered by category | Rebuild | P2 | Supports event discovery | `4.d` |
| Event tag archive | Event archive filtered by tag | Rebuild | P2 | Supports event discovery | `4.d` |
| Basket / cart | Commerce cart page | Merge | P1 | Use a unified checkout basket for directory, event, advert, and other paid packages | `3.a`, `4.b`, `5.c`, `8.a`, `8.b`, `10.d` |
| Checkout | Multi-step checkout with guidance | Rebuild | P1 | Central payment and subscription activation flow | `8.a`, `8.b`, `8.c`, `10.d`, `10.e` |
| My account / customer account | Public account entry and self-service billing area | Merge | P1 | Consolidate account entry into a single Laravel account area | `3.c`, `8.c`, `8.d`, `9.e` |
| Marketplace landing | Free classifieds landing page | Rebuild | P1 | Explicitly required as a free public classifieds section | `7.a`, `7.b`, `7.c` |
| Marketplace product list | Classified listings archive | Rebuild | P1 | Public browsing of free classifieds | `7.a`, `7.b` |
| Marketplace product single | Classified detail page | Rebuild | P1 | Public access to item/service listings is core to classifieds | `7.b` |
| Marketplace search | Classified-specific search/filter page | Merge | P2 | Can be folded into unified search plus classifieds filters | `7.b`, `10.a` |
| Sell / submit product | Classified submission flow | Rebuild | P1 | Users must be able to post products/services for free | `7.a`, `7.b`, `7.c` |
| Product management | User classified management dashboard | Rebuild | P2 | Self-service management is required after free posting exists | `7.a`, `7.c` |
| Services listing | Services directory / archive | Rebuild | P2 | The live site exposes services; likely converges with classifieds or business upsells | `7.a`, `7.b`, `10.a` |
| Service detail | Service single page | Rebuild | P2 | Required if services remain a distinct public content type | `7.b` |
| Service search | Service search page | Merge | P2 | Can likely merge into unified search | `7.b` |
| Service category archive | Service category page | Rebuild | P3 | Only if services remain separate from classifieds and directory | `7.b` |
| Vouchers landing | Voucher / deal landing page | Defer | P3 | Publicly visible and monetisable, but not called out in the current core specification | `1.a`, `5.c` |
| Voucher category archive | Voucher category listing pages | Defer | P3 | Same as above; maintain in scope for discovery | `1.a`, `5.c` |
| Shop landing | Commercial shop/archive | Review | P3 | Need business decision whether WooCommerce-like shop remains separate from classifieds | `1.a`, `7.a`, `8.a` |
| Product single (commercial) | Paid product detail page | Review | P3 | Need scope decision: keep as ecommerce, convert to classifieds, or remove | `1.a`, `8.a`, `8.b` |
| Product category archive | Shop category pages | Review | P3 | Depends on ecommerce scope decision | `1.a`, `8.a` |
| Vendor profile | Vendor public profile page | Review | P3 | May merge into business directory owner profile | `3.e`, `3.f` |
| Public login page | Visitor login | Rebuild | P1 | Needed for writers, classifieds users, businesses, staff, and support roles | `2.a`, `7.a`, `9.f` |
| Public registration page | Registration and role-entry flow | Rebuild | P1 | Must support user onboarding into the platform ecosystem | `2.a`, `7.a`, `9.f` |
| Writer/staff signup page | Guided staff or writer application flow | Rebuild | P2 | Needed because staff and writers have distinct earning workflows | `1.a`, `2.a`, `3.d`, `9.b`, `9.f` |
| Staff dashboard public entry page | Staff landing / login redirect | Merge | P2 | Merge into unified authenticated role-based dashboard system | `3.d`, `9.b`, `9.f` |
| Staff payments page | Staff earnings / payout request page | Rebuild | P1 | Critical to staff wallet and payout workflow | `3.d`, `9.b` |
| Editor dashboard public entry page | Content-manager landing / login redirect | Merge | P2 | Use unified role-aware dashboard | `2.a`, `9.f` |
| Messages page | Internal messaging / communication page | Defer | P3 | Useful, but not directly required by the spec | `10.a` |
| Transport booking pages | Transport booking flow pages | Review | P3 | Live site has them, but the current source-of-truth spec does not require them | `1.a` |
| Navigation test / sample pages / simple nav test | QA or temporary content pages | Remove from rebuild scope | P3 | Not part of the product requirement | N/A |
| 404 page | Custom public 404 page | Rebuild | P2 | Required public UX surface | `10.a`, `10.c` |

## 2. Public Flow Consolidation Decisions

These WordPress pages should be consolidated into clearer Laravel flows:

| WordPress Pages | Laravel Consolidation | Decision | Clause Trace |
|---|---|---|---|
| `basket`, `checkout`, `my-account`, `customer-account` | One billing/account domain with checkout, invoices, subscriptions, and package renewals | Merge | `8.a`, `8.b`, `8.c`, `8.d`, `9.e`, `10.d` |
| `staff-dashboard`, `editor-dashboard`, `vendor-dashboard`, `shop-dashboard`, `driver-dashboard` | One authenticated dashboard shell with role-based modules | Merge | `9.f`, `10.a` |
| `marketplace`, `marketplace-products`, `product-marketplace`, `sell`, `submit-product`, `product-management` | One classifieds domain with browse, create, edit, moderate, and manage flows | Merge | `7.a`, `7.b`, `7.c` |
| `service-listings`, `service-detail`, `service-search` | Either merge into classifieds or keep as a dedicated services domain after scope confirmation | Review | `7.a`, `7.b` |
| `add-listing`, `advertise-with-us`, event purchase pages | One advertiser acquisition funnel with package selection and upsell logic | Merge | `3.a`, `4.a`, `5.c`, `6.a`, `8.a` |

Current status note:
- the account-area consolidation, classifieds-domain consolidation, and advertiser acquisition funnel are already active in Laravel at baseline level
- service-search and retained shop/transport consolidation decisions are still open

## 3. Admin Module Matrix

| Admin Module | Primary Responsibility | Priority | Clause Trace |
|---|---|---|---|
| Global settings | Manage pricing, writer rates, VAT, invoice prefix, geo defaults, notification fee | P1 | `3.b`, `4.b`, `5.d`, `6.b`, `9.a` |
| Writer management | Review writer accounts, word counts, ledgers, and exports | P1 | `1.a`, `2.a`, `9.b` |
| Article moderation | Approve, reject, revise, publish, and classify content | P1 | `2.a`, `2.b`, `9.b`, `9.f` |
| Business management | CRUD businesses, package status, expiry, renewal, geo overrides | P1 | `3.a`, `3.d`, `3.e`, `3.f`, `9.c` |
| Event management | CRUD events, verify business eligibility, manage bundles and expiry | P1 | `4.a`, `4.b`, `4.c`, `4.d`, `9.c` |
| Advert management | Approve creatives, schedule, monitor impressions/clicks, pause campaigns | P1 | `5.a`, `5.b`, `5.c`, `9.d` |
| Push notification management | Compose, segment, schedule, and monitor push campaigns | P1 | `6.a`, `6.b` |
| Classified moderation | Flag, hide, approve, and review user-submitted listings | P1 | `7.a`, `7.b`, `7.c` |
| Payments dashboard | View transactions, failures, refunds, overrides, renewals | P1 | `8.a`, `8.b`, `8.c`, `8.d`, `9.e` |
| Invoice dashboard | Generate, resend, export, and sync invoices | P1 | `8.c`, `9.e` |
| Staff wallet and payouts | Accrue commission, payout requests, mark paid | P1 | `3.d`, `9.e` |
| Role and permission management | Enforce Super-Admin, Content-Manager, Sales-Staff, Support access | P1 | `9.f` |
| KPI and monitoring dashboard | Track revenue, failed payments, DAU, uptime, campaign metrics | P2 | `9.d`, `11.e` |
| Audit log viewer | Review security and admin actions | P2 | `11.d` |

Current status note:
- settings, writer management, article moderation, business/event management, classifieds moderation, payments/invoices/renewals, and support workflows are live at baseline level
- advert management and push notification management are partially delivered with baseline approval controls, dispatch controls, and counters in place; richer analytics and reporting remain outstanding
- staff wallet and payouts are delivered at baseline level (commission credit, wallet view, payout request lifecycle, admin processing)

## 4. Core Entity Matrix

| Entity | Why It Exists | Key Relationships | Priority | Clause Trace |
|---|---|---|---|---|
| User | Foundation for writers, businesses, staff, support, and public accounts | Owns submissions, listings, wallets, orders, invoices | P1 | `2.a`, `7.a`, `9.f` |
| Role / permission set | Controls access boundaries | Assigned to users | P1 | `9.f`, `11.d` |
| Writer profile | Stores writer-specific payment and editorial metadata | Belongs to user, links to articles and word ledger | P1 | `1.a`, `2.a`, `9.b` |
| Article | Published content monetised with ads | Authored by user, belongs to categories/tags/locations | P1 | `1.a`, `2.a`, `2.b`, `5.a` |
| Article word ledger entry | Tracks payable word counts | Belongs to article and writer | P1 | `2.a`, `9.b` |
| Business listing | Primary advertiser record and package entry point | Belongs to owner/staff, has package, ads, events | P1 | `3.a`, `3.e`, `3.f` |
| Business package / subscription | Tracks active directory entitlement and renewals | Belongs to business and order/payment | P1 | `3.a`, `3.b`, `3.c`, `8.b` |
| Event | Paid add-on linked to active business | Belongs to business, package, organiser | P1 | `4.a`, `4.c`, `4.d` |
| Event package / subscription | Tracks event package entitlement | Belongs to event/business and order/payment | P1 | `4.b`, `8.b` |
| Advert package | Defines sellable ad inventory | Used by campaigns and pricing settings | P1 | `5.a`, `5.b`, `5.c`, `5.d` |
| Advert campaign | Active purchased campaign | Belongs to business/event/package | P1 | `5.c`, `9.d` |
| Advert creative | Stores artwork/copy for approval | Belongs to campaign | P1 | `9.d` |
| Push campaign | Premium notification campaign | Belongs to business/event and targets audience | P1 | `6.a`, `6.b` |
| Classified listing | Free public product/service listing | Belongs to user, categories, media | P1 | `7.a`, `7.b`, `7.c` |
| Classified moderation action | Tracks approve/hide/flag lifecycle | Belongs to classified and moderator | P1 | `7.c` |
| Order | Commercial transaction container | Has line items, payments, invoices | P1 | `8.a`, `8.b`, `8.c` |
| Payment | PayFast transaction record | Belongs to order | P1 | `8.a`, `8.c`, `8.d` |
| Payment token | Recurring billing token metadata | Belongs to user/payment method | P1 | `8.b`, `11.d` |
| Invoice | Accounting and customer billing record | Belongs to order/payment | P1 | `8.c`, `9.e` |
| Refund / override record | Tracks manual finance interventions | Belongs to payment/order/admin | P1 | `8.d`, `9.e` |
| Staff wallet | Accrues staff commission earnings | Belongs to staff user | P1 | `3.d` |
| Payout request | Staff payout request and admin status | Belongs to wallet and admin action | P1 | `3.d` |
| Category / taxonomy | Organises content, listings, events, classifieds | Shared across content domains where needed | P1 | `2.b`, `3.e`, `4.c`, `7.b` |
| Tag | Supports article and event/place discovery | Belongs to content/listings/events | P2 | `2.b`, `4.d` |
| Location node | Normalised geo target and location selection | Links to listings, events, articles, audiences | P1 | `2.b`, `3.f`, `4.d`, `6.b` |
| Geo point / search index | Supports proximity sorting and geo filtering | Links to listings, events, audiences | P1 | `3.f`, `4.d` |
| Media asset | Shared image/gallery/banner/logo storage | Belongs polymorphically to multiple domains | P1 | `3.e`, `4.c`, `7.b`, `10.b` |
| Audit log | Security and compliance event tracking | Belongs to users/admins/objects | P2 | `11.d` |
| KPI snapshot | Business metrics and operational dashboards | Feeds admin analytics | P2 | `11.e` |

## 5. Workflow Matrix

| Workflow | Outcome | Priority | Clause Trace |
|---|---|---|---|
| Writer submits article -> content review -> publish -> ledger accrual -> payment batch | Paid local content production | P1 | `1.a`, `2.a`, `2.b`, `9.b` |
| Advertiser buys business package -> listing onboarding -> publish -> renew | Primary commercial funnel | P1 | `3.a`, `3.b`, `3.c`, `3.d`, `8.a`, `8.b` |
| Staff captures business lead -> admin/staff creates listing -> wallet commission accrues -> payout request | Staff-assisted sales revenue and job creation | P1 | `3.d`, `9.c`, `9.e` |
| Active business buys event package -> event publish -> geo-ranked discovery | Conditional event upsell | P1 | `4.a`, `4.b`, `4.c`, `4.d` |
| Business buys ad slot/banner package -> creative approval -> scheduled serving -> impression/click tracking | Core ad monetisation | P1 | `5.a`, `5.b`, `5.c`, `9.d` |
| Business buys push campaign -> audience targeting -> schedule -> open-rate tracking | Highest-tier promotion | P1 | `6.a`, `6.b` |
| User submits free classified -> moderation -> publish/hide/flag | Free user acquisition and classifieds loop | P1 | `7.a`, `7.b`, `7.c` |
| User purchases package -> PayFast payment -> invoice generation -> renewal reminders | Commercial transaction lifecycle | P1 | `8.a`, `8.b`, `8.c`, `9.e` |
| Admin refunds, overrides, or extends package | Manual recovery and support process | P1 | `8.d`, `9.e`, `9.f` |
| User searches by category/tag/location and gets geo-ranked results | Core public discovery UX | P1 | `2.b`, `3.f`, `4.d`, `10.a` |

Current status note:
- writer submission -> review -> publish -> ledger accrual is live
- business package -> payment -> activation -> listing visibility is live
- event upsell purchase -> publish flow is live
- classifieds submission -> moderation -> publish/hide/flag is live
- unified public search is live
- push workflow is partially live through purchase, targeting fields, schedule handling, dispatch logging, and baseline open counter; richer open telemetry and reporting are still outstanding
- staff commission and payout flow is live at baseline level

## 6. Role Matrix

| Role | Public Capabilities | Admin / Backoffice Capabilities | Priority | Clause Trace |
|---|---|---|---|---|
| Visitor | Browse content, directory, events, ads, classifieds, and search | None | P1 | `2.b`, `3.f`, `4.d`, `7.a`, `10.a` |
| Registered user | Create classifieds, manage own account, purchase packages, optionally become writer/business owner | Limited self-service account actions | P1 | `7.a`, `8.c`, `10.d` |
| Writer | Submit articles, track word counts and payouts | Writer dashboard and ledger visibility | P1 | `1.a`, `2.a`, `9.b` |
| Business owner | Buy packages, manage listing, manage events, run adverts, request upgrades | Self-service business and campaign management | P1 | `3.c`, `4.a`, `5.c`, `6.a`, `8.b` |
| Sales-Staff | Capture leads, create assisted listings, manage owned pipeline, earn wallet commission | Sales-specific business creation and wallet tools | P1 | `3.d`, `9.f` |
| Content-Manager | Review, edit, publish articles and possibly listings/events depending policy | Content moderation and publication controls | P1 | `2.a`, `9.b`, `9.f` |
| Support | Resolve customer issues, inspect payments, assist package/account handling | Restricted support tools | P1 | `8.d`, `9.e`, `9.f` |
| Super-Admin | Full platform access | Pricing, permissions, payouts, compliance, monitoring, overrides | P1 | `9.a`, `9.c`, `9.d`, `9.e`, `9.f`, `11.d`, `11.e` |

## 7. Release Slice Recommendation

### Release 1: Revenue And Publishing Core

Current status:
- broadly delivered at baseline level

Built in Laravel:
- Public home, article, directory, event, and search experience
- Writer workflow and payment ledger
- Business package sales and listing publication
- Event package sales gated by active business package
- PayFast checkout, invoices, renewals
- Admin settings, business/event management, and writer management

Clause anchor:
- `1.a`, `2.a`, `2.b`, `3.a` to `3.f`, `4.a` to `4.d`, `8.a` to `8.d`, `9.a` to `9.f`, `10.a` to `10.e`

### Release 2: Advert Monetisation Layer

Current status:
- partially delivered

Built in Laravel so far:
- Advert campaign purchasing and owner/admin self-service, including baseline impression and click tracking
- Push-notification campaign purchasing, owner/admin self-service, dispatch logging baseline, and baseline open counter

Still to build:
- final ad inventory/placement policy decisions if more slot types are needed
- richer campaign analytics and reporting beyond baseline counters
- deeper push open telemetry and reporting beyond baseline counter

Clause anchor:
- `5.a` to `5.d`, `6.a`, `6.b`, `11.e`

### Release 3: Classifieds, Extended Commerce, And Non-Core Public Flows

Current status:
- partially delivered

Built in Laravel so far:
- Free classifieds

Still to build or confirm:
- Services decision path
- Vouchers
- Any retained marketplace/shop features
- Any retained transport-related pages

Clause anchor:
- `7.a` to `7.c`, plus any retained business decisions outside the current spec

## 8. Planning Output Dependencies

The next planning documents should be produced in this order:

1. Information architecture and route map
2. Public page-by-page rebuild specification
3. Database schema and entity relationship plan
4. Billing, package, invoice, and payout architecture
5. Admin module architecture
6. API surface and auth strategy
7. Test, deployment, monitoring, and compliance plan

Each of those documents must continue to reference the clause numbers from:
- `platform-specification-source-of-truth.md`
