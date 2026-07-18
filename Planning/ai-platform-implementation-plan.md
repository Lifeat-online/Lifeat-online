# Life@ AI Platform Implementation Plan

**Status:** Canonical AI roadmap

**Owner:** Life@ platform team

**Last verified:** 2026-07-18 on `feature/ai-platform` at implementation commit `68a43b9`

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
| Implemented | Code and deterministic local verification satisfy the scoped behavior; external release gates may still apply. |
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
| AI gateway | Implemented | Provider capability validation now rejects incompatible routes, and generation telemetry records trace ID, latency, finish reason, actual token use, cache result, and normalized failures. |
| Feature routing | Existing | `config/ai_features.php` routes AI features by provider/model profile. |
| Prompt management | Existing | `AiPromptCatalog` provides versioned defaults and database overrides. |
| Ask Life | Partial | `AskLifeService` is 2,216 lines and combines language, intent, retrieval, ranking, fallbacks, formatting, prompting, and model execution. |
| Public availability | Implemented | Developer, authenticated, and anonymous stages are independently gated by environment and database settings; authenticated and anonymous gates remain disabled by default. |
| Ask Life tests | Existing | Feature tests cover developer access, deterministic fallback, configured AI, source cards, bilingual behavior, voice, and non-developer blocking. |
| Research collection | Implemented | Collection creates durable snapshots, story clusters, dossiers, claims, and evidence links through an allowlisted SSRF-hardened fetcher. |
| Editorial briefs | Implemented | Briefs can be linked to evidence dossiers and unsupported high-importance claims block the evidence-writing gate. |
| Jimmy writer | Implemented | Jimmy consumes stored dossier/snapshot evidence and cannot fetch arbitrary URLs while writing. |
| AI Manager | Partial | A typed, authorized, risk-classified operator runtime supports health reads, article-status proposals, and approved article-status mutations; the broader operator conversation and domain tool catalog remain. |
| Authorization | Existing | Role-aware routes and policies exist; `dev`/`developer` is treated as an operator equivalent for admin/editor access checks. |
| Audit/operations | Existing | Audit logs, AI operations, queues, scheduler, health checks, backups, and operator notifications provide foundations to extend. |
| Deployment database | Partial | PostgreSQL is now the application default and the knowledge migration requires administrator-provisioned pgvector 0.8.2; live Coolify service verification remains. |

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

**Status:** Implemented in code; PostgreSQL CI and Coolify runtime verification pending

**Effort:** L

**Blocks:** Knowledge indexing, semantic retrieval, authenticated Ask Life beta

### Target

