# PLUGIN_ROADMAP.md

## Purpose

Forward continuity only (future planning).  
Historical closure details live elsewhere.

Use:
- `docs/CURRENT_STATE.md` for current confirmed state
- this file only for what comes next

---

## Baseline

- Current delivery baseline: **Fase 43**
- Fase 43 status: **COMPLETA**

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
