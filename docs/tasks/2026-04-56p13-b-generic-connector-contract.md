# 56P13-B — Generic Connector Contract

## Objective

Define the generic technical contract every future inbound inventory connector must follow.

## Contract

- Task contract: `docs/contracts/56P13-B.md`
- Validation contract: `docs/contracts/validation/56P13-B-validation.md`

## Scope

Documentation-only phase.

Included:
- generic connector identity
- adapter responsibility contract
- normalized inventory payload
- validation rules
- sync operation vocabulary
- standard error model
- logging expectations
- conflict handling
- security requirements
- future phase alignment

Excluded:
- code implementation
- schema changes
- runtime connector
- provider adapter
- scheduled sync
- connector admin UI
- changes under `includes/*`
- changes under `assets/*`

## Files Created

- `docs/INVENTORY_CONNECTOR_CONTRACT.md`
- `docs/tasks/2026-04-56p13-b-generic-connector-contract.md`

## Files Updated

- `docs/contracts/56P13-B.md`
- `docs/contracts/validation/56P13-B-validation.md`
- `docs/CURRENT_STATE.md`
- `docs/QA_REPORT.md`
- `docs/PLUGIN_ROADMAP.md`

## Summary

Created the canonical generic connector contract for future inventory providers. The contract keeps provider logic isolated in adapters, defines a normalized payload before catalog sync, standardizes sync operation names and error codes, and preserves business-scoped catalog writes through `Vehicle_Catalog_Service`.

## Normalized Payload

Required:
- `external_id`
- `business_id`
- `make`
- `model`
- `year`

Optional:
- `trim_version`
- `body_type`
- `fuel_type`
- `transmission`
- `engine`
- `vin`
- `plate`
- `color`
- `mileage`
- `price`
- `currency`
- `stock_status`
- `media`
- `notes`
- `raw_payload`

## Error Model

Standard errors:
- `invalid_credentials`
- `provider_unavailable`
- `invalid_payload`
- `missing_required_field`
- `duplicate_external_id`
- `business_scope_violation`
- `rate_limited`
- `sync_conflict`

## Validation

- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P13-B-validation.md --output=text` -> PASS automated checks
- Manual/static checks:
  - generic connector contract defined -> PASS
  - normalized payload defined -> PASS
  - error model defined -> PASS
  - architecture alignment confirmed -> PASS
  - no `includes/*` or `assets/*` modified -> PASS

## Runtime

Runtime real is not applicable for this phase because no code, schema, UI, or runtime behavior was implemented.

## Deferred

- runtime connector interfaces
- provider adapters
- connector persistence/schema
- per-business credential storage
- scheduled sync
- webhook sync
- queue workers
- retry strategy
- external media sync
- connector admin UI

## Final Status

COMPLETA
