# CURRENT_STATE.md

Date: 2026-03-31

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

- Phase baseline: **Fase 39**
- Block status:
  - `39B` COMPLETE
  - `39C` COMPLETE
  - `39D` COMPLETE
  - `39E` COMPLETE

---

## 39E Complete Scope

- Internal scheduler: `sm_crm_scheduler_tick`
- CRM persisted alerts: `sm_crm_alerts`
- Batch recalculation and state resolution (`active → resolved`)
- Persisted alerts consumed in UI:
  - list
  - kanban
  - view
- Controlled runtime fallback when persisted alerts are absent
- No N+1 queries in alert consumption

---

## System Capability State

The system is now capable of:

- Persistent alert lifecycle (not runtime-only)
- Stable CRM operational signals
- Cross-module consumption of alerts
- Reliable scheduler-based recalculation
- UI consistency between persisted + fallback states

This enables the transition from **data system → operational system**

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

### Fase 40 — Operación Interna (Core)

The next phase shifts the system into **daily operational usability**.

Immediate next block:

👉 **40B — Workload operativo por usuario**

Focus:

- unified workload per user
- aggregation of:
  - tasks
  - alerts (persisted)
  - processes
  - appointments
- prioritization and operational clarity

---

## Important Rule

This file reflects **only the current system state**.

- Do not add historical narrative
- Do not document future phases beyond immediate continuity
- Do not duplicate roadmap content