# Platform Specification Source Of Truth

This document is the governing platform specification for the Laravel rebuild.

Purpose:
- Establish the business, product, UX, technical, and compliance requirements that all future planning and implementation must follow.
- Ensure every wireframe, page flow, schema, API, admin screen, and code decision can be traced back to the numbered clauses below.

## Traceability Rule

All future planning outputs must explicitly map decisions back to the clause numbers in this document.

Examples:
- Information architecture must reference the relevant clauses that justify each page and flow.
- Database schemas must reference the clauses that require each entity, field, relationship, and workflow.
- Admin features must reference the clauses that define pricing, payouts, approvals, monitoring, and access control.
- UI and UX decisions must reference the clauses that define accessibility, responsiveness, dark mode, and checkout behavior.
- Technical design decisions must reference the clauses that define APIs, testing, CI/CD, security, monitoring, and performance.

## 1. Core Purpose

### 1.a Local Job-Creation And Monetisation Engine

The platform must be positioned as a local job-creation engine that simultaneously:
- Generates paid writing work through per-word compensation for local news and articles.
- Monetises audience reach through a multi-tier, geo-aware advertising stack.

## 2. Content And Writer Module

### 2.a Writer Submission And Payment Workflow

The platform must provide an admin-approved workflow for writers to:
- Submit articles.
- Track word count.
- Receive per-word payment.

### 2.b Article Publishing Experience

Published articles must be exposed through a responsive, SEO-optimised front end with:
- Category filters.
- Tag filters.
- Location filters.

## 3. Business Directory (Primary Revenue Gateway)

### 3.a Mandatory First Step For Advertising

Any entity wishing to advertise must first purchase a Business Directory slot.

### 3.b Business Directory Pricing

Default pricing:
- R500 every 6 months.

This must be editable in:
- Admin -> Settings -> Pricing

### 3.c Self-Service Business Listing Option

Self-service option:
- R750 every 6 months.

This applies when the business creates or edits its own listing.

### 3.d Staff-Assisted Business Listing Option

The platform must support a lower-cost staff-assisted option where staff can capture listing data via:
- Site visit.
- WhatsApp.
- Phone.
- Online form.

Staff then publish on behalf of the client.

Staff compensation rule:
- Staff get paid 50% of the income they generate.
- This must be paid into a staff wallet.
- Staff can request payouts.
- Admin must mark requested payouts as Paid after manual payout.

### 3.e Required Business Listing Data

Each business listing must store:
- Trading name.
- Full address.
- GPS coordinates.
- Contact details.
- Logo.
- Gallery.
- Operating hours.
- Social links.
- Rich-description editor content.

### 3.f Geo-Ranking Algorithm

Listings must be ranked by proximity to the user's detected or selected location.

The platform must support:
- Closest listings first.
- Configurable fallback radius.

This fallback radius must be editable in Admin.

## 4. Events Directory (Conditional Add-On)

### 4.a Active Business Requirement

Events may only be advertised if the organiser's business already has an active Business Directory package.

### 4.b Event Package Models

The platform must support:
- One-off event-to-event packages.
- Recurring month-to-month event bundles.

Pricing must be editable in Admin.

### 4.c Required Event Data

Each event must capture:
- Event name.
- Date and time.
- Venue and GPS coordinates.
- Ticket info.
- Thumbnail.
- Banner.
- Description.
- Organiser linkage to the relevant Business Directory entry.

### 4.d Event Geo-Ranking

Events must use the same geo-ranking logic as the Business Directory.

## 5. Advert Placement Packages

### 5.a In-Article Ad Slots

The platform must support multiple in-article ad slot packages, including examples such as:
- Paragraph 1 slot.
- Mid-article slot.
- End-of-article slot.

### 5.b Banner Placements

The platform must support banner placements including:
- Header.
- Sidebar.
- Sticky mobile footer.
- Interstitial.

### 5.c Package Controls

Each advert package must support:
- Impression limits.
- Start dates.
- End dates.
- CPM pricing or fixed-rate pricing.
- Audience geo-filtering.

### 5.d Advert Pricing Management

All advert rates and timeframes must be editable in:
- Admin -> Settings -> Advert Pricing

## 6. Premium Push-Notification Service

### 6.a Highest Revenue Tier

The platform must support a premium push-notification service as the highest revenue tier.

This service promotes either:
- A business.
- An event linked to a business.

### 6.b Push Notification Admin UI

The admin interface must support:
- Compose.
- Schedule.
- Target by location radius.
- Target by user interest tags.
- Track open rates.

## 7. Free Classified Section

### 7.a Free User Listings

Any registered user must be able to post products or personal services at no cost.

### 7.b Required Classified Fields

