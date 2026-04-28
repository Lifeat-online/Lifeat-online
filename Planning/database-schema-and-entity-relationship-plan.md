# Database Schema And Entity Relationship Plan

Primary sources:
- `platform-specification-source-of-truth.md`
- `clause-traceable-rebuild-matrix.md`
- `information-architecture-and-route-map.md`
- `public-page-by-page-rebuild-spec.md`

This document defines the target schema direction for the Laravel rebuild. It focuses on:
- entity boundaries
- table groups
- key fields
- lifecycle rules
- relationship logic
- SQL-first implementation priorities

It is intentionally clause-traceable and designed to guide actual migrations, models, policies, and reporting.

## 1. Schema Strategy

### SQL-First Core

The platform should use SQL as the system of record for:
- users
- content
- listings
- events
- classifieds
- packages
- orders
- payments
- invoices
- wallets
- payouts
- ad campaigns
- moderation
- settings
- audit logs

Clause trace:
- `2.a`, `3.a` to `3.f`, `4.a` to `4.d`, `5.a` to `5.d`, `6.b`, `7.a` to `7.c`, `8.a` to `8.d`, `9.a` to `9.f`, `11.d`

### Search / Read Optimization Layer

Geo-ranking and fast cross-domain search can later use:
- SQL indexes first
- dedicated search/index tables next
- external search engine later if needed

Clause trace:
- `2.b`, `3.f`, `4.d`, `6.b`, `10.b`, `11.a`

## 2. Table Groups

### Group A: Identity, Roles, Access
- `users`
- `roles`
- `permissions`
- `role_user`
- `permission_role`
- `user_profiles`
- `writer_profiles`
- `business_owner_profiles`
- `staff_profiles`

### Group B: Taxonomy And Location
- `categories`
- `tags`
- `location_nodes`
- `location_aliases`
- `geo_points`

### Group C: Content And Writer Compensation
- `articles`
- `article_category`
- `article_tag`
- `article_locations`
- `article_revisions`
- `article_word_ledgers`
- `writer_payment_batches`
- `writer_payment_batch_items`

### Group D: Business Directory
- `businesses`
- `business_categories`
- `business_tags`
- `business_social_links`
- `business_hours`
- `business_contacts`
- `business_media`
- `business_claims`

### Group E: Package And Subscription Layer
- `package_types`
- `packages`
- `package_prices`
- `subscriptions`
- `subscription_items`
- `entitlements`
- `renewal_reminders`

### Group F: Events
- `events`
- `event_categories`
- `event_tags`
- `event_media`
- `event_ticket_links`

### Group G: Advert And Push Monetisation
- `ad_inventory_slots`
- `ad_packages`
- `ad_campaigns`
- `ad_creatives`
- `ad_delivery_rules`
- `ad_impression_logs`
- `ad_click_logs`
- `push_campaigns`
- `push_campaign_targets`
- `push_open_logs`

### Group H: Classifieds
- `classifieds`
- `classified_categories`
- `classified_media`
- `classified_flags`
- `classified_moderation_actions`

### Group I: Commerce, Billing, Payment
- `carts`
- `cart_items`
- `orders`
- `order_items`
- `payments`
- `payment_attempts`
- `payment_tokens`
- `invoices`
- `invoice_items`
- `refunds`
- `manual_adjustments`

### Group J: Staff Wallet And Payouts
- `staff_wallets`
- `wallet_ledger_entries`
- `payout_requests`
- `payout_request_items`

### Group K: Admin, Settings, Audit, Reporting
- `settings`
- `setting_groups`
- `audit_logs`
- `kpi_daily_snapshots`
- `notification_logs`
- `export_jobs`

## 3. Core Entity Definitions

## 3.1 Users

### Table: `users`

Purpose:
- master identity for all authenticated users

Key fields:
- `id`
- `name`
- `email`
- `password`
- `email_verified_at`
- `status` (`active`, `inactive`, `suspended`)
- `default_location_node_id`
- `last_login_at`
- `created_at`
- `updated_at`

Notes:
- Keep role assignment separate from the `users` table via `roles` and pivots.
- This avoids hard-coding future platform roles into a single enum.

Clause trace:
- `2.a`, `7.a`, `9.f`, `11.d`

## 3.2 Roles

### Table: `roles`

Seeded roles:
- `super_admin`
- `content_manager`
- `sales_staff`
- `support`
- `writer`
- `business_owner`
- `registered_user`

### Table: `permissions`

