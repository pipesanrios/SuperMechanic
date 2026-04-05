# CURRENT_STATE.md

Date: 2026-04-05

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

- Last completed phase baseline: **Fase 47 (en progreso)**
- Last completed block: **47A**
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
