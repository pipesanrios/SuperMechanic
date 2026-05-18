# 56P12-A - Vehicle Catalog Schema/Service

Date: 2026-05-06
Status: COMPLETA

## Scope

Created reusable vehicle catalog foundation for business-scoped catalog records.

Implemented:
- vehicle catalog schema/table
- `Vehicle_Catalog_Repository`
- `Vehicle_Catalog_Service`
- CRUD/deactivation foundation methods
- business-scoped reads and writes

Out of scope respected:
- no admin UI
- no CSV import
- no external inventory connector
- no vehicle creation UI integration
- no VIN decoding
- no API changes
- no process/payment/CRM/users changes

## Schema/Table Result

Schema version:
- `1.20.0`

Table:
- `sm_vehicle_catalog`

Runtime table:
- `wp_sm_vehicle_catalog`

Fields:
- `id`
- `business_id`
- `make`
- `model`
- `year`
- `trim_version`
- `body_type`
- `fuel_type`
- `transmission`
- `engine`
- `notes`
- `status`
- `created_at`
- `updated_at`

Indexes:
- primary key `id`
- `business_id`
- `make_model`
- `year`
- `status`
- `business_status`

Runtime table check:
- `catalog_table_exists` -> PASS

## Repository/Service Foundation

Created:
- `includes/vehicles/class-vehicle-catalog-repository.php`
- `includes/vehicles/class-vehicle-catalog-service.php`

Service methods:
- `create_catalog_vehicle(...)`
- `update_catalog_vehicle(...)`
- `get_catalog_vehicle(...)`
- `list_catalog_vehicles(...)`
- `deactivate_catalog_vehicle(...)`

Repository behavior:
- SQL remains only in repository/database layer
- insert/update/get/list/count/deactivate are business-scoped
- explicit invalid business scope does not fall back to active business

Service behavior:
- normalizes allowed fields
- validates required `business_id`, `make`, `model`
- validates year range and status
- supports `trim` input alias into `trim_version`
- returns controlled `WP_Error` values for invalid payloads or missing catalog records

## Runtime/Manual Validation

Migration/schema:
- `Installer::install()` applied schema
- `wp_sm_vehicle_catalog` exists -> PASS

CRUD:
- create catalog vehicle -> PASS
- get catalog vehicle -> PASS
- update catalog vehicle -> PASS
- list catalog vehicles -> PASS
- deactivate catalog vehicle -> PASS
- status after deactivate -> `inactive`

Business scope:
- read with correct `business_id=1` -> PASS
- read with invalid explicit `business_id=999999` -> PASS, no record returned
- create with invalid explicit `business_id=999999` -> PASS controlled `business_required`

Note:
- runtime QA created two inactive catalog records with `QA Make 56P12A` names while validating service behavior.

## Automated Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 286
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P12-A-validation.md --output=text` -> PASS automated checks
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3 manual checks tracked above

## Risks / Deferred Areas

- no admin UI yet
- no CSV/import tooling yet
- no integration with customer vehicle creation yet
- no duplicate catalog detection yet
- no catalog-to-vehicle materialization flow yet
- inactive QA validation records remain in the catalog table

## Final Status

COMPLETA
