# Billing, Package, Invoice, And Payout Architecture

Primary sources:
- `platform-specification-source-of-truth.md`
- `clause-traceable-rebuild-matrix.md`
- `database-schema-and-entity-relationship-plan.md`
- `public-page-by-page-rebuild-spec.md`

This document defines how monetisation works operationally across:
- business directory packages
- event packages
- advert packages
- push-notification campaigns
- invoices
- PayFast payments
- recurring renewals
- staff wallet accrual
- payout requests
- refunds, overrides, and extensions

## 1. Billing Principles

## 1.1 Directory-First Rule

No advertiser may buy:
- an event package
- an advert package
- a push campaign

unless they already hold an active business directory entitlement.

Clause trace:
- `3.a`, `4.a`, `6.a`

## 1.2 Package Types In Scope

The monetised package families are:
- Business Directory standard 6-month package
- Business Directory self-service 6-month package
- Staff-assisted business package
- Event one-off package
- Event monthly recurring package
- In-article advert packages
- Banner advert packages
- Push-notification package

Clause trace:
- `3.b`, `3.c`, `3.d`, `4.b`, `5.a`, `5.b`, `6.a`

## 1.3 Billing Models

Supported billing models:
- once-off
- monthly recurring
- six-month recurring

Clause trace:
- `8.b`

## 2. Package Catalogue

## 2.1 Business Directory Standard

### Package Code
- `business_directory_standard_6m`

### Billing
- six-month recurring or fixed six-month term depending final operational policy

### Default Price
- R500 every 6 months

### Buyer Profile
- business wants presence but does not necessarily self-manage listing

### Rights Granted
- active directory listing entitlement
- eligibility to buy event, advert, and push packages

Clause trace:
- `3.a`, `3.b`, `3.e`, `3.f`

## 2.2 Business Directory Self-Service

### Package Code
- `business_directory_self_service_6m`

### Billing
- six-month recurring or fixed six-month term

### Default Price
- R750 every 6 months

### Buyer Profile
- business creates and edits its own listing

### Rights Granted
- active directory listing entitlement
- self-service editing rights
- eligibility for event, advert, and push upsells

Clause trace:
- `3.c`

## 2.3 Business Directory Staff-Assisted

### Package Code
- `business_directory_staff_assisted_6m`

### Billing
- six-month recurring or fixed six-month term

### Price
- lower-cost option defined in admin settings

### Buyer Profile
- listing captured by staff through field sales, WhatsApp, phone, or form

### Rights Granted
- active directory listing entitlement
- platform records staff commission against revenue

Clause trace:
- `3.d`, `9.a`

## 2.4 Event One-Off

### Package Code
- `event_one_off`

### Billing
- once-off

### Eligibility
- linked business must have active business entitlement

### Rights Granted
- publish one event within defined active window

Clause trace:
- `4.a`, `4.b`

## 2.5 Event Monthly

### Package Code
- `event_monthly_bundle`

### Billing
- monthly recurring

### Eligibility
- linked business must have active business entitlement

### Rights Granted
- publish events according to monthly bundle rules

Clause trace:
- `4.a`, `4.b`

## 2.6 Ad Packages

### Families
- in-article slots
- banners

### Billing
- once-off fixed fee or CPM-backed package definition

### Controls
- date range
- impression cap
- geo targeting
- slot restrictions

Clause trace:
- `5.a`, `5.b`, `5.c`, `5.d`

## 2.7 Push Package

### Package Code
- `push_notification_campaign`

### Billing
- once-off

### Controls
- scheduled send time
- geo radius
- interest-tag targeting

Clause trace:
- `6.a`, `6.b`

## 3. Pricing Architecture

## 3.1 Admin-Editable Pricing

All commercial rates must be editable in admin settings, not hard-coded.