Purpose:
- fine-grained policy and UI access control

### Pivot tables
- `role_user`
- `permission_role`

Clause trace:
- `9.f`, `11.d`

## 3.3 Writer Profile

### Table: `writer_profiles`

Purpose:
- store writer-specific settings and payout information

Key fields:
- `id`
- `user_id`
- `status`
- `per_word_rate_override`
- `bio`
- `notes`

Clause trace:
- `1.a`, `2.a`, `9.b`

## 3.4 Articles

### Table: `articles`

Purpose:
- published local news and article content

Key fields:
- `id`
- `user_id` (writer/author)
- `editor_user_id`
- `title`
- `slug`
- `excerpt`
- `body`
- `featured_image_id`
- `status` (`draft`, `pending_review`, `revision_requested`, `published`, `archived`)
- `published_at`
- `location_mode` (`global`, `single_location`, `multi_location`)
- `seo_title`
- `seo_description`
- `created_at`
- `updated_at`

Supporting pivots:
- `article_category`
- `article_tag`
- `article_locations`

Clause trace:
- `1.a`, `2.a`, `2.b`, `9.b`

## 3.5 Article Word Ledger

### Table: `article_word_ledgers`

Purpose:
- immutable payment ledger for writer compensation

Key fields:
- `id`
- `article_id`
- `writer_user_id`
- `approved_by_user_id`
- `word_count`
- `rate_per_word`
- `gross_amount`
- `status` (`pending`, `batched`, `paid`, `void`)
- `approved_at`
- `paid_at`

Rules:
- create only on approval/publish event
- preserve original word count and rate snapshot

Clause trace:
- `1.a`, `2.a`, `9.b`

## 3.6 Businesses

### Table: `businesses`

Purpose:
- core paid directory entity and advertising gateway

Key fields:
- `id`
- `owner_user_id` nullable
- `created_by_user_id`
- `source_channel` (`self_service`, `staff_assisted`, `admin_created`)
- `sales_staff_user_id` nullable
- `trading_name`
- `slug`
- `description_html`
- `address_line_1`
- `address_line_2`
- `suburb`
- `city`
- `region`
- `country`
- `postal_code`
- `location_node_id`
- `latitude`
- `longitude`
- `primary_phone`
- `secondary_phone`
- `email`
- `website_url`
- `status` (`draft`, `pending_review`, `active`, `expired`, `suspended`)
- `active_subscription_id` nullable
- `claimed_at`
- `published_at`

Supporting tables:
- `business_social_links`
- `business_hours`
- `business_contacts`
- `business_media`

Clause trace:
- `3.a`, `3.b`, `3.c`, `3.d`, `3.e`, `3.f`, `9.c`

## 3.7 Business Hours

### Table: `business_hours`

Purpose:
- store structured operating hours

Key fields:
- `business_id`
- `day_of_week`
- `opens_at`
- `closes_at`
- `is_closed`
- `notes`

Clause trace:
- `3.e`

## 3.8 Business Media

### Table: `business_media`

Purpose:
- logo, gallery, banners, other listing visuals

Key fields:
- `id`
- `business_id`
- `media_asset_id`
- `usage_type` (`logo`, `gallery`, `banner`, `thumbnail`)
- `sort_order`

Clause trace:
- `3.e`, `10.b`

## 3.9 Events

### Table: `events`

Purpose:
- paid events tied to active businesses

Key fields:
- `id`
- `business_id`
- `created_by_user_id`
- `title`
- `slug`
- `excerpt`
- `description_html`
- `venue_name`
- `address_line_1`
- `city`
- `region`
- `country`
- `location_node_id`
- `latitude`
- `longitude`
- `start_at`
- `end_at`
- `ticket_url`
- `ticket_price_text`
- `thumbnail_media_id`
- `banner_media_id`
- `status`
- `active_subscription_id`
- `published_at`

Rules:
- event cannot be published unless linked business has valid active entitlement

Clause trace:
- `4.a`, `4.b`, `4.c`, `4.d`, `9.c`

## 3.10 Classifieds

### Table: `classifieds`

Purpose:
- free public product and personal service listings

Key fields:
- `id`
- `user_id`
- `title`
- `slug`
- `description`
- `price`
- `currency`
- `contact_for_price`
- `category_id`
- `location_node_id`
- `city`
- `region`
- `country`
- `latitude`
- `longitude`
- `status` (`pending`, `published`, `hidden`, `flagged`, `rejected`)
- `published_at`
- `expires_at`

