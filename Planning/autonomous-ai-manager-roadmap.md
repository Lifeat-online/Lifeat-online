# Life@ Autonomous AI Manager Roadmap

Status: Phase 0 control-room foundation in progress.

This document defines the Life@ autonomous AI manager as an operating layer for the platform, not a free-running chatbot. The goal is to test how far an AI manager can run Life@ while preserving owner control, public trust, legal accountability, and clean financial records.

## Product Principle

Life@ can become an AI-operated local platform company. Jimmy remains the public assistant, while the AI Manager works behind the scenes as the general manager:

- Watches platform, revenue, content, advertising, writer, and support signals.
- Proposes daily operating actions.
- Funds human-written articles from a configured percentage of advertising revenue.
- Helps grow the platform through business advertising and campaign planning.
- Escalates risky public, financial, legal, or reputational actions to humans.

## Autonomy Modes

1. Observer
   - AI can read platform signals and create recommendations.
   - No public publishing, direct marketing, paid spend, payouts, refunds, or legal commitments.

2. Approval
   - AI drafts actions and packages them for owner/editor approval.
   - Humans approve before anything public or financial happens.

3. Budgeted Autonomy
   - AI can execute low-risk approved action classes inside hard caps.
   - Examples: draft campaign copy, queue article briefs, identify underperforming ads, prepare outreach lists.
   - Still blocked: real payouts, refunds, public publishing, direct marketing sends, external ad spend above cap.

4. Full Autonomy Sandbox
   - AI runs a cloned/sandboxed copy aggressively for experiments.
   - Compare AI decisions against revenue, quality, complaints, writer throughput, and owner review.

5. Production Autonomy
   - Only after measured success in the sandbox and budgeted phases.
   - Requires action allowlists, emergency stop, visible audit trails, rollback paths, and daily owner reports.

## Specialist Manager Agents

- Growth Agent: platform advertising, acquisition campaigns, outreach targets, package conversion.
- Editorial Agent: story ideas, brief queue, writer commissions, quality checks, article publishing readiness.
- Advertising Revenue Agent: ad package health, placement performance, underperforming creative detection, upsell suggestions.
- Finance Allocation Agent: revenue splits, article fund reserve, owner share, writer liability checks, payout preparation.
- Community Support Agent: user issues, business onboarding, listing corrections, complaints, reminders.
- Safety And Compliance Agent: sponsored labels, privacy risk, direct marketing risk, claims review, disclosure checks.

## Revenue Allocation Rule

Advertising-generated revenue should be split by policy:

- Article fund percentage: configurable by owner.
- Owner share: remaining advertising revenue after the article fund reserve.
- Human writer payments: paid from approved article ledgers and/or writer payment batches.
- Payouts stay human-approved until production autonomy is explicitly enabled.

The first implementation should calculate the article fund reserve from paid advertising/package revenue and compare it against pending writer liabilities. It should not move money automatically.

## Guardrails

- Public publishing requires human approval until specifically allowed by policy.
- Sponsored or paid placement content must remain clearly labelled.
- Direct marketing sends require human approval until policy and unsubscribe/compliance checks are proven.
- The AI manager must never fabricate reviews, testimonials, journalist quotes, payment status, or official statements.
- Real payouts, refunds, bank actions, and owner-share withdrawals require human approval.
- Every recommendation and decision must be logged with status, reviewer, risk level, rationale, and source payload.
- Emergency stop must pause all autonomous execution while leaving observer mode available.

## Phase 0 Implementation Slice

Build an admin-only AI Manager control room:

- Autonomy policy settings.
- Revenue allocation snapshot.
- Operational KPI brief.
- Deterministic recommendation generator.
- `ai_manager_actions` ledger for proposed, approved, dismissed, blocked, and executed actions.
- Owner-facing action review controls.

This gives Life@ a real AI manager operating surface without granting dangerous authority too early.

## Phase 1 Implementation Slice

Add AI-generated strategy briefs:

- Daily operating brief.
- Weekly growth report.
- Article commissioning plan based on the article fund reserve.
- Advertising improvement plan based on campaign performance.
- Suggested outreach copy for business acquisition.

Store each strategy brief as an AI generation and attach resulting proposed actions to the AI Manager ledger.

## Phase 2 Implementation Slice

Let approved action classes execute inside strict caps:

- Create draft article briefs.
- Create draft ad/push copy.
- Queue but do not send outreach.
- Prepare writer payment batches but do not mark paid.
- Prepare campaign recommendations but do not buy external ads.

## Phase 3 Sandbox Experiment

Clone current platform state into a test environment and let the AI Manager run simulated operations for 7 to 30 days. Score:

- Revenue changes.
- Article quality and writer throughput.
- Advertiser conversion.
- Support load.
- Risk events.
- Human override frequency.
- Owner trust in the recommendations.

## Current First Step

Implement the Phase 0 control room in the Life@ admin area. Keep all generated actions in proposed status by default.
