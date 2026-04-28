# Public Page-By-Page Rebuild Spec

Primary sources:
- `platform-specification-source-of-truth.md`
- `public-facing-pages-inventory.md`
- `clause-traceable-rebuild-matrix.md`
- `information-architecture-and-route-map.md`

This document defines the target public pages for the Laravel rebuild, what each page must do, what data it consumes, how it monetises traffic, and what acceptance criteria it must satisfy.

## 1. Home Page

### Route
- `/`

### Purpose
- Act as the public front door for the platform.
- Combine local content, business discovery, event discovery, and monetisation entry points.

### Clause Trace
- `1.a`, `2.b`, `3.f`, `4.d`, `5.a`, `5.b`, `10.a`, `10.c`

### Primary Components
- Top navigation with dark-mode toggle
- Hero search with location selector/detection
- Featured articles
- Featured businesses
- Upcoming events
- Ad placements: header banner, in-feed promo blocks
- CTA blocks for advertisers, writers, and classifieds users
- Footer with legal and informational links

### Data Requirements
- Featured/pinned articles
- Featured/premium directory listings
- Upcoming geo-ranked events
- Active ad creatives for homepage zones
- Current user-selected or detected location

### Monetisation Hooks
- Header banner
- In-feed sponsorship cards
- “Advertise with us” CTA
- Push notification signup CTA

### Acceptance Criteria
- Loads in under 2 seconds on a 3G-equivalent budget target
- Supports mobile-first layout and WCAG 2.2 AA
- Search and location filter route users into relevant results
- Ads render only when active and eligible by geo rules

## 2. Article Archive

### Route
- `/articles`

### Purpose
- Surface published local content with category, tag, and location filtering.

### Clause Trace
- `1.a`, `2.a`, `2.b`, `10.a`

### Primary Components
- Archive header
- Filter bar: category, tag, location, keyword
- Paginated article cards
- Sponsored content or in-feed ad blocks

### Data Requirements
- Published articles
- Article categories
- Article tags
- Location metadata

### Monetisation Hooks
- Sponsored article slots
- In-article package upsell banners leading to advertise flow

### Acceptance Criteria
- Filters work independently and in combination
- SEO metadata and canonicals are correct for paginated/filter views
- Archive pages expose structured data where appropriate

## 3. Article Detail Page

### Route
- `/articles/{article:slug}`

### Purpose
- Publish monetisable writer content with strong SEO and ad inventory.

### Clause Trace
- `1.a`, `2.a`, `2.b`, `5.a`, `5.c`, `10.a`, `10.b`

### Primary Components
- Headline, author, publish date, location context
- Featured image/media
- Article body
- In-article advert slots: paragraph 1, mid-article, end-of-article
- Related content
- Business/event promotion blocks where relevant

### Data Requirements
- Article content and author
- Word count metadata for back-office reporting
- In-article ad placement inventory
- Related articles by category/tag/location

### Monetisation Hooks
- In-article ad slots
- End-of-article CTA to advertise, list a business, or promote an event

### Acceptance Criteria
- Ad slots can be configured per package definition
- Content is readable on mobile with 48x48 tappable targets
- Article page remains performant despite media and ads

## 4. Directory Archive

### Route
- `/directory`

### Purpose
- Provide the primary paid business discovery experience and rank listings by proximity.

### Clause Trace
- `3.a`, `3.b`, `3.c`, `3.d`, `3.e`, `3.f`, `10.a`

### Primary Components
- Search bar
- Category filters
- Location/radius controls
- Geo-ranked result cards
- Map view toggle
- Premium placement badges

### Data Requirements
- Active business listings only
- Package status and premium/featured flags
- Category taxonomy
- Geo coordinates and fallback radius defaults

### Monetisation Hooks
- Premium ranking/featured badge logic
- “Get listed” CTA
- Banner slots around directory results

### Acceptance Criteria
- Only active package holders appear
- Proximity ranking respects detected/selected location
- Fallback radius is configurable through admin settings
- Self-service and staff-assisted acquisition both lead into this domain

## 5. Directory Detail Page

