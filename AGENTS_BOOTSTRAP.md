# AGENTS_BOOTSTRAP.md

# Super Mechanic — Official AI Entry Point

This is the **only official START HERE file** for any AI agent working on this repository.

No code, schema, wiring, or structural changes are allowed before completing the required reading sequence.

If this reading order is skipped, the agent is considered **out of context** and must **not** modify the project.

---

## Purpose Of This File

This file defines:

- the official entrypoint for any AI session
- the mandatory reading order
- the document hierarchy
- the source of truth by topic
- the document conflict resolution rule
- the minimum execution protocol before implementation

This file does **not** replace the rest of the documentation.  
It only defines **how to enter the system correctly**.

---

## Repository Knowledge Hierarchy

The repository is governed by the following hierarchy:

1. **Level 1 — Entrypoint**
   - `AGENTS_BOOTSTRAP.md`

2. **Level 2 — Core Rules**
   - `AGENTS.md`

3. **Level 3 — AI Execution Context**
   - `.vscode/AI_CONTEXT.md`
   - `ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt`

4. **Level 4 — Rules System**
   - `ai/rules/*`

5. **Level 5 — Context Support**
   - `ai/context/*`

6. **Level 6 — System Documentation**
   - `docs/*`
   - `ARCHITECTURE.md`

---

## Required Reading Order (Mandatory)

The following reading order is mandatory for every new AI session:

1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`
4. Core rules:
   - `ai/rules/AI_RULES.md`
   - `ai/rules/GUARDRAILS.md`
   - `ai/rules/MODULE_BOUNDARIES.md`
5. Context support:
   - `ai/context/AGENTS_QUICK_CONTEXT.md`
   - `ai/context/PROJECT_MEMORY.md`
   - `ai/context/WORKFLOW.md`
6. `docs/CURRENT_STATE.md`
7. `docs/PROJECT_TRANSFER_CONTEXT.md`
8. `docs/PLUGIN_ROADMAP.md`
9. `ARCHITECTURE.md`
10. Supporting technical docs as needed:
    - `docs/DATABASE_MAP.md`
    - `docs/MODULE_REGISTRY.md`
    - `docs/SYSTEM_MAP.md`
    - `docs/TEST_SCENARIOS.md`
    - `docs/KNOWN_TRAPS.md`

Do **not** skip layers.  
Do **not** jump directly into implementation.

---

## Document Classes

To reduce ambiguity, documents are grouped as follows:

### Core Documents (must be read first)
- `AGENTS_BOOTSTRAP.md`
- `AGENTS.md`
- `.vscode/AI_CONTEXT.md`
- `docs/CURRENT_STATE.md`
- `docs/PROJECT_TRANSFER_CONTEXT.md`

### Technical Core
- `ARCHITECTURE.md`
- `docs/DATABASE_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/SYSTEM_MAP.md`

### Operational Support
- `docs/PLUGIN_ROADMAP.md`
- `docs/TEST_SCENARIOS.md`
- `docs/KNOWN_TRAPS.md`

### AI Support
- `ai/rules/*`
- `ai/context/*`
- `ai/prompts/*`

### Reference / Historical
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/PRE_API_BASELINE.md`
- `docs/QA_REPORT.md`

Reference/historical documents are **not** the primary source of truth unless explicitly required.

---

## Source Of Truth By Topic

| Topic | Source |
|---|---|
| Current state | `docs/CURRENT_STATE.md` |
| Architecture | `ARCHITECTURE.md` |
| Database | `docs/DATABASE_MAP.md` |
| Module inventory | `docs/MODULE_REGISTRY.md` |
| Functional system map | `docs/SYSTEM_MAP.md` |
| Continuity / phase progression | `docs/PLUGIN_ROADMAP.md` |
| AI hard rules | `AGENTS.md` |
| Entry point | `AGENTS_BOOTSTRAP.md` |
| Handoff context | `docs/PROJECT_TRANSFER_CONTEXT.md` |
| Known risks / traps | `docs/KNOWN_TRAPS.md` |

---

## Document Priority Rule

If documents conflict, use this order:

1. **Code**
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Remaining active docs
7. Historical/reference docs

If code and docs differ, **code wins**.  
Docs must then be updated accordingly.

---

## Session Start Protocol

Before any implementation:

1. Complete the mandatory reading order.
2. Identify the current phase and next continuity from the roadmap.
3. Verify current system state in `CURRENT_STATE.md`.
4. Check risks in `KNOWN_TRAPS.md` if the task touches sensitive areas.
5. Run analysis first.
6. Only then decide whether implementation is allowed.

No AI should begin with code changes before this protocol is complete.

---

## Task Execution Model

This repository uses a contract-driven execution model.

### Rule
Non-trivial tasks require a **Task Contract** before execution.

### Minimum Contract Requirements
A valid Task Contract must define:

- `scope`
- `acceptance_criteria`
- `validations_required`
- `docs_to_update`
- `technical_debt`

### Enforcement
- The contract must be read before coding.
- The contract defines scope boundaries.
- The contract defines file boundaries.
- If no contract exists, create one first.
- If the requested work exceeds the contract, stop and report it.

---

## Validation Model

This repository also uses **Validation Contracts** for non-trivial work.

Validation must distinguish between:

- automated checks
- manual checks
- runtime checks
- regression checks

A phase is not complete just because code compiles.  
Validation must match the contract.

---

## What This File Does Not Allow

This file does **not** authorize:

- direct coding without context
- schema changes without explicit scope
- architecture changes without reading source docs
- global documentation rewrites without a dedicated audit task
- phase closure without contract + validation

---

## Final Rule

If you did **not** complete the required reading order, you are **not authorized** to modify code, schema, wiring, prompts, rules, or structural documentation.