# Validation Contract Examples

This folder stores optional examples for QA Runner-compatible validation contracts.

## Recommended naming
- `<phase>-validation-example.md`

## Minimal runner-compatible format
Use marker-delimited JSON payload:

```text
QA_CONTRACT_START
{
  "phase": "39E-4",
  "validation_contract_id": "39E-4-validation",
  "automated_checks": [],
  "manual_checks": []
}
QA_CONTRACT_END
```

## Notes
- Keep checks deterministic.
- Leave runtime UI/E2E checks under `manual_checks`.
