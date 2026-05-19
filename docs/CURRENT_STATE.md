# CURRENT_STATE.md

Date: 2026-04-07

## Purpose

Current system state only.  
No historical narrative.  
No roadmap explanation.  
No future speculation beyond immediate continuity.

---

## Runtime Versions

- Plugin: `0.1.0`
- Schema: `1.22.0`

---

## Active Runtime Architecture

- Active path: `includes/*`
- Legacy path (not active): `includes/modules/*`
- Pattern: `Controller -> Service -> Repository -> Database`

---

## Current Delivery Baseline

- Last completed phase baseline: **Phase 57G-B Queue Retry Handling**
- Last completed block: **57G-B manual SaaS queue retry handling**
- Block status:
  - `39B` COMPLETE
  - `39C` COMPLETE
  - `39D` COMPLETE
  - `39E` COMPLETE
  - `40A` COMPLETE
  - `40B` COMPLETE
  - `40C` COMPLETE
  - `40D` COMPLETE
  - `41A` COMPLETE
  - `41B` COMPLETE
  - `41C` COMPLETE
  - `41D` COMPLETE
  - `41E` COMPLETE
  - `42A` COMPLETE
  - `42B` PARTIAL
  - `42C` COMPLETE
  - `42D` COMPLETE
  - `42E` COMPLETE
  - `43A` COMPLETE
  - `43B` COMPLETE
  - `43C` COMPLETE
  - `43D` COMPLETE
  - `43E` COMPLETE
  - `47A` COMPLETE
  - `47B` PARTIAL
  - `47C` PARTIAL
  - `48A` COMPLETE
  - `48B` COMPLETE
  - `48C` COMPLETE
  - `48D` PARTIAL
  - `48E` PARTIAL
  - `49A` COMPLETE
  - `49B` COMPLETE
  - `49C` COMPLETE
  - `49D` COMPLETE
  - `49E` COMPLETE
  - `50A` COMPLETE
  - `50B` COMPLETE
  - `50C` COMPLETE
  - `50D` COMPLETE
  - `50E` COMPLETE
  - `50F` COMPLETE
  - `56P1` COMPLETE technical
  - `56P2` COMPLETE technical
  - `56P3` PARTIAL runtime
  - `56P4` COMPLETE technical
  - `56P5` COMPLETE technical
  - `56P6` COMPLETE technical
  - `56P7` COMPLETE technical
  - `56P8` COMPLETE technical
  - `56P9` COMPLETE technical
  - `56P10` COMPLETE
  - `56P11` COMPLETE with minor fixture debt
  - `56P12` PARTIAL runtime
  - `56P13` COMPLETE
  - `57A` COMPLETE
  - `57B` COMPLETE
  - `57C` COMPLETE
  - `57D` COMPLETE
  - `57D1` COMPLETE
  - `57E` COMPLETE
  - `57F` COMPLETE
  - `57G-A` COMPLETA
  - `57G-B` COMPLETA

---

## Phase 56P Final Closure (COMPLETA documental)

Closure document:
- `docs/PHASE_56P_FINAL_CLOSURE.md`

Final baseline:
- Phase 56P is considered the stable pre-SaaS Mekvort baseline.
- Runtime validation was completed progressively through automated checks, targeted runtime checks, static/manual verification and QA evidence.
- Architecture is stabilized around:
  - `Controller -> Service -> Repository -> Database`
  - business-scoped permission and ownership checks
  - hardened API auth
  - notification/email service separation
  - embedded/shared PDF engine strategy
  - Vehicle Catalog as reusable catalog foundation
  - provider-agnostic inventory connector architecture

Consolidated capabilities:
- workshops, clients, mechanics, processes, quotes, invoices, reporting, PDFs, CRM, roles, catalog records, CSV catalog import and mock inventory connector simulation.

Remaining macro debt:
- real provider connectors
- scheduled sync
- webhook sync
- media sync
- retry queues
- advanced duplicate matching
- SaaS orchestration
- multitenancy evolution
- performance/indexing future work

Next continuity:
- Phase 57 — SaaS Foundation

---

## Phase 57 Roadmap Alignment (COMPLETA documental)

Roadmap document:
- `docs/PHASE_57_SAAS_FOUNDATION.md`

Task contract:
- `docs/contracts/57-ROADMAP-ALIGNMENT.md`

Validation contract:
- `docs/contracts/validation/57-ROADMAP-ALIGNMENT-validation.md`

Official Phase 57 structure:
- `57A — SaaS foundation bootstrap`
- `57B — Tenant context layer`
- `57C — SaaS licensing`
- `57D — Async jobs / queues`
- `57E — Connector runtime`
- `57F — Queue persistence foundation`
- `57G — Media architecture`
- `57H — SaaS operations`

Principles:
- preserve `Controller -> Service -> Repository -> Database`
- preserve Vehicle Catalog as canonical internal inventory model
- preserve provider-agnostic connector strategy
- preserve business scope isolation
- prefer async-first future architecture
- evolve toward SaaS without breaking current plugin runtime

Deferred/not included:
- no multiserver orchestration
- no Kubernetes/container orchestration
- no billing provider implementation
- no real connector providers
- no websocket/live sync
- no queue workers implementation
- no runtime/schema/API changes

Scope safeguards:
- documentation-only roadmap alignment
- no `includes/*` changes
- no `assets/*` changes
- no schema/database changes
- no runtime code changes

---

## Phase 57A SaaS Foundation Bootstrap (COMPLETA)

Architecture document:
- `docs/SAAS_FOUNDATION_ARCHITECTURE.md`

Task contract:
- `docs/contracts/57A.md`

Validation contract:
- `docs/contracts/validation/57A-validation.md`

Delivered foundation layer:
- passive SaaS namespace under `includes/saas/*`
- runtime context abstraction:
  - `self_hosted`
  - `saas_future`
  - `local_development`
- tenant context abstraction:
  - future `tenant_id`
  - current `business_id`
  - `business_id` remains the active scope boundary
- license context abstraction:
  - `license_key`
  - `subscription_status`
  - `plan_type`
  - `instance_id`
- queue/async placeholder contracts:
  - queue jobs
  - retry jobs
  - scheduled sync jobs

Scope safeguards:
- no runtime bootstrap rewiring
- no CRM/process/payment/API changes
- no schema/database changes
- no assets changes
- no billing provider, OAuth provider, queue worker or external SaaS integration

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57A-validation.md`) PASS automated checks:
  - PASS: 8
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static/manual scope verification PASS:
  - runtime modes are defined
  - tenant abstraction preserves current `business_id`
  - license abstraction has no billing provider integration
  - queue contracts are placeholders only
  - no forbidden modules were touched by this phase

Runtime/manual state:
- runtime real NOT_APPLICABLE
  - passive foundation layer only; no UI, schema, API, external provider or worker is wired in this phase

---

## Phase 57B Tenant Context Layer (COMPLETA)

Task contract:
- `docs/contracts/57B.md`

Validation contract:
- `docs/contracts/validation/57B-validation.md`

Delivered bridge:
- `Tenant_Context` enhanced as a passive bridge between future `tenant_id` and current `business_id`
- `Runtime_Context::get_tenant_context()` added for future composition layers
- `business_id` remains the canonical active runtime scope
- `tenant_id` remains nullable/future-only unless explicitly derived for a future SaaS runtime context

Scope safeguards:
- no runtime hooks
- no API changes
- no frontend/assets changes
- no schema/database changes
- no users/roles, CRM, process or payment changes
- no billing and no tenant DB split

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57B-validation.md`) PASS automated checks:
  - PASS: 9
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks
- static/manual scope verification PASS:
  - `business_id` remains active scope
  - tenant context remains passive
  - no schema/database changes
  - no API/frontend changes
  - no runtime hooks

Runtime/manual state:
- runtime real NOT_APPLICABLE
  - passive bridge only; not wired into current UI/API/schema/runtime hooks

---

## Phase 57C Licensing & Subscription Core (COMPLETA)

Task contract:
- `docs/contracts/57C.md`

Validation contract:
- `docs/contracts/validation/57C-validation.md`

Delivered bridge:
- `License_Context` enhanced as a passive bridge from current local license state to future SaaS subscription state
- `Subscription_Context` added as passive subscription abstraction
- entitlement snapshot normalized with:
  - `max_businesses`
  - `max_users`
  - `max_vehicles`
  - `max_webhooks`
  - `feature_flags`
- stable local `instance_id` prepared without external registration

