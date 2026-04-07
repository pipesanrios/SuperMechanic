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

