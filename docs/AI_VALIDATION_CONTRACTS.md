# AI_VALIDATION_CONTRACTS.md

## What is a Validation Contract
A Validation Contract is a phase-specific verification agreement that defines how completed work must be validated with explicit and reproducible checks.

## Difference vs Task Contract
- Task Contract: defines scope, files, constraints, and expected implementation outputs.
- Validation Contract: defines verification rules and measurable completion evidence.

## When it is required
Required for all non-trivial phases/tasks.

## Relationship with existing governance
- Task Contracts provide execution boundaries.
- Validation Contracts provide verification boundaries.
- Phase Closure Prompt consumes validation outcomes before closure.
- `docs/QA_REPORT.md` stores per-phase validation traceability.

## Mandatory Validation Contract Fields
Runner-compatible validation contracts must include:
- `phase`
- `validation_contract_id`
- `automated_checks`
- `manual_checks`

## Validation sections (logical)
A Validation Contract should cover:
1. Functional validations
2. Technical validations
3. Runtime validations
4. Regression checks
5. Security checks (if applicable)
6. Performance checks (if applicable)
7. Edge cases (if applicable)
8. Acceptance criteria mapping

## Automated vs manual
- `automated_checks`: deterministic, low-risk, repeatable technical checks.
- `manual_checks`: runtime/browser/UX checks that are not safe to auto-assert.

## Linking rule
- Validation contracts should be linked from Task Contracts:
  - `validation_contract: docs/contracts/validation/<phase>-validation.md`

## QA Runner compatibility
Validation contracts consumed by the QA Runner must expose JSON payload between markers:

```text
QA_CONTRACT_START
{ ...json... }
QA_CONTRACT_END
```

## Enforcement
- Completion cannot be marked as complete when required validation fails.
- If a check cannot be verified reliably, use `SKIPPED` or `NOT_RUN`.
- Never fabricate `PASS`.
- PASS tecnico no implica fase completa.