Scope safeguards:
- no Stripe/payment provider
- no external billing API
- no webhooks
- no schema/database changes
- no runtime enforcement takeover
- no CRM/process/payment/users/API/frontend/assets changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57C-validation.md`) PASS automated checks:
  - PASS: 9
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks
- static/manual scope verification PASS:
  - subscription context is passive
  - no Stripe/payment provider/API calls
  - current license behavior preserved
  - no schema/database changes
  - no runtime enforcement takeover

Runtime/manual state:
- runtime real NOT_APPLICABLE
  - passive subscription context only; not wired into UI/API/schema/billing/webhooks

---

## Phase 57D Async Queue Foundation (COMPLETA)

Task contract:
- `docs/contracts/57D.md`

Validation contract:
- `docs/contracts/validation/57D-validation.md`

Architecture document:
- `docs/ASYNC_QUEUE_FOUNDATION.md`

Delivered foundation:
- passive async job contract under `includes/saas/*`
- queue job normalization with:
  - `job_id`
  - `job_type`
  - `business_id`
  - nullable `tenant_id`
  - `payload`
  - `status`
  - `attempts`
  - `max_attempts`
  - `scheduled_at`
  - `created_at`
  - `last_error`
- passive queue context, dispatcher and result classes
- supported future job types:
  - `inventory_import`
  - `inventory_connector_sync`
  - `email_delivery`
  - `webhook_delivery`
  - `google_calendar_sync`
  - `pdf_generation`
- supported statuses:
  - `pending`
  - `running`
  - `completed`
  - `failed`
  - `retry_scheduled`
  - `cancelled`

Scope safeguards:
- no schema/database changes
- no `$wpdb` or SQL in SaaS queue classes
- no cron/hook registration
- no external HTTP calls
- no workers or background execution
- existing active `includes/queue/*` runtime remains unchanged

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57D-validation.md`) PASS automated checks:
  - PASS: 13
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static/manual scope verification PASS:
  - no `$wpdb`/SQL usage in `includes/saas/*`
  - no hook/cron registration in `includes/saas/*`
  - no external HTTP calls in `includes/saas/*`
  - no schema/database diff

Runtime/manual state:
- runtime real NOT_APPLICABLE
  - passive contract layer only; no UI, worker, schema, external provider or active background execution is introduced

Deferred:
- persistent SaaS queue storage
- worker runtime
- retry executor and dead-letter queue
- scheduled sync activation
- connector/email/webhook/calendar/PDF async wiring

---

## Phase 57D1 Async Queue Runtime Smoke Validation (COMPLETA)

Task contract:
- `docs/contracts/57D1.md`

Validation contract:
- `docs/contracts/validation/57D1-validation.md`

Task evidence:
- `docs/tasks/2026-04-57d1-async-queue-runtime-smoke-validation.md`

Smoke result:
- passive queue objects instantiate successfully
- valid jobs normalize for:
  - `inventory_import`
  - `inventory_connector_sync`
  - `email_delivery`
  - `webhook_delivery`
  - `google_calendar_sync`
  - `pdf_generation`
- normalized job structure includes all required fields
- invalid jobs return expected errors:
  - `unsupported_job_type`
  - `business_id_required`
  - `payload_must_be_array`
  - `attempts_exceed_max_attempts`
- dispatcher remains passive:
  - `writes = 0`
  - `executed = false`
  - `passive = true`
- status model exposes:
  - `pending`
  - `running`
  - `completed`
  - `failed`
  - `retry_scheduled`
  - `cancelled`

Safe fix:
- `Queue_Job_Contract` now preserves non-array payloads as invalid so smoke validation can report `payload_must_be_array`.

Scope safeguards:
- no real workers
- no cron/hook registration
- no database writes
- no external HTTP calls
- no schema/database changes
- no connector execution
- no email sending
- no PDF generation

Runtime/manual state:
- runtime smoke PASS
- runtime real NOT_APPLICABLE
  - passive class validation only; no UI, worker, schema, external service or background execution is activated

---

## Phase 57E Connector Runtime Wiring (COMPLETA)

Task contract:
- `docs/contracts/57E.md`

Validation contract:
- `docs/contracts/validation/57E-validation.md`

Task evidence:
- `docs/tasks/2026-04-57e-connector-runtime-wiring.md`

Delivered bridge:
- mock inventory connector dry-run intent now builds a passive `inventory_connector_sync` queue job
- mock inventory connector sync simulation intent now builds a passive `inventory_connector_sync` queue job
- `Inventory_Connector_Service` exposes:
  - `build_dry_run_job(...)`
  - `build_sync_job(...)`
  - `dispatch_dry_run_intent(...)`
  - `dispatch_sync_intent(...)`

Runtime smoke result:
- dry-run queue job PASS:
  - `connector_key`: `mock_inventory`
  - `operation`: `dry_run`
  - `dry_run`: `true`
  - `provider_type`: `mock`
  - normalized item count: `3`
  - `writes = 0`
  - `executed = false`
  - `passive = true`
- sync-intent queue job PASS:
  - `connector_key`: `mock_inventory`
  - `operation`: `sync_simulation`
  - `dry_run`: `false`
  - `provider_type`: `mock`
  - normalized item count: `3`
  - `writes = 0`
  - `executed = false`
  - `passive = true`

Scope safeguards:
- no real provider APIs
- no OAuth
- no scheduled sync
- no real job execution
- no DB writes
- no schema changes
- no admin UI

Runtime/manual state:
- runtime smoke PASS
- runtime real NOT_APPLICABLE
  - passive bridge only; no UI, worker, schema, external service or background execution is activated

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57E-validation.md`) PASS automated checks:
  - PASS: 8
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static/manual scope verification PASS:
  - no `$wpdb`/SQL usage in 57E wiring scope
  - no hook/cron registration in 57E wiring scope
  - no external HTTP calls in 57E wiring scope
  - no real import, worker, email or PDF execution in 57E wiring scope
  - no schema/database changes

---

## Phase 57F Queue Persistence Foundation (COMPLETA)

Task contract:
- `docs/contracts/57F.md`

Validation contract:
- `docs/contracts/validation/57F-validation.md`

Task evidence:
- `docs/tasks/2026-04-57f-queue-persistence-foundation.md`

Delivered foundation:
- SaaS queue persistence table registered:
  - `sm_saas_queue_jobs`
- schema version updated to `1.22.0`
- repository added:
  - `includes/saas/class-queue-job-repository.php`
- `Queue_Context` now supports opt-in `persistence_enabled`
- `Queue_Dispatcher` can persist normalized jobs only when persistence is explicitly enabled
- default dispatcher remains passive and non-persistent

Repository methods:
- `create_job(array $job)`
- `get_job_by_id($job_id)`
- `list_jobs(array $filters = array())`
- `update_status($job_id, $status, array $meta = array())`
- `mark_failed($job_id, $error)`
- `mark_completed($job_id)`
- `schedule_retry($job_id, $available_at, $error = '')`

Runtime smoke result:
- table `wp_sm_saas_queue_jobs` exists -> PASS
- repository create/retrieve/update/retry/complete -> PASS
- dispatcher default does not persist -> PASS
- dispatcher persistence-enabled persists without execution -> PASS
- persisted dispatcher result:
  - `persisted = true`
  - `writes = 1`
  - `executed = false`
  - `passive = true`

Scope safeguards:
- no workers
- no cron/hook activation
- no external HTTP calls
- no connector execution
- no email sending
- no API/admin UI/frontend changes
- no CRM/users/process/payment changes

Runtime/manual state:
- runtime persistence smoke PASS
- runtime real browser/admin NOT_APPLICABLE
  - no UI, API endpoint, worker, cron, external provider or background execution is activated

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/57F-validation.md`) PASS automated checks:
  - PASS: 12
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static/manual scope verification PASS:
  - SQL/`$wpdb` only in repository/database layer
  - no hook/cron registration in 57F scope
  - no external HTTP calls in 57F scope
  - no worker, connector execution, email or PDF execution in 57F scope
  - no forbidden module changes by 57F

Deferred:
- worker runtime
- scheduler/cron activation
- job claim/lock execution lifecycle
- dead-letter queue
- retention/pruning
- queue admin UI
- external queue provider

---

## Phase 57G-A Manual Queue Worker (COMPLETA)

Task contract:
- `docs/contracts/57G-A.md`

Validation contract:
- `docs/contracts/validation/57G-A-validation.md`

Task evidence:
- `docs/tasks/2026-04-57g-a-manual-queue-worker.md`

Delivered foundation:
- manual queue worker added:
  - `includes/saas/class-queue-worker.php`
- repository claim helpers added:
  - `get_next_available_job(...)`
  - `claim_job(...)`
  - `release_lock(...)`
  - `update_attempts(...)`
  - `mark_running(...)`
- worker processes one job per manual `process_next(...)` call
- supported job type:
  - `inventory_connector_sync`
- handler is simulation-only and rejects unsafe non-simulation payloads

Runtime smoke result:
- valid simulation job:
  - `pending -> completed`
  - handler code `simulation_completed`
  - `executed = false`
  - handler writes `0`
- invalid unsafe job:
  - `pending -> failed`
  - last error `unsafe_non_simulation_payload`
  - `executed = false`
- lock token behavior:
  - claimed job stores lock token while `running`
  - lock token is cleared after completion

Scope safeguards:
- no cron/hook activation
- no scheduled worker
- no batch processing
- no external HTTP calls
- no real provider API calls
- no catalog imports
- no email/PDF/Google Calendar execution
- no API/admin UI/frontend changes

Runtime/manual state:
- runtime manual smoke PASS
- runtime real browser/admin NOT_APPLICABLE
  - no UI, API endpoint, cron, scheduler, external provider or background execution is activated

Validation state:
- `php-lint` PASS
- QA Runner PASS: 13 automated checks passed, 0 failed, 0 skipped, 5 manual checks not run by the runner.
- static/manual forbidden-pattern verification PASS: no cron/scheduled hooks, no external HTTP/provider execution, no real imports, no email sending, no PDF generation and no Google Calendar execution in worker/repository changes.

Deferred:
- automatic workers
- scheduler/cron activation
- retry executor
- dead-letter queue
- admin UI
- real connector handlers
- operational observability UI

---

## Phase 57G-B Queue Retry Handling (COMPLETA)

Task contract:
- `docs/contracts/57G-B.md`

Validation contract:
- `docs/contracts/validation/57G-B-validation.md`

Task evidence:
- `docs/tasks/2026-04-57g-b-queue-retry-handling.md`

Delivered behavior:
- manual queue worker now schedules retries for failed jobs below max attempts
- attempts increment on processing failure, not on claim
- retry scheduling uses deterministic backoff:
  - attempt 1: +5 minutes
  - attempt 2: +15 minutes
  - attempt 3 and above: +30 minutes
- future `retry_scheduled` jobs remain ignored by `process_next()`
- due `retry_scheduled` jobs can be picked manually
- jobs reaching max attempts become final `failed`
- lock token is cleared after retry scheduling and final failure

Runtime smoke result:
- first failing job:
  - `retry_scheduled`
  - attempts `1`
  - future `available_at`
  - `lock_token = null`
  - `executed = false`
- future retry job:
  - skipped with `no_available_job`
- due retry job:
  - picked manually and rescheduled with attempts `2`
  - `executed = false`
- max attempts job:
  - final `failed`
  - attempts `3`
  - `lock_token = null`
  - `executed = false`

Scope safeguards:
- no cron/hook activation
- no scheduled worker
- no batch processing
- no external HTTP calls
- no real provider API calls
- no catalog imports
- no email/PDF/Google Calendar execution
- no API/admin UI/frontend changes

Runtime/manual state:
- runtime manual smoke PASS
- runtime real browser/admin NOT_APPLICABLE
  - no UI, API endpoint, cron, scheduler, external provider or background execution is activated

Validation state:
- `php-lint` PASS
- QA Runner PASS: 12 automated checks passed, 0 failed, 0 skipped, 6 manual checks not run by the runner.
- static/manual forbidden-pattern verification PASS: no cron/scheduled hooks, no external HTTP execution, no real imports, no email sending, no PDF generation and no Google Calendar execution in worker/repository/context changes.

Deferred:
- automatic workers
- scheduler/cron activation
- retry executor daemon
- dead-letter queue
- admin UI
- real connector handlers
- operational observability UI

---

## 42 Delivery Scope

- 42A:
  - assisted operational actions with safe manual navigation
- 42B (PARTIAL):
  - controlled reassignment implementation for `crm_task` is complete
  - technical validation is OK
  - observability for zero-proposal state is complete
  - runtime validation remains pending due to lack of dataset with:
    - overloaded users
    - available users
    - executable reassignment candidate
- 42C:
  - safe bulk actions layer (`bulk_resolve`, `bulk_reassign`) with strict validations
- 42D:
  - operational action center consolidating assisted, reassignment and bulk execution entry points
- 42E:
  - configurable executable-rules layer (evaluation + preview only, no auto execution)
- Constraints respected:
  - no new tables
  - no cron
  - no automatic execution in configurable rules
  - no functional regressions reported in core modules

---

## Automation Execution Layer (Fase 43)

Status: **COMPLETA**

Delivered capabilities:
- operational rules engine evaluable and persistent by `business_id`
- execution in three levels:
  - guided (`manual`)
  - confirmable (`confirm_required`)
  - auto controlled (`auto_controlled`)
- active execution guardrails with explicit allow/deny context
- controlled rollback support for supported mutation actions
- UX empty states for tenant with no operational data and empty critical action center

Conclusion:
- System is ready for controlled production operations with guarded automation.

---

## System Capability State

The system is now capable of:

- complete operational observability layer
- controlled manual execution layer:
  - assisted actions
  - controlled reassignment
  - safe bulk actions
- configurable rules layer:
  - rule definition
  - rule evaluation
  - action preview
  - persistent tenant configuration
- multi-level execution layer:
  - guided
  - confirmable
  - auto controlled
- mandatory safety layer:
  - guardrails
  - rollback for supported actions

This confirms transition from **controlled automation readiness -> controlled automation execution**

---

## Operational Constraints

- No external notification automation yet (email, WhatsApp, etc.)
- No mass automation rollout yet
- No push/real-time notification layer
- Tenancy enforced by `business_id`
- Alerts are **persisted-first**, not computed on-demand

---

## 46A Query Optimization State

Status: **Applied (technical)**  

Delivered in current code:
- request-level optimization in admin render paths to avoid loading full workload payload only to resolve `business_id` in:
  - Automation Center
  - Operational Logs
- operational rules service instance reuse in dashboard controller to avoid duplicate service bootstrap in same request
- logs listing actor resolution optimized from per-row lookup to batch lookup with in-request memoization

Functional behavior:
- unchanged business logic
- unchanged safety/guardrails
- unchanged CRM Pipeline behavior

---

## 47A UX Based On Profiling State

Status: **COMPLETE**

Delivered in current code:
- dashboard visual hierarchy refined with operational focus first
- lazy-loaded secondary sections compacted for lower visual dominance
- lighter UX copy for scanability in header and deferred sections
- lightweight lazy-state feedback (`loaded` / `error`) without business-logic changes
- compatibility preserved with request-level caching, lazy loading and profiling instrumentation

Validation state:
- `php-lint` PASS
- validation contract PASS in automated checks
- runtime/manual validation confirmed

Constraints respected:
- no business-logic changes
- no CRM Pipeline changes
- no new pages
- no extra data recomputation

---

## 48D Lightweight Dashboard Preferences State

Status: **Applied (technical)**

Delivered in current code:
- per-user dashboard UI preferences stored via `user_meta`
- supported preference keys:
  - `collapsed_blocks`
  - `hidden_secondary_blocks`
  - `compact_mode`
- secondary block controls added for:
  - Smart suggestions
  - Automation summary
  - Secondary operational data (lazy section shell)
- critical blocks remain outside hide controls:
  - KPI header
  - Centro de Acción Operativa
  - Mi trabajo
  - Quick actions

Validation state:
- `php-lint` PASS
- validation contract automated checks PASS
- runtime/manual pending for closure

---

## 48E Roles & Access Management State

Status: **Applied (technical)**

Delivered in current code:
- dedicated admin page `Roles & Access` (`super-mechanic-roles`)
- operational role/access summary service:
  - WP roles
  - detected operational role
  - `business_id`
  - dashboard access
  - automation/logs access
  - warning summary
- safe basic actions via POST + nonce:
  - assign `sm_admin`
  - assign `sm_mechanic`
  - remove operational role
- inconsistency visibility for common internal-access mismatches

Validation state:
- `php-lint` pending for this phase
- validation contract execution pending for this phase
- runtime/manual pending for closure

---

## Fase 49 Multi-Business Access Model

Status: **COMPLETA**

Consolidated delivery:
- 49A — Business Membership Model:
  - table `sm_business_user_roles` active
  - installer/repository/service available for memberships
- 49B — Super Admin / Global Access:
  - global scope centralized in `Role_Access_Service`
  - canonical superadmin identity: `admin@mardisom.com`
  - non-global users restricted to active memberships
- 49C — Roles & Access UI by business:
  - secure membership management UI in `super-mechanic-roles`
  - membership actions protected by capability + nonce
- 49D — Membership transfers:
  - transfer modes `replace` and `add` available
  - primary consistency preserved during transfer
- 49E — Consistency hardening:
  - centralized consistency validation methods:
    - `validate_membership_consistency($user_id)`
    - `get_membership_consistency_warnings($user_id)`
  - safe repair method:
    - `repair_membership_consistency($user_id)`
  - precise warnings and safe repair action surfaced in Roles & Access

Final model state:
- memberships per business are operational
- global vs membership-scoped access is explicit
- admins/mechanics/clients can be managed by business scope
- transfers are available without aggressive destructive behavior
- consistency hardening protects ambiguous/invalid membership states

Validation state:
- technical checks: PASS
- runtime/manual checks: validated for Fase 49 closure

---

## Phase Closure Review 47-50 (2026-04-06)

Consolidated closure status:
- Fase 47: **PARCIAL**
  - 47A closed
  - 47B/47C remain partial in closure evidence
- Fase 48: **PARCIAL**
  - 48A/48B/48C delivered
  - 48D/48E still documented as runtime/manual pending
- Fase 49: **COMPLETA**
  - consolidated closure documented and runtime validated
- Fase 50: **COMPLETA**
  - 50A-50F validated with consolidated runtime/manual evidence in 50Z
  - notifications, webhooks and automation engine tested end-to-end in runtime
  - no duplicate events observed in runtime closure checks

---

## Known Active Debt (Current)

- Legacy placeholder files still present:
  - `class-rest-api`
  - `class-hooks`
  - `class-post-types`
- No full automated WordPress E2E runtime suite
- QA runner coverage is partial (technical checks only)
- API key / webhook admin UX can be expanded
- No caching layer for heavy aggregations (future need)

---

## Next Continuity

### Fase 50 — Notificaciones / Triggers / Integraciones

Continuity target:
- leverage the finalized Fase 49 multi-business access model
- add notifications/triggers without regressions in roles, memberships and dashboard
- preserve tenant isolation and safety guarantees from Fases 43–49
- move continuity to next phase planning after Fase 50 closure

---

## Fase 51A Licensing System Base

Status: **Applied (technical)**

Delivered in current code:
- local licensing table `sm_licenses` via installer/repository/service/admin controller pattern
- status support:
  - `active`, `inactive`, `expired`, `revoked`
- plan support:
  - `starter`, `pro`, `enterprise`
- local activation/deactivation by admin page:
  - `Super Mechanic -> License` (`super-mechanic-license`)
- local domain binding using current WordPress site domain
- non-blocking behavior preserved:
  - no aggressive enforcement
  - no CRM Pipeline impact

Validation state:
- `php-lint` PASS
- validation contract automated checks PASS
- runtime/manual pending for phase closure

---

## Fase 51B Branding / White-label Base

Status: **Applied (technical)**

Delivered in current code:
- centralized branding settings service with defaults and WP option persistence:
  - `system_name`
  - `logo_url` / `logo_attachment_id`
  - `primary_color`
  - `secondary_color`
  - `admin_footer_text`
- dedicated admin page:
  - `Super Mechanic -> Branding` (`super-mechanic-branding`)
- secure save flow:
  - capability `sm_manage_plugin`
  - nonce-protected POST action
  - strict sanitization
- safe visual application across plugin admin pages:
  - runtime color variables applied in plugin shells
  - branded top banner (name/logo)
  - optional branded admin footer text

Validation state:
- `php-lint` PASS
- validation contract automated checks PASS
- runtime/manual pending for phase closure

---

## Fase 51C Plan Limits / Pricing Base

Status: **Applied (technical)**

Delivered in current code:
- centralized plan limits layer through `Plan_Limits_Service`:
  - plan catalog: `starter`, `pro`, `enterprise`
  - limits and usage status methods:
    - `get_plan_limits()`
    - `get_current_plan_type()`
    - `get_current_usage()`
    - `get_limit_status()`
    - `is_within_limit()`
    - `get_exceeded_limits()`
- tracked resources for visible non-blocking limits:
  - businesses
  - internal users
  - active processes
  - active webhooks
- global active webhook counting support in repository:
  - `Webhook_Repository::count_active_webhooks($business_id = 0)`
- License admin page extended with:
  - effective plan
  - limit matrix per resource
  - current usage
  - exceeded warning state
  - starter fallback notice when license is inactive

Validation state:
- `php-lint` PASS
- validation contract automated checks PASS
- runtime/manual pending for phase closure

---

## Fase 51D Onboarding Base

Status: **Applied (technical)**

Delivered in current code:
- centralized onboarding diagnostics through `Onboarding_Service`:
  - `get_onboarding_state()`
  - `is_onboarding_complete()`
  - `get_next_recommended_step()`
  - `mark_onboarding_complete()`
  - `reset_onboarding_state()`
- onboarding state checks include:
  - `has_license`
  - `has_branding_basic`
  - `has_business`
  - `has_business_admin`
  - `is_onboarding_complete`
- dedicated admin page:
  - `Super Mechanic -> Onboarding` (`super-mechanic-onboarding`)
- UI includes:
  - setup checklist
  - next recommended step
  - direct links to existing pages:
    - License
    - Branding
    - Businesses
    - Roles & Access
- optional admin warning notice on plugin pages when onboarding is incomplete
- no duplicated setup forms; onboarding is orchestration/diagnostic only

Validation state:
- `php-lint` PASS
- validation contract automated checks PASS
- runtime/manual pending for phase closure

---

## FASE 53 - UX & VISUAL LAYER (COMPLETA)

Incluye:
- Dashboard operativo (admin)
- Portal cliente mejorado
- Responsive mobile completo
- Widgets visuales reutilizables

Subfases:

53A - Dashboard operativo
- metricas reales por business_id
- actividad reciente desde logs

53B - Portal cliente mejorado
- nueva capa portal desacoplada
- mejor visualizacion de procesos, documentos, historial

53C - Mobile optimization
- layout responsive completo
- tablas adaptadas
- UX tactil mejorada

53D - Widgets UX
- KPI widgets
- Client summary
- Mechanic summary
- Process summary cards

Resultado:

Sistema visualmente operativo,
usable en produccion real,
listo para escalado comercial.

---

## Fase 54E.2 Embedded TCPDF (Applied technical)

Delivered in current code:
- embedded TCPDF library available inside plugin at:
  - `includes/libs/pdf/tcpdf/tcpdf.php`
- reporting PDF service loads embedded TCPDF from plugin path when present
- reporting PDF generation uses HTML rendering flow (`writeHTML`) with:
  - `AddPage()`
  - `SetFont('dejavusans', '', 10)`
- fallback message remains active when no PDF engine can be loaded

Validation state:
- `php-lint` PASS
- QA runner (54E validation contract) PASS in automated checks
- runtime/manual visual closure pending

---

## Fase 55B Public API Formalization (Applied technical)

Delivered in current code:
- formal API loader integrated in active runtime:
  - `includes/api/class-api-loader.php`
- new versioned namespace:
  - `/wp-json/sm/v1/`
- endpoints exposed:
  - `GET /clients`
  - `GET /vehicles`
  - `GET /processes`
  - `GET /processes/{id}`
  - `GET /invoices`
  - `GET /reporting/summary`
  - `POST /quotes/{id}/approve` (optional endpoint delivered)
- endpoint controller layer added in:
  - `includes/api/controllers/class-public-api-controller.php`
- integration wired from composition root:
  - `includes/class-plugin.php`

Security and scope behavior:
- auth based on WordPress current user context (session/app-password compatible)
- strict `business_id` normalization by user scope
- explicit ownership checks via existing services/access-control
- standardized JSON payloads for success/error responses

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/55B-validation.md`) PASS in automated checks
- runtime/manual REST checklist pending for final closure

---

## Fase 55C Webhooks / Event Dispatch Formalization (Applied technical)

Delivered in current code:
- outbound webhook dispatch formalization in:
  - `includes/webhooks/class-webhook-service.php`
- automation event-name sanitization hardened in:
  - `includes/automation/class-automation-engine-service.php`
- canonical formal events supported (with legacy compatibility):
  - `process.created` (legacy alias: `process_created`)
  - `process.updated` (legacy alias: `process_updated`)
  - `quote.approved` (legacy alias: `quote_approved`)
  - `invoice.paid` (legacy alias: `invoice_paid`)
  - `payment.created` (legacy alias: `payment_registered`)
- standardized outbound payload normalization added:
  - `event`
  - `timestamp` (ISO-8601 UTC)
  - `business_id`
  - `entity_type`
  - `entity_id`
  - `data`
- queue/retry/log integration preserved:
  - queued dispatch when queue is available
  - immediate fallback when queue enqueue fails
  - webhook logging maintained

Compatibility behavior:
- existing webhook subscriptions using legacy event names continue to receive dispatches
- formal event naming is now unified in outbound payloads
- no invasive CRM Pipeline changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/55C-validation.md`) PASS in automated checks
- runtime/manual webhook dispatch checklist pending for final closure

---

## Fase 55D External Connectors Base (Applied technical)

Delivered in current code:
- outbound connectors base layer added in:
  - `includes/integrations/connectors/class-connector-repository.php`
  - `includes/integrations/connectors/class-connector-service.php`
- connectors admin UI/controller added in:
  - `includes/admin/class-connectors-admin-controller.php`
- composition root integration added in:
  - `includes/class-plugin.php`
- webhook payload reuse helper exposed for connector interoperability:
  - `Webhook_Service::build_standard_event_payload(...)`

Connector model persisted (WP option structured storage):
- `id`
- `name`
- `connector_type`
- `endpoint_url`
- `status`
- `event_name`
- `config_json`
- `created_at`
- `updated_at`

Supported connector types:
- `webhook`
- `google_sheets`
- `email_trigger`

55C event integration enabled:
- `process.created`
- `process.updated`
- `quote.approved`
- `invoice.paid`
- `payment.created`

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/55D-validation.md`) PASS in automated checks
- runtime/manual connectors checklist pending for final closure

---

## FASE 55 — INTEGRACIONES / ECOSISTEMA (COMPLETA)

### Subfases incluidas
- 55A — Elementor Integration
- 55B — Public API
- 55C — Webhooks & Events
- 55D — External Connectors
- 55E1 — Commercial Hooks
- 55E2 — Monetization Core

### Capacidades habilitadas

#### UI / Frontend
- Widgets Elementor nativos conectados a shortcodes
- Portales cliente/mecánico integrables en páginas reales

#### API
- Namespace `/wp-json/sm/v1/`
- Endpoints:
  - clients
  - vehicles
  - processes
  - invoices
  - reporting

#### Eventos
- Sistema canónico:
  - process.created
  - process.updated
  - quote.approved
  - invoice.paid
  - payment.created

#### Webhooks
- Dispatch externo funcional
- Payload estandarizado
- Compatibilidad legacy

#### Conectores
- webhook
- google_sheets (webhook-style)
- email_trigger

#### Comercial
- hooks comerciales:
  - sm_quote_created
  - sm_quote_approved
  - sm_invoice_created
  - sm_invoice_paid
  - sm_payment_created
  - sm_process_completed

#### Monetización
- estados de licencia:
  - active
  - trial
  - expired
  - inactive
  - revoked
- trial funcional
- enforcement en creación:
  - business
  - processes
  - webhooks
  - memberships

### Resultado arquitectónico

El sistema pasa de:
- plugin funcional

a:

- plataforma extensible
- integrable
- automatizable
- monetizable

---

## Demo Recovery Baseline (2026-04-05)

- canonical dataset seeder: `scripts/seed-full-demo-multibusiness.php`
- enforced superadmin identity: `admin@mardisom.com`
- seeded multi-business demo includes:
  - clients/vehicles/processes/tasks/appointments
  - quotes/invoices/payments
  - execution logs
  - role-aware users (admin/mechanic/client)
- recovery/handoff guide: `docs/tasks/2026-04-demo-dataset-recovery-guide.md`

---

## Important Rule

This file reflects **only the current system state**.

- Do not add historical narrative
- Do not document future phases beyond immediate continuity
- Do not duplicate roadmap content

---

## Fase 56P1-A1 Visible Branding (Applied technical)

Delivered in current code:
- plugin visible header identity renamed to `Mekvort` in:
  - `super-mechanic.php` (`Plugin Name`)
- branding default visible system name updated to `Mekvort` in:
  - `includes/branding/class-branding-service.php` (`system_name` default)

Intentional non-changes for backward compatibility:
- technical identifiers unchanged:
  - file name `super-mechanic.php`
  - text domain `super-mechanic`
  - namespaces/classes/constants/options/slugs/hooks
- admin navigation labels and runtime-sensitive admin screens intentionally unchanged in this subphase

---

## Fase 56P1-A2 Admin Menu Visible Rename (Reverted / Postponed)

Status:
- `REVERTIDA / POSTERGADA`

Runtime decision:
- top-level admin menu visible rename to `Mekvort` was rolled back after runtime regression evidence affecting `Roles & Access`.
- top-level admin menu label restored to stable pre-56P1-A2 value:
  - `Super Mechanic` in `includes/class-admin-menu.php` (`add_menu_page` page/menu labels)

Confirmed preserved state:
- 56P1-A1 remains active and unchanged:
  - plugin visible header identity = `Mekvort`
  - branding default `system_name` = `Mekvort`
- technical identifiers remain unchanged (slug/text domain/namespaces/options/REST identifiers).

---

## Fase 56P1-B Language Settings (Applied technical)

Delivered in current code:
- visible `Language Settings` section added in Settings UI:
  - `includes/class-settings.php`
- current/default language is visible in settings:
  - label + locale code shown from current persisted `language_locale`
- selector remains available with bundled languages:
  - `English (en_US)`
  - `Español (es_ES)`
  - `Italiano (it_IT)`
- visible future expansion placeholder added:
  - explicit prepared area indicating additional languages planned for `56P1-C`

Persistence and compatibility:
- language selection persists safely in existing settings option (`wp_options`) through existing sanitize/sync flow
- no admin menu/roles/CRM/reset/API/schema changes in this subphase
- full i18n system remains pending for `56P1-C`

---

## Fase 56P1-C I18N Helper Base (Applied technical)

Delivered in current code:
- centralized helper added in active runtime:
  - `includes/helpers/class-i18n-helper.php`
- helper baseline methods available:
  - `get_current_language()`
  - `set_current_language()`
  - `get_available_languages()`
  - `translate($key, $fallback = '')`
- bundled language registry:
  - `en_US` (English)
  - `es_ES` (Español)
  - `it_IT` (Italiano)
- safe fallback behavior:
  - unsupported/missing locale falls back to `en_US`

Persistence and compatibility:
- helper reads persisted language from existing settings baseline (`sm_settings.business.locale`)
- helper write path preserves compatibility with legacy option (`super_mechanic_settings.language_locale`)
- no full translation rollout, no mass label replacement, no frontend switching in this subphase

---

## Fase 56P2-A Superadmin Bootstrap (Applied technical)

Delivered in current code:
- centralized bootstrap service added in users layer:
  - `includes/users/class-superadmin-bootstrap-service.php`
- bootstrap execution wired in:
  - activation path (`super-mechanic.php`)
  - first safe runtime bootstrap (`includes/class-plugin.php`)
- initial Mekvort superadmin baseline now resolves to primary WordPress administrator (lowest admin user ID)
- bootstrap state persisted in `wp_options`:
  - option key: `sm_superadmin_bootstrap_state`
  - persisted payload includes `user_id`, `user_email`, `bootstrapped_at`, `source`

Access model safeguards:
- only primary WP admin is auto-bootstrapped with direct `sm_global_access`
- other WP admins are not auto-promoted by default bootstrap path
- global superadmin resolution no longer auto-promotes all `manage_options` users

Deferred continuity:
- broader superadmin assignment/reassignment management remains deferred to later controlled subphases

---

## Fase 56P2-A1 Superadmin Bootstrap Completion Fix (Applied technical)

Delivered in current code:
- primary bootstrap superadmin now resolves as locked runtime superadmin in Roles & Access:
  - `includes/users/class-role-access-service.php`
  - `includes/users/class-admin-roles-controller.php`
- locked superadmin state is rendered as:
  - `Locked superadmin`
  - `Global scope`
  - `admin + mechanic + client`
- locked superadmin no longer depends on normal manual membership controls in Roles & Access:
  - `Add membership` and transfer controls hidden for locked superadmin rows
  - role action controls blocked for locked superadmin rows
  - AJAX membership writes by `user_id` blocked for locked superadmin
- bootstrap state normalization hardened:
  - `includes/users/class-superadmin-bootstrap-service.php`
  - persisted bootstrap payload is refreshed safely while preserving initial `bootstrapped_at`
  - direct global capability remains enforced only for the primary bootstrapped admin
  - other WP admins continue without auto-promotion

Continuity notes:
- broader manual promotion/reassignment of additional superadmins remains deferred to next subphase
- no CRM/reset/rename-i18n/API/schema changes were introduced in this subphase

---

## Fase 56P2-B Superadmin Assignment Controls (Applied technical)

Delivered in current code:
- controlled superadmin assignment/revocation flows added in users layer:
  - `includes/users/class-role-access-service.php`
  - methods:
    - `assign_superadmin($actor_user_id, $target_user_id)`
    - `revoke_superadmin($actor_user_id, $target_user_id)`
    - `get_superadmin_rows()`
    - `get_superadmin_eligible_admin_rows()`
- management authorization is now restricted:
  - only existing Mekvort superadmins can assign/revoke superadmin
- promotion eligibility is restricted:
  - only WordPress administrators can be promoted
  - no automatic promotion of all administrators
- persistence model extended safely in existing bootstrap option:
  - `sm_superadmin_bootstrap_state.managed_superadmin_ids`
  - bootstrap refresh preserves `managed_superadmin_ids` and `bootstrapped_at`
- safe control surface exposed in Roles & Access:
  - `includes/users/class-admin-roles-controller.php`
  - dedicated `Superadmin assignment controls` section
  - assign form for eligible WP administrators
  - revoke controls for managed superadmins
  - locked bootstrap superadmin remains non-revokable from this flow
  - self-revocation blocked to reduce lockout risk

Continuity notes:
- broader role-management redesign remains deferred
- CRM/reset/language/API/schema remain untouched in this subphase

---

## Fase 56P2-B1 Managed Superadmin Operational Parity (Applied technical)

Delivered in current code:
- operational/visual parity applied to all Mekvort superadmins (bootstrap + managed) in Roles & Access:
  - `includes/users/class-role-access-service.php`
  - `includes/users/class-admin-roles-controller.php`
- any Mekvort superadmin now resolves as locked superadmin for operational UI behavior:
  - rendered as `Locked superadmin`
  - rendered with `Global scope`
  - rendered with `admin + mechanic + client`
- normal membership controls remain disabled for all superadmins:
  - no `Add membership`
  - no transfer/normal membership controls
  - no dependency on manual memberships for superadmin operation
- safe revocation distinction preserved:
  - bootstrap superadmin remains non-revocable from normal flow
  - managed superadmin remains revocable by authorized superadmin

Safety and compatibility notes:
- promotion/revocation/non-superadmin restrictions from 56P2-B remain active
- no CRM/reset/rename-i18n/API/schema changes in this subphase

---

## Fase 56P3-A Reset Engine (Applied technical)

Delivered in current code:
- centralized reset engine baseline added in active runtime:
  - `includes/helpers/class-reset-engine-service.php`
  - `includes/database/class-reset-engine-repository.php`
- DB security reset flow now delegates reset orchestration to the centralized reset engine:
  - `includes/helpers/class-db-security-service.php`
- backward-compatible reset entrypoint remains unchanged in Settings:
  - `sm_db_security_reset` in `includes/class-settings.php`

Reset scope now covered by centralized engine:
- operational/business runtime data:
  - clients
  - vehicles
  - client-vehicle relations
  - processes and process runtime logs/meta/parts
  - appointments and sync runtime data
  - maintenance/pre-delivery/paperwork runtime data
  - quotes/invoices/payments runtime data
- CRM runtime data:
  - client CRM meta
  - pipeline
  - tasks
  - alerts
- notifications and webhook runtime data:
  - notifications
  - webhooks
  - webhook deliveries
- deterministic default business baseline re-seeded after reset

Deferred continuity (next 56P3 subphases):
- user cleanup/full user integrity reset remains deferred
- full runtime/manual reset verification remains pending for closure

---

## Fase 56P3-B User Handling (Applied technical)

Delivered in current code:
- reset user-handling layer added in users runtime:
  - `includes/users/class-reset-user-handling-service.php`
- reset engine orchestration extended to include user cleanup after operational data reset:
  - `includes/helpers/class-reset-engine-service.php`
- membership repository extended for reset-support cleanup operations:
  - `includes/users/class-business-membership-repository.php`

User-handling reset policy now applied:
- protected Mekvort superadmins are preserved:
  - bootstrap superadmin (`sm_superadmin_bootstrap_state.user_id`)
  - managed superadmins (`sm_superadmin_bootstrap_state.managed_superadmin_ids`)
  - current global superadmins resolved by role-access model
- non-protected runtime/business users are removed when they are part of plugin runtime scope:
  - WordPress administrators without protected Mekvort superadmin status
  - users with plugin runtime roles (`sm_admin`, `sm_mechanic`, `sm_client`)
  - users referenced by business memberships table
- stale managed superadmin IDs are normalized after cleanup

56P3-B fix note:
- previous policy incorrectly preserved normal WordPress administrators and could leave them outside reset cleanup scope
- current policy now includes WordPress administrators in reset candidates and preserves only protected Mekvort superadmins

Continuity notes:
- broader reset integrity/runtime verification remains pending
- broader user integrity hardening remains deferred to `56P3-C`

---

## Fase 56P3-C Data Integrity Validation (Applied technical)

Delivered in current code:
- centralized integrity validation repository added:
  - `includes/database/class-data-integrity-validation-repository.php`
- centralized integrity validation service added:
  - `includes/helpers/class-data-integrity-validation-service.php`

Validation scope covered:
- clients / vehicles / ownership relations:
  - vehicles without client
  - client-vehicle links with missing client or vehicle
- processes / logs:
  - processes with invalid core relations (client/vehicle and maintenance constraints)
  - process logs without process or with cross-business mismatch
- CRM relations:
  - client CRM meta without client
  - CRM pipeline with invalid client/vehicle/process references
  - CRM tasks without pipeline
  - CRM alerts without pipeline
- invoices/payments integrity:
  - payments without invoice or with cross-business mismatch
  - invoice items without invoice or with cross-business mismatch

Report model:
- structured output includes:
  - `overall_status`
  - summary counters (`total_checks`, `passed_checks`, `failed_checks`, `total_issues`)
  - per-check status + issue count + sample IDs
- optional auto-fix flag supported but no destructive auto-fix is applied in 56P3-C (validation-only scope)

Deferred integrity areas:
- runtime/manual integrity execution evidence remains pending for closure
- non-trivial repair workflows remain deferred (future subphases)

---

## Fase 56P4-A Dashboard Layout Fix (Applied technical)

Delivered in current code:
- dashboard admin layout restructured to card/grid sections in:
  - `includes/admin/class-dashboard-admin-controller.php`
- dashboard-specific visual styles extended in:
  - `assets/css/admin.css`

UI/layout changes applied:
- metric cards grouped into clear visual sections:
  - `Operations`
  - `Platform Signals`
- responsive two-column section shell for desktop, collapsing to one column on mobile
- improved card spacing, borders, and subtle shadows for KPI readability
- recent activity block aligned to section/card structure (inline style removed)

Scope safeguards:
- no data query changes
- no service/repository/business logic changes
- no cross-module runtime behavior changes

---

## Fase 56P4-B Reporting Layout Fix (Applied technical)

Delivered in current code:
- reporting metrics layout restructured to grouped card/grid sections in:
  - `includes/admin/class-reporting-admin-controller.php`
- reporting-specific visual styles extended in:
  - `assets/css/admin.css`

UI/layout changes applied:
- reporting metrics grouped into clear visual sections:
  - `Commercial`
  - `Operations`
- responsive two-column metrics shell for desktop, collapsing to one column on mobile
- improved card spacing, borders, and subtle shadows for reporting KPI readability
- trend and delta visual rendering preserved inside each metric card

Scope safeguards:
- no reporting service/repository/query changes
- no PDF generation changes
- no business logic changes

56P4-B fix note:
- runtime issue confirmed: reporting metric cards were still stacked because card wrappers did not activate CSS grid layout.
- root cause: `sm-grid-cards` / `sm-grid-cards-compact` provide `grid-template-columns` but not `display:grid`.
- fix applied: `assets/css/admin.css` now sets `display:grid` for `.sm-reporting-card-grid` (reporting-only scope), preserving desktop multi-column and mobile single-column behavior.

56P4-B final fix note:
- runtime issue persisted due to CSS-effective layout still relying on shared generic classes and insufficient autonomous reporting grid definition.
- final fix hardened reporting selectors and container sizing:
  - `.sm-reporting-metrics-grid`: explicit `width:100%`, grid + gap
  - `.sm-reporting-metric-group`: explicit `width:100%` + `min-width:0`
  - `.sm-reporting-metrics-shell .sm-reporting-card-grid`: explicit `display:grid`, `width:100%`, `grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))`
  - `.sm-reporting-kpi-card`: explicit `width:auto` + `min-width:0`
- mobile collapse preserved with explicit reporting card-grid override to one column in `@media (max-width: 782px)`.

---

## Fase 56P4-C Branding UX Cleanup (Applied technical)

Delivered in current code:
- branding admin UX structure improved in:
  - `includes/admin/class-branding-admin-controller.php`
- branding-specific UI styles extended in:
  - `assets/css/admin.css`

UI/layout changes applied:
- branding page now uses clearer two-column structure:
  - settings form area
  - dedicated preview area
- form readability improved with grouped cards:
  - `Brand identity`
  - `Color theme`
  - `Footer text`
- labels and helper descriptions improved for all branding inputs without changing payload/save behavior
- preview panel added for current branding snapshot:
  - brand name/logo block
  - primary/secondary color swatches
  - footer text preview state
- responsive behavior hardened for tablet/mobile:
  - preview stacks below form
  - color fields collapse to one column on small screens

Scope safeguards:
- no branding business-logic changes
- no save/action/nonce/capability flow changes
- no rename/i18n/roles-access/CRM/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P4-C-validation.md`) PASS in automated checks
- runtime/manual branding closure pending

---

## Fase 56P4-D Settings / License Consistency (Applied technical)

Delivered in current code:
- settings/license UX consistency cleanup in:
  - `includes/class-settings.php`
- settings/license UI helper styles extended in:
  - `assets/css/admin.css`

UI/layout changes applied:
- Settings license card refocused as `License summary` (read-only intent)
- duplicated license action controls removed from Settings:
  - activate
  - validate
  - deactivate
- clear guidance added in Settings to use dedicated License page for management
- direct CTA links added from Settings to:
  - `admin.php?page=super-mechanic-license`
- summary clarity improved in Settings with visible license/plan snapshot:
  - current status
  - masked license key
  - provider
  - effective plan + source
  - activation/last validation timestamps
- plan section copy clarified as diagnostic/read-only in Settings

Scope safeguards:
- no license business logic changes
- no trial/enforcement flow changes
- no roles/CRM/reset/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P4-D-validation.md`) PASS in automated checks
- runtime/manual settings-license closure pending

---

## Fase 56P5-A CRM Bulk Actions (Applied technical)

Delivered in current code:
- CRM pipeline bulk-action support added in:
  - `includes/crm/class-crm-pipeline-admin-controller.php`
- CRM bulk-action UI styles added in:
  - `assets/css/admin.css`

UI/layout changes applied:
- list view now supports row-level multi-select:
  - one checkbox per opportunity row
- select-all control added in list header
- bulk actions bar added above CRM list table:
  - action dropdown
  - apply button
- first supported bulk action:
  - `Delete selected`
- post-action summary notices added:
  - deleted count
  - failed count

Flow/safety behavior:
- bulk flow is list-view scoped (kanban unchanged)
- nonce-protected POST flow:
  - `sm_crm_pipeline_bulk_action`
- selected IDs are sanitized/unique-filtered before processing
- bulk delete reuses existing service delete path:
  - `Crm_Pipeline_Service::delete_opportunity(...)`
- no cascade delete for related tasks introduced in this subphase

Scope safeguards:
- no CRM stage/state redesign
- no API/schema/reset/roles changes
- existing single-item CRM actions preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P5-A-validation.md`) PASS in automated checks
- runtime/manual CRM bulk closure pending

---

## Fase 56P5-B CRM Cascade Delete (Applied technical)

Delivered in current code:
- CRM delete cascade support added in:
  - `includes/crm/class-crm-pipeline-service.php`
  - `includes/crm/class-crm-task-service.php`
  - `includes/crm/class-crm-task-repository.php`

Delete flow changes applied:
- single opportunity delete now cascades to CRM tasks:
  - pipeline service deletes tasks by `crm_pipeline_id` before deleting opportunity
- bulk opportunity delete now cascades to CRM tasks through same service path:
  - bulk action reuses `delete_opportunity(...)`
  - cascade is applied per selected opportunity
- task cleanup is scoped by active `business_id`
- no orphan CRM tasks should remain after supported opportunity delete flows

Scope safeguards:
- no CRM redesign
- no task model redesign
- no API/schema/reset/roles/settings changes
- existing notices/UX preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P5-B-validation.md`) PASS in automated checks
- runtime/manual cascade closure pending

---

## Fase 56P5-C CRM State Consistency (Applied technical)

Delivered in current code:
- CRM consistency hardening for delete flows added in:
  - `includes/crm/class-crm-pipeline-service.php`
  - `includes/crm/class-crm-alert-service.php`
  - `includes/crm/class-crm-alert-repository.php`

State-consistency changes applied:
- single opportunity delete now enforces full CRM relation cleanup in service flow:
  - delete related CRM tasks by `crm_pipeline_id`
  - resolve active CRM alerts by `crm_pipeline_id`
  - delete opportunity row only after related cleanup succeeds
- bulk delete inherits the same consistency hardening because it reuses `delete_opportunity(...)` per selected row
- tenant safety preserved:
  - task cleanup remains scoped by active `business_id`
  - alert resolution is scoped by active `business_id`

Scope safeguards:
- no CRM/kanban redesign
- no schema changes
- no API/reset/roles/settings changes
- existing admin notices/UX preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P5-C-validation.md`) PASS in automated checks
- runtime/manual consistency closure pending

---

## Fase 56P6-A Roles & Access UI Stabilization (Applied technical)

Delivered in current code:
- Roles & Access UI stabilization updates in:
  - `includes/users/class-admin-roles-controller.php`
  - `assets/css/admin.css`

UI/layout changes applied:
- readability/hierarchy stabilized in users summary section:
  - column-visibility toolbar restored (original behavior preserved)
  - added explicit guidance note for protected superadmin behavior
- superadmin rendering clarity improved:
  - dedicated row styling for locked superadmin users
  - clear protected superadmin badge in user identity cell
  - clearer visual distinction of global/protected status
- normal user rendering preserved and made more legible:
  - operational role labels humanized (`sm_admin` -> `Admin`, etc.)
  - action controls layout improved for scanability and reduced crowding
  - responsive roles-table overrides added for mobile stability

Scope safeguards:
- no role-system redesign
- no membership/superadmin business-logic changes
- no CRM/reset/i18n/API/schema changes
- existing promote/revoke/membership flows preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-A-validation.md`) PASS in automated checks
- runtime/manual Roles & Access closure pending

56P6-A fix note (regression hotfix):
- detected regression corrected:
  - `WP roles` column no longer stacks vertically
  - column filter toolbar restored with prior behavior (render + JS toggle flow)
- fix scope remained UI-only in:
  - `includes/users/class-admin-roles-controller.php`
  - `assets/css/admin.css`

---

## Fase 56P6-B Roles & Access Visible Columns Extension (Applied technical)

Delivered in current code:
- visible-columns extension in:
  - `includes/users/class-admin-roles-controller.php`
  - `assets/js/admin-roles-access.js`

UI/behavior changes applied:
- `Visible columns` toolbar now includes:
  - `ID`
  - `Name`
  - `Email`
- existing supported columns remain available:
  - `WP roles`
  - `Operational role`
  - `Business`
  - `Memberships`
  - `Dashboard access`
  - `Automation/Logs`
  - `Status`
  - `Actions`
- column toggle mapping remains 1:1 through shared `data-col` keys across:
  - toolbar checkbox values
  - table header cells (`th[data-col]`)
  - row cells (`td[data-col]`)
- visible-columns persistence hardened using localStorage with compatibility fallback for legacy keys

Scope safeguards:
- no role/membership business-logic changes
- no superadmin action-flow changes
- no CRM/reset/settings/admin-menu/API/schema changes

Validation state:
- `php-lint` PASS
- runtime/manual checklist pending

56P6-B adjustment note (default visible columns + persistence):
- default first-load visible set is now restricted to:
  - `name`
  - `operational_role`
  - `business`
  - `memberships`
  - `actions`
- default first-load hidden set now includes:
  - `id`
  - `email`
  - `wp_roles`
  - `dashboard_access`
  - `automation_access`
  - `status`
- persistence behavior refined:
  - defaults apply only when no stored preference exists
  - manual user selection is persisted and respected on reload
  - legacy localStorage keys are read and migrated safely to primary key format

---

## Fase 56P6-C Roles & Access Backend Enforcement (Applied technical)

Delivered in current code:
- backend enforcement hardening in:
  - `includes/users/class-admin-roles-controller.php`
  - `includes/users/class-business-membership-service.php`
  - `includes/users/class-role-access-service.php`

Backend enforcement changes applied:
- membership AJAX actions now resolve the effective target user server-side, including actions driven by `membership_id`:
  - `update_membership_role`
  - `set_membership_status`
  - `set_primary_membership`
  - `remove_membership`
- protected superadmin membership restrictions are now enforced consistently for all membership action paths (not only payloads with direct `user_id`)
- membership service now exposes safe read accessor `get_membership_by_id(...)` for backend validation/ownership resolution
- role-sensitive hardening added in access service:
  - membership consistency repair is blocked for locked superadmins
  - superadmin revocation flow is restricted to managed superadmins only

Scope safeguards:
- no role model redesign
- no UI redesign
- no CRM/reset/i18n/API/schema changes
- existing authorized membership/role flows preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-C-validation.md`) PASS in automated checks
- runtime/manual backend enforcement closure pending

---

## Fase 56P6-C1 Membership Action Consistency (Applied technical)

Delivered in current code:
- roles/memberships consistency hardening in:
  - `includes/users/class-admin-roles-controller.php`
  - `includes/users/class-role-access-service.php`

Consistency changes applied:
- actions column now hides role assignment controls that no longer apply:
  - `Assign admin` hidden when user already has admin-level role
  - `Assign mechanic` hidden when user already has mechanic role
  - `Assign client` hidden when user already has client role
- operational role assignment flow now supports `sm_client` and keeps final state consistent by clearing prior operational roles before applying new one
- operational role assignment/removal flow was further refined in 56P6-C2 for one-role-at-a-time removal without replacing unrelated roles
- memberships UI now blocks invalid primary deactivation attempts before submit:
  - primary active memberships no longer render direct `Deactivate` action
  - guidance message is shown to set another primary first
- `Add membership` card now renders only when at least one active business still has missing role coverage (`admin`/`mechanic`/`client`)

Scope safeguards:
- no superadmin model redesign
- no CRM/reset/i18n/API/schema changes
- no broad Roles & Access redesign

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-C1-validation.md`) PASS in automated checks
- runtime/manual checklist pending

---

## Fase 56P6-C2 Multi-role Membership Consistency (Applied technical)

Delivered in current code:
- multi-role consistency hardening in:
  - `includes/users/class-business-membership-service.php`
  - `includes/users/class-admin-roles-controller.php`
  - `includes/users/class-role-access-service.php`

Model consistency changes applied:
- membership creation no longer replaces existing role in same business:
  - add/reactivate now resolves by exact tuple `(user_id, business_id, role)`
  - adding `client` no longer removes `mechanic` (and vice versa)
- primary-membership handling now supports valid role removal/deactivation paths:
  - deactivating/removing a primary membership auto-reassigns primary when another active membership exists
  - operation remains blocked only when no valid replacement active membership is available
- membership consistency/repair rules no longer collapse valid multi-role memberships per business:
  - duplicate check moved from `same business` to `same business + same role`
  - repair deactivates only true duplicates of the same role, preserving distinct active roles in one business
- Roles & Access membership UI now aligns add options with missing roles:
  - `Add membership` renders quick-add entries for missing `(business, role)` combinations only
  - businesses with complete active role coverage (`admin`, `mechanic`, `client`) no longer expose add options
- role actions service alignment:
  - assign role adds target role without removing other operational roles
  - remove role flow removes one resolved operational role at a time

Scope safeguards:
- no superadmin model redesign
- no CRM/reset/i18n/API/schema changes
- no broad Roles & Access redesign

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-C2-validation.md`) PASS in automated checks
- runtime/manual checklist pending

---

## Fase 56P6-C3 Per-business Membership UI Consolidation (Applied technical)

Delivered in current code:
- memberships UI consolidation updates in:
  - `includes/users/class-admin-roles-controller.php`
- supporting consistency model retained from:
  - `includes/users/class-business-membership-service.php`
  - `includes/users/class-role-access-service.php`

UI consolidation changes applied:
- `Current memberships` now renders one card per business (instead of per-role card)
- roles are displayed as readable badges/text inside each business card
- role state view no longer uses role dropdown for current membership rendering
- per-membership action controls remain available but grouped inside the business card context
- primary action guidance remains guarded:
  - primary deactivation is only offered when an alternative active membership exists

Add-membership UX changes:
- compact dropdown flow restored:
  - one dropdown with missing `(business, role)` targets
  - standard submit flow (no per-combination button grid)
- options include only missing roles for each business
- businesses with full role coverage (`admin`, `mechanic`, `client`) no longer generate add-target options

Scope safeguards:
- no CRM/reset/i18n/API changes
- no superadmin model redesign
- no schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-C3-validation.md`) PASS in automated checks
- runtime/manual checklist pending

56P6-C3 fix note (membership consolidation + transfer regression):
- corrected regression in `Current memberships` business card consolidation:
  - roles are now merged from all membership rows in the business card scope (not from a single row source)
  - role badges now reflect both presence and effective status per role in that business
- corrected transfer form runtime warnings in controller render:
  - restored initialization of `$has_businesses`
  - restored initialization of `$role_options`
  - transfer block now renders safely with/without businesses available
- primary controls remain available per membership row where applicable and still protected by primary handoff validation

---

## Fase 56P6-C4 Actions State Sync (Applied technical)

Delivered in current code:
- actions-state sync corrective updates in:
  - `includes/users/class-admin-roles-controller.php`
  - `includes/users/class-business-membership-service.php`

Actions sync changes applied:
- Actions column now evaluates role visibility from persisted active memberships in the target business scope (not from WP role flags)
- role assignment actions in `Actions` now post explicit business-role payload:
  - `assign_business_role` + `business_id` + `role`
- role removal actions in `Actions` now remove the exact target role in the exact target business:
  - `remove_business_role` + `business_id` + `role`
  - membership service resolves tuple `(user_id, business_id, role)` and removes that membership safely
- after remove/add, visible action buttons are recomputed from refreshed persisted membership state, restoring expected `Assign <Role>` visibility

Scope safeguards:
- consolidated per-business membership card UI preserved
- primary handoff safety preserved through existing membership service validation
- no CRM/reset/i18n/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P6-C4-validation.md`) PASS in automated checks
- runtime/manual checklist pending for closure

---

## Fase 56P7-A Client Panel Base (Applied technical)

Delivered in current code:
- unified client panel shortcode composition in:
  - `includes/dashboard/class-client-dashboard-shortcodes.php`
- client panel composition styles in:
  - `assets/css/portal.css`

Client panel base changes applied:
- new unified private entrypoint shortcode:
  - `[sm_client_panel]`
- coherent section navigation added for authenticated client users:
  - Process hub
  - Vehicles
  - Processes
  - Quotes
  - Invoices
  - Notifications
  - Documents (context-based)
- panel composition reuses existing client-facing render/services without duplicating business logic:
  - `Client_Portal_Controller::render_portal(...)`
  - `Client_Dashboard_Controller` render methods for vehicles/processes/quotes/invoices/notifications
  - existing document/timeline shortcodes for process-specific context
- existing shortcode behavior preserved:
  - `[sm_client_dashboard]`
  - `[sm_client_vehicles]`
  - `[sm_client_processes]`

Scope safeguards:
- no mechanic panel redesign
- no CRM/reset/API/schema changes
- no business-logic rewrite in quotes/invoices/processes/documents

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P7-A-validation.md`) PASS in automated checks
- runtime/manual checklist pending for closure

---

## Fase 56P7-B Client Panel Data Resolution (Applied technical)

Delivered in current code:
- client-link resolution hardening in:
  - `includes/helpers/class-access-control-service.php`

Identity resolution changes applied:
- primary resolution preserved through canonical user meta link:
  - `sm_client_id`
- safe fallback migration added for missing links:
  - resolve by exact authenticated WP user email in current tenant scope
  - require a unique exact client match
  - block auto-link if matched client is already linked to another WP user
  - persist `sm_client_id` automatically after successful unique fallback

Scope safeguards:
- no schema changes
- no legacy module usage
- no CRM/process/business-logic redesign

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P7-B-validation.md`) PASS in automated checks
- runtime/manual checklist pending for closure

---

## Fase 56P7-C Mechanic Panel UX (Applied technical)

Delivered in current code:
- mechanic panel UX cleanup in:
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
- mechanic panel frontend readability styles in:
  - `assets/css/portal.css`

UX changes applied:
- corrupted/confusing mechanic labels corrected (titles, table headers, form labels, action text)
- clearer section flow with quick navigation anchors:
  - filters
  - process list
  - appointments
- action visibility improved with button-style quick actions in process/attachment tables
- filter panel guidance note added to reduce ambiguity in workload filtering
- maintenance/detail wording normalized for readability without changing mechanic actions

Scope safeguards:
- no mechanic business-logic redesign
- no CRM/reset/API/schema changes
- no logic duplication introduced

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P7-C-validation.md`) PASS in automated checks
- runtime/manual checklist pending for closure

---

## Fase 56P7-D Shortcode Registry Alignment (Applied technical)

Delivered in current code:
- mechanic shortcode registry extension in:
  - `includes/dashboard/class-mechanic-dashboard-shortcodes.php`
- shortcode catalog alignment in:
  - `includes/class-shortcode-admin-controller.php`

Shortcode alignment changes applied:
- new general mechanic panel alias shortcode added:
  - `[mekvort_mechanic_panel]`
  - reuses existing mechanic panel render flow (`render_mechanic_dashboard`)
- existing client panel alias remains active and now catalog-aligned:
  - `[mekvort_client_panel]`
- catalog metadata aligned with active panel shortcodes:
  - mechanic group includes `sm_mechanic_dashboard`, `sm_mechanic_processes`, `mekvort_mechanic_panel`
  - client group now includes `sm_client_panel` and `mekvort_client_panel` in addition to existing `sm_client_*`
- compatibility preserved for existing shortcodes:
  - `sm_client_*`
  - `sm_mechanic_*`

Scope safeguards:
- no panel business-logic duplication
- no CRM/reset/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P7-D-validation.md`) PASS in automated checks
- runtime/manual checklist pending for closure

---

## Fase 56P8-A Email Trigger System (Applied technical)

Delivered in current code:
- centralized email trigger service in:
  - `includes/services/class-email-trigger-service.php`
- composition-root wiring in:
  - `includes/class-plugin.php`

Trigger system changes applied:
- new `Email_Trigger_Service` listens to existing domain events and emits structured notification intents (no real email sending yet)
- required trigger methods added:
  - `trigger_process_status_change(...)`
  - `trigger_quote_status_change(...)`
  - `trigger_invoice_status_change(...)`
- integration points covered through existing event flow:
  - process status transitions (`sm_event_process_status_changed`, `sm_event_process_finalized`)
  - quote approval/rejection (`sm_event_quote_approved`, `sm_event_quote_rejected`)
  - invoice collection transitions via payment events (`sm_event_payment_registered`, `sm_event_invoice_paid`)
- debug-friendly intent persistence enabled through existing structured logs:
  - `Log_Service::log_notification_event(...)` with source `email_trigger`

Scope safeguards:
- no business-flow redesign
- no email provider/SMTP integration
- no template rendering layer changes
- no CRM/reset/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P8-A-validation.md`) PASS in automated checks
- runtime/manual checklist pending

---

## Fase 56P8-B Email Templates (Applied technical)

Delivered in current code:
- centralized email template service in:
  - `includes/services/class-email-template-service.php`
- trigger-template integration in:
  - `includes/services/class-email-trigger-service.php`

Template layer changes applied:
- reusable template builder introduced through `Email_Template_Service`
- event-to-template mapping implemented for:
  - process status change
  - quote approved
  - quote rejected
  - invoice paid
  - invoice partial
- output payload now includes reusable template contract:
  - `template_key`
  - `subject`
  - `body`
  - `metadata` (`delivery_channel`, `template_version`, `ready_for_send`)
- trigger intents from 56P8-A now include rendered template output, preserving delivery/provider separation

Scope safeguards:
- no SMTP/provider integration
- no business-flow changes
- no CRM/reset/API/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P8-B-validation.md`) PASS in automated checks
- runtime/manual checklist pending

---

## Fase 56P8-C Email Delivery Wiring (Applied technical)

Delivered in current code:
- centralized service-layer email delivery added in:
  - `includes/services/class-email-delivery-service.php`
- email trigger flow now uses the service-layer delivery class for real `wp_mail(...)` attempts
- existing template payloads are used as delivery source:
  - `subject`
  - `body`
  - `metadata.ready_for_send`
- recipient resolution is handled from current domain entities through existing services:
  - process -> client
  - quote -> client
  - invoice -> client
  - linked WP user fallback by `sm_client_id`
- delivery outcomes are logged through `Log_Service::log_notification_event(...)` with source `email_delivery`

Scope safeguards:
- no external provider SDK integration
- no queue/retry redesign
- no admin email settings UI
- no CRM/reset/API/schema changes
- existing trigger/template separation preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P8-C-validation.md`) PASS in automated checks
- runtime/manual email delivery checklist pending

---

## Fase 56P9-A Google Calendar Config Validation (Applied technical)

Delivered in current code:
- centralized Google Calendar configuration service added in:
  - `includes/services/class-google-calendar-config-service.php`
- service methods available:
  - `get_config()`
  - `save_config()`
  - `validate_config()`
  - `is_ready()`
- configuration keys covered:
  - `client_id`
  - `client_secret`
  - `redirect_uri`
  - `calendar_id`
- storage reuses the existing settings option/group through `Settings_Service` (`google_calendar`)
- validation is limited to local completeness and basic string sanity

Scope safeguards:
- no OAuth execution
- no token exchange
- no Google API calls
- no event sync
- no frontend/admin UI changes
- no schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P9-A-validation.md`) PASS in automated checks
- manual config save/validation/readiness checks pending

---

## Fase 56P9-B Google Calendar Sync Logic (Applied technical)

Delivered in current code:
- centralized Google Calendar payload builder added in:
  - `includes/services/class-google-calendar-sync-service.php`
- service methods available:
  - `build_event_payload(...)`
  - `build_appointment_event_payload(...)`
  - `build_process_event_payload(...)`
  - `validate_event_payload(...)`
- normalized calendar-ready payload shape includes:
  - `summary`
  - `description`
  - `start.datetime`
  - `end.datetime`
  - `timezone`
  - `attendees`
  - `metadata.source`
  - `metadata.entity_type`
  - `metadata.entity_id`
- appointment payloads map existing appointment/client/vehicle/mechanic fields without changing appointment logic
- process payloads map existing process/client/vehicle/date fields without changing process logic
- validation returns structured missing-field errors for required calendar payload fields

Scope safeguards:
- no OAuth execution
- no token exchange
- no Google API calls
- no real remote sync or external event ID persistence added
- no frontend/API/CRM/reset/schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P9-B-validation.md`) PASS in automated checks
- manual payload-build checks PASS for appointment, process and missing-field validation

---

## Fase 56P9-C Google Calendar Architecture Consolidation (Applied technical)

Delivered in current code:
- Google Calendar integration architecture consolidated:
  - canonical payload builder remains in `includes/services/class-google-calendar-sync-service.php`
  - integration-facing service renamed/reoriented to `includes/integrations/google-calendar/class-google-calendar-client-service.php`
- integration service class is now:
  - `Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Client_Service`
- removed duplicated domain payload builder from integration layer:
  - no `build_event_payload(...)` method remains under `includes/integrations/google-calendar/*`
- integration layer now prepares provider-ready event shape from canonical payloads through:
  - `prepare_provider_event_payload_from_appointment(...)`
  - `prepare_provider_event_payload(...)`
- wiring updated for plugin bootstrap, settings, auth controller, webhook controller and existing integration sync orchestration

Scope safeguards:
- no OAuth implementation changes
- no new Google API calls
- no schema changes
- no frontend/API/CRM/reset/dashboard changes
- canonical payload logic remains service-layer owned

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P9-C-validation.md`) PASS in automated checks
- manual architecture checks PASS:
  - no duplicated payload builder in integration layer
  - canonical sync service builds payloads
  - integration client service loads and converts canonical payloads to provider-ready event shape

---

## Fase 56P10-A API Auth Model Audit (COMPLETA)

Delivered audit:
- REST route inventory completed across active API-related surfaces:
  - formal API namespace `sm/v1`
  - internal/admin namespace `super-mechanic/v1`
  - public integration namespace `super-mechanic-public/v1`
  - Google Calendar webhook route
- formal `sm/v1` API auth behavior documented:
  - all 7 routes currently register `permission_callback => __return_true`
  - authentication is enforced inside callbacks via current WordPress user resolution
  - business scope is resolved via `Business_Context_Service`
  - entity ownership checks are delegated to existing services where implemented
- adjacent auth models documented:
  - internal/admin routes use login + capability or portal permission callbacks
  - public integration API uses plugin-managed API keys with Bearer / `X-SM-API-Key`, scopes and business binding
  - Google Calendar webhook uses public route registration with provider header/channel validation inside service layer

Identified hardening gaps:
- formal `sm/v1` routes lack strict route-level permission callbacks
- formal `sm/v1` has no plugin-managed external API-key/token model
- formal `sm/v1` has no explicit per-route capability policy
- quote approval mutation should be prioritized for route-level hardening in 56P10-B

Scope safeguards:
- audit only
- no endpoint behavior changes
- no new auth implementation
- no schema/frontend/business-logic changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P10-A-validation.md`) PASS in automated checks
- manual audit checks PASS:
  - routes inventoried
  - permission callbacks reviewed
  - auth gaps documented
- runtime REST calls NOT_RUN (audit-only phase)

---

## Fase 56P10-B Endpoint Protection Hardening (COMPLETA)

Delivered in current code:
- formal `sm/v1` endpoint protection hardened in:
  - `includes/api/controllers/class-public-api-controller.php`
- all 7 formal `sm/v1` routes now use named permission callbacks instead of `__return_true`
- read routes now use:
  - `Public_API_Controller::permission_can_read(...)`
- quote approval mutation now uses:
  - `Public_API_Controller::permission_can_approve_quote(...)`
- base write policy added for mutation routes:
  - `Public_API_Controller::permission_can_write(...)`
- shared permission helper added:
  - authenticated WordPress current-user requirement
  - requested `business_id` validation through existing `Business_Context_Service`
- quote approval is protected at REST permission layer with existing `Quote_Service::user_can_access_quote(...)`

Protected `sm/v1` route map:
- `GET /clients` -> `permission_can_read`
- `GET /vehicles` -> `permission_can_read`
- `GET /processes` -> `permission_can_read`
- `GET /processes/{id}` -> `permission_can_read`
- `GET /invoices` -> `permission_can_read`
- `GET /reporting/summary` -> `permission_can_read`
- `POST /quotes/{id}/approve` -> `permission_can_approve_quote`

Scope safeguards:
- no external API-key system added to `sm/v1`
- no changes to `super-mechanic-public/v1`
- no route path or namespace changes
- no endpoint redesign
- existing callback-level auth, business-scope and ownership checks preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P10-B-validation.md`) PASS in automated checks
- manual/static checks PASS:
  - no `__return_true` remains under `includes/api`
  - read route permission callbacks reviewed
  - quote approval permission callback reviewed
- runtime REST smoke calls NOT_RUN

---

## Fase 56P10-C External API QA (COMPLETA)

Runtime QA executed:
- unauthenticated HTTP requests against exact `/wp-json/sm/v1/...` routes:
  - `GET /clients` -> `401 sm_api_authentication_required`
  - `GET /vehicles` -> `401 sm_api_authentication_required`
  - `GET /processes` -> `401 sm_api_authentication_required`
  - `POST /quotes/1/approve` -> `401 sm_api_authentication_required`
- authenticated WordPress runtime REST dispatch with administrator user `admin@mardisom.com`:
  - `GET /clients` -> `200`, response keys `success,data,meta`
  - `GET /vehicles` -> `200`, response keys `success,data,meta`
  - `GET /processes` -> `200`, response keys `success,data,meta`
  - `GET /reporting/summary` -> `200`, response keys `success,data,meta`
- unauthorized mutation runtime dispatch with `sm_client` user `client@mekvort.local`:
  - `POST /quotes/999999/approve` -> `403 sm_api_quote_approve_forbidden`

Compatibility state:
- namespace remains `sm/v1`
- public REST path remains `/wp-json/sm/v1/...`
- authenticated success payload shape remains `success`, `data`, `meta`
- permission-layer error shape is standard WordPress REST error payload

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P10-C-validation.md`) executed successfully; contract is manual-only and reports NOT_RUN for runner manual checks
- manual/runtime QA checks PASS
- no critical bug discovered
- no code changes required

---

## Fase 56P11-A PDF Layout Final (PARCIAL)

Delivered in current code:
- invoice printable/PDF HTML layout improved in:
  - `includes/invoices/class-invoice-service.php`
- quote printable/PDF HTML layout improved in:
  - `includes/quotes/class-quote-service.php`
- contract-required PDF directory placeholder added:
  - `includes/pdf/.gitkeep`

Layout changes applied:
- stronger PDF header hierarchy with company brand and document title
- structured document metadata tables for client, process, status and date fields
- item tables updated for clearer column widths, readable spacing and right-aligned numeric columns
- totals moved from loose paragraphs into a dedicated summary table
- invoice payment summary and payment history made easier to scan
- notes and footer sections separated from financial totals for print clarity

Compatibility and scope safeguards:
- no totals calculation, tax logic, discount logic or payment logic changed
- no PDF engine replacement
- no export/download flow redesign
- quote and invoice PDF filenames and service entrypoints preserved
- reporting PDF export compatibility validated through existing `Report_PDF_Service`
- reporting visual renderer was not modified because active code lives in `includes/reporting/class-report-pdf-service.php`, which is outside the 56P11-A allowed file list
- localized critical PDF bug fixed in allowed invoice file:
  - `Invoice_Service::get_invoice_print_context()` now imports the existing `Super_Mechanic\Settings` class required by `Settings::OPTION_NAME`

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-A-validation.md`) PASS in automated check:
  - `pdf_folder_exists` PASS
  - manual checks NOT_RUN by runner
- manual/runtime checks PASS:
  - invoice PDF HTML readable with new layout classes and preserved total value
  - quote PDF HTML readable with new layout classes and preserved total value
- reporting PDF binary generated successfully with existing export flow
- totals preserved in rendered output

Closure state:
- PARCIAL because invoice and quote PDF layouts were improved and validated, but reporting visual layout was not changed due the active renderer being outside contract allowed files

---

## Fase 56P11-A1 Reporting PDF Layout Final (COMPLETA)

Delivered in current code:
- reporting PDF visual layout finalized in:
  - `includes/reporting/class-report-pdf-service.php`
- 56P11-A reporting visual pending area completed under explicit A1 scope

Layout changes applied:
- professional reporting PDF header with Mekvort / Super Mechanic branding
- clearer document title and export context subtitle
- structured metadata table for:
  - generated at
  - range
  - business scope
- key metrics table improved with consistent spacing, column widths and right-aligned numeric values
- period comparison table improved with clearer current/previous/delta/trend columns
- visual hierarchy improved through section headings, borders and footer note

Compatibility and scope safeguards:
- same metrics and comparison rows preserved
- same reporting service/data methods preserved
- same PDF engine loading and generation flow preserved
- same filename and response payload behavior preserved
- no invoice or quote files touched in A1
- no calculations, schema, API, CRM, reset, frontend or PDF library changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-A1-validation.md`) PASS in automated check:
  - `report_pdf_service_exists` PASS
  - manual checks NOT_RUN by runner
- manual/runtime checks PASS:
  - reporting PDF generated successfully
  - reporting layout source contains new header/meta/table structure
  - expected metrics remain present
  - PDF binary does not expose raw HTML/CSS text

---

## Fase 56P11-B PDF Data Mapping Verification (COMPLETA)

Verification completed:
- invoice PDF data mapping audited from invoice context/source rows to rendered PDF HTML
- quote PDF data mapping audited from quote context/source rows to rendered PDF HTML
- reporting PDF data mapping audited from reporting summary/comparison payloads to rendered PDF HTML and generated PDF binary

Invoice mapping verified:
- invoice number, company, client, process, status, issued/due dates
- item labels, descriptions, quantities, unit prices and line totals
- subtotal, tax total, discount total and grand total
- paid amount, remaining balance and payment history
- empty item state

Quote mapping verified:
- quote number, company, client, process, status and created date
- item labels, descriptions, quantities, unit prices and line totals
- subtotal, tax total, discount total and grand total
- empty item state

Reporting mapping verified:
- generated at, range label and business scope
- all 9 reporting metric values
- comparison current/previous/delta values where available
- generated PDF binary remains valid

Result:
- no mapping mismatches found
- no code changes required
- calculations, taxes, discounts, payments, balances, PDF engine and export flows preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-B-validation.md`) PASS in automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks
- manual/runtime checks PASS:
  - invoice PDF data matches source
  - quote PDF data matches source
  - reporting PDF data matches source
  - financial totals preserved

---

## Fase 56P11-C PDF Export Stability (PARCIAL)

Stability audit completed:
- invoice PDF repeated export stability checked
- quote PDF repeated export stability checked
- reporting PDF repeated export stability checked
- download header handling audited
- filenames checked for PDF extension and sanitized output
- invalid/missing export targets checked for controlled failures

Runtime/manual results:
- repeated invoice export stable -> PASS
  - invoice ID `1`
  - filename `smi-20260422-0001.pdf`
  - repeated byte size `9462`
- repeated quote export stable -> PASS
  - quote ID `2`
  - filename `smq-20260425034507-1671.pdf`
  - repeated byte size `8902`
- repeated reporting export stable -> PASS
  - business ID `1`, range `30d`
  - generated filename pattern `sm-reporting-b1-30d-YYYYMMDD-HHMMSS.pdf`
  - repeated byte size `59010`
- quote empty item state -> PASS
- reporting empty business scope -> PASS
- invalid invoice ID -> PASS with `sm_invoice_not_found`
- invalid quote ID -> PASS with `sm_quote_not_found`

Header/export behavior:
- invoice and quote exports use `PDF_Service::stream_pdf(...)`
- reporting export uses `Reporting_Admin_Controller::handle_download_pdf(...)`
- both emit PDF content type, attachment disposition and content length from generated content
- filenames are sanitized before response

Result:
- no export instability found
- no code changes required
- calculations, mapping, PDF engine and export flow preserved

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-C-validation.md`) PASS in automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- manual/runtime checks PASS for repeated exports, headers/filenames and invalid/missing errors

Closure state:
- PARCIAL because partial payment export stability could not be validated; the local dataset only contains invoice ID `1` fully paid (`paid=32`, `remaining=0`)

---

## Fase 56P11-C0 Embedded PDF Engine Restore (COMPLETA)

Delivered in current code:
- reporting PDF embedded-engine detection hardened in:
  - `includes/reporting/class-report-pdf-service.php`
- embedded TCPDF physical package confirmed at:
  - `includes/libs/pdf/tcpdf/tcpdf.php`
- reporting PDF service now treats embedded TCPDF loading as an explicit success/failure result before checking external engine classes
- fallback message now points to the missing/unloadable bundled TCPDF package instead of asking users to install external PDF libraries/plugins

Runtime/manual results:
- embedded TCPDF file exists -> PASS
- `Report_PDF_Service::can_generate_pdf()` -> PASS
- `class_exists('TCPDF')` after service detection -> PASS
- active reporting engine label -> `TCPDF`
- reporting PDF generation -> PASS
  - sample filename `sm-reporting-b1-30d-20260506-010158.pdf`
  - MIME `application/pdf`
  - binary header `%PDF-`
  - no raw `<html` payload detected
- Reporting admin page render -> PASS
  - `Download PDF Report` button present and enabled
  - engine notice reports `TCPDF`
  - missing-engine/install message absent

Scope safeguards:
- no external plugin/library dependency added
- no reporting calculations, data mapping, layout, filename behavior, response behavior, schema, API, invoices or quotes changed

Validation state:
- `php-lint` PASS

---

## Fase 56P11-C Retake After C0 PDF Export Stability (PARCIAL)

Retake context:
- 56P11-C0 restored embedded TCPDF for Reporting PDF
- reporting engine detection reports `TCPDF`
- reporting PDF generation produces valid `%PDF-` binary

Runtime/manual results:
- fresh shared PDF service check -> FAIL
  - `Super_Mechanic\Helpers\PDF_Service::can_generate_pdf()` returns false in a fresh request
  - invoice and quote exports fail before entity validation with `sm_pdf_engine_unavailable`
- invoice PDF fresh request -> FAIL 3/3
  - invoice ID `1`
  - error `sm_pdf_engine_unavailable`
- quote PDF fresh request -> FAIL 3/3
  - quote ID `1`
  - error `sm_pdf_engine_unavailable`
- reporting PDF -> PASS 3/3
  - business ID `1`, range `30d`
  - filename pattern `sm-reporting-b1-30d-YYYYMMDD-HHMMSS.pdf`
  - MIME `application/pdf`
  - repeated byte size `59009`
  - binary header `%PDF-`
  - no raw HTML detected
- reporting empty business scope -> PASS
- reporting empty range -> PASS
- invoice/quote conditional behavior after Reporting loads TCPDF -> PASS 3/3 each
  - invoice filename `smi-20260422-0001.pdf`, bytes `9462`, total `32.00`
  - quote filename `smq-20260422031820-1916.pdf`, bytes `9013`, total `32.00`

Issue isolated:
- 56P11-C0 restored embedded TCPDF loading in `Report_PDF_Service`
- invoice/quote exports use the shared `PDF_Service`, which does not load embedded TCPDF by itself
- required minimal fix is in `includes/helpers/class-pdf-service.php`, outside the 56P11-C allowed file list

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-C-validation.md`) PASS automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks

Closure state:
- PARCIAL because reporting stability passes, but invoice/quote PDF exports fail in fresh requests until embedded TCPDF loading is centralized for the shared PDF service

---

## Fase 56P11-C1 Shared PDF Engine Loader (COMPLETA)

Delivered in current code:
- shared embedded PDF engine loader centralized in:
  - `includes/helpers/class-pdf-service.php`
- reporting PDF service aligned to the shared loader in:
  - `includes/reporting/class-report-pdf-service.php`

Root cause resolved:
- Reporting loaded embedded TCPDF through `Report_PDF_Service`
- invoice and quote exports use shared `PDF_Service`, which did not load embedded TCPDF in fresh requests
- C1 makes shared `PDF_Service` load bundled TCPDF from:
  - `includes/libs/pdf/tcpdf/tcpdf.php`
  - `vendor/tecnickcom/tcpdf/tcpdf.php` when present
  - `vendor/autoload.php` when present

Runtime/manual results:
- `PDF_Service::can_generate_pdf()` in fresh request -> PASS
- `class_exists('TCPDF')` after detector -> PASS
- invoice PDF fresh export -> PASS 3/3
  - filename `smi-20260422-0001.pdf`
  - MIME `application/pdf`
  - bytes `9462`
  - binary header `%PDF-`
- quote PDF fresh export -> PASS 3/3
  - filename `smq-20260422031820-1916.pdf`
  - MIME `application/pdf`
  - bytes `9013`
  - binary header `%PDF-`
- reporting PDF remains stable -> PASS 3/3
  - engine `TCPDF`
  - bytes `59009`
  - binary header `%PDF-`
- invalid invoice ID `999999` -> PASS controlled `sm_invoice_not_found`
- invalid quote ID `999999` -> PASS controlled `sm_quote_not_found`

Scope safeguards:
- no calculation changes
- no layout changes
- no filename changes
- no PDF engine replacement
- no invoice/quote business logic changes
- no schema changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P11-C1-validation.md`) PASS automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks

Closure state:
- COMPLETA

---

## Fase 56P13-C First Connector Prototype Mock (COMPLETA)

Delivered in current code:
- first inbound inventory connector prototype added under:
  - `includes/integrations/inventory-connectors/`
- mock connector facade added:
  - `Mock_Inventory_Connector`
- mock local adapter added:
  - `Mock_Inventory_Adapter`
- connector orchestration service added:
  - `Inventory_Connector_Service`
- normalized payload mapper/validator added:
  - `Inventory_Sync_Mapper`
- mock inventory records included:
  - Toyota Corolla 2024 Hybrid
  - Honda Civic 2023 Sport
  - Fiat 500 2022 Lounge

Connector behavior:
- provider key: `mock_inventory`
- local/mock records only
- no real provider
- no external API calls
- no OAuth
- no scheduled sync
- no admin UI
- no DB writes

Dry-run result verified locally:
- `total_rows`: 3
- `valid_rows`: 3
- `invalid_rows`: 0
- `would_create`: 3
- `would_update`: 0
- `would_skip`: 0
- `writes`: 0

Sync simulation result verified locally:
- `result`: `success`
- `imported`: 3
- `updated`: 0
- `skipped`: 0
- `writes`: 0
- `simulation`: true

Scope safeguards:
- no CRM/users/process/payment/API changes
- no schema changes
- no admin UI changes
- no assets changes
- connector payload remains business-scoped
- provider-specific logic stays outside `Vehicle_Catalog_Service`

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P13-C-validation.md`) PASS automated checks:
  - PASS: 7
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- local mock execution PASS:
  - dry-run returns expected counters
  - sync simulation returns expected counters
  - no writes performed

Runtime/manual state:
- WordPress UI runtime validation NOT_APPLICABLE
- runtime real not required because there is no UI, schema migration, external provider call, scheduled sync or DB mutation in this phase

Deferred:
- real provider adapter
- connector persistence/sync mapping schema
- connector admin UI
- scheduled sync
- OAuth/credential storage
- queue/retry handling
- external media sync
- real catalog import from connector sync

Closure state:
- COMPLETA

---

## Fase 56P13-B Generic Connector Contract (COMPLETA)

Delivered in current documentation:
- generic inventory connector technical contract defined in:
  - `docs/INVENTORY_CONNECTOR_CONTRACT.md`
- connector identity requirements documented:
  - `connector_key`
  - `provider_name`
  - `provider_type`
  - `version`
  - `business_id`
- adapter method contract documented:
  - `get_connector_key()`
  - `validate_credentials()`
  - `fetch_inventory()`
  - `normalize_item()`
  - `dry_run()`
  - `sync()`
- normalized inventory payload documented for required and optional provider-neutral fields
- sync operation vocabulary documented:
  - `dry_run`
  - `import_new`
  - `update_existing`
  - `deactivate_stale`
  - `skip_invalid`
  - `conflict_detected`
- standard connector error model documented
- logging, conflict handling and security expectations documented

Scope safeguards:
- documentation-only phase
- no `includes/*` changes
- no `assets/*` changes
- no schema changes
- no runtime connector implementation
- no customer vehicle creation from connectors

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P13-B-validation.md`) PASS automated checks:
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- manual/static confirmation:
  - no `includes/*` files modified by this phase
  - no `assets/*` files modified by this phase
  - contract aligns with `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`
  - roadmap/state/QA docs aligned

Deferred:
- connector runtime interfaces
- provider adapter implementation
- connector persistence/schema
- encrypted credentials storage
- scheduled sync
- webhook sync
- queue workers
- retry strategy
- external media sync

Closure state:
- COMPLETA

---

## Fase 56P13-A Connector Architecture Decision (COMPLETA)

Delivered in current documentation:
- canonical inbound inventory connector strategy defined in:
  - `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`
- connector architecture decision documented as provider-agnostic and isolated from core catalog logic
- future inventory providers are defined as adapters, not core logic:
  - `mobile_de`
  - `autoscout24`
  - `dealercenter`
  - `generic_csv_api`
- canonical flow defined:
  - external provider
  - provider adapter
  - raw provider records
  - sync mapper
  - normalized catalog payload
  - sync validation
  - catalog sync service
  - `Vehicle_Catalog_Service`
  - vehicle catalog
- recommended future layers documented:
  - Connector Controller
  - Connector Service
  - Provider Adapter
  - Sync Mapper
  - Sync Repository

Scope safeguards:
- documentation-only phase
- no runtime implementation
- no schema changes
- no `includes/*` changes
- no `assets/*` changes
- no CRM/users/process/payment/API implementation changes

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P13-A-validation.md`) PASS automated checks:
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks
- manual/static confirmation:
  - no `includes/*` files modified by this phase
  - no `assets/*` files modified by this phase
  - roadmap/state/QA docs aligned

Deferred:
- connector implementation
- connector persistence/schema
- OAuth
- scheduled sync
- webhook sync
- queue workers
- retry strategy
- external media sync

Closure state:
- COMPLETA as architecture-only documentation phase

---

## Fase 56P12-D Inventory Import Base (PARCIAL)

Delivered in current code:
- CSV import foundation added for reusable vehicle catalog records
- import service added:
  - `includes/vehicles/class-vehicle-catalog-import-service.php`
- Vehicle Catalog admin now includes CSV import flow:
  - upload CSV
  - dry-run validation and preview
  - confirmed import of valid rows
- dry-run reports:
  - total rows
  - valid rows
  - invalid rows
  - header/row errors
  - preview rows
- confirmed import writes catalog records through:
  - `Vehicle_Catalog_Service::create_catalog_vehicle(...)`

CSV format:
- required columns:
  - `make`
  - `model`
  - `year`
- optional columns:
  - `trim_version`
  - `body_type`
  - `fuel_type`
  - `transmission`
  - `engine`
  - `notes`
  - `status`

Scope safeguards:
- no external inventory connector added
- no scheduled sync added
- no VIN decoder added
- no customer vehicle creation from import
- no CRM/users/process/payment/API/frontend portal changes
- no schema changes
- no SQL added outside repository/database layers
- import remains business-scoped and admin-only

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P12-D-validation.md`) PASS automated checks:
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks

Runtime/manual state:
- Static code verification PASS:
  - dry-run does not call persistence
  - confirmed import uses `Vehicle_Catalog_Service`
  - admin actions enforce nonce/capability/business scope and `.csv` extension
  - no SQL detected in import service/admin controller
- WordPress admin browser validation NOT_RUN

Deferred:
- runtime browser-admin upload/dry-run/import confirmation
- duplicate catalog detection beyond current service validation
- large-file/background import
- external inventory connector/scheduled sync

Closure state:
- PARCIAL until runtime real admin CSV dry-run/import is validated

---

## Fase 56P12-B Vehicle Catalog Admin UI (PARCIAL)

Delivered in current code:
- Vehicle Catalog admin page added under Super Mechanic:
  - `super-mechanic-vehicle-catalog`
- admin controller added:
  - `includes/admin/class-vehicle-catalog-admin-controller.php`
- admin menu wiring added in:
  - `includes/class-admin-menu.php`
- catalog count exposed through:
  - `Vehicle_Catalog_Service::count_catalog_vehicles(...)`

Admin capabilities:
- list catalog records by business
- create catalog records
- edit catalog records
- deactivate catalog records
- filter by business/status/search

Scope safeguards:
- no CSV import added
- no catalog-to-customer-vehicle creation added
- no schema changes
- no API/frontend/CRM/users/process/payment changes
- admin controller uses `Vehicle_Catalog_Service` for catalog persistence
- no SQL added outside repository/database layers
- business selector and requested business scope are filtered through current user business access

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P12-B-validation.md`) PASS automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3 manual checks

Runtime/manual state:
- WordPress admin browser validation NOT_RUN
- WP-CLI runtime validation attempted but `wp` command is unavailable in this shell

Deferred:
- CSV import
- catalog-to-customer-vehicle workflow
- duplicate catalog detection
- external inventory connector

Closure state:
- PARCIAL until runtime real admin UI create/edit/deactivate is validated

---

## Fase 56P12-C Vehicle Creation From Catalog (PARCIAL)

Delivered in current code:
- vehicle create/edit admin UI now exposes an optional Vehicle Catalog selector
- selector options are loaded through `Vehicle_Catalog_Service`
- catalog list is active-record only and business-scoped by the current business context
- selecting a catalog record pre-fills compatible persisted vehicle fields:
  - brand/make
  - model
  - year
- catalog-only details are surfaced as preview because the customer vehicle schema does not store them:
  - trim/version
  - body type
  - fuel type
  - transmission
  - engine
- vehicle-specific fields remain manual/editable:
  - client
  - VIN
  - plate
  - color
  - mileage
  - notes

Scope safeguards:
- no CSV import added
- no external inventory connector added
- no CRM/users/process/payment/API/frontend portal changes
- no SQL added to the admin controller
- no schema change introduced
- catalog reference is not persisted because `sm_vehicles` has no safe `catalog_vehicle_id` or equivalent column

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P12-C-validation.md`) PASS automated checks:
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks

Runtime/manual state:
- WordPress admin browser validation NOT_RUN

Deferred:
- persisted catalog reference for customer vehicles
- persisted trim/body/fuel/transmission/engine vehicle attributes
- richer duplicate/matching workflow

Closure state:
- PARCIAL until runtime real admin create/edit from catalog is validated

---

## Fase 56P12-C1 Vehicle Schema Enrichment From Catalog (PARCIAL)

Delivered in current code:
- customer vehicle schema enriched in `sm_vehicles`
- schema version updated to `1.21.0`
- new nullable vehicle fields:
  - `catalog_vehicle_id`
  - `trim_version`
  - `body_type`
  - `fuel_type`
  - `transmission`
  - `engine`
- repository create/update persistence supports enriched vehicle fields
- service normalization supports enriched fields and validates selected catalog ID through `Vehicle_Catalog_Service`
- vehicle admin create/edit form exposes editable technical fields
- catalog selection now fills:
  - brand/make
  - model
  - year
  - trim/version
  - body type
  - fuel
  - transmission
  - engine
- vehicle detail view displays persisted technical fields

Scope safeguards:
- no CSV import added
- no external inventory connector added
- no CRM/users/process/payment/API/frontend portal changes
- no SQL added to admin controller or service
- existing vehicle records remain compatible through nullable fields

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P12-C1-validation.md`) PASS automated checks:
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks

Runtime/manual state:
- WordPress schema upgrade/runtime dbDelta validation NOT_RUN
- WordPress admin browser validation NOT_RUN

Deferred:
- runtime confirmation that `sm_vehicles` columns were applied by dbDelta
- historical vehicle backfill
- duplicate/matching workflow

Closure state:
- PARCIAL until schema/runtime admin persistence is validated in WordPress

---

## Fase 56P12-A Vehicle Catalog Schema/Service (COMPLETA)

Delivered in current code:
- reusable business-scoped vehicle catalog foundation added
- schema version updated to `1.20.0`
- catalog table registered in schema:
  - `sm_vehicle_catalog`
- repository added:
  - `includes/vehicles/class-vehicle-catalog-repository.php`
- service added:
  - `includes/vehicles/class-vehicle-catalog-service.php`

Table fields:
- `business_id`
- `make`
- `model`
- `year`
- `trim_version`
- `body_type`
- `fuel_type`
- `transmission`
- `engine`
- `notes`
- `status`
- timestamps

Service methods available:
- `create_catalog_vehicle(...)`
- `update_catalog_vehicle(...)`
- `get_catalog_vehicle(...)`
- `list_catalog_vehicles(...)`
- `deactivate_catalog_vehicle(...)`

Scope safeguards:
- no admin UI added
- no CSV import added
- no external inventory connector added
- no existing vehicle logic changed
- no process/payment/CRM/users/API/frontend files touched
- SQL remains in repository/database layer
- explicit invalid business scope does not fall back to active business for catalog reads

Runtime/manual results:
- `wp_sm_vehicle_catalog` exists -> PASS
- create/read/update/list/deactivate through service -> PASS
- business scope respected -> PASS
- invalid explicit business create fails with controlled `business_required`

Validation state:
- `php-lint` PASS
- QA runner (`docs/contracts/validation/56P12-A-validation.md`) PASS automated checks:
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3 manual checks

Deferred:
- admin UI
- CSV import
- catalog-to-customer-vehicle workflow
- duplicate catalog detection

Closure state:
- COMPLETA
