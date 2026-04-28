# Public-Facing Pages Inventory

This document inventories the public-facing WordPress URLs currently exposed by the live site at `http://life.local/` and related public content endpoints.

## Sources Used

- Live homepage at `http://life.local/`
- WordPress sitemap index at `http://life.local/wp-sitemap.xml`
- WordPress REST API:
  - `http://life.local/wp-json/wp/v2/pages?per_page=100`
  - `http://life.local/wp-json/wp/v2/posts?per_page=100`
- Active theme inspection in `wp-content/themes/life`

## WordPress Planning Docs Copied Into This Folder

- `laravel-wordpress-migration-prd.md`
- `laravel-wordpress-migration-technical-architecture.md`

## 1. Core Verified Public Views

- Home page: `http://life.local/`
- Single blog post template is active and publicly visible
- Author archive is publicly visible
- Category archive is publicly visible
- GeoDirectory places archive and single place pages are publicly visible
- GeoDirectory events archive and single event pages are publicly visible
- WooCommerce product pages are publicly visible
- Service directory pages are publicly visible

## 2. Static / CMS Pages

- `http://life.local/about-us/`
- `http://life.local/add-listing/`
- `http://life.local/advertise-with-us/`
- `http://life.local/basket/`
- `http://life.local/checkout/`
- `http://life.local/customer-account/`
- `http://life.local/driver-dashboard/`
- `http://life.local/editor-dashboard/`
- `http://life.local/gd-archive/`
- `http://life.local/gd-archive-item/`
- `http://life.local/gd-details/`
- `http://life.local/gig-detail/`
- `http://life.local/gig-listings/`
- `http://life.local/gig-search/`
- `http://life.local/item-detail/`
- `http://life.local/item-listings/`
- `http://life.local/location/`
- `http://life.local/marketplace/`
- `http://life.local/marketplace-products/`
- `http://life.local/marketplace-search/`
- `http://life.local/messages/`
- `http://life.local/my-account/`
- `http://life.local/navigation-test/`
- `http://life.local/product-management/`
- `http://life.local/product-marketplace/`
- `http://life.local/sample-page/`
- `http://life.local/sample-page-2/`
- `http://life.local/search/`
- `http://life.local/sell/`
- `http://life.local/service-detail/`
- `http://life.local/service-listings/`
- `http://life.local/service-search/`
- `http://life.local/shop/`
- `http://life.local/shop-analytics/`
- `http://life.local/shop-dashboard/`
- `http://life.local/shop-orders/`
- `http://life.local/shop-signup/`
- `http://life.local/simple-nav-test/`
- `http://life.local/staff-dashboard/`
- `http://life.local/staff-payments/`
- `http://life.local/staff-signup/`
- `http://life.local/submit-product/`
- `http://life.local/submit-shop/`
- `http://life.local/terms-and-conditions/`
- `http://life.local/transaction-history/`
- `http://life.local/transport_booking/`
- `http://life.local/transport_booking_fixed_hourly/`
- `http://life.local/transport_booking_manual/`
- `http://life.local/transport-result/`
- `http://life.local/transport-tabs/`
- `http://life.local/vendor-dashboard/`
- `http://life.local/vendor-profile/`
- `http://life.local/vouchers/`

## 3. Blog Post URLs

- `http://life.local/spec-savers-dihlabeng-your-trusted-eye-care-partner-in-bethlehem/`

## 4. GeoDirectory Place URLs

- `http://life.local/places/spec-savers-dihlabeng/`
- `http://life.local/places/clicks-pharmacy-bethlehem/`
- `http://life.local/places/test-pharmacy-bethlehem/`
- `http://life.local/places/new-test-business/`
- `http://life.local/places/twice-as-nice/`

## 5. GeoDirectory Event URLs

- `http://life.local/events/twice-as-nice-karaoke/`
- `http://life.local/events/test-event-for-map-display/`

## 6. Product URLs

- `http://life.local/product/i-will-built-you-a-website/`
- `http://life.local/product/68f6692b67125/`
- `http://life.local/product/68f6692c227a1/`
- `http://life.local/product/68f6692cd94ac/`
- `http://life.local/product/68f6692d9751d/`
- `http://life.local/product/68f6692e5dd8f/`
- `http://life.local/product/68f6692f15718/`
- `http://life.local/product/68f6692fc8d5d/`

## 7. Service URLs

- `http://life.local/services/updated-web-design-service/`

## 8. Public Taxonomy / Archive URLs

### Blog Categories

- `http://life.local/category/advertising/`

### Event Categories

- `http://life.local/events/category/karaoke/`

### Place Categories

- `http://life.local/places/category/attractions/`
- `http://life.local/places/category/it-company/`
- `http://life.local/places/category/technology/`
- `http://life.local/places/category/bar/`

### Product Categories

- `http://life.local/product-category/uncategorised/`

### Service Categories

- `http://life.local/service-category/website-development/`

### Voucher Categories

- `http://life.local/voucher-category/food-beverages/`
- `http://life.local/voucher-category/accommodation/`
- `http://life.local/voucher-category/drinks/`

### Event Tags

- `http://life.local/events/tags/twice/`
- `http://life.local/events/tags/as/`
- `http://life.local/events/tags/nice/`

### Place Tags

- `http://life.local/places/tags/optometrist/`
- `http://life.local/places/tags/eyecare/`
- `http://life.local/places/tags/glasses/`
- `http://life.local/places/tags/sunglasses/`
- `http://life.local/places/tags/eye-test/`
- `http://life.local/places/tags/bethlehem/`
- `http://life.local/places/tags/pharmacy/`
- `http://life.local/places/tags/health/`
- `http://life.local/places/tags/wellness/`
- `http://life.local/places/tags/drinks/`
- `http://life.local/places/tags/cold-beer/`
- `http://life.local/places/tags/sports/`
- `http://life.local/places/tags/braai/`

### Author Archives

- `http://life.local/author/james/`

## 9. Public Template-Level Views To Recreate In Laravel

These are public-facing templates or route types confirmed by live behavior or theme inspection, even if not every variant appears in the sitemap yet.

- Front page / home page
- Blog post single page
- Blog category archive page
- Author archive page
- GeoDirectory places archive page
- GeoDirectory place single page
- GeoDirectory place category archive page
- GeoDirectory place tag archive page
- GeoDirectory events archive page
- GeoDirectory event single page
- GeoDirectory event category archive page
- GeoDirectory event tag archive page
- Product archive / shop page
- Product single page
- Product category archive page
- Services archive / listing page
- Service single page
- Service category archive page
- Voucher landing page
- Search page / search results page
- Login / registration related public entry pages where visitors can start flows
- Cart / basket page
- Checkout page
- Public account page entry
- 404 page

## 10. Notes For Laravel Rebuild

- Not every public page above should necessarily keep the exact WordPress implementation, but every public URL/view type should be mapped during planning.
- Several WordPress pages appear to be workflow pages for marketplace, transport, shop, staff, vendor, and listing submissions. These may require different Laravel backends, but they are still public-facing entry pages and should remain in scope.
- The homepage currently behaves like a news/magazine front page, while the active custom `life` theme also contains GeoDirectory-specific archive and single templates. The Laravel rebuild must account for both content/news and directory/event experiences.
