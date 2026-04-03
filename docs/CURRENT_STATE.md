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

- Last completed phase baseline: **Fase 40**
- Last completed block: **40D**
- Block status:
  - `39B` COMPLETE
  - `39C` COMPLETE
  - `39D` COMPLETE
  - `39E` COMPLETE
  - `40A` COMPLETE
  - `40B` COMPLETE
  - `40C` COMPLETE
  - `40D` COMPLETE

---

## 40 Complete Scope

- 40A:
  - global operational summary in admin dashboard
  - business-level aggregated visibility
- 40B:
  - `includes/dashboard/class-workload-service.php`
  - section **Mi trabajo** with per-user workload
  - aggregation of CRM tasks, operational signals, active processes, upcoming appointments
- Hotfix alignment:
  - dashboard signals aligned with CRM Pipeline policy (`persisted` first, `runtime fallback` only when persisted is absent)
- 40C:
  - operational/SLA metrics via `get_operational_metrics($business_id)`
  - tasks, processes, alerts, appointments KPI blocks
- 40D:
  - UI consistency improvements in CRM Pipeline operational views
- Constraints respected:
  - no new tables
  - no cron
  - no persisted-alert recalculation when persisted exists
  - no functional regressions in core modules

---

## System Capability State

The system is now capable of:

- Unified operational dashboard layer:
  - global summary (40A)
  - user workload (40B)
  - SLA metrics (40C)
- Cross-module operational signal alignment with CRM Pipeline
- Improved operational UI consistency for CRM Pipeline (40D)
- Stable operational baseline ready for automation continuity

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

### Fase 41 — Automatización Operativa

The next phase extends the now-stable operational layer with automation workflows.

Immediate next block:

👉 **41A — Automatización Operativa (entrypoint)**

Focus:

- automation based on validated operational signals
- low-friction operational execution
- preserving tenancy, safety, and architectural boundaries

---

## Important Rule

This file reflects **only the current system state**.

- Do not add historical narrative
- Do not document future phases beyond immediate continuity
- Do not duplicate roadmap content