Each classified listing must support:
- Title.
- Description.
- Price or "Contact for Price".
- Up to N images.
- Location.
- Category.

### 7.c Moderation Queue

The platform must provide lightweight moderation actions:
- Flag.
- Hide.
- Approve.

## 8. Payment Infrastructure

### 8.a PayFast Integration

The platform must integrate PayFast for:
- Card payments.
- EFT.
- Instant EFT.

### 8.b Billing Models

The platform must support:
- Once-off billing.
- Monthly recurring billing.
- Six-month recurring billing.
- Tokenised card storage.

### 8.c Invoice And Payment Lifecycle

The platform must:
- Generate invoices.
- Email invoices.
- Record payment status.
- Handle failures.
- Support retry logic.

### 8.d Manual Admin Overrides

Admin must be able to:
- Override packages.
- Refund payments.
- Extend packages manually.

## 9. Admin Back-End Requirements

### 9.a Global Settings

Admin must have a Global Settings page to edit:
- Writer per-word rate.
- Directory prices for 6-month and self-service options.
- Advert package rates.
- Push-notification fee.
- Geo-radius defaults.
- VAT percentage.
- Invoice prefix.

### 9.b Writer Management

Admin must be able to:
- View articles.
- View word count ledger.
- Export payment batches.

### 9.c Business And Event Management

Admin must support:
- CRUD for businesses and events.
- Expiry alerts.
- Renewal reminders.
- Geo-coordinate override.

### 9.d Advert Management

Admin must support:
- Approve creatives.
- Monitor impressions and clicks.
- Pause campaigns.

### 9.e Payment And Invoice Dashboard

Admin must support:
- Filter by status.
- Export to XLS or CSV.
- Sync with accounting software via API.

### 9.f Role-Based Access

The platform must support these roles:
- Super-Admin.
- Content-Manager.
- Sales-Staff.
- Support.

## 10. User Experience And Design Constraints

### 10.a Responsive And Accessible

The platform must be:
- Mobile-first.
- Responsive.
- WCAG 2.2 AA compliant.

### 10.b Performance

The platform must target:
- Page load under 2 seconds on 3G.
- Lazy-loaded images.
- Next-gen image formats such as WebP and AVIF.

### 10.c Design System Requirements

The front end must use:
- Modern card-based layout.
- Consistent 8-point spacing system.
- Dark-mode toggle.

### 10.d Checkout Guidance

The checkout must include:
- Inline contextual help.
- Progress indicators for multi-step flows.

### 10.e Mobile CTA Ergonomics

The interface must ensure:
- One-hand reachable CTAs on mobile.
- Minimum 48 x 48 px tappable areas.

## 11. Technical And Non-Functional Requirements

### 11.a Core Stack

The platform must use:
- Decoupled REST and/or GraphQL API.
- Secure JWT or OAuth 2.0.
- Scalable SQL/NoSQL hybrid.
- CDN for static assets.

### 11.b Testing

The platform must provide:
- Unit test coverage greater than 80%.
- Integration tests for payment webhooks.
- End-to-end tests for checkout flows.

### 11.c CI/CD

The platform must include:
- CI/CD pipeline.
- Blue-green deployment.
- Rollback within 2 minutes.

### 11.d Security And Compliance

The platform must support:
- PCI-DSS compliant token handling.
- GDPR and POPIA compliance.
- Encrypted data at rest.
- Rate limiting.
- Audit logs.

### 11.e Monitoring And KPIs

The platform must include:
- Real-time uptime monitoring.
- Error alerting.
- Business KPI dashboard.

KPI examples:
- Daily active users.
- Revenue.
- Failed payments.

## 12. Deliverables And Acceptance Criteria

### 12.a Completed Rebuild

The completed Laravel platform rebuild must satisfy every numbered requirement in this document.

### 12.b Documentation

Deliverables must include:
- Fully documented API.
- Admin handbook.

### 12.c Load Testing

Deliverables must include:
- Load-test report showing 1,000 concurrent users with less than 500 ms p95 response time.

### 12.d Security Evidence

Deliverables must include:
- Pen-test certificate.
- PCI-DSS AOC or SAQ-A.

### 12.e Training Deliverables

Deliverables must include:
- Training session recordings for admin staff.
- Training session recordings for sales staff.

## Planning Implication

Before rebuild execution planning begins, all future outputs should explicitly trace back to this document by clause number.

This includes:
- Site map and information architecture.
- Public page rebuild matrix.
- Database schema.
- Payment model.
- Ad engine design.
- Writer workflow.
- Staff wallet and payout workflow.
- UI wireframes.
- API contracts.
- Admin module breakdown.
- Test strategy.
- Deployment and compliance plan.
