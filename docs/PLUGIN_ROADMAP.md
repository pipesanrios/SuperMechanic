# PLUGIN_ROADMAP.md

## Purpose

Forward continuity only (future planning).  
Historical closure details live elsewhere.

Use:
- `docs/CURRENT_STATE.md` for current confirmed state
- this file only for what comes next

---

## Baseline

- Current delivery baseline: **Fase 56P Final Closure**
- Fase 56P status: **COMPLETA documental / stable pre-SaaS baseline**
- Canonical closure document:
  - `docs/PHASE_56P_FINAL_CLOSURE.md`

## Next Continuity — Phase 57 — SaaS Foundation

Canonical phase document:
- `docs/PHASE_57_SAAS_FOUNDATION.md`

Recommended macro scope:
- tenancy evolution
- SaaS billing
- centralized licensing
- async jobs
- connector runtime
- media storage strategy
- queue architecture
- provider credential storage
- SaaS-ready performance/indexing review

Continuity constraints:
- preserve `Controller -> Service -> Repository -> Database`
- preserve `business_id` isolation
- preserve existing API auth hardening
- preserve Vehicle Catalog as internal canonical inventory model
- connector provider logic must remain adapter-based

Official Phase 57 structure:
- 57A — SaaS foundation bootstrap
  - passive runtime context, tenant context, license context and queue placeholder contracts under `includes/saas/*`
  - canonical architecture document: `docs/SAAS_FOUNDATION_ARCHITECTURE.md`
- 57B — Tenant context layer
  - passive bridge between future `tenant_id` and current canonical `business_id`
- 57C — SaaS licensing
  - activation flow, plan validation, subscription state, license enforcement and centralized licensing architecture
  - passive licensing/subscription context delivered; billing and enforcement takeover remain deferred
- 57D — Async jobs / queues
  - imports, notifications, retries, sync queues and async processing strategy
  - passive queue contract, context, dispatcher and result model delivered under `includes/saas/*`
  - no workers, cron, persistence, external queues or background execution enabled
  - 57D1 smoke validation completed for valid jobs, invalid jobs, status model and passive dispatcher behavior
- 57E — Connector runtime
  - first real provider, scheduled sync, retry handling, connector execution runtime and sync orchestration
  - passive mock connector runtime wiring delivered as queue intent bridge
  - real providers, scheduled sync and workers remain deferred
- 57F — Queue persistence foundation
  - persistent SaaS queue table and repository delivered
  - dispatcher persistence remains opt-in; default remains passive and non-persistent
  - workers, cron, real execution and external queue providers remain deferred
- 57G-A — Manual queue worker
  - manual one-job worker foundation delivered for persisted SaaS queue jobs
  - simulation-only `inventory_connector_sync` processing
  - cron, scheduled workers, real provider execution and batch processing remain deferred
- 57G-B — Queue retry handling
  - controlled retry scheduling delivered for manual queue worker failures
  - deterministic backoff: +5 minutes, +15 minutes, +30 minutes
  - future retry jobs remain ignored until `available_at <= now`
  - cron, scheduled workers, automatic retry executor and batch processing remain deferred
- 57G — Media architecture
  - image sync, external storage strategy, CDN readiness, media ownership model and connector media lifecycle
- 57H — SaaS operations
  - telemetry, health checks, centralized admin, runtime diagnostics and SaaS operational tooling

Deferred/not included in roadmap alignment:
- no full multiserver orchestration yet
- no Kubernetes/container orchestration yet
- no billing provider implementation yet
- no real connector providers yet
- no websocket/live sync yet
- no queue workers implementation yet

Next recommended implementation continuity:
- 57G — media architecture
- 57H — SaaS operations

---

## Phase 42 — Operación Avanzada y Automatización Controlada (COMPLETED WITH PARTIAL COMPONENT)

Completed scope:
- 42A: assisted operational actions (manual safe navigation)
- 42B: controlled operational reassignment (PARTIAL runtime closure)
- 42C: safe bulk actions execution
- 42D: unified operational action center
- 42E: configurable executable rules (preview/evaluation only)

---

### Subphases Status

#### 🔹 42A — Acciones Operativas Asistidas (COMPLETED)

- dashboard actions derived from recommendations/escalation/workload
- safe navigation only
- no data mutation by this layer

---

#### 🔹 42B — Reasignación Operativa Controlada (PARTIAL)

- implementation complete for controlled `crm_task` reassignment
- technical validations passing
- operational observability added for zero-proposal diagnostics
- runtime completion pending dataset with:
  - overloaded users
  - available users
  - executable CRM task candidate

---

