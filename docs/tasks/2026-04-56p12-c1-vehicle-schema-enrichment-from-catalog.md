# 56P12-C1 - Vehicle Schema Enrichment From Catalog

Task Contract: `docs/contracts/56P12-C1.md`
Validation Contract: `docs/contracts/validation/56P12-C1-validation.md`

## Objective

Persist catalog-derived technical vehicle fields in customer vehicles.

## Scope Applied

- `sm_vehicles` schema enriched with nullable fields:
  - `catalog_vehicle_id`
  - `trim_version`
  - `body_type`
  - `fuel_type`
  - `transmission`
  - `engine`
- schema version updated to `1.21.0`
- `Vehicle_Repository` now uses field-aware write formats for create/update payloads
- `Vehicle_Service` normalizes and validates enriched fields
- selected `catalog_vehicle_id` is accepted only when `Vehicle_Catalog_Service` can resolve it for the current vehicle business
- vehicle admin create/edit form exposes technical fields as editable inputs
- catalog selection fills:
  - brand/make
  - model
  - year
  - trim/version
  - body type
  - fuel
  - transmission
  - engine
- vehicle detail view displays persisted technical fields

## Scope Safeguards

- no CSV import added
- no external inventory connector added
- no CRM/users/process/payment/API/frontend portal changes
- no SQL added to admin controller or service
- existing vehicle create/edit flow remains backward compatible
- existing vehicles receive nullable empty values until edited or future backfill

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 287
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P12-C1-validation.md --output=text` -> PASS automated checks
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks

## Manual/Runtime Checklist

- run plugin schema upgrade/dbDelta in WordPress runtime -> NOT_RUN
- confirm `sm_vehicles` has new nullable columns -> NOT_RUN
- open vehicle create/edit admin UI -> NOT_RUN
- select active catalog record and confirm technical fields fill -> NOT_RUN
- manually override technical field before save -> NOT_RUN
- save vehicle and confirm technical fields persist -> NOT_RUN
- confirm invalid/cross-business catalog ID is rejected -> NOT_RUN

## Deferred

- runtime browser-admin validation
- automatic backfill for historical vehicles
- catalog-to-vehicle duplicate detection/matching

## Status

PARCIAL
