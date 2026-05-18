# 56P12-C - Vehicle Creation From Catalog

Task Contract: `docs/contracts/56P12-C.md`
Validation Contract: `docs/contracts/validation/56P12-C-validation.md`

## Objective

Allow admin users to accelerate customer vehicle create/edit flows by selecting an active reusable vehicle catalog record.

## Scope Applied

- Vehicle create/edit admin UI now includes an optional catalog selector.
- Selector options are loaded through `Vehicle_Catalog_Service::list_catalog_vehicles(...)`.
- Catalog options are limited to active records in the current business context.
- Selecting a catalog record fills compatible persisted vehicle fields:
  - brand/make
  - model
  - year
- Catalog details that are not present in the vehicle schema are shown as contextual preview:
  - trim/version
  - body type
  - fuel type
  - transmission
  - engine
- Manual vehicle-specific fields remain editable:
  - client
  - VIN
  - plate
  - color
  - mileage
  - notes

## Implementation Notes

- No CSV import was added.
- No external connector was added.
- No CRM, users/roles, process/payment, API or frontend portal files were changed.
- No SQL was added to the admin controller.
- No schema change was introduced.
- Catalog reference is not persisted because the current `sm_vehicles` schema has no safe `catalog_vehicle_id` or equivalent column.

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 287
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P12-C-validation.md --output=text` -> PASS automated checks
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks

## Manual/Runtime Checklist

- Admin vehicle create form loads selector -> NOT_RUN
- Admin vehicle edit form loads selector -> NOT_RUN
- Selecting catalog record fills brand/model/year -> NOT_RUN
- User can override filled values before saving -> NOT_RUN
- VIN/plate/color/mileage/client remain manual -> NOT_RUN
- Business-scoped catalog options verified in browser -> NOT_RUN

## Deferred

- Persisting `catalog_vehicle_id` requires an explicit scoped schema phase.
- Persisting trim/body/fuel/transmission/engine on customer vehicles requires explicit vehicle schema support.
- Browser-admin runtime validation remains required for full closure.

## Status

PARCIAL
