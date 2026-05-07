# Advertising Dashboards API (Session Auth)

These endpoints are consumed by the client and staff advertising dashboards. They use the normal web session (cookie) authentication and CSRF protection.

## Auth & Headers

- Auth: logged-in session (`web` guard)
- CSRF: required for `PUT`/`POST` requests (send `X-CSRF-TOKEN`)
- Accept header: `Accept: application/json`

## Client (Business Owner) Endpoints

### GET `/api/client/advertising/listings`

Returns all businesses owned by the logged-in user.

Response 200:

```json
{
  "listings": [
    {
      "id": 123,
      "title": "My Business",
      "slug": "my-business-abc123",
      "status": "published",
      "has_active_business_entitlement": true,
      "ad_campaigns_count": 2,
      "push_campaigns_count": 1
    }
  ]
}
```

### GET `/api/client/advertising/listings/{listing}`

Returns dashboard summary data for one owned listing.

Responses:
- 200 JSON summary
- 403 if listing is not owned by the authenticated user

Response 200 (shape):

```json
{
  "listing": {
    "id": 123,
    "title": "My Business",
    "status": "published",
    "has_active_business_entitlement": true,
    "updated_at": "2026-05-07T10:00:00+00:00"
  },
  "ad_campaigns": [
    {
      "id": 1,
      "title": "Banner Boost",
      "status": "active",
      "placement": "banner",
      "start_at": "2026-05-07T10:00:00+00:00",
      "end_at": null,
      "impressions": 0,
      "clicks": 0,
      "ctr": 0,
      "updated_at": "2026-05-07T10:00:00+00:00"
    }
  ],
  "push_campaigns": [
    {
      "id": 10,
      "title": "Weekend Promo",
      "status": "scheduled",
      "schedule_at": "2026-05-08T08:00:00+00:00",
      "open_count": 0,
      "open_rate": 0,
      "updated_at": "2026-05-07T10:00:00+00:00"
    }
  ],
  "integrations": [
    {
      "id": 99,
      "type": "email_marketing",
      "provider": "Mailchimp",
      "status": "active",
      "settings": { "audience_id": "abc" },
      "updated_at": "2026-05-07T10:00:00+00:00"
    }
  ]
}
```

### PUT `/api/client/advertising/listings/{listing}/integrations/{type}`

Upserts a marketing integration record for the listing (owner-only).

Body:

```json
{
  "provider": "Mailchimp",
  "status": "active",
  "settings": { "audience_id": "abc" }
}
```

Responses:
- 200 `{ ok: true, integration: {...} }`
- 403 if listing not owned
- 422 validation errors

## Staff Endpoints

Staff endpoints require `role:staff` middleware (sales staff).

### GET `/api/staff/advertising/businesses`

Returns businesses assigned to the logged-in staff member (via `listings.registered_by_user_id`).

### GET `/api/staff/advertising/businesses/{listing}`

Returns full summary + editable configuration.

### PUT `/api/staff/advertising/ad-campaigns/{adCampaign}`

Updates ad campaign configuration with conflict detection.

Body:

```json
{
  "expected_updated_at": "2026-05-07T10:00:00+00:00",
  "status": "active",
  "placement": "popup",
  "budget_amount": 100,
  "budget_currency": "ZAR",
  "start_at": "2026-05-07T10:00:00+00:00",
  "end_at": null,
  "targeting": { "city": "Bethlehem" },
  "popup_settings": { "frequency": "once_per_day" }
}
```

Responses:
- 200 `{ ok: true, campaign: { id, updated_at } }`
- 403 if not assigned to the listing
- 409 if `expected_updated_at` does not match the current record `updated_at`
- 422 validation errors

### PUT `/api/staff/advertising/push-campaigns/{pushCampaign}`

Updates push campaign configuration with conflict detection.

### PUT `/api/staff/advertising/businesses/{listing}/integrations/{type}`

Upserts integration configuration with conflict detection when `expected_updated_at` is supplied.

## Audit Logging

All staff update endpoints create `audit_logs` records capturing:
- actor user
- action name
- subject type + id
- before + after snapshots
- IP + user agent

