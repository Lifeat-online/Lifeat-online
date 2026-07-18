# Life@ AI Platform Implementation Plan

**Status:** Canonical AI roadmap

**Owner:** Life@ platform team

**Last verified:** 2026-07-18 against `master` commit `e6f20edf213749a301b03c3e0b79e2588c562ea9`

**Target stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS, Alpine.js, Vite, queues, scheduler, Reverb, PostgreSQL 17, and pgvector 0.8.2

This is the authoritative plan for AI work in `Lifeat-online/Lifeat-online`. The older opportunity and autonomous-manager roadmaps are retained for historical context but are no longer independent sources of implementation priority.

## 1. Executive decision

Build three separately governed AI products on one shared infrastructure layer:

1. **Ask Life** — a read-only public assistant grounded only in published Life@ information.
2. **Editorial Intelligence** — internal research, evidence, drafting, and fact-checking tools with human publication approval.
3. **Operator Assistant** — an authenticated admin assistant that can use only registered, authorized, risk-classified tools.

Do not build a single unrestricted agent. No model receives raw database, SQL, shell, filesystem, deployment, secret, or direct-production access.

Ask Life is the first delivery priority. PostgreSQL migration is its infrastructure prerequisite. Editorial Intelligence follows the public-assistant foundation. The Operator Assistant starts only after the shared audit, policy, and evaluation controls are proven.

### Naming

- The public assistant is **Ask Life** in English and Afrikaans.
- **Jimmy** is reserved for the editorial writer.
- Existing public-widget references to Jimmy/Jakobus are compatibility copy to be replaced during the public-assistant UI phase.

## 2. How to maintain this plan

### Status legend

| Status | Meaning |
|---|---|
| Existing | Verified in the repository at the commit above. |
| Partial | Useful implementation exists but does not satisfy the target behavior. |
| Planned | Approved work not yet implemented. |
| Blocked | Cannot proceed until the named dependency or gate is complete. |
| Deferred | Intentionally outside the current delivery sequence. |

### Update rules

- Update the verification commit and date whenever implementation status changes.
- Mark work complete only after code, tests, and deployment evidence agree.
- Record architectural changes in the decision log; do not silently rewrite prior decisions.
- Keep one active backlog here. Specialist design or execution plans may add detail but must link back to a milestone in this document.
- Use exact exit criteria. A changed status or generated response is not proof that an external action occurred.

### Effort bands

| Band | Planning meaning |
|---|---|
| S | One contained, independently testable pull request. |
| M | Two or three coordinated pull requests with one release gate. |
| L | Multiple releases or infrastructure work with separate acceptance gates. |

Effort bands express relative scope, not delivery promises.

## 3. Verified repository baseline

| Area | Status | Verified state |
|---|---|---|
| AI gateway | Partial | `AiGatewayService` provides provider routing, structured generation, fallbacks, generation records, estimated cost, and budget enforcement, but remains a large service without capability-specific provider contracts. |
| Feature routing | Existing | `config/ai_features.php` routes AI features by provider/model profile. |
| Prompt management | Existing | `AiPromptCatalog` provides versioned defaults and database overrides. |
| Ask Life | Partial | `AskLifeService` is 2,216 lines and combines language, intent, retrieval, ranking, fallbacks, formatting, prompting, and model execution. |
| Public availability | Blocked | `AskLifeController` limits store, feedback, and speech endpoints to `dev`/`developer`. |
| Ask Life tests | Existing | Feature tests cover developer access, deterministic fallback, configured AI, source cards, bilingual behavior, voice, and non-developer blocking. |
| Research collection | Partial | RSS and Google News collection creates deduplicated `ResearchItem` records but does not snapshot full evidence, cluster reports, or track claims. |
| Editorial briefs | Partial | A brief is generated from one `ResearchItem`; editors can review, edit, approve, or reject it. |
| Jimmy writer | Partial | An approved brief can create an unpublished article draft, but the writer fetches supplied URLs at writing time and lacks a durable evidence graph. |
| AI Manager | Partial | The dashboard calculates signals, creates recommendations, and changes action statuses. It has no general typed tool runtime or verified execution layer. |
| Authorization | Existing | Role-aware routes and policies exist; `dev`/`developer` is treated as an operator equivalent for admin/editor access checks. |
| Audit/operations | Existing | Audit logs, AI operations, queues, scheduler, health checks, backups, and operator notifications provide foundations to extend. |
| Production database | Existing | The Hetzner/Coolify runbook currently documents SQLite. PostgreSQL migration is required before vector retrieval. |

