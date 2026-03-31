# DOCUMENTATION_RULES.md

Purpose:
Define how documentation is authored, updated, and normalized in Super Mechanic.

## Golden Rules
1. Code is the final authority when conflict exists.
2. One document, one purpose. Do not mix scopes.
3. Keep state, architecture, roadmap, and historical memory separated.
4. Prefer links/references over duplicated explanations.

## Scope Ownership
- `docs/CURRENT_STATE.md` -> current state only
- `ARCHITECTURE.md` -> runtime architecture only
- `docs/PLUGIN_ROADMAP.md` -> future continuity only
- `docs/DATABASE_MAP.md` -> database only
- `docs/MODULE_REGISTRY.md` -> module inventory only
- `docs/SYSTEM_MAP.md` -> logical mapping only
- `docs/FINAL_ARCHITECTURE_MAP.md` -> historical reference only

## Update Protocol
When work is closed:
1. Validate technical baseline.
2. Update only impacted docs.
3. Remove duplicated text instead of copying across files.
4. Keep wording operational and verifiable.

## Multi-AI Safety
- Entrypoint is always `AGENTS_BOOTSTRAP.md`.
- Every AI session must follow required reading order.
- Context/support files must never override core docs.

## Task Contract Requirements
- Every non-trivial phase/subphase task must have a Task Contract.
- Contracts must be version-aligned with the active baseline.
- Contracts must reflect `docs/CURRENT_STATE.md`.
- Contract files should live under `docs/contracts/`.

## Validation Contract Requirements
- Every non-trivial phase/subphase should have a Validation Contract.
- Validation contract must align with the linked Task Contract.
- Validation must be reproducible and based on explicit expected results.
- Validation contracts should live under `docs/contracts/validation/`.

## QA Runner Rules
- QA Runner executes only deterministic technical checks.
- QA Runner output does not replace manual runtime validation.
- PASS tecnico no implica fase completa.
- If a check is not reliably verifiable, use `SKIPPED` or `NOT_RUN`.