- Coolify-managed [`pgvector/pgvector:0.8.2-pg17`](https://github.com/pgvector/pgvector#docker).
- A privileged Coolify database-administrator provisioning step creates the PostgreSQL `vector` extension before application deployment.
- The Laravel application role must not receive `CREATE EXTENSION`, database-owner, superuser, or equivalent infrastructure privileges.
- Laravel migrations verify that `vector` exists with `extversion = '0.8.2'` and then create application-owned vector columns and indexes; they fail with an actionable error when provisioning is missing or the version differs.
- Laravel deployment configuration changes from SQLite to PostgreSQL.
- Existing application migrations must run successfully from an empty PostgreSQL database.

### Clean database reset

The site is not in production and existing SQLite data is disposable. Do not build data-import, reconciliation, rehearsal, dual-write, or rollback tooling.

1. Provision the clean PostgreSQL service and administrator-owned pgvector extension.
2. Point local/test deployment configuration at PostgreSQL.
3. Run all application migrations against the empty database.
4. Run the required reference and development seeders.
5. Verify login, roles, public pages, admin pages, queues, scheduler, backups, `/up`, and `/health`.
6. Configure the Coolify application, worker, and scheduler processes to use the same PostgreSQL service.
7. Remove obsolete SQLite deployment/runbook assumptions after the PostgreSQL deployment is verified.

### PostgreSQL exit criteria

- All migrations succeed from empty PostgreSQL.
- Coolify administrator provisioning enables `vector`, Laravel verifies `extversion = '0.8.2'`, and the restricted application role can create/query vector columns without extension-management privileges.
- Login, roles, admin access, public pages, checkout/payment reads, queue, scheduler, backups, `/up`, and `/health` pass against PostgreSQL.
- PostgreSQL backup and restore scripts support the clean deployment database; no migration rehearsal is required.

## 7. Milestone 1 — Ask Life compatibility refactor

**Status:** Partial; compatibility facade is active, but the remaining focused-service extraction is still required

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

**Status:** Implemented in code; PostgreSQL recall measurement remains a release gate

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

**Status:** Implemented behind disabled rollout gates; external evaluation, privacy approval, and staged enablement remain

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

**Status:** Implemented foundation; editorial workflow UI and accepted historical-outcome evaluation remain

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

**Status:** Partial; governed runtime and first tools are implemented, while persistent conversation and the broader read-tool catalog remain

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

1. Clean PostgreSQL/pgvector provisioning, restricted application-role design, application configuration, migrations, and seed verification.
2. PostgreSQL backup/restore runbook and removal of obsolete SQLite deployment assumptions.
3. Ask Life behavior-lock tests and focused service interfaces.
4. Compatibility-preserving extraction from `AskLifeService`.
5. Knowledge document/chunk migrations, builders, and visibility rules.
6. OpenAI embedding provider contract, deterministic fake, queue jobs, reindex, and audit commands.
7. Relational, PostgreSQL lexical, and pgvector retrieval with rank fusion behind a disabled feature flag.
8. The 50-question authenticated-beta evaluation and security report.
9. A draft pull request for review; no automatic merge or deployment.

### 16.1 Current implementation verification

Verified locally on 2026-07-18:

- full Laravel suite: 479 tests, 2,892 assertions;
- focused AI suite: 46 tests, 277 assertions;
- Vite production build completed;
- Composer metadata validation completed with pre-existing package-metadata warnings only;
- AI capability configuration validation completed;
- versioned fixture shape: Ask Life 150, Editorial 50, Operator 100, with zero fixture-marked unauthorized executions;
- PHP syntax and `git diff --check` completed.

The JSONL counts prove fixture coverage and schema only. They do not yet prove the PostgreSQL retrieval recall@5, groundedness, editorial historical outcomes, or live operator authorization targets. Those metrics must be measured in the PostgreSQL CI/evaluation environment before rollout gates are enabled.

The implementation deliberately does not include SQLite import, migration rehearsal, dual writes, or legacy-data rollback tooling because the database is disposable and the target is a clean PostgreSQL deployment.

Remaining code scope after this implementation package:

1. complete the focused-service extraction from the legacy `AskLifeService` compatibility engine;
2. add the persistent Operator conversation UI and the broader authorized read/propose tool catalog;
3. complete editor-facing dossier, contradiction, claim-map, and fact-check workflow screens;
4. run accepted evaluations against seeded PostgreSQL/pgvector data and record measured results.

Remaining external release gates:

1. run the PostgreSQL workflow and verify clean migrations under the restricted application role;
2. configure and verify the Coolify application, worker, scheduler, backups, health endpoints, and pgvector 0.8.2 service;
3. obtain product-owner approval for privacy/subprocessor disclosures before anonymous access;
4. enable authenticated and anonymous database gates only after their measured acceptance criteria pass.

## 17. Decision log

| Date | Decision | Reason |
|---|---|---|
| 2026-07-18 | Use this file as the canonical AI roadmap. | Prevent three overlapping documents from becoming independent sources of truth. |
| 2026-07-18 | Deliver Ask Life first. | It extends a working developer preview and establishes shared retrieval, safety, budget, and evaluation foundations. |
| 2026-07-18 | Use PostgreSQL instead of SQLite for deployment before semantic retrieval. | pgvector keeps transactional public data and embeddings in one governed store. |
| 2026-07-18 | Start with an empty PostgreSQL schema and do not migrate SQLite data. | The site is not in production and existing data is disposable, so import rehearsal and rollback tooling add no value. |
| 2026-07-18 | Provision pgvector through a privileged Coolify administrator step. | Keep extension-management privileges away from the Laravel application role and verify the pinned extension version explicitly. |
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
