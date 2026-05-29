# Life@ Online Mall Implementation Todo

Source spec: `C:\Users\Phoenix\Downloads\lifeat_mall_implementation_spec.md`

Last updated: 2026-05-30

## Current Status

Core standalone mall implementation landed and verified with focused tests. This checklist adapts the source specification to the current Life@ Laravel 13 application while keeping the mall completely separate from the existing checkout, order, payment, subscription, invoice, and package infrastructure.

The mall subsystem must use its own prefixed database tables, models, services, controllers, routes, and payment handling:

- `mall_stores`, not `stores`
- `mall_products`, not `products`
- `mall_carts`, not shared basket or checkout tables
- `mall_orders`, not existing `orders`
- `mall_order_items`, not existing `order_items`
- `mall_payments`, not existing `payments`
- `Mall*` model and service names to avoid accidental coupling

## Non-Negotiable Rules

- [x] Carts are scoped to exactly one mall store.
- [x] One PayFast checkout creates one vendor-scoped mall payment.
- [x] Stock is never decremented on cart add.
- [x] Stock is decremented only after PayFast ITN confirms payment.
- [x] PayFast ITN is excluded from CSRF protection.
- [x] Product prices are snapshotted on cart item creation and order item creation.
- [x] Guests can browse and add to cart; auth is required only at checkout.
- [x] Money is stored as decimals and calculated through `App\Support\Mall\MallMoney`.

## Phase 1 - Schema And Models

- [x] Add standalone mall category, store, product, product category, cart, cart item, order, order item, payment, and vendor profile tables.
- [x] Use `mall_*` table names for every mall-commerce table.
- [x] Do not alter or rely on existing `orders`, `order_items`, `payments`, `payment_attempts`, `invoices`, `subscriptions`, `packages`, or checkout tables for mall sales.
- [x] Add standalone `Mall*` models and relationships.
- [x] Add user relationships for mall store ownership and mall carts only.
- [x] Seed initial mall store categories.

## Phase 2 - Config And Services

- [x] Add mall configuration and PayFast mall defaults.
- [x] Add cart service for guest/user per-store carts and price snapshots.
- [x] Add standalone PayFast mall service for signed checkout payloads, optional split receiver payloads, ITN validation, idempotency, stock decrement, and cart cleanup.

## Phase 3 - Routes And Middleware

- [x] Add public mall browsing routes.
- [x] Add store-scoped cart routes.
- [x] Add auth-protected checkout routes.
- [x] Add CSRF-exempt PayFast ITN route.
- [x] Add vendor dashboard route guard using mall store state.

## Phase 4 - Public Mall UX

- [x] Add mall entrance page with compact horizontally scrollable product shelves per store.
- [x] Add store window page with featured products embedded in the same storefront block and scrollable left to right.
- [x] Add inside-store catalog with filters, search, sort, and cart summary.
- [x] Add product detail page.
- [x] Add store cart page.
- [x] Add checkout summary, PayFast redirect, return, and cancel pages.

## Phase 5 - Auth Integration

- [x] Merge guest mall carts into user carts on login.
- [x] Add customer mall order history.

## Phase 6 - Vendor Dashboard

- [x] Add vendor registration.
- [x] Add vendor dashboard summary.
- [x] Add product CRUD.
- [x] Add vendor order list and status updates.
- [x] Add store profile editing, logo, and banner uploads.
- [x] Add vendor earnings report.

## Phase 7 - Admin

- [x] Add admin store list, detail, approve, and suspend actions.
- [x] Add admin mall order views.
- [x] Add admin commission report.
- [x] Add admin store edit/update page for status, featuring, categories, profile copy, and payout settings.
- [x] Add admin product index/edit/update pages for moderation, featured/window control, stock, and pricing.
- [ ] Add admin product category management screens.

## Phase 8 - Mall Fulfillment

- [x] Add standalone mall fulfillment records linked to `mall_orders`.
- [x] Add delivery method selection at mall checkout.
- [x] Add store pickup as a zero-fee fulfillment option.
- [x] Add store pickup address and coordinates to mall store admin/vendor profiles as the pickup point for taxi delivery.
- [x] Add PUDO-ready non-local fulfillment option without coupling mall orders to existing checkout/payment tables.
- [x] Replace fixed PUDO pricing with PUDO/The Courier Guy Locker API locker lookup, live rate quote, and shipment creation after PayFast confirms payment.
- [x] Add optional taxi delivery fulfillment priced from active transport vehicle per-km rules with the normal transport 10% platform cut on the delivery fee.
- [x] Calculate local taxi delivery distance from the store pickup point to the customer's selected delivery address instead of accepting customer-entered kilometres.
- [x] Add vendor/admin per-product parcel kg estimates and use the cart's summed product weight for taxi delivery quotes instead of accepting customer-entered parcel weight.
- [x] Limit checkout vehicle-type selection to active eligible taxi parcel vehicle categories that can carry the current basket weight.
- [x] Keep product commission, delivery platform fee, vendor amount, and delivery provider amount snapshotted separately.
- [x] Create and link normal transport delivery requests after PayFast confirms a paid mall taxi delivery without making transport own mall orders.
- [x] Send taxi delivery offers through the existing transport driver offer/notification flow.
- [x] Allow the dev owner/admin to use transport manager driver setup without replacing their existing admin identity.