Supporting tables:
- `classified_media`
- `classified_flags`
- `classified_moderation_actions`

Clause trace:
- `7.a`, `7.b`, `7.c`

## 3.11 Package Types

### Table: `package_types`

Purpose:
- distinguish business, event, ad, push, and other monetised package families

Suggested values:
- `business_directory`
- `business_directory_self_service`
- `event_one_off`
- `event_monthly`
- `article_ad_slot`
- `banner_ad`
- `push_notification`

Clause trace:
- `3.b`, `3.c`, `4.b`, `5.a`, `5.b`, `6.a`

## 3.12 Packages

### Table: `packages`

Purpose:
- reusable sellable package definitions

Key fields:
- `id`
- `package_type_id`
- `code`
- `name`
- `billing_model` (`once_off`, `monthly`, `six_monthly`)
- `is_self_service`
- `duration_days`
- `status`
- `settings_json`

Notes:
- `settings_json` can store package-specific configuration, such as impression caps or push radius defaults.

Clause trace:
- `3.b`, `3.c`, `4.b`, `5.c`, `6.b`, `8.b`

## 3.13 Package Prices

### Table: `package_prices`

Purpose:
- track editable pricing over time

Key fields:
- `id`
- `package_id`
- `currency`
- `amount`
- `vat_inclusive`
- `effective_from`
- `effective_to`
- `created_by_user_id`

Clause trace:
- `3.b`, `4.b`, `5.d`, `9.a`

## 3.14 Subscriptions / Entitlements

### Table: `subscriptions`

Purpose:
- recurring or time-bound package ownership record

Key fields:
- `id`
- `user_id`
- `package_id`
- `subscribable_type`
- `subscribable_id`
- `status` (`pending`, `active`, `past_due`, `cancelled`, `expired`)
- `starts_at`
- `ends_at`
- `renews_at`
- `renewal_mode`
- `payment_token_id`

### Table: `entitlements`

Purpose:
- simplified “is active?” layer for business/event/ad eligibility checks

Key fields:
- `id`
- `subscription_id`
- `entitled_type`
- `entitled_id`
- `entitlement_code`
- `active_from`
- `active_until`
- `status`

Clause trace:
- `3.a`, `4.a`, `4.b`, `5.c`, `8.b`, `8.d`

## 3.15 Orders

### Table: `orders`

Purpose:
- commercial purchase container

Key fields:
- `id`
- `user_id`
- `order_number`
- `status`
- `currency`
- `subtotal`
- `vat_amount`
- `discount_amount`
- `total`
- `invoice_id`
- `placed_at`

### Table: `order_items`

Purpose:
- individual package or service lines

Key fields:
- `id`
- `order_id`
- `package_id`
- `purchasable_type`
- `purchasable_id`
- `name_snapshot`
- `unit_price`
- `quantity`
- `billing_model`
- `starts_at`
- `ends_at`

Clause trace:
- `8.a`, `8.b`, `8.c`, `9.e`

## 3.16 Payments

### Table: `payments`

Purpose:
- logical payment record linked to an order

Key fields:
- `id`
- `order_id`
- `user_id`
- `provider` (`payfast`)
- `status`
- `amount`
- `currency`
- `provider_transaction_id`
- `paid_at`
- `failure_reason`

### Table: `payment_attempts`

Purpose:
- detailed retry/failure tracking

Key fields:
- `id`
- `payment_id`
- `attempt_number`
- `request_payload_json`
- `response_payload_json`
- `status`
- `attempted_at`

### Table: `payment_tokens`

Purpose:
- store safe token references for recurring billing

Key fields:
- `id`
- `user_id`
- `provider`
- `provider_token`
- `masked_reference`
- `status`
- `last_used_at`

Security note:
- do not store raw card data

Clause trace:
- `8.a`, `8.b`, `8.c`, `8.d`, `11.d`

## 3.17 Invoices

### Table: `invoices`

Purpose:
- customer-facing tax/accounting record

Key fields:
- `id`
- `order_id`
- `invoice_number`
- `invoice_prefix_snapshot`
- `status`
- `currency`
- `subtotal`
- `vat_amount`
- `total`
- `issued_at`
- `due_at`
- `emailed_at`

### Table: `invoice_items`

Purpose:
- line-level invoice detail

Clause trace:
- `8.c`, `9.a`, `9.e`

## 3.18 Refunds And Manual Adjustments

### Table: `refunds`

Purpose:
- track customer refunds

### Table: `manual_adjustments`

