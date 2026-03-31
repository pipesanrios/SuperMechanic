# Contracts Directory

This directory stores task contracts for non-trivial execution units.

## Location rule
- All task contracts must live under `docs/contracts/`.

## Naming convention
- `phase-subphase.md`

## Examples
- `39E-2.md`
- `40B-1.md`

## Usage rule
- Contract must exist and be read before implementation starts.

## Validation contracts
- Validation contracts live in `docs/contracts/validation/`.
- Naming convention for validation contracts:
  - `<phase>-validation.md`
- A task contract can reference a validation contract through:
  - `validation_contract: docs/contracts/validation/<phase>-validation.md`
