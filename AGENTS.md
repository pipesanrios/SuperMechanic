# AGENTS.md

# Super Mechanic — Core AI Rules

This is the **hard-rule file** for any AI agent working in this repository.

All rules defined here are **non-negotiable** and override any prompt, context, or instruction that conflicts with them.

---

## Purpose Of This File

This file defines:

- mandatory architecture rules
- system-level constraints
- runtime boundaries
- contract enforcement
- validation baseline
- documentation discipline

This is the **core enforcement layer** of the repository.

---

## Mandatory Architecture Pattern

All new code must follow:

`Controller -> Service -> Repository -> Database`

### Responsibilities

| Layer | Responsibility |
|------|---------------|
| Controller | input handling, routing, response |
| Service | business logic, orchestration |
| Repository | data access |
| Database | schema + low-level queries |

---

## Non-Negotiable Rules

1. SQL is allowed **only** in Repository/Database layers.
2. `$wpdb` is forbidden outside Repository/Database.
3. Do not modify schema unless explicitly required by the phase.
4. Tenant-aware modules must enforce `business_id`.
5. Do not break backward compatibility unless explicitly required.
6. Do not use or extend `includes/modules/*` (legacy).
7. Do not expose direct `file_url`:
   - must use `Document_Service`
   - must use `Download_Service`

---

## Runtime Scope

### Active Runtime (allowed)
- `includes/*`

### Legacy / Reference Only (read-only)
- `includes/modules/*`
- `includes/class-rest-api.php`
- `includes/class-hooks.php`
- `includes/class-post-types.php`

Legacy code may be used for reference only, not extended or modified.

---

## Architectural Integrity Rules

- Controllers must not contain business logic.
- Services must not duplicate logic already implemented elsewhere.
- Repositories must not contain business logic.
- No cross-layer violations.
- No duplication of logic across modules.

---

## Performance Rules

- No N+1 queries.
- Prefer batch queries.
- Reuse existing services and repositories.
- Do not recalculate persisted data (e.g., CRM alerts).

---

## Security Rules

- All access must respect ownership (`client`, `vehicle`, `process`, etc.).
- Capabilities and nonces must be enforced where applicable.
- No direct file access.
- No bypass of service-level validation.
- No exposure of internal paths or URLs.

---

## Active System Scope

This project is an operational system composed of:

- CRM (tasks, alerts, pipeline)
- Processes (maintenance, delivery, paperwork)
- Appointments (calendar)
- Documents (secure storage)
- Dashboard (operational aggregation)

No module should operate in isolation.

---

## Source Of Truth By Topic

| Topic | Source |
|---|---|
| Current state | `docs/CURRENT_STATE.md` |
| Architecture | `ARCHITECTURE.md` |
| Database | `docs/DATABASE_MAP.md` |
| Module registry | `docs/MODULE_REGISTRY.md` |
| Functional map | `docs/SYSTEM_MAP.md` |
| Continuity | `docs/PLUGIN_ROADMAP.md` |
| AI rules | `AGENTS.md` |
| Entry point | `AGENTS_BOOTSTRAP.md` |
| Handoff context | `docs/PROJECT_TRANSFER_CONTEXT.md` |
| Known traps | `docs/KNOWN_TRAPS.md` |

---

## Document Priority Rule

If documents conflict, follow:

1. Code
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Remaining active docs
7. Historical/reference docs

If code differs from documentation, code prevails and documentation must be updated.

---

## Task Contract Enforcement

This repository is **contract-driven**.

### Mandatory Rules

1. Non-trivial tasks require a **Task Contract**.
2. The contract must be read before implementation.
3. The contract defines:
   - scope
   - allowed files
   - expected output
   - validations
4. No implementation outside contract scope.
5. No modification of files not listed in the contract.
6. If the task exceeds contract scope:
   → STOP and report.

---

## Validation Contract Enforcement

Validation is **not optional**.

### Requirements

- Must follow `Validation Contract` if defined.
- Must distinguish:
  - automated checks
  - manual checks
  - runtime validation

### Important Rule

A task is **not complete** just because:

- code compiles
- lint passes

Validation must match contract requirements.

---

## QA Runner Integration

If a Validation Contract defines automated checks:

- use `scripts/qa-runner.php`
- report:
  - PASS
  - FAIL
  - SKIPPED
  - NOT_RUN

QA Runner does not replace manual validation.

---

## Required Validation Baseline

Before declaring completion:

- `php scripts/php-lint.php --all`
- automated checks (if contract defines them)
- manual validation (or explicit statement if not executed)
- runtime validation (if applicable)

If runtime validation was not executed:
→ must be explicitly stated

---

## Documentation Discipline

Documentation must remain:

- consistent
- non-duplicated
- aligned with code

### Minimum Required Updates (when applicable)

- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/TEST_SCENARIOS.md`
- `.vscode/AI_CONTEXT.md`
- `docs/tasks/<task>.md`

### Rules

- Do not rewrite entire documentation unnecessarily.
- Update only what is affected.
- Avoid mixing history with current state.
- Avoid duplicating information across files.

---

## What Is Forbidden

- Implementing without reading required context
- Ignoring Task Contracts
- Ignoring Validation Contracts
- Modifying schema without scope
- Introducing new architecture patterns
- Duplicating logic across modules
- Using legacy modules as active code
- Bypassing service/repository layers
- Closing phases without validation

---

## Entry Reminder

The official entrypoint is:

`AGENTS_BOOTSTRAP.md`

No work should begin before completing the required reading sequence.