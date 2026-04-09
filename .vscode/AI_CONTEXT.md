# AI_CONTEXT.md

# Super Mechanic — AI Execution Context

## Purpose

This file provides a **short operational context** for AI agents.

It is a **bridge between rules, contracts, and execution**, not a replacement for core documentation.

- Do not treat this file as source of truth.
- Do not duplicate rules from `AGENTS.md`.
- Use this file only to **orient execution quickly after bootstrap**.

---

## Entrypoint

The mandatory entrypoint is:

`AGENTS_BOOTSTRAP.md`

No execution is allowed without completing the required reading sequence.

---

## Required Reading Order (Condensed)

This mirrors the bootstrap order and must be respected:

1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`

### Rules
4. `ai/rules/AI_RULES.md`
5. `ai/rules/GUARDRAILS.md`
6. `ai/rules/MODULE_BOUNDARIES.md`

### Context
7. `ai/context/AGENTS_QUICK_CONTEXT.md`
8. `ai/context/PROJECT_MEMORY.md`
9. `ai/context/WORKFLOW.md`

### System state
10. `docs/CURRENT_STATE.md`
11. `docs/PROJECT_TRANSFER_CONTEXT.md`
12. `docs/PLUGIN_ROADMAP.md`
13. `ARCHITECTURE.md`

### Technical support (when needed)
14. `docs/DATABASE_MAP.md`
15. `docs/MODULE_REGISTRY.md`
16. `docs/SYSTEM_MAP.md`
17. `docs/KNOWN_TRAPS.md`

---

## Current Baseline

- Plugin version: `0.1.0`
- Schema version: `1.19.0`
- Current confirmed baseline: **Fase 50 COMPLETA**
- System state: **stable operational + multi-level automation execution + finalized multi-business access model (49A-49E) + notifications/webhooks/automation validated (50A-50F)**
- Next continuity: **fase siguiente de roadmap (post-50)**

⚠️ Important:
Before starting any task, confirm continuity in:
- `docs/PLUGIN_ROADMAP.md`
- `docs/CURRENT_STATE.md`

Operational status reminder:
- rules are modeled, evaluated and persisted by tenant (`Operational_Rules_Service`)
- execution modes are active:
  - `manual` (guided)
  - `confirmable`
  - `auto_controlled`
- safety controls are mandatory in execution paths:
  - guardrails
  - controlled rollback
- no cron-based uncontrolled execution
- no execution should bypass capability/nonce/business validation
- dashboard supports lightweight per-user UI persistence for secondary sections without changing operational logic
- roles/access administration page available for authorized admins (`super-mechanic-roles`) with safe operational role assignment and inconsistency visibility
- business memberships are fully active through `Business_Membership_Service`
- global vs membership-scoped access resolution is centralized in `Role_Access_Service`
- Roles & Access supports secure membership management + transfer actions
- consistency hardening is active:
  - membership validation warnings
  - safe repair path for repairable inconsistencies
- phase closure review 47-50 is active:
  - Fase 47: PARCIAL
  - Fase 48: PARCIAL
  - Fase 49: COMPLETA
  - Fase 50: COMPLETA (runtime/manual closure consolidated in 50Z)
- licensing baseline (51A) is now active at technical level:
  - local table-backed license model (`sm_licenses`)
  - local activation/deactivation UI (`super-mechanic-license`)
  - no remote validation, billing, or aggressive enforcement yet
- branding baseline (51B) is now active at technical level:
  - centralized branding settings service (`Branding_Service`)
  - admin management page (`super-mechanic-branding`)
  - safe white-label visual application in plugin admin pages (name/logo/colors/footer)
- plan limits baseline (51C) is now active at technical level:
  - centralized limits/usage service (`Plan_Limits_Service`)
  - starter/pro/enterprise limits catalog with starter fallback on inactive license
  - limits and usage visibility integrated in License admin page (non-blocking warnings)
- onboarding baseline (51D) is now active at technical level:
  - centralized onboarding diagnostics (`Onboarding_Service`)
  - admin onboarding checklist page (`super-mechanic-onboarding`)
  - recommended next-step orchestration to existing setup pages (no wizard, no duplicated forms)

## CURRENT PHASE
FASE 53 - COMPLETA

## SYSTEM STATUS
- CORE: estable
- UX: estable
- PORTAL: operativo
- DASHBOARD: operativo

## READY FOR:
- Fase 54 (reporting/export)
- integracion externa
- escalado comercial

## 54E.2 STATUS
- embedded TCPDF integrated in plugin path (`includes/libs/pdf/tcpdf`)
- reporting PDF service configured to load embedded TCPDF and render HTML via `writeHTML`
- runtime/manual closure still required for final phase completion

## 55B STATUS
- API formalization layer integrated in runtime (`includes/api/class-api-loader.php`)
- versioned namespace available: `/wp-json/sm/v1/`
- delivered endpoints:
  - `GET /clients`
  - `GET /vehicles`
  - `GET /processes`
  - `GET /processes/{id}`
  - `GET /invoices`
  - `GET /reporting/summary`
  - `POST /quotes/{id}/approve`
- ownership and business scope enforced through existing services (`Access_Control_Service` + `Business_Context_Service`)
- automated validation PASS (`php-lint` + QA runner 55B); runtime/manual REST closure still required

## 55C STATUS
- webhook outbound dispatch formalized in `Webhook_Service`
- canonical commercial/operational event names enabled with legacy alias compatibility:
  - `process.created`
  - `process.updated`
  - `quote.approved`
  - `invoice.paid`
  - `payment.created`
- standardized payload normalization added with stable structure:
  - `event`, `timestamp`, `business_id`, `entity_type`, `entity_id`, `data`
- queue/retry/logging behavior preserved (queue-first + immediate fallback)
- automation engine event sanitization aligned for dotted canonical names without breaking existing flow
- automated validation PASS (`php-lint` + QA runner 55C); runtime/manual webhook closure still required

## 55D STATUS
- external connectors base layer integrated in runtime:
  - `includes/integrations/connectors/class-connector-repository.php`
  - `includes/integrations/connectors/class-connector-service.php`
- connectors admin management page integrated:
  - `includes/admin/class-connectors-admin-controller.php`
  - menu slug: `super-mechanic-connectors`
- connector dispatch reused standardized webhook payload normalization through:
  - `Webhook_Service::build_standard_event_payload(...)`
- supported connector types:
  - `webhook`
  - `google_sheets`
  - `email_trigger`
- supported canonical events:
  - `process.created`
  - `process.updated`
  - `quote.approved`
  - `invoice.paid`
  - `payment.created`
- automated validation PASS (`php-lint` + QA runner 55D); runtime/manual connectors closure still required

## 55E1 STATUS
- commercial extensibility centralized through:
  - `includes/commercial/class-commercial-hooks-service.php`
- standardized commercial hooks available:
  - `sm_quote_created`
  - `sm_quote_approved`
  - `sm_invoice_created`
  - `sm_invoice_paid`
  - `sm_payment_created`
  - `sm_process_completed`

## 55E2 STATUS
- monetization core integrated in runtime:
  - trial lifecycle support (`start`, `active`, `expired`)
  - effective license state resolution:
    - `active`
    - `trial`
    - `expired`
    - `inactive`
    - `revoked`
- controlled creation enforcement enabled (no full read/list block):
  - business creation
  - process creation
  - webhook creation
  - membership creation
- license admin UI extended with:
  - effective state
  - trial start/end
  - trial remaining days
  - creation restriction visibility

## FASE 55 RULES
- always use canonical events:
  - `process.created`
  - `process.updated`
  - `quote.approved`
  - `invoice.paid`
  - `payment.created`
- do not duplicate webhook logic; reuse `Webhook_Service` normalization/dispatch flow
- use `Commercial_Hooks_Service` for commercial extensibility hooks
- use `License_Service` (with `Plan_Limits_Service`) for controlled creation enforcement

---

## Execution Model

This system is **contract-driven**.

### Required Flow

1. Read context (bootstrap sequence)
2. Identify phase and scope
3. Load Task Contract
4. Validate Task Contract
5. Load Validation Contract (if exists)
6. Run analysis
7. Implement within scope
8. Validate (automated + manual)
9. Update documentation (if required)
10. Close task using contract rules

---

## Task Contract Rule

For any non-trivial task:

- A **Task Contract is required**
- Must define:
  - scope
  - allowed files
  - validations
  - docs to update

If no contract exists:
→ create one before coding

If request exceeds contract:
→ STOP

---

## Validation Model

Validation must be explicit and structured:

### Types

- Automated (QA Runner)
- Manual
- Runtime (WordPress real environment)
- Regression

### Rule

A task is not complete unless validation matches contract requirements.

---

## QA Runner (if applicable)

If automated validation is defined:

- Use: `php scripts/qa-runner.php`
- Results must be reported as:
  - PASS
  - FAIL
  - SKIPPED
  - NOT_RUN

QA Runner does not replace manual validation.

---

## Scope Control

- Do not expand scope beyond contract.
- Do not modify unrelated files.
- Do not introduce new architecture.
- Do not modify schema without explicit requirement.

---

## Performance Awareness

- Avoid N+1 queries
- Prefer batch operations
- Reuse services and repositories
- Do not recalculate persisted data (e.g., alerts)
- Prefer request-level reuse in controllers/services before adding new heavy reads
- Resolve `business_id` from business context when workload payload is not required
- Avoid per-row user lookups in admin tables; use batch resolution + in-request memoization

---

## Documentation Behavior

- Update only what is affected
- Do not duplicate information
- Do not mix history with current state
- Follow `DOCUMENTATION_RULES.md`

---

## Demo Dataset Recovery Context

- Canonical seed script: `scripts/seed-full-demo-multibusiness.php`
- Canonical recovery guide:
  - `docs/tasks/2026-04-demo-dataset-recovery-guide.md`
- Superadmin reference identity:
  - `admin@mardisom.com` (administrator + `sm_manage_plugin`)
- Roles & Access lists WP users only (not all CRM clients)

---

## Important Reminder

This file is **not source of truth**.

Always defer to:

- `AGENTS.md`
- `docs/CURRENT_STATE.md`
- actual code

---

## Final Rule

If the required reading order was not completed:

→ The AI is **not authorized** to implement changes.