### Route
- `/directory/{listing:slug}`

### Purpose
- Deliver the value of a paid listing and create a compelling advertiser profile.

### Clause Trace
- `3.e`, `3.f`, `5.b`, `10.a`

### Primary Components
- Trading name
- Full address and map
- GPS location
- Contact details
- Logo
- Gallery
- Operating hours
- Social links
- Rich description
- Linked events
- Optional promo/advert blocks

### Data Requirements
- Full listing profile
- Media assets
- Business geo data
- Linked organiser events

### Monetisation Hooks
- Display banner zones
- Upgrade prompts for advert packages or push campaigns

### Acceptance Criteria
- All required listing fields are supported
- Media is lazy-loaded and next-gen where possible
- Geo and contact data are easy to access on mobile

## 6. Events Archive

### Route
- `/events`

### Purpose
- List paid events tied to businesses with active business packages.

### Clause Trace
- `4.a`, `4.b`, `4.c`, `4.d`, `10.a`

### Primary Components
- Search/filter controls
- Category and location filters
- Event cards with organiser attribution
- Geo-ranked listing behavior

### Data Requirements
- Only events tied to qualifying active business packages
- Event categories
- Venue and geo data

### Monetisation Hooks
- Event package upsell CTA
- Banner placements

### Acceptance Criteria
- Ineligible organisers cannot surface events
- Event ranking follows the same geo rules as the business directory

## 7. Event Detail Page

### Route
- `/events/{event:slug}`

### Purpose
- Showcase a promotable event with clear linkage to the organiser business.

### Clause Trace
- `4.a`, `4.c`, `4.d`, `5.b`

### Primary Components
- Event title, date/time
- Venue and GPS
- Thumbnail and banner
- Description
- Ticket information
- Organiser business link
- Related events

### Data Requirements
- Event package entitlement
- Event media and venue data
- Organiser business reference

### Monetisation Hooks
- Push campaign upsell for eligible organisers
- Banner placements or event sponsorship blocks

### Acceptance Criteria
- Event always links back to its business listing
- Media and venue details are prominent and mobile-friendly

## 8. Search Results Page

### Route
- `/search`

### Purpose
- Provide a unified discovery surface across articles, businesses, events, and classifieds.

### Clause Trace
- `2.b`, `3.f`, `4.d`, `7.b`, `10.a`

### Primary Components
- Keyword input
- Content-type tabs or grouped sections
- Category/location filters
- Geo-aware ranking for business and event subsets

### Data Requirements
- Cross-domain search index or coordinated search queries
- Location context
- Type filters

### Monetisation Hooks
- Search sponsorship
- Advertise CTA on empty/low-result states

### Acceptance Criteria
- Search returns grouped, clearly labeled result types
- Business and event result ordering remains geo-aware

## 9. Classifieds Archive

### Route
- `/classifieds`

### Purpose
- Let users browse free product and personal service listings.

### Clause Trace
- `7.a`, `7.b`, `7.c`, `10.a`

### Primary Components
- Search/filter
- Category filters
- Item cards with price/contact-for-price
- Moderation-aware visibility

### Data Requirements
- Published approved classifieds only
- Categories
- Media and location fields

### Monetisation Hooks
- Cross-sell directory listing upgrades for serious sellers/businesses
- Banner inventory if desired later

### Acceptance Criteria
- Any registered user can eventually post for free
- Hidden/flagged/unapproved items never appear publicly

## 10. Classified Detail Page

### Route
- `/classifieds/{classified:slug}`

### Purpose
- Provide a lightweight public detail page for free listings.

### Clause Trace
- `7.b`, `7.c`

### Primary Components
- Title
- Description
- Price or contact-for-price
- Image gallery
- Location
- Category

### Data Requirements
- Approved classified record
- Attached media

### Acceptance Criteria
- Supports multiple images up to configured limit
- Respects moderation status

## 11. Advertise Landing Page

### Route
- `/advertise`

### Purpose
- Explain the monetisation ladder and route prospective advertisers into the correct package journey.

### Clause Trace
- `1.a`, `3.a`, `5.a`, `5.b`, `5.c`, `5.d`, `6.a`

