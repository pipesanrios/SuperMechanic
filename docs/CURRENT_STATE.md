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

