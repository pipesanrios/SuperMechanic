# AI_QA_RUNNER.md

## Purpose
Define the semi-automated QA Runner used to execute deterministic technical checks from Validation Contracts.

## Scope
- Reads Validation Contracts.
- Executes repeatable technical checks.
- Produces structured results.
- Supports phase closure traceability.

## Out of scope
- Does not replace runtime manual validation.
- Does not replace browser E2E validation.
- Does not decide phase completion by itself.

## Critical rule
PASS tecnico no implica fase completa.

## Usage
- `php scripts/qa-runner.php --contract=docs/contracts/validation/<phase>-validation.md`
- `php scripts/qa-runner.php --contract=... --output=text`
- `php scripts/qa-runner.php --contract=... --output=json`
- `php scripts/qa-runner.php --contract=... --output=markdown`

## Supported outputs
- `text` (default): console-readable summary
- `json`: structured machine-readable output
- `markdown`: portable report summary

## Supported automated checks (v1)
- `php_lint`
- `file_exists`
- `class_exists`
- `method_exists`
- `doc_exists`
- `hook_registered` (static detection on expected file)

## Determinism and reliability
- Only low-risk deterministic checks are automated.
- If a check cannot be verified reliably, status must be `SKIPPED` or `NOT_RUN`.
- The runner must never fabricate `PASS`.

## Validation Contract expected structure
Validation contracts consumed by runner must include:
- `phase`
- `validation_contract_id`
- `automated_checks`
- `manual_checks`

The runner expects a JSON payload inside markers:

```text
QA_CONTRACT_START
{ ...json... }
QA_CONTRACT_END
```

## Malformed contract behavior
If required fields are missing or malformed:
- Runner exits with non-zero code
- Prints clear error message

## Recommended integration
- Task Contract links Validation Contract path.
- QA Runner output is referenced in `docs/QA_REPORT.md`.
- Phase closure prompt consumes runner result plus manual runtime validation.
