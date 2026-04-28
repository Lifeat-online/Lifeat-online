# Test, Deployment, Monitoring, And Compliance Plan

Primary sources:
- `platform-specification-source-of-truth.md`
- `api-surface-and-auth-strategy.md`
- `billing-package-invoice-and-payout-architecture.md`
- `admin-module-architecture.md`
- `database-schema-and-entity-relationship-plan.md`

This document defines the operational quality, delivery, monitoring, and compliance plan for the Laravel rebuild.

Goals:
- ensure the rebuild is testable and releasable
- define CI/CD and rollback expectations
- define monitoring, alerting, and KPI instrumentation
- define security and compliance evidence requirements
- align operational controls with the numbered platform clauses

## 1. Quality Strategy

## 1.1 Quality Principles

The platform should treat quality as a layered system:
- unit tests for business rules and domain services
- integration tests for infrastructure boundaries
- end-to-end tests for revenue-critical flows
- synthetic monitoring in production
- security and compliance validation as release gates

Clause trace:
- `11.b`, `11.c`, `11.d`, `11.e`, `12.a`

## 1.2 Critical Risk Areas

Highest-risk functional areas:
- PayFast checkout and webhook processing
- subscription activation and expiry
- event eligibility based on active business entitlement
- staff commission accrual and payout lifecycle
- writer word ledger and payment batching
- ad campaign delivery, impression caps, and geo targeting
- role-based access and finance overrides

Clause trace:
- `2.a`, `3.a`, `3.d`, `4.a`, `5.c`, `8.a` to `8.d`, `9.f`

## 2. Test Pyramid

## 2.1 Unit Tests

Target:
- greater than 80% coverage on domain-critical code

Best fit for:
- pricing calculators
- VAT calculations
- invoice numbering
- commission calculations
- entitlement decision services
- geo-ranking and fallback logic
- moderation state transitions
- policy and permission checks

Clause trace:
- `11.b`, `3.f`, `8.c`, `9.a`, `9.f`

## 2.2 Integration Tests

Use integration tests for:
- PayFast webhook verification and idempotency
- invoice generation
- subscription activation
- wallet ledger writes
- payout request state transitions
- settings persistence
- audit log creation
- media upload/storage path behavior

Clause trace:
- `8.a`, `8.c`, `8.d`, `9.e`, `11.b`, `11.d`

## 2.3 End-To-End Tests

Use E2E tests for:
- business directory purchase flow
- self-service business onboarding
- staff-assisted sales flow
- event add-on purchase flow
- checkout failure and retry flow
- writer article submit -> approve -> ledger flow
- staff payout request flow
- classifieds submit -> moderation -> publish flow

Clause trace:
- `2.a`, `3.c`, `3.d`, `4.a`, `7.c`, `8.c`, `11.b`

## 3. Required Test Suites

## 3.1 Public Page Tests

Coverage should include:
- route resolution
- SEO metadata presence
- filter and pagination behavior
- dark-mode toggle behavior where practical
- geo-aware ranking/order behavior

Clause trace:
- `2.b`, `3.f`, `4.d`, `10.a`, `10.b`, `10.c`

## 3.2 Admin Module Tests

Coverage should include:
- role access restrictions
- CRUD validation
- export authorization
- audit trail creation
- settings update propagation

Clause trace:
- `9.a` to `9.f`, `11.d`

## 3.3 Commerce Tests

Coverage should include:
- package pricing snapshots
- cart totals and VAT
- invoice emission
- payment retry flows
- refund and override effects
- renewal reminder scheduling

Clause trace:
- `8.a`, `8.b`, `8.c`, `8.d`, `9.e`

## 3.4 Security Tests

Coverage should include:
- access denial on unauthorized routes
- CSRF/session protection
- token scope enforcement
- rate limiting
- webhook signature verification
- file upload validation

Clause trace:
- `11.d`

## 4. Test Environment Strategy

## 4.1 Environment Tiers

Recommended environments:
- local development
- CI test environment
- staging / pre-production
- production blue
- production green

Clause trace:
- `11.c`

## 4.2 Test Data Strategy

Create stable fixtures for:
- users by role
- businesses with active and expired packages
- events with valid and invalid organiser eligibility
- orders, invoices, and payment outcomes
- classifieds with moderation states
- staff wallets with pending and available balances