Core settings:
- `pricing.business_directory_6m`
- `pricing.business_directory_self_service_6m`
- `pricing.business_directory_staff_assisted_6m`
- `pricing.event_one_off`
- `pricing.event_monthly`
- `pricing.push_notification`
- `pricing.ad_slot.*`
- `pricing.banner_slot.*`
- `billing.vat_percentage`
- `billing.invoice_prefix`

Clause trace:
- `3.b`, `4.b`, `5.d`, `6.b`, `9.a`

## 3.2 Price Snapshot Rule

At purchase time, the platform must snapshot:
- package name
- billing model
- pre-VAT price
- VAT percentage
- VAT amount
- total amount

This snapshot is stored on:
- order items
- invoice items

Reason:
- future price changes must not mutate historic invoices or payment records

Clause trace:
- `8.c`, `9.e`

## 4. Cart And Checkout Architecture

## 4.1 Cart Scope

The cart can contain:
- business package purchase
- business renewal
- event package purchase
- advert campaign purchase
- push campaign purchase

Initial rule for v1:
- only one primary business or business-linked commercial journey per checkout session

Reason:
- simplifies entitlement validation and invoice logic

Clause trace:
- `3.a`, `4.a`, `5.c`, `8.a`, `10.d`

## 4.2 Checkout Stages

Recommended stages:
1. package selection
2. business or campaign details
3. pricing review
4. billing details
5. payment handoff to PayFast
6. success/failure recovery

Checkout UI requirements:
- progress indicator
- inline help
- mobile-friendly CTA placement

Clause trace:
- `8.a`, `8.b`, `10.d`, `10.e`

## 4.3 Eligibility Validation Before Payment

Validation rules:
- event purchase requires active business entitlement
- advert purchase requires active business entitlement
- push purchase requires active business entitlement
- self-service listing requires business owner account context
- staff-assisted package requires linked sales staff attribution when sold via staff flow

Clause trace:
- `3.a`, `3.c`, `3.d`, `4.a`, `6.a`

## 5. Order Architecture

## 5.1 Order Statuses

Recommended order statuses:
- `draft`
- `pending_payment`
- `paid`
- `partially_refunded`
- `refunded`
- `cancelled`
- `expired`

## 5.2 Order Item Responsibilities

Each order item must record:
- package snapshot
- billing model
- validity period
- related entity type and id
- pre-VAT and post-VAT totals
- lifecycle outcome

Clause trace:
- `8.b`, `8.c`, `9.e`

## 6. PayFast Payment Architecture

## 6.1 Payment Initiation

For each checkout:
- create order
- create invoice draft
- create payment record with `pending`
- redirect or post to PayFast

Clause trace:
- `8.a`, `8.c`

## 6.2 Webhook / ITN Processing

On PayFast callback:
- verify signature and source
- match payment to order
- record provider transaction id
- set payment to success/failure/pending
- write payment attempt log
- update order status
- issue or finalize invoice
- activate subscription/entitlement if payment is successful

Clause trace:
- `8.a`, `8.c`, `11.d`

## 6.3 Failure Handling

Failure outcomes:
- payment remains unpaid
- order remains recoverable
- user sees retry option
- admin sees failure reason in dashboard

Retry logic:
- manual retry from account
- optional automated reminders for incomplete checkout

Clause trace:
- `8.c`, `9.e`

## 6.4 Tokenised Recurring Billing

For recurring plans:
- store provider token reference only
- never store raw card data
- link token to user and subscription

Recurring billing engine must support:
- scheduled renewal jobs
- failed renewal retries
- past-due state
- cancellation rules

Clause trace:
- `8.b`, `11.d`

## 7. Invoice Architecture

## 7.1 Invoice Generation

Invoice creation points:
- draft invoice at checkout creation
- final invoice on successful payment
- credit note or refund invoice later if needed

Core invoice fields:
- invoice number
- invoice prefix snapshot
- issue date
- line items
- VAT breakdown
- currency
- customer details
- payment status

Clause trace:
- `8.c`, `9.a`, `9.e`

## 7.2 Invoice Delivery

After successful payment:
- email invoice automatically
- expose downloadable PDF in account/admin