Purpose:
- track admin overrides and package extensions

Key fields:
- actor
- target entity
- reason
- before/after values
- effective dates

Clause trace:
- `8.d`, `9.e`, `11.d`

## 3.19 Staff Wallet

### Table: `staff_wallets`

Purpose:
- balance summary per sales staff user

Key fields:
- `id`
- `user_id`
- `currency`
- `available_balance`
- `pending_balance`
- `paid_out_total`

### Table: `wallet_ledger_entries`

Purpose:
- immutable commission and payout ledger

Key fields:
- `id`
- `wallet_id`
- `entry_type` (`commission_credit`, `payout_debit`, `adjustment`)
- `source_type`
- `source_id`
- `gross_amount`
- `net_amount`
- `status`
- `recorded_at`

### Table: `payout_requests`

Purpose:
- payout request lifecycle

Key fields:
- `id`
- `wallet_id`
- `requested_by_user_id`
- `approved_by_user_id`
- `amount`
- `status` (`requested`, `approved`, `paid`, `rejected`, `cancelled`)
- `requested_at`
- `paid_at`
- `notes`

Rules:
- 50% of staff-assisted revenue should credit the staff wallet
- payout is only complete when admin marks it paid

Clause trace:
- `3.d`, `9.e`, `9.f`

## 3.20 Ad Inventory

### Table: `ad_inventory_slots`

Purpose:
- define the sellable placement inventory

Examples:
- homepage_header
- article_paragraph_1
- article_mid
- article_end
- sidebar_banner
- sticky_mobile_footer
- interstitial

Key fields:
- `slot_code`
- `slot_type`
- `page_scope`
- `default_dimensions`
- `supports_geo_targeting`

Clause trace:
- `5.a`, `5.b`, `5.c`

## 3.21 Ad Campaigns

### Table: `ad_campaigns`

Purpose:
- purchased campaign instance

Key fields:
- `business_id`
- `event_id` nullable
- `package_id`
- `status`
- `start_at`
- `end_at`
- `pricing_model` (`cpm`, `fixed`)
- `impression_limit`
- `geo_radius_km`
- `location_node_id`

### Table: `ad_creatives`

Purpose:
- actual uploaded ad assets and copy

### Tables: `ad_impression_logs`, `ad_click_logs`

Purpose:
- analytics and billing evidence

Clause trace:
- `5.a`, `5.b`, `5.c`, `5.d`, `9.d`, `11.e`

## 3.22 Push Campaigns

### Table: `push_campaigns`

Purpose:
- premium push-notification promotion

Key fields:
- `business_id`
- `event_id` nullable
- `created_by_user_id`
- `title`
- `message`
- `schedule_at`
- `status`
- `geo_radius_km`
- `location_node_id`

### Table: `push_campaign_targets`

Purpose:
- saved audience targeting

Fields:
- interest tags
- geo rules
- audience counts

### Table: `push_open_logs`

Purpose:
- open-rate tracking

Clause trace:
- `6.a`, `6.b`, `9.a`

## 3.23 Settings

### Table: `settings`

Purpose:
- store editable admin-configurable platform values

Recommended keys:
- `writer.per_word_rate`
- `pricing.business_directory_6m`
- `pricing.business_directory_self_service_6m`
- `pricing.event_one_off`
- `pricing.event_monthly`
- `pricing.push_notification`
- `pricing.advert.*`
- `geo.default_radius_km`
- `geo.fallback_radius_km`
- `billing.vat_percentage`
- `billing.invoice_prefix`

Fields:
- `key`
- `value`
- `type`
- `group`
- `updated_by_user_id`

Clause trace:
- `3.b`, `4.b`, `5.d`, `6.b`, `9.a`

## 3.24 Audit Logs

### Table: `audit_logs`

Purpose:
- system-wide compliance and change history

Key fields:
- `actor_user_id`
- `action`
- `subject_type`
- `subject_id`
- `before_json`
- `after_json`
- `ip_address`
- `user_agent`
- `created_at`

Clause trace:
- `11.d`, `9.e`

## 4. Location And Geo Design

## 4.1 Location Nodes

### Table: `location_nodes`

Purpose:
- canonical hierarchy for places such as town, region, district

Key fields:
- `id`
- `name`
- `slug`
- `parent_id`
- `type`
- `latitude`
- `longitude`

Clause trace:
- `2.b`, `3.f`, `4.d`, `6.b`

## 4.2 Geo Points

### Table: `geo_points`

