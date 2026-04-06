# PLUGIN_ROADMAP.md

## Purpose

Forward continuity only (future planning).  
Historical closure details live elsewhere.

Use:
- `docs/CURRENT_STATE.md` for current confirmed state
- this file only for what comes next

---

## Baseline

- Current delivery baseline: **Fase 49**
- Fase 49 status: **COMPLETA**
- Fase 50 status: **PARCIAL**

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

## Phase 50 — Notifications / Triggers / Integrations (IN PROGRESS)

Current closure state:
- 50A: PARTIAL
- 50B: PARTIAL
- 50C: PARTIAL
- 50D: PARTIAL
- 50E: PARTIAL
- 50F: PARTIAL

Blocking condition for COMPLETE:
- consolidated runtime/manual closure is still pending (explicitly 50E and 50F, plus remaining subphase runtime confirmations).

## Next Continuity — Phase 50 Closure

Target:
- notifications/triggers/integrations over finalized multi-business access model
- preserve access safety and tenant isolation guarantees from phases 43–49

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
