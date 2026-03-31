# AGENTS_BOOTSTRAP.md
Super Mechanic - ONLY ENTRYPOINT FOR ANY AI AGENT

This is the official START HERE file.
No code, schema, or wiring changes are allowed before completing the required reading.

If this order is not followed, the system will break.
Do not skip any layer.

## System Hierarchy
1. Level 1 (Entrypoint): `AGENTS_BOOTSTRAP.md`
2. Level 2 (Core rules): `AGENTS.md`
3. Level 3 (AI execution context): `.vscode/AI_CONTEXT.md` + Prompt Master
4. Level 4 (Rules system): `ai/rules/*`
5. Level 5 (Context support): `ai/context/*`
6. Level 6 (System documentation): `docs/*`

## Required Reading Order (Mandatory)
1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`
4. Rules:
   - `ai/rules/AI_RULES.md`
   - `ai/rules/GUARDRAILS.md`
   - `ai/rules/MODULE_BOUNDARIES.md`
5. Context:
   - `ai/context/AGENTS_QUICK_CONTEXT.md`
   - `ai/context/PROJECT_MEMORY.md`
   - `ai/context/WORKFLOW.md`
6. `docs/CURRENT_STATE.md`
7. `docs/PROJECT_TRANSFER_CONTEXT.md`
8. `docs/PLUGIN_ROADMAP.md`
9. `ARCHITECTURE.md`

## Source Of Truth By Topic
| Topic | Source |
|---|---|
| Current state | `docs/CURRENT_STATE.md` |
| Architecture | `ARCHITECTURE.md` |
| Database | `docs/DATABASE_MAP.md` |
| AI hard rules | `AGENTS.md` |
| Entry point | `AGENTS_BOOTSTRAP.md` |
| Continuity | `docs/PLUGIN_ROADMAP.md` |
| Handoff context | `docs/PROJECT_TRANSFER_CONTEXT.md` |

## Document Priority Rule
If documents conflict, use this order:
1. Code
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Remaining docs

## Session Start Protocol
1. Complete required reading order.
2. Confirm phase target from roadmap.
3. Verify current state and risks.
4. Run analysis phase before any implementation.

## Task Execution Model
- Non-trivial tasks require a Task Contract before execution.
- The contract must be read before coding.
- The contract defines scope boundaries and file boundaries.
- If no contract exists, create one first.

## Final Rule
If you did not complete the required reading order, you are not authorized to modify code.