Clause trace:
- `2.a`, `3.a`, `3.d`, `4.a`, `7.c`, `8.c`

## 4.3 Sandboxes

Use provider-safe test modes for:
- PayFast integration
- outbound email
- push notification provider
- accounting sync

Clause trace:
- `8.a`, `8.c`, `6.b`, `9.e`

## 5. CI/CD Pipeline Plan

## 5.1 Pipeline Stages

Recommended CI pipeline stages:
1. install dependencies
2. static analysis and lint
3. unit tests
4. integration tests
5. build frontend assets
6. package artifact/container
7. deploy to staging
8. smoke tests
9. approval gate
10. blue-green production deployment
11. post-deploy smoke tests

Clause trace:
- `11.b`, `11.c`

## 5.2 Release Gates

Blocking gates before production:
- tests pass
- migrations validated
- security checks pass
- staging smoke checks pass
- release notes produced

Clause trace:
- `11.c`, `11.d`, `12.a`

## 5.3 Blue-Green Deployment

Recommended production strategy:
- deploy new version to inactive environment
- run smoke and health checks there
- switch traffic only after validation
- keep old environment available for rollback

Clause trace:
- `11.c`

## 5.4 Rollback Strategy

Rollback target:
- under 2 minutes

Rollback requirements:
- immutable deploy artifacts
- reversible config switches
- careful migration strategy
- rollback runbook

Migration rule:
- prefer expand/migrate/contract patterns over destructive schema changes

Clause trace:
- `11.c`

## 6. Smoke Test Plan

After staging and production deployment, run automated smoke checks for:
- homepage
- article archive/detail
- directory archive/detail
- events archive/detail
- checkout page
- login
- admin dashboard
- PayFast webhook endpoint health

Clause trace:
- `2.b`, `3.f`, `4.d`, `8.a`, `9.f`, `11.c`

## 7. Performance And Load Testing

## 7.1 Performance Budgets

Primary targets:
- public pages under 2s on 3G budget assumptions
- optimized media delivery with lazy loading
- API p95 targets defined by endpoint type

Suggested initial API targets:
- public read endpoints: p95 < 300 ms
- authenticated CRUD endpoints: p95 < 400 ms
- finance and export-trigger endpoints: p95 < 500 ms

Clause trace:
- `10.b`, `12.c`

## 7.2 Load Testing Scope

Load-test these flows:
- homepage and public search
- directory archive with geo filters
- event archive/detail
- article detail with ad slots
- checkout init
- admin dashboard summaries

Clause trace:
- `3.f`, `4.d`, `5.a`, `10.b`, `12.c`

## 7.3 Required Evidence

Deliverables should include:
- load-test report
- methodology
- environment assumptions
- p95 response time evidence for 1,000 concurrent users

Clause trace:
- `12.c`

## 8. Monitoring And Alerting

## 8.1 Technical Monitoring

Monitor:
- uptime
- response time
- error rates
- queue health
- database health
- storage usage
- webhook failures
- scheduler failures

Clause trace:
- `11.e`

## 8.2 Business Monitoring

Monitor KPIs:
- daily active users
- revenue
- failed payments
- active subscriptions
- expiring subscriptions
- writer payable totals
- pending payout requests
- ad campaign delivery health
- push open rates

Clause trace:
- `11.e`

## 8.3 Alerting Thresholds

Create alerts for:
- payment webhook failures
- checkout failure spikes
- invoice generation failures
- queue backlog spikes
- site down or degraded
- failed renewal spikes
- payout job failures
- security anomaly/rate-limit spikes

Clause trace:
- `8.c`, `9.e`, `11.d`, `11.e`

## 8.4 Observability Requirements

Every service request should support:
- request correlation IDs
- structured logs
- traceable error events
- audit/event linkage for sensitive actions

Clause trace:
- `11.d`, `11.e`

## 9. Logging Strategy

## 9.1 Application Logs

Log:
- request failures
- business rule exceptions
- queue job failures
- webhook processing outcomes
- export failures
- notification send failures

## 9.2 Security Logs

Log:
- login failures
- permission denials on sensitive endpoints
- role changes
- refund actions
- payout approvals
- pricing changes
- override and extension actions

Clause trace:
- `9.a`, `9.e`, `11.d`

## 9.3 Retention

Define retention by class:
- short-lived verbose app logs
- longer-lived finance and security logs
- long-lived audit logs required for compliance/investigation

Clause trace:
- `11.d`

## 10. Security Assurance

## 10.1 Security Controls

Required baseline controls:
- HTTPS everywhere
- encrypted sensitive data at rest
- least privilege access
- secure secret management
- CSRF protection on session flows
- rate limiting
- audit logs
- token safety

Clause trace:
- `11.d`

## 10.2 Security Testing

Required testing activities:
- dependency vulnerability scanning
- SAST
- secrets scanning
- DAST on staging
- permission boundary tests
- webhook signature verification tests
- upload validation tests

Clause trace:
- `11.d`

## 10.3 Penetration Testing

Before release signoff:
- perform external or third-party penetration test
- document findings
- remediate critical/high findings
- retain certificate/report artifact

Clause trace:
- `12.d`

## 11. Compliance Plan

## 11.1 PCI-DSS / Payment Scope

Target operational stance:
- keep payment card handling out of platform storage as far as possible
- use provider tokenization
- avoid raw PAN storage
- document SAQ-A or equivalent scope position where possible

Clause trace:
- `8.b`, `11.d`, `12.d`

## 11.2 GDPR / POPIA

Required capabilities:
- privacy notice and lawful basis mapping
- data subject export process
- deletion/anonymization process where lawful
- minimization of stored sensitive data
- access controls on personal data

Clause trace:
- `11.d`

## 11.3 Compliance Evidence Pack

Maintain an evidence pack including:
- security policy references
- audit samples
- penetration test report
- payment token handling documentation
- retention/deletion procedures
- access review records

Clause trace:
- `11.d`, `12.d`

## 12. Documentation Deliverables

Required operational docs:
- API documentation
- admin handbook
- release checklist
- rollback runbook
- incident response guide
- finance operations guide
- staff payout operations guide
- writer payment operations guide

Clause trace:
- `12.b`, `12.e`

## 13. Training Deliverables

Training outputs should include:
- admin training recordings
- sales staff training recordings
- workflow demos for finance and payouts
- support workflow demos

Clause trace:
- `12.e`

## 14. Incident Response And Recovery

## 14.1 Severity Categories

Recommended categories:
- Sev 1: site unavailable, checkout unavailable, webhook outage
- Sev 2: degraded finance/admin operations, broken renewals
- Sev 3: isolated module defects

## 14.2 Required Runbooks

Create runbooks for:
- failed deployment rollback
- payment webhook outage
- invoice generation failure
- renewal job failure
- payout processing failure
- abusive traffic/rate-limit attack

Clause trace:
- `8.c`, `9.e`, `11.c`, `11.d`

## 15. Acceptance And Exit Criteria

The platform should not be considered production ready until all of the following are true:
- unit coverage target is achieved for critical business logic
- integration tests exist for payment webhooks
- E2E exists for checkout and revenue-critical flows
- CI/CD blue-green deployment and rollback are verified
- monitoring and alerting are active
- load testing demonstrates the target concurrency and response budget
- security review and penetration testing are complete
- compliance evidence for payment handling and privacy is documented
- admin and sales training materials are produced

Clause trace:
- `11.b`, `11.c`, `11.d`, `11.e`, `12.a`, `12.c`, `12.d`, `12.e`

## 16. Recommended Implementation Sequence

### Phase 1
- test foundations
- CI pipeline
- smoke tests
- basic uptime/error monitoring

### Phase 2
- webhook integration tests
- checkout E2E
- finance and payout test coverage
- structured logs and alerting

### Phase 3
- load testing
- DAST and pen-test
- compliance evidence pack
- rollback drills

Clause trace:
- `11.b`, `11.c`, `11.d`, `11.e`, `12.c`, `12.d`

## 17. Open Decisions

- Which observability stack to use for metrics, logs, and alerting
- Whether blue-green is achieved at infrastructure, container, or load-balancer layer
- Whether pen-testing is internal first, third-party only, or both
- Which endpoints count toward public API p95 SLA vs internal admin SLA
- What minimum coverage threshold applies only to domain code vs total repository coverage

These should be resolved before production delivery planning is finalized.