Admin controls:
- resend invoice
- export invoice data
- sync invoice metadata with accounting API

Clause trace:
- `8.c`, `9.e`

## 8. Subscription And Entitlement Architecture

## 8.1 Subscription Purpose

Subscriptions track:
- billing period
- renewal behavior
- current status
- payment linkage

Entitlements track:
- what the user or business is allowed to do right now

Reason:
- subscriptions describe billing
- entitlements describe platform eligibility

Clause trace:
- `3.a`, `4.a`, `5.c`, `8.b`

## 8.2 Business Entitlement Rules

Business entitlement becomes active only when:
- related order is paid
- subscription is active
- no manual suspension exists

When expired:
- listing is no longer active commercially
- linked event/ad/push purchases become blocked

Clause trace:
- `3.a`, `3.f`, `4.a`, `6.a`

## 8.3 Event Entitlement Rules

Event entitlement becomes active only when:
- linked business entitlement is active
- event package order is paid
- event falls within active package validity

If business entitlement expires first:
- event publication should become restricted according to policy

Clause trace:
- `4.a`, `4.b`, `4.c`

## 8.4 Advert And Push Entitlement Rules

Campaigns become live only when:
- payment succeeds
- package dates are valid
- creative is approved where applicable
- linked business remains entitled

Clause trace:
- `5.c`, `6.b`, `9.d`

## 9. Staff-Assisted Revenue And Wallet Architecture

## 9.1 Commission Trigger

A staff commission ledger entry is created when:
- a staff-assisted sale is paid successfully
- the sale is attributable to a specific sales staff user

Commission rule:
- 50% of generated income

Recommended basis:
- apply to net ex-VAT revenue unless the business decides otherwise

This should be made explicit in finance policy.

Clause trace:
- `3.d`

## 9.2 Wallet States

Recommended wallet balances:
- `pending_balance`
- `available_balance`
- `paid_out_total`

Lifecycle:
- payment success -> credit pending
- after any hold/reconciliation policy -> move to available
- payout approval/payment -> debit available and increase paid out total

Clause trace:
- `3.d`, `9.e`

## 9.3 Payout Request Flow

Flow:
1. staff requests payout
2. admin reviews available balance
3. admin approves or rejects
4. manual payout occurs outside platform if needed
5. admin marks payout as paid
6. wallet ledger updated

Statuses:
- `requested`
- `approved`
- `paid`
- `rejected`
- `cancelled`

Clause trace:
- `3.d`, `9.e`, `9.f`

## 10. Writer Payment Architecture

## 10.1 Writer Ledger

Writer earnings are not treated as customer billing.

They use a separate payable ledger:
- word count snapshot
- rate snapshot
- gross amount
- approval status
- payout batch status

Clause trace:
- `1.a`, `2.a`, `9.b`

## 10.2 Writer Batch Export

Admin must be able to:
- view unpaid ledger lines
- batch them
- export for payout processing
- mark as paid

Clause trace:
- `9.b`

## 11. Refund, Override, And Extension Architecture

## 11.1 Refunds

Admin must be able to:
- refund full or partial payment
- record reason
- preserve audit trail

Refund effects:
- payment status updated
- order status updated
- invoice/credit note implications recorded
- entitlement reduced or cancelled if required

Clause trace:
- `8.d`, `9.e`, `11.d`

## 11.2 Manual Overrides

Override actions may include:
- activate package manually
- suspend package
- zero-price grant
- exception pricing
- entitlement correction after support issue

Every override must capture:
- actor
- reason
- before state
- after state
- timestamps

Clause trace:
- `8.d`, `9.e`, `11.d`

## 11.3 Manual Extensions

Extension actions:
- extend business package end date
- extend event package validity
- extend ad campaign dates

Rules:
- must be visible in admin
- must be auditable
- should preserve original payment history

Clause trace:
- `8.d`, `9.c`, `9.d`, `11.d`

## 12. Renewal Architecture

## 12.1 Renewal Triggers