Purpose:
- normalized lat/lng coordinates for distance calculations

Key fields:
- `pointable_type`
- `pointable_id`
- `latitude`
- `longitude`
- `geohash`

Notes:
- can be denormalized for businesses, events, classifieds if simpler for v1

Clause trace:
- `3.f`, `4.d`

## 5. Relationship Summary

## Core Relationships
- A `user` can be a writer, business owner, staff user, or support/admin through roles.
- A `writer_profile` belongs to one `user`.
- A `business` may belong to an owner user and may also be created by a sales staff user.
- A `business` must have an active business-directory `subscription` before it can advertise or create events.
- An `event` belongs to one `business`.
- An `article_word_ledger` belongs to one article and one writer.
- An `order` has many `order_items`, `payments`, and one or more `invoices`.
- A `staff_wallet` belongs to one staff user and has many immutable `wallet_ledger_entries`.
- A `payout_request` belongs to a wallet and references wallet ledger items or balances.
- A `classified` belongs to one user and can have many media items and moderation actions.
- An `ad_campaign` belongs to a business and optionally an event.
- A `push_campaign` belongs to a business and optionally an event.

## 6. Lifecycle Rules

## Business Lifecycle
- Draft -> Pending Review -> Active -> Expired / Suspended
- Activation depends on successful payment and package entitlement

Clause trace:
- `3.a`, `3.b`, `3.c`, `8.c`, `8.d`

## Event Lifecycle
- Draft -> Pending Review -> Active -> Expired / Cancelled
- Publication blocked if linked business entitlement is inactive

Clause trace:
- `4.a`, `4.b`, `4.c`

## Writer Payment Lifecycle
- Article submitted -> reviewed -> approved/published -> word ledger created -> batched -> paid

Clause trace:
- `2.a`, `9.b`

## Staff Commission Lifecycle
- Staff-assisted sale closes -> commission ledger credit created -> wallet available -> payout requested -> admin paid

Clause trace:
- `3.d`, `9.e`

## Commerce Lifecycle
- Cart -> Order -> Payment attempt -> Payment success/failure -> Invoice -> Subscription/entitlement -> Renewal / Refund / Override

Clause trace:
- `8.a`, `8.b`, `8.c`, `8.d`

## 7. SQL Indexing Priorities

High-priority indexes:
- `users.email`
- `roles.slug`
- `articles.slug`, `articles.status`, `articles.published_at`
- article category/tag pivot indexes
- `businesses.slug`, `businesses.status`, `businesses.location_node_id`
- `events.slug`, `events.status`, `events.start_at`, `events.business_id`
- `classifieds.slug`, `classifieds.status`, `classifieds.location_node_id`
- `subscriptions.status`, `subscriptions.ends_at`, `subscriptions.package_id`
- `orders.order_number`, `orders.status`
- `payments.provider_transaction_id`, `payments.status`
- `invoices.invoice_number`, `invoices.status`
- `wallet_ledger_entries.wallet_id`, `wallet_ledger_entries.status`
- `ad_campaigns.status`, `ad_campaigns.start_at`, `ad_campaigns.end_at`
- `push_campaigns.status`, `push_campaigns.schedule_at`
- `audit_logs.actor_user_id`, `audit_logs.subject_type`, `audit_logs.subject_id`

Clause trace:
- `10.b`, `11.e`

## 8. V1 Migration Priority

### Build First
- users, roles, permissions
- settings
- articles, article ledgers
- businesses, business media/hours/socials
- packages, package prices, subscriptions, entitlements
- events
- orders, payments, invoices
- staff wallets, wallet ledger, payout requests
- classifieds and moderation basics

Clause trace:
- `1.a`, `2.a`, `3.a` to `3.f`, `4.a` to `4.d`, `7.a` to `7.c`, `8.a` to `8.d`, `9.a` to `9.f`

### Build Second
- ad inventory, campaigns, creatives, delivery logs
- push campaigns and open logs
- KPI snapshots
- audit detail expansion

Clause trace:
- `5.a` to `5.d`, `6.a`, `6.b`, `11.d`, `11.e`

## 9. Open Design Decisions

- Whether services remain a separate public type or fold into classifieds
- Whether retained shop/product pages survive as ecommerce or are retired
- Whether article locations use a pivot table only or also denormalized location summary fields
- Whether ad analytics logs remain in SQL or later stream into a warehouse/search store
- Whether recurring billing state is tracked entirely internally or partially delegated to PayFast metadata

These should be resolved before final migration authoring.
