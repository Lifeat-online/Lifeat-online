# Advertising Dashboards User Guides

## Client Self-Service Dashboard

### Where to find it

- Go to **My Account** → **Advertising dashboard**
- Direct URL: `/account/advertising`

### What you can do

- Select a business you own
- View campaign status indicators for:
  - Push notification campaigns
  - Banner ads
  - Promotional pop-ups (ad campaigns with placement = `popup`)
  - Email marketing integration
  - Social ads integration
- Review high-level performance metrics (impressions and clicks)
- Jump into full management screens for:
  - Push campaigns: `/account/listings/{listing}/push-campaigns`
  - Ad campaigns: `/account/listings/{listing}/ad-campaigns`
- Open billing and packages:
  - `/checkout`

### Availability rules

- If you have no business listings, the advertising dashboard shows a “Start business registration” call-to-action.

## Staff Service Dashboard

### Where to find it

- Staff Workspace button: **Client Advertising**
- Direct URL: `/staff/advertising`

### What you can do

- Select one of your assigned businesses (based on `listings.registered_by_user_id`)
- View business owner contact details
- Edit ad campaign configuration (banner/popup):
  - status
  - placement
  - budget amount + currency
  - schedule window
  - targeting JSON and pop-up JSON
- Edit push campaign configuration:
  - status
  - budget amount + currency
  - schedule time
  - audience + geo targeting (city/region/radius)
- Configure marketing integrations:
  - Email marketing integration
  - Social ads integration

### Conflict protection

- If someone else updates a campaign while you are editing it, the save will return a conflict message.
- Reload the business summary and try again.

### Audit logging

- Every staff save action is logged to `audit_logs` with before/after snapshots, user, and IP address.