## Phase 9 - Events, Mail, And Operations

- [x] Add order-paid event and listener.
- [x] Add customer order confirmation email.
- [x] Add vendor new-order notification email.
- [x] Add abandoned pending order sweep.
- [x] Add storage directories.
- [ ] Add bespoke mall placeholder image assets if the existing Life@ illustration fallbacks are not sufficient.

## Follow-Up Backlog

- [x] Add opt-in demo mall seeder for browser QA.
- [ ] Add vendor-facing product category management screens.
- [x] Confirm current PUDO API locker/rate/shipment rules from PUDO/The Courier Guy Locker API docs; production still needs real account credentials in environment variables.
- [ ] Decide whether to install `intervention/image` for server-side image resizing; current implementation stores uploaded images directly and does not require it.
- [ ] Enable `MALL_PAYFAST_VALIDATE_ITN_WITH_SERVER=true` in production once the deployed `notify_url` is public HTTPS.
- [x] Add initial browser/UI verification with seeded demo store across storefront, basket, login handoff, and checkout summary.
- [ ] Add browser/UI verification for vendor and admin management screens.

## Verification Log

- 2026-05-29: `vendor\phpunit\phpunit\phpunit --filter MallStandaloneCommerceTest` passed with bundled PHP and explicit `mbstring`, `pdo_sqlite`, `sqlite3`, and `openssl` extensions. Result: 4 tests, 30 assertions.
- 2026-05-29: `artisan route:list --name=mall` passed and showed 37 standalone mall routes.
- 2026-05-29: PHP syntax checks passed for representative mall services, controllers, command, model, and test files.
- 2026-05-29: `MallDemoSeeder` syntax check passed.
- 2026-05-29: Disposable SQLite demo database at `C:\tmp\lifeat-mall-demo-20260529.sqlite` migrated and seeded with `MallDemoSeeder`.
- 2026-05-29: Browser QA passed on `http://localhost:8105/mall`: mall entrance displayed the demo store, storefront displayed featured products, inside-store add-to-basket worked, basket displayed the item, login handoff merged the guest cart, and checkout displayed the PayFast summary without horizontal overflow.
- 2026-05-29: Updated `/mall` so the mall home is the window-shopping corridor itself. Store panels now show featured products behind a shop-window treatment and use `Enter Store` as the primary action into the full catalog.
- 2026-05-29: Updated `/mall/stores/life-market-demo` so there is no second `Window picks` section. The storefront page now keeps the store details, `Enter Store` action, and featured products in one block with a horizontally scrollable product strip. Focused mall tests passed again: 6 tests, 42 assertions. Browser QA confirmed 5 products inside the strip, `overflow-x: auto`, and no page-level horizontal overflow.
- 2026-05-29: Updated `/mall` store panels so product cards no longer stack into multiple rows on narrower screens. The mall home now uses compact horizontal product shelves; browser QA confirmed 5 products in 1 row, `overflow-x: auto`, shelf scroll width greater than client width, and no page-level horizontal overflow. Focused mall tests passed again: 6 tests, 43 assertions.
- 2026-05-30: Added standalone mall fulfillment records, checkout delivery selection, pickup, PUDO-ready non-local delivery, and optional taxi delivery with its own 10% delivery platform fee. Product commission, delivery platform fee, vendor amount, and delivery provider amount are snapshotted separately on mall-owned tables.
- 2026-05-30: Added mall admin store edit/update and product index/edit/update pages for status, featured/window controls, pricing, stock, categories, store copy, and PayFast split settings.
- 2026-05-30: Focused mall tests passed again: 9 tests, 73 assertions. `artisan route:list --name=mall` passed and showed 42 standalone mall routes.
- 2026-05-30: Disposable SQLite demo database migrated with `mall_fulfillments` and reseeded with admin/vendor/customer demo users. Browser QA confirmed `/mall/admin/products`, `/mall/admin/stores/life-market-demo/edit`, and `/mall/stores/life-market-demo/checkout` render without page-level horizontal overflow; checkout shows local taxi delivery for local orders and selects PUDO when switching to non-local delivery.
- 2026-05-30: Replaced fixed mall taxi delivery fee with active transport vehicle pricing. Taxi delivery checkout now quotes from `TransportFareService` using eligible parcel vehicles, distance, vehicle type, and parcel weight. After PayFast ITN confirms payment, mall taxi fulfillments create normal `transport_requests`, call `TransportDispatchService`, create `transport_request_offers`, and broadcast the existing transport driver offer events.
- 2026-05-30: Focused mall tests passed again: 10 tests, 88 assertions. Focused transport tests passed: 7 tests, 104 assertions. Browser QA confirmed `/mall/stores/life-market-demo/checkout` estimates a 4 km taxi delivery as `R 34.00` from the seeded `Mall Parcel Bike` transport vehicle at `R 6.00/km`, with no page-level horizontal overflow.
- 2026-05-30: Added mall store pickup address/coordinate fields to admin and vendor store profile forms. Checkout now uses the taxi map address setup for customer delivery address selection, calculates delivery distance server-side from the store pickup point to the selected customer coordinates, snapshots pickup/dropoff coordinates on mall fulfillment meta, and passes those coordinates into the linked `transport_requests` record.
- 2026-05-30: Focused mall tests passed again: 10 tests, 97 assertions. Focused transport tests passed: 7 tests, 104 assertions. Disposable SQLite demo database was migrated with the store pickup-point migration and reseeded. Browser QA confirmed checkout shows the store pickup point, has address autocomplete on the customer delivery address, no longer renders a manual distance field, and has no page-level horizontal overflow.
- 2026-05-30: Added `parcel_weight_kg` to mall products and mall order-item snapshots. Vendor and admin product forms now collect a per-product parcel kg estimate, checkout displays the summed basket estimate, and taxi delivery quotes use that server-calculated cart weight rather than a customer-editable parcel kg field.
- 2026-05-30: Focused mall tests passed again: 10 tests, 99 assertions. Focused transport tests passed: 7 tests, 104 assertions. Disposable SQLite demo database was migrated with the product parcel-weight migration and reseeded. Browser QA confirmed checkout no longer renders a customer parcel kg input, shows the vendor-derived parcel estimate, keeps the manual distance field removed, and has no page-level horizontal overflow.
- 2026-05-30: Checkout vehicle type options now come from active eligible transport parcel vehicles instead of a static list, filtered by the current basket's vendor-derived parcel weight. Backend validation also rejects manually posted inactive/unavailable vehicle categories.
- 2026-05-30: Focused mall tests passed again: 10 tests, 106 assertions. Focused transport tests passed: 7 tests, 104 assertions. Browser QA confirmed the demo checkout vehicle dropdown offers only `Any available parcel vehicle` and `Bicycle`, does not offer `LDV`, has no console errors, and has no page-level horizontal overflow.
- 2026-05-30: Moved delivery address capture into the checkout Delivery block above the taxi quote estimate and added a `Locate Me` action using browser geolocation plus the existing taxi maps reverse-geocode endpoint.
- 2026-05-30: Focused mall tests passed again: 10 tests, 108 assertions. Focused transport tests passed: 7 tests, 104 assertions. Browser QA confirmed the checkout Delivery flow shows the delivery/PUDO address input before the taxi quote prompt, has autocomplete enabled, shows `Locate Me`, has no console errors, and has no page-level horizontal overflow.
- 2026-05-30: Delivery address capture is now conditional. The shared address/`Locate Me` control is hidden for pickup and moves directly beneath the selected Taxi or PUDO delivery option when either one is selected.
- 2026-05-30: Focused mall tests passed again: 10 tests, 108 assertions. Focused transport tests passed: 7 tests, 104 assertions. Browser QA confirmed pickup hides the address field, Taxi shows it under the taxi option, PUDO shows it under the PUDO option, and there are no console errors or page-level horizontal overflow.
- 2026-05-30: Implemented PUDO/The Courier Guy Locker API integration for mall fulfillment. Added configurable API base URL/key/auth header, locker lookup, live `/rates` quote for Door-to-Locker delivery, shipment creation through `/shipments` after PayFast confirms payment, fulfillment meta snapshots for PUDO quote/shipment data, and checkout UI for selecting a PUDO locker and previewing the live rate.
- 2026-05-30: Focused mall tests passed again: 10 tests, 117 assertions with faked PUDO `/rates` and `/shipments` API calls. Focused transport tests passed: 7 tests, 104 assertions. Browser QA confirmed PUDO selection shows the locker API controls under PUDO, uses `Live rate`, keeps hidden locker fields, has no console errors, and has no page-level horizontal overflow.
- 2026-05-30: Fixed taxi/transport manager access for the dev owner. The `dev` capability is now recognized for transport manager routes and links, existing users can be granted transport manager access without changing their primary role, focused transport tests passed again: 9 tests, 118 assertions, and browser QA confirmed `/transport/manager` opens without a 403.
