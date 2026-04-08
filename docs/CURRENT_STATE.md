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
- Schema: `1.19.0`

---

## Active Runtime Architecture

- Active path: `includes/*`
- Legacy path (not active): `includes/modules/*`
- Pattern: `Controller -> Service -> Repository -> Database`

---

## Current Delivery Baseline

- Last completed phase baseline: **Fase 50 (COMPLETA)**
- Last completed block: **50F (COMPLETA)**
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

