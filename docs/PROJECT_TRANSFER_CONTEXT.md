# PROJECT_TRANSFER_CONTEXT.md

Long-form handoff context for multi-AI continuity.  
Date: 2026-03-31

---

## This File Is

- A **continuity and handoff layer** between AI sessions.
- A **high-level system memory**, not a source of truth.

---

## This File Is NOT

- Not the source of truth for runtime state → use `docs/CURRENT_STATE.md`
- Not the roadmap → use `docs/PLUGIN_ROADMAP.md`
- Not the architecture definition → use `ARCHITECTURE.md`

---

## System Hierarchy (Reference)

1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`
4. `ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt`
5. `ai/rules/*`
6. `ai/context/*`
7. `docs/*`

---

## Current Baseline Summary

- Plugin: `0.1.0`
- Schema: `1.19.0`
- Phase baseline: **Fase 39**
- Block `39E`: **COMPLETE**

### 39E Delivered

- Internal scheduler: `sm_crm_scheduler_tick`
- Persisted CRM alerts: `sm_crm_alerts`
- Batch recalculation + state resolution
- UI consumption:
  - list
  - kanban
  - view
- Controlled fallback (only when persisted alerts are missing)

---

## System Maturity State

The system has transitioned from:

- **runtime-calculated signals**
→ to
- **persisted operational signals**

This enables:

- stable cross-module behavior
- predictable UI state
- reliable prioritization logic
- foundation for operational tooling (Phase 40)

---

## Architectural Memory

- Active runtime path: `includes/*`
- Legacy path: `includes/modules/*` (reference only)
- Pattern:
  `Controller -> Service -> Repository -> Database`
- Tenancy:
  enforced via `business_id`

---

## Key Continuity Risks

- Reintroducing duplicated rule systems
- Mixing:
  - state
  - roadmap
  - architecture
  in the same document
- Ignoring entrypoint (`AGENTS_BOOTSTRAP.md`)
- Coding without reading context
- Recalculating data that is already persisted (alerts)
- Creating N+1 queries in aggregation layers

---

## Current Execution Model

The system is now fully:

### Contract-Driven

- Task Contracts define:
  - scope
  - allowed files
  - outputs
  - validations
  - documentation updates

- Validation Contracts define:
  - automated checks
  - manual checks
  - runtime validation

- QA Runner:
  - executes automated checks
  - produces structured results

---

## Recommended Next Continuity

### Fase 40 — Operación Interna (Core)

The next phase focuses on:

- real operational usability
- reducing cognitive load
- improving daily workflows
- leveraging persisted alert system (39E)

### First Step

👉 **40B — Workload Operativo por Usuario**

This introduces:

- unified workload view per user
- aggregation of:
  - tasks
  - alerts (persisted)
  - processes
  - appointments
- prioritization layer

---

## Strategic Direction

The system evolution path is now:

1. Data integrity (completed)
2. Signal persistence (39E complete)
3. Operational layer (Phase 40 — current focus)
4. Infrastructure scaling (future)
5. SaaS evolution (future)

---

## Canonical References

- State → `docs/CURRENT_STATE.md`
- Roadmap → `docs/PLUGIN_ROADMAP.md`
- Architecture → `ARCHITECTURE.md`
- Database → `docs/DATABASE_MAP.md`
- Module registry → `docs/MODULE_REGISTRY.md`
- Rule system → `docs/RULE_SYSTEM.md`
- Known traps → `docs/KNOWN_TRAPS.md`

---

## Final Rule

This file provides continuity, not authority.

If any conflict exists:

→ follow code  
→ then `CURRENT_STATE.md`  
→ then `AI_CONTEXT.md`