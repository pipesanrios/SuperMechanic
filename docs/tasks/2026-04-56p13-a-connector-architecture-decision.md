# 56P13-A — Connector Architecture Decision

Fecha de ejecucion: 2026-05-19

## Contract
- Task contract: `docs/contracts/56P13-A.md`
- Validation contract: `docs/contracts/validation/56P13-A-validation.md`

## Scope
- Architecture-only decision for future external inventory connectors.
- Documentation only.
- No runtime implementation.

## Created
- `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`

## Updated
- `docs/contracts/56P13-A.md`
- `docs/contracts/validation/56P13-A-validation.md`
- `docs/CURRENT_STATE.md`
- `docs/QA_REPORT.md`
- `docs/PLUGIN_ROADMAP.md`

## Architecture Summary
- Inventory connectors must be inbound, business-scoped and provider-agnostic.
- Provider-specific behavior belongs in adapters and mappers, not in vehicle catalog core logic.
- The canonical internal target remains `Vehicle_Catalog_Service` and the reusable vehicle catalog model.

## Recommended Layers
- Connector Controller
- Connector Service
- Provider Adapter
- Sync Mapper
- Sync Repository

## Canonical Flow
External provider
-> provider adapter
-> raw provider records
-> sync mapper
-> normalized catalog payload
-> sync validation
-> catalog sync service
-> `Vehicle_Catalog_Service`
-> vehicle catalog

## Provider Abstraction
Future providers are adapters:
- `mobile_de`
- `autoscout24`
- `dealercenter`
- `generic_csv_api`

## Sync Lifecycle
- initial import
- update sync
- stale inventory deactivation
- conflict handling
- manual override ownership

## Multi-Business Strategy
- connector config belongs to one `business_id`
- credentials/config must be per business
- external IDs are scoped by business/provider/connector
- sync execution must operate in one explicit business context

## Deferred
- connector implementation
- connector persistence/schema
- OAuth
- scheduled sync
- webhook sync
- queue workers
- retry strategy
- external media sync

## Validation
- `php scripts/php-lint.php --all` -> PASS
  - files checked: 288
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P13-A-validation.md --output=text` -> PASS automated checks
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4
- Static/manual:
  - no `includes/*` modified by this phase
  - no `assets/*` modified by this phase
  - roadmap/state consistency preserved

## Final Status
- COMPLETA