For recurring packages:
- scheduled renewal attempt before or on renewal date

For non-recurring time-bound packages:
- reminder sequence before expiry
- user must actively renew

Clause trace:
- `8.b`, `9.c`, `9.e`

## 12.2 Reminder Cadence

Recommended reminders:
- 30 days before expiry
- 7 days before expiry
- 1 day before expiry
- on expiry

For failed recurring payments:
- immediate failure notice
- retry notice
- final suspension notice

Clause trace:
- `9.c`, `9.e`

## 13. VAT And Totals Rules

## 13.1 VAT

VAT percentage is admin-editable.

Rules:
- VAT snapshot on each order/invoice
- do not recompute historical invoices after VAT setting changes

Clause trace:
- `9.a`, `8.c`

## 13.2 Currency

Primary currency:
- `ZAR`

All v1 package prices and invoices should assume ZAR unless multi-currency later becomes necessary.

## 14. Admin Dashboard Requirements For Billing

Admin finance views must support:
- orders by status
- payments by status
- invoice filters
- failed payment list
- refund list
- manual override list
- active/expiring subscriptions
- payout requests
- wallet balances
- CSV/XLS export
- accounting sync queue visibility

Clause trace:
- `9.e`, `9.a`, `11.e`

## 15. Security And Compliance Rules

## 15.1 PCI / Token Safety

- raw card details never stored locally
- token references only
- sensitive provider payloads minimized and redacted where appropriate

Clause trace:
- `8.b`, `11.d`

## 15.2 Auditability

The following actions must always be auditable:
- payment status changes
- refund actions
- manual overrides
- package extensions
- payout request decisions
- pricing changes
- invoice resend and export events

Clause trace:
- `9.a`, `9.e`, `11.d`

## 16. Recommended State Machines

## 16.1 Payment Status
- `pending`
- `processing`
- `paid`
- `failed`
- `cancelled`
- `refunded`
- `partially_refunded`

## 16.2 Subscription Status
- `pending`
- `active`
- `past_due`
- `cancelled`
- `expired`
- `suspended`

## 16.3 Invoice Status
- `draft`
- `issued`
- `paid`
- `void`
- `refunded`

## 16.4 Payout Request Status
- `requested`
- `approved`
- `paid`
- `rejected`
- `cancelled`

## 17. API And Job Requirements

## 17.1 API Surface

Needed endpoints:
- package catalogue
- cart
- checkout init
- payment callback/webhook
- invoice download/list
- account subscriptions
- staff wallet summary
- payout request create/list
- admin finance dashboard

Clause trace:
- `8.a` to `8.d`, `9.e`, `11.a`

## 17.2 Jobs / Schedulers

Needed background jobs:
- renewal attempts
- expiry reminders
- failed payment reminders
- invoice email sending
- payout export generation
- accounting sync jobs

Clause trace:
- `8.c`, `8.d`, `9.c`, `9.e`, `11.e`

## 18. V1 Build Sequence

### Step 1
- settings for pricing, VAT, invoice prefix
- package catalogue

### Step 2
- cart, order, invoice, payment models
- PayFast checkout init and callback handling

### Step 3
- subscription and entitlement activation
- business purchase flow
- self-service/staff-assisted branching

### Step 4
- event package validation and activation

### Step 5
- staff wallet accrual and payout request flow

### Step 6
- admin finance dashboard, refunds, overrides, extensions

Clause trace:
- `3.b`, `3.c`, `3.d`, `4.a`, `8.a` to `8.d`, `9.a`, `9.e`

## 19. Open Design Decisions

- Whether business directory packages auto-renew by default or default to reminder-driven renewal
- Whether staff-assisted package pricing differs by capture channel
- Whether commission is calculated on gross or net-of-VAT revenue
- Whether push campaigns require prepayment only or can be invoiced on account for approved businesses
- Whether ad campaigns can overserve after impression cap due to delivery lag or must hard-stop instantly

These should be resolved before final billing migrations and payment service implementation.