## 4. Product and security boundaries

### Ask Life

- Users: anonymous visitors and signed-in users after staged rollout.
- Reads: published and publicly approved articles, events, listings, vouchers, classifieds, civic information, and public platform guides.
- Never reads: drafts, private profiles, addresses not intentionally public, payments, wallets, admin records, support notes, audit internals, or raw database rows.
- Never mutates platform data.
- Every factual answer must show supporting source cards or decline to answer.

### Editorial Intelligence

- Users: editors, developers, and approved editorial staff.
- May create research records, clusters, dossiers, briefs, notes, and unpublished drafts.
- May read allowlisted public sources through a hardened fetcher.
- Cannot publish automatically in the initial release.

### Operator Assistant

- Users: authenticated staff according to Laravel authorization and tool policy.
- Permissions come from the authenticated user, never a model-supplied role.
- All tools have typed inputs, risk level, authorization, idempotency behavior, audit behavior, and approval requirements.
- No arbitrary SQL, Artisan, PHP, shell, filesystem, secret, deployment, or production-code tools.

### Developer workflow

Code changes occur through an external GitHub workflow: issue or request, implementation plan, branch, isolated checks, draft pull request, human review, merge, then Coolify deployment. The production web application cannot pull code, merge, or deploy.

## 5. Shared AI infrastructure

Retain existing service entry points as compatibility facades while extracting focused components under `app/Ai/`.

Required provider capabilities:

- structured generation;
- conversational streaming;
- embeddings;
- optional reranking;
- tool calling;
- moderation/safety;
- image generation;
- voice.

Each configured feature declares its required capabilities. Configuration validation must reject a provider/model combination that cannot supply them.

Continue using existing generation records, budgets, feature routing, fallbacks, prompt overrides, retry controls, and masked keys. Add trace ID, latency, finish reason, actual token use, cache result, retrieval candidates, selected sources, tool calls, approvals, and normalized error category.

## 6. Milestone 0 — PostgreSQL and pgvector foundation

**Status:** Planned

**Effort:** L

**Blocks:** Knowledge indexing, semantic retrieval, authenticated Ask Life beta

### Target