#### 🔹 42C — Acciones Masivas Seguras (COMPLETED)

- controlled bulk execution for `crm_task`
- strict capability/nonce/business validation
- no cron and no auto execution

---

#### 🔹 42D — Centro de Acción Operativa (COMPLETED)

- unified block for assisted actions, reassignment and bulk actions
- top operational UX layer without changing business rules

---

#### 🔹 42E — Reglas Ejecutables Configurables (COMPLETED)

- `Operational_Rules_Service` introduced
- rules definition + evaluation + action preview
- explicitly no automatic execution and no cron

---

## Phase 43 — Automatización Operativa Real (COMPLETED)

Completed scope:
- 43A: guided manual execution from triggered rules
- 43B: confirmable execution (`Confirm and Run`) with explicit human approval
- 43C: controlled auto execution with bounded eligibility and limits
- 43D: execution safety layer (`guardrails` + controlled rollback)
- 43E: persistent rules engine by tenant (`business_id`)

System capabilities after 43:
- guided execution
- confirmable execution
- auto controlled execution
- safety controls (guardrails + rollback)
- tenant-persistent rules configuration

### Subphases Status

#### 🔹 43A — Ejecución manual guiada (COMPLETA)

- rules evaluation connected to safe guided actions
- no auto execution in this layer

#### 🔹 43B — Ejecución confirmable (COMPLETA)

- prepared actions require explicit confirmation before mutation
- no mutation by GET and strict nonce/capability checks

#### 🔹 43C — Ejecución automática controlada (COMPLETA)

- controlled automation path available with bounded execution
- only supported safe actions are eligible

#### 🔹 43D — Seguridad y rollback (COMPLETA)

- execution guardrails exposed and enforced
- controlled rollback for supported actions

#### 🔹 43E — Motor de reglas persistente (COMPLETA)

- tenant-scoped persisted rule configuration
- default fallback preserved when no tenant config is present

---

## Phase 49 — Multi-Business + Access Model (COMPLETED)

Completed scope:
- 49A: business membership base model (`sm_business_user_roles`)
- 49B: global super admin scope vs membership-scoped access
- 49C: Roles & Access UI by business (secure membership management)
- 49D: membership transfer flows (`replace` / `add`)
- 49E: consistency hardening (validation + safe repair)

### Subphases Status

#### 🔹 49A — Business Membership Model (COMPLETA)
- installer, repository and service for business memberships
- primary membership support and active-status resolution

#### 🔹 49B — Super Admin / Global Access (COMPLETA)
- centralized access scope in `Role_Access_Service`
- canonical superadmin identity: `admin@mardisom.com`

#### 🔹 49C — Roles & Access UI por negocio (COMPLETA)
- per-user membership management in admin UI
- nonce/capability protected write actions

#### 🔹 49D — Membership Transfers (COMPLETA)
- user transfer across businesses with safe `replace` / `add` modes
- no aggressive destructive behavior

#### 🔹 49E — Consistency Hardening (COMPLETA)
- membership consistency validation methods
- safe repair path for repairable inconsistencies
- precise, actionable warnings in Roles & Access

---

## Phase 50 — Notifications / Triggers / Integrations (COMPLETED)

Current closure state:
- 50A: COMPLETE
- 50B: COMPLETE
- 50C: COMPLETE
- 50D: COMPLETE
- 50E: COMPLETE
- 50F: COMPLETE

Runtime closure:
- 50Z runtime closure executed on 2026-04-07 with PASS runtime evidence for:
  - notifications flow
  - webhooks CRUD/test/dispatch
  - automation engine event processing
  - no duplicate event dispatch observed

## Phase 56P — Pre-SaaS Baseline Closure

Closure:
- Phase 56P final documentary closure completed.
- Canonical closure:
  - `docs/PHASE_56P_FINAL_CLOSURE.md`

Connector baseline:
- 56P13-A completed architecture decision.
- 56P13-B completed generic connector contract.
- 56P13-C completed mock connector prototype.
- Canonical decision document:
  - `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`
- Canonical connector contract:
  - `docs/INVENTORY_CONNECTOR_CONTRACT.md`

Deferred into Phase 57 or later:
- real provider connectors
- scheduled sync
- webhook sync
- connector admin UI
- queues/retries
- OAuth/credential storage
- external media sync

---

## Roadmap Rules

- Do not rewrite completed history in this file
- Keep focus on forward continuity only
- Do not mix current state with planning
- Do not skip phases without explicit decision

---

## Priority Rule

If roadmap conflicts with:

- code
- `docs/CURRENT_STATE.md`

→ current state and code win  
→ roadmap must be updated
