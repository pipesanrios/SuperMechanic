# Validation Contracts Directory

This directory stores validation contracts linked to task contracts.

## Naming convention
- `<phase>-validation.md`
- Examples:
  - `39E-4-validation.md`
  - `40B-1-validation.md`

## Relationship with task contracts
- A task contract may include:
  - `validation_contract: docs/contracts/validation/<phase>-validation.md`
- Validation contract and task contract must refer to the same phase/subphase.

## Required fields
Runner-compatible contracts must include:
- `phase`
- `validation_contract_id`
- `automated_checks`
- `manual_checks`

## Runner payload format
Include JSON payload between markers:

```text
QA_CONTRACT_START
{ ...json... }
QA_CONTRACT_END
```

## Version alignment rules
- Validation contract must match:
  - current task scope
  - current acceptance criteria
  - current baseline in `docs/CURRENT_STATE.md`