- Coolify-managed [`pgvector/pgvector:0.8.2-pg17`](https://github.com/pgvector/pgvector#docker).
- A privileged Coolify database-administrator provisioning step creates the PostgreSQL `vector` extension before application deployment.
- The Laravel application role must not receive `CREATE EXTENSION`, database-owner, superuser, or equivalent infrastructure privileges.
- Laravel migrations verify that `vector` exists with `extversion = '0.8.2'` and then create application-owned vector columns and indexes; they fail with an actionable error when provisioning is missing or the version differs.
- Laravel production connection changed from SQLite to PostgreSQL only after rehearsal and reconciliation pass.
- Existing application migrations must run successfully from an empty PostgreSQL database.

### Selective data import

Start with a clean PostgreSQL schema and import business-critical records from a consistent SQLite snapshot.

Preserve:

- identity/access: users, roles, role assignments, authentication ownership, required consent/state, active browser-push subscriptions, and other user-owned records that are not explicitly transient;
- settings/reference: application settings, locations, categories, tags, packages, pricing, feature policy, and lookup/reference tables needed to interpret preserved records;
- editorial/public content: articles, authors, translations, briefs, events, listings, listing media, vouchers, classifieds, civic records, reviews, moderation state, and their pivots/history;
- commercial growth: advertising campaigns, advertisements, push campaigns, targeting, delivery/tracking history, applications, entitlements, and subscription records;
- mall commerce: every live `mall_*` ownership, catalogue, cart/order, payment, fulfilment, vendor, and related history table;
- transport: every live `transport_*` user, driver, vehicle, duty, request, quote, tracking, incident, and related history table;
- finance: all order families, payments, payouts, refunds, wallets, ledger entries, invoices, commissions, writer applications, writer payment batches/ledgers, and required external transaction references;
- operations/compliance: approvals, audit logs, operator alert/action state, AI Manager policy/actions, and records required to explain financial, public, moderation, or operational state;
- media/storage references used by preserved records.

Do not import:

- cache, queue, failed-job, session, password-reset, or temporary token rows;
- seeded test/demo records explicitly identified as disposable;
- disposable AI generations, transient chat history, or rebuildable derived indexes;
- orphaned records that fail the documented ownership or foreign-key rules.

Before any rehearsal, add a version-controlled migration manifest that lists every source table discovered from the final SQLite schema and classifies it as `PRESERVE`, `REBUILD`, or `DROP`, with its import order, transformation, reconciliation rule, and reason. The import command must fail when a source table is absent from the manifest. Family rules above define the default classification; an individual exception requires an entry in the decision log and product-owner approval.

### Import process

1. Inventory source tables, row counts, foreign keys, polymorphic references, encrypted fields, and SQLite-specific values.
2. Define an explicit include/exclude manifest and per-table transformation map.
3. Create a production backup and immutable SQLite archive.
4. Run the import against a non-production PostgreSQL database.
5. Compare source and target counts for every included table.
6. Reconcile financial totals by currency and status, plus wallet/ledger balances.
7. Verify ownership, pivot tables, slugs, timestamps, enum-like statuses, JSON, booleans, encrypted values, and media references.
8. Run application tests and representative authenticated/public smoke flows against PostgreSQL.
9. Before the production cutover, provision the clean PostgreSQL service and administrator-owned pgvector extension without pointing application traffic at it.
10. Enter maintenance mode and block all mutating traffic at the application/ingress boundary. Drain queued work to zero, then stop queue workers, scheduler/cron, Reverb consumers, and every other background writer. Verify no process can write before taking the final SQLite snapshot.
11. Hash and archive the final SQLite snapshot, run application migrations against the empty PostgreSQL schema, and execute the validated selective import in manifest order.
12. Reset every PostgreSQL identity/serial sequence after preserved IDs are imported, using each target table's maximum imported ID as the sequence baseline.
13. Reconcile counts, relationships, financial totals, balances, media, extension version, and representative records while traffic and background writers remain stopped.
14. Update production connection settings, clear/rebuild Laravel caches, and start web processes against PostgreSQL while public traffic remains closed. Run authenticated, public, payment-read, health, queue, scheduler, and backup smoke checks.
15. Apply the go/no-go gate. Before traffic reopens, any failed check restores the frozen SQLite configuration and restarts the previous processes; no accepted writes may exist on either database during this rollback.
16. Reopen traffic only after every exit criterion is signed off, then restart queue/scheduler/Reverb writers against PostgreSQL. This is the no-return gate: after PostgreSQL accepts new writes, SQLite is an audit archive and any rollback requires a separate reverse-migration/reconciliation plan that preserves those new writes.

### PostgreSQL exit criteria

- All migrations succeed from empty PostgreSQL.
- Coolify administrator provisioning enables `vector`, Laravel verifies `extversion = '0.8.2'`, and the restricted application role can create/query vector columns without extension-management privileges.
- Every source table is explicitly classified in the version-controlled import manifest; there are no unclassified tables.
- Every included table has a signed reconciliation result.
- Financial sums and wallet balances match the approved source snapshot.
- No excluded session, cache, queue, test, or disposable AI rows are imported.
- Imported PostgreSQL identity/serial sequences advance beyond the maximum preserved IDs.
- Login, roles, admin access, public pages, checkout/payment reads, queue, scheduler, backups, `/up`, and `/health` pass against PostgreSQL.
- The PostgreSQL backup and restore drill succeeds before AI indexing begins.

## 7. Milestone 1 — Ask Life compatibility refactor

**Status:** Planned

**Effort:** L

**Depends on:** PostgreSQL foundation for final integration; extraction work may begin earlier

Keep the current request and response contract stable while splitting responsibilities into focused services:

- `PublicAssistantService` — compatibility facade and orchestration;
- `QueryUnderstandingService` — locale, intent, entities, dates, location, and search terms;
- `RelationalRetriever` — existing public Eloquent queries and visibility rules;
- `RetrievalRanker` — deterministic ranking and business rules;
- `SourceCardFormatter` — source cards and actions;
- `GroundedAnswerService` — prompt preparation, model invocation, citation validation, and fallback;
- `PublicAssistantContext` and result value objects — stable typed boundaries between components.

### Compatibility requirements

- Preserve `/ask-life`, feedback, and speech payload shapes.
- Preserve English/Afrikaans output, deterministic guidance, record-type actions, page context, source cards, feedback, voice, budget stops, and provider fallbacks.
- Keep developer-only access until the authenticated-beta milestone.
- Voice remains supported but does not block anonymous text-chat launch.

### Compatibility-refactor exit criteria

- Existing Ask Life and voice feature tests pass unchanged except intentional naming-copy assertions.
- New unit tests cover each extracted service.
- Deterministic fixtures prove the old and new orchestration return equivalent structured results for the accepted compatibility cases.
- `AskLifeService` becomes a thin compatibility facade or is replaced only after callers migrate.

## 8. Milestone 2 — Public knowledge index and hybrid retrieval

**Status:** Planned

**Effort:** L

**Depends on:** PostgreSQL foundation and stable public-assistant interfaces

### Data model

Create canonical public knowledge documents and chunks with:

- source type and stable source ID;
- locale, title, canonical URL, public text, and structured metadata;
- visibility and publication timestamps;
- content hash and index version;
- chunk order, token/character counts, and searchable PostgreSQL text vector;
- embedding, provider, model, dimensions, and embedded timestamp.

Do not duplicate private fields in metadata. Deleting, unpublishing, expiring, or making a record private must remove or deactivate its public document and chunks.

### Embeddings

- Contract: `EmbeddingProvider::embed(array $texts): array` returns one normalized vector per input in order.
- Production default: direct OpenAI Embeddings API using [`text-embedding-3-small`](https://developers.openai.com/api/docs/models/text-embedding-3-small) with 1,536 dimensions.
- Store model and dimensions with each chunk and reject mixed-dimension queries.
- Tests use a deterministic fake; ordinary CI never calls a paid provider.
- Content hashes prevent re-embedding unchanged chunks.
- Retry transient failures through queues; leave failed chunks visible to the index audit without blocking lexical retrieval.

### Retrieval

1. Apply public visibility, locale, date-validity, and entity filters.
2. Retrieve relational candidates for exact records and business rules.
3. Retrieve PostgreSQL lexical candidates.
4. Retrieve pgvector cosine-similarity candidates.
5. Fuse ranked lists with reciprocal-rank fusion.
6. Apply freshness, location, record-type, voucher/event validity, and exact-match boosts.
7. Send only the bounded top context to answer generation.
8. Reject unsupported citations and return a cautious deterministic fallback when evidence is insufficient.

### Operations

- Add queued index/update/delete jobs triggered after committed public-record changes.
- Add `life:knowledge:reindex` with type/ID/locale filters, batching, resume behavior, and dry-run.
- Add an audit command for missing, stale, duplicate, private, failed, and model-mismatched chunks.
- Track index age, candidates, chosen sources, cache use, embedding usage/cost, latency, and no-answer rate.

### Knowledge-index exit criteria

- A 50-question accepted beta set reaches the expected source in the top five at least 90% of the time.
- Public/private visibility tests show zero draft or private leakage.
- Reindexing is idempotent and unchanged content is not re-embedded.
- Expired events/vouchers and unpublished records cannot support answers.
- Provider failure leaves relational/lexical fallback available.

## 9. Milestone 3 — Staged Ask Life rollout

**Status:** Blocked by Milestones 0–2

**Effort:** M

### Access stages

1. **Developer preview:** current access; validate traces, retrieval, cost, and evaluation results.
2. **Authenticated beta:** signed-in users; collect feedback and abuse signals against the 50-question set.
3. **Anonymous public:** enable only after the 150-question launch evaluation and security gates pass.

Each stage uses an independent database setting plus an environment master switch. Emergency disable must return deterministic search results and source cards rather than a server error.

### Public behavior

- Use SSE for initial text streaming; do not make Reverb a launch dependency.
- Persist chat sessions/messages for 30 days for anonymous and authenticated users.
- Allow earlier deletion of a user's linked chat history.
- Retain only redacted aggregate metrics after message expiry.
- Rate-limit by hashed IP, user, and session; cap messages, payload size, context, output, and daily/monthly spend.
- Show source cards, feedback controls, privacy purpose/retention copy, and clear AI disclosure.
- Treat external/public text as untrusted data that cannot choose tools or override instructions.

### Anonymous launch gate

- At least 150 versioned questions cover exact lookup, discovery, current/past events, locations, vouchers, civic help, English, Afrikaans, ambiguity, no-answer, prompt injection, and private-information requests.
- Expected public source appears in the top five for at least 90% of accepted retrieval cases.
- Every factual answer is supported by a returned source or declines to answer.
- Zero draft/private disclosures in feature and adversarial tests.
- Retention/deletion, rate limit, cost hard stop, provider failure, cache, feedback, and emergency-disable tests pass.
- Product owner approves the privacy notice and AI subprocessor/cross-border-processing disclosure.

## 10. Milestone 4 — Editorial Intelligence

**Status:** Planned after the public-assistant foundation

**Effort:** L

Build a durable evidence pipeline:

```text
Discovery -> secure fetch -> source snapshot -> cluster
          -> claims/evidence -> dossier -> brief
          -> draft/claim map -> fact-check -> copy edit
          -> human review -> translation -> publication
```

Key requirements:

- allowlisted source registry with source type, rights, trust, locale, schedule, and health;
- SSRF-safe fetcher with DNS/redirect rechecks, private-network blocking, byte/type/time limits, isolated parsing, and fetch audit;
- durable documents and immutable snapshots;
- story/event clustering and duplicate handling;
- claim-to-evidence links, contradiction visibility, source authority, and event confidence;
- Jimmy consumes an approved evidence package and cannot fetch arbitrary URLs at writing time;
- unsupported high-importance claims cannot pass the fact-check gate silently;
- editors approve public publication; translation and image generation occur after factual approval;
- generated illustrations cannot be presented as documentary photographs.

## 11. Milestone 5 — Operator Assistant

**Status:** Planned after shared audit/policy controls

**Effort:** L

### First release: read/propose

- persistent operator conversation and stable page/entity context;
- typed read-only tools for public content, users, listings, campaigns, finance summaries, AI operations, audits, and health;
- server-side Laravel authorization on every call;
- visible tool arguments/results, links to affected records, risk, and failure reason;
- proposals and diffs without mutation.

### Later: controlled mutations

- registered tools only;
- risk levels `R0` read, `R1` reversible low risk, `R2` approval required, `R3` dual/high-risk approval, and `R4` prohibited;
- signed expiring approvals invalidated by record/version changes;
- reauthorization immediately before execution;
- transactions, idempotency keys, pre/post snapshots, audit, verification, and rollback where reversible;
- publishing, finance, permissions, identity, deletion, deployment, and code operations remain human-approved or prohibited.

Target evaluation result: zero unauthorized executions.

## 12. Deferred opportunity register

These ideas came from the historical AI roadmaps. They remain valid opportunities but are not active scope until the preceding milestones meet their exit criteria.

| Opportunity | Intended home | Status |
|---|---|---|
| Listing copy, categorization, onboarding, and quality suggestions | Existing content-assistant patterns plus Operator tools | Deferred |
| Civic report classification, duplicate detection, severity, and public summaries | Editorial/Operator tools | Deferred |
| Transport request triage, incident summaries, demand forecasting, and safety detection | Operator tools | Deferred |
| Classified quality, fraud signals, and moderation assistance | Operator tools | Deferred |
| Voucher recommendations, expiry prompts, and campaign insights | Ask Life/Operator tools | Deferred |
| Review summarization, reputation signals, and coordinated-abuse detection | Operator tools | Deferred |
| Advertising copy, campaign planning, lead prioritization, and budget recommendations | Existing content assistant/Operator tools | Deferred |
| Personalization and narrative analytics | Separate privacy-reviewed product design | Deferred |
| Financial forecasting | Read-only analytics; no autonomous payments/refunds | Deferred |

## 13. Cross-cutting security and privacy

- Delimit untrusted content from instructions and never let retrieved text select tools.
- Validate all structured/model output against application schemas.
- Derive roles and permissions server-side; never accept model-supplied authorization.
- Keep secrets in environment/secret management and redact logs, prompts, traces, and settings.
- Store only required public fields in the knowledge index.
- Apply POPIA purpose limitation, minimization, deletion, retention, subprocessor, and cross-border-processing controls.
- Hash public IP data and avoid logging unnecessary message content.
- Provide feature-level budgets, per-user/session caps, provider routing, caching, and hard-stop fallback behavior.
- Maintain an environment-level emergency stop for public chat and operator execution.

## 14. Testing and evaluation

### Deterministic automated tests

- provider capability and configuration validation;
- public visibility, chunking, hashing, indexing, deletion, and model-version handling;
- PostgreSQL lexical/vector retrieval and rank fusion;
- citation/source validation and no-answer behavior;
- authorization, risk, approval expiry, stale-record concurrency, idempotency, audit, and rollback;
- SSRF validation, prompt injection, redaction, retention, deletion, rate limits, and cost stops;
- provider failure, malformed structured output, timeouts, and fallback routing.

All provider interactions use deterministic fakes in ordinary CI. Paid external calls run only in explicit evaluation environments.

### Versioned evaluation data

Store JSONL fixtures under `tests/Fixtures/AiEvaluations/`:

- Ask Life: 50 questions for authenticated beta, expanded to at least 150 for anonymous launch.
- Editorial: at least 50 historical source clusters with known evidence and outcomes.
- Operator: at least 100 authorization, approval, injection, retry, idempotency, and rollback scenarios.

Track retrieval recall@5, source precision, citation validity, groundedness, no-answer correctness, language, unsafe disclosure, latency, cost, editorial claim support, and unauthorized executions.

## 15. Observability and cost controls

Track requests, success/failure, p50/p95 latency, tokens, cost, cache rate, retrieval candidates, selected sources, no-answer rate, feedback, source health, extraction failures, dossier throughput, tool risk, approvals, blocked calls, failures, and rollbacks.

Use separate budgets for public chat, embeddings, research, writing, translation, images, operator tools, and developer workflow. When public-chat generation is unavailable or over budget, return deterministic search results and source cards.

Never log full secrets, raw financial data, or unnecessary private chat content.

## 16. First implementation package

The first coding package is complete only when these reviewable slices land in order:

1. PostgreSQL compatibility audit, complete table-classification manifest, restricted application-role design, and privileged Coolify pgvector provisioning.
2. Selective SQLite-to-PostgreSQL import, sequence reset, reconciliation report, backup/restore runbook, and write-free production cutover/rollback plan.
3. Ask Life behavior-lock tests and focused service interfaces.
4. Compatibility-preserving extraction from `AskLifeService`.
5. Knowledge document/chunk migrations, builders, and visibility rules.
6. OpenAI embedding provider contract, deterministic fake, queue jobs, reindex, and audit commands.
7. Relational, PostgreSQL lexical, and pgvector retrieval with rank fusion behind a disabled feature flag.
8. The 50-question authenticated-beta evaluation and security report.
9. A draft pull request for review; no automatic merge or deployment.

## 17. Decision log

| Date | Decision | Reason |
|---|---|---|
| 2026-07-18 | Use this file as the canonical AI roadmap. | Prevent three overlapping documents from becoming independent sources of truth. |
| 2026-07-18 | Deliver Ask Life first. | It extends a working developer preview and establishes shared retrieval, safety, budget, and evaluation foundations. |
| 2026-07-18 | Migrate production from SQLite to PostgreSQL before semantic retrieval. | pgvector keeps transactional public data and embeddings in one governed store. |
| 2026-07-18 | Start with a clean PostgreSQL schema and selectively import business-critical data. | Preserve business, public, financial, and compliance history without carrying transient/test state forward. |
| 2026-07-18 | Require an exhaustive per-table migration manifest. | Prevent unlisted schema families from being silently dropped during the selective import. |
| 2026-07-18 | Provision pgvector through a privileged Coolify administrator step. | Keep extension-management privileges away from the Laravel application role and verify the pinned extension version explicitly. |
| 2026-07-18 | Treat reopening production traffic as the database no-return gate. | Before reopening, rollback can safely restore the frozen SQLite database; afterward, new PostgreSQL writes must be preserved by a separate reverse-migration plan. |
| 2026-07-18 | Use direct OpenAI `text-embedding-3-small` behind an embedding contract. | Establish a concrete first provider while keeping tests deterministic and the implementation replaceable. |
| 2026-07-18 | Roll out developer preview, authenticated beta, then anonymous access. | Measure retrieval, privacy, abuse, and cost before opening public traffic. |
| 2026-07-18 | Retain chat content for 30 days. | Provide a clear uniform limit while allowing earlier deletion and longer-lived redacted metrics. |
| 2026-07-18 | Reserve Jimmy for editorial writing and name public chat Ask Life. | Avoid prompt, UI, analytics, and support ambiguity between two different products. |

## 18. Definition of done

### Ask Life completion

- Anonymous access is enabled only after the staged gates pass.
- Only published public records are retrieved.
- Every factual answer has valid source cards or declines to answer.
- English and Afrikaans, retention/deletion, rate limits, budgets, feedback, tracing, and emergency disable work.

### Editorial Intelligence completion

- Sources are securely acquired, snapshotted, clustered, and linked to claims.
- Contradictions and unsupported claims are visible.
- Jimmy writes only from approved evidence packages.
- Human publication approval remains mandatory.

### Operator Assistant completion

- Every tool is typed, authorized, risk-classified, and audited.
- Read/propose mode operates without mutation.
- Mutations are previewed and approved where required, transactional, idempotent, and verified.
- Evaluation records zero unauthorized executions.
- Code changes use draft pull requests rather than production application access.

## 19. Prohibited implementations

- One prompt or agent for public support, journalism, administration, finance, and development.
- Direct model database access or model-generated SQL execution.
- Arbitrary shell, Artisan, PHP, filesystem, secret, or deployment access.
- Automatic public publishing in the initial editorial release.
- Automatic refunds, payouts, account deletion, role changes, merge, or deploy.
- Indexing private fields and relying on prompting to hide them.
- Treating an AI Manager status change as proof an operation executed.
- Paid provider calls in ordinary CI.
- AI illustrations presented as documentary news photographs.