### Primary Components
- Business directory requirement explanation
- Package comparison cards
- Event add-on explanation
- Banner and in-article package overview
- Push-notification premium pitch
- CTA into business package checkout

### Data Requirements
- Pricing settings from admin
- Package definitions

### Acceptance Criteria
- Makes it clear that a business directory package is mandatory first
- Pricing is editable from admin, not hard-coded in long term

## 12. Account Entry Page

### Route
- `/account`

### Purpose
- Serve as a public-friendly entry to the authenticated account area.

### Clause Trace
- `8.c`, `9.e`, `10.d`

### Primary Components
- Profile summary
- Links to dashboard, invoices, package status, submissions

### Acceptance Criteria
- Auth required
- Routes users into role-appropriate modules without confusion

## 13. Basket Page

### Route
- `/basket`

### Purpose
- Consolidate pending purchases across packages before checkout.

### Clause Trace
- `8.a`, `8.b`, `10.d`

### Primary Components
- Line items
- VAT display
- package duration and recurring/once-off labels
- clear CTA to checkout

### Acceptance Criteria
- Supports once-off, monthly, and 6-monthly billable items
- Displays totals and VAT correctly

## 14. Checkout Page

### Route
- `/checkout`

### Purpose
- Execute the payment flow for packages and services via PayFast.

### Clause Trace
- `8.a`, `8.b`, `8.c`, `8.d`, `10.d`, `10.e`, `11.d`

### Primary Components
- Multi-step checkout
- Billing details
- Package summary
- Contextual help
- Progress indicator
- Payment method selection

### Data Requirements
- Order
- Invoice draft
- PayFast session/initiation data
- recurring billing metadata when applicable

### Acceptance Criteria
- Supports card, EFT, and Instant EFT via PayFast
- Can generate invoice and payment records
- Handles failure states and retries
- Supports tokenised recurring billing design

## 15. Legal / Informational Pages

### Routes
- `/about-us`
- `/terms-and-conditions`
- future privacy/compliance pages as needed

### Purpose
- Provide trust, compliance, and public information.

### Clause Trace
- `10.a`, `11.d`, `12.b`

### Acceptance Criteria
- Terms page is available before payment and package purchase
- Content is accessible and mobile-friendly

## 16. Cross-Page Requirements

These apply to every public-facing page unless explicitly exempted.

### Accessibility
- WCAG 2.2 AA baseline
- Keyboard navigable
- Proper heading hierarchy
- Sufficient contrast

Clause trace:
- `10.a`

### Performance
- Target under 2 seconds on 3G budget assumptions
- Lazy-load media
- Prefer WebP/AVIF and responsive image sizes

Clause trace:
- `10.b`

### Design System
- Mobile-first
- Card-based layout
- 8-point spacing system
- Dark-mode toggle

Clause trace:
- `10.a`, `10.c`

### Ads And Monetisation
- Every eligible page must define whether it has ad inventory
- Geo-targeting rules must be respected where relevant

Clause trace:
- `1.a`, `5.a`, `5.b`, `5.c`, `6.b`

### SEO
- Canonical URLs
- OpenGraph/Twitter metadata
- XML sitemap inclusion where indexable

Clause trace:
- `2.b`, `10.a`, `10.b`

## 17. Build Order Recommendation

### First Build Group
- Home
- Articles archive/detail
- Directory archive/detail
- Events archive/detail
- Search
- Advertise
- Basket
- Checkout

Reason:
- These pages directly support the job-creation plus monetisation model.

Clause trace:
- `1.a`, `2.a`, `2.b`, `3.a`–`3.f`, `4.a`–`4.d`, `5.a`–`5.d`, `8.a`–`8.d`

### Second Build Group
- Account
- Writer/account-related public entry pages
- Terms/About
- Classifieds archive/detail and submission flow

Clause trace:
- `7.a`–`7.c`, `8.c`, `9.e`, `10.a`

### Third Build Group
- Deferred or review pages: vouchers, services split, retained shop flows, transport flows

Clause trace:
- Business decision pending beyond core specification
