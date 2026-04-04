# CURRENT_STATE.md

Date: 2026-04-03

## Purpose

Current system state only.  
No historical narrative.  
No roadmap explanation.  
No future speculation beyond immediate continuity.

---

## Runtime Versions

- Plugin: `0.1.0`
- Schema: `1.19.0`

---

## Active Runtime Architecture

- Active path: `includes/*`
- Legacy path (not active): `includes/modules/*`
- Pattern: `Controller -> Service -> Repository -> Database`

---

## Current Delivery Baseline

- Last completed phase baseline: **Fase 43**
- Last completed block: **43E**
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

### Fase 44 — Continuidad operativa (pending definition)

Continuity target:
- preserve safety guarantees already active in 43
- expand controlled automation coverage without regression
- keep tenant isolation and execution guardrails as non-negotiable

---

## Important Rule

This file reflects **only the current system state**.

- Do not add historical narrative
- Do not document future phases beyond immediate continuity
- Do not duplicate roadmap content
