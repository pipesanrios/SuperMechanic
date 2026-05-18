# 56P12-D — Inventory Import Base

Fecha de ejecucion: 2026-05-19

## Contract
- Task contract: `docs/contracts/56P12-D.md`
- Validation contract: `docs/contracts/validation/56P12-D-validation.md`

## Scope
- Admin-only CSV import foundation for reusable vehicle catalog records.
- Import target: `sm_vehicle_catalog`.
- Persistence path: `Vehicle_Catalog_Import_Service` -> `Vehicle_Catalog_Service` -> repository.

## Implemented
- Added `includes/vehicles/class-vehicle-catalog-import-service.php`.
- Added CSV parser and validation methods:
  - `parse_csv_file(...)`
  - `validate_rows(...)`
  - `dry_run(...)`
  - `import_rows(...)`
- Added Vehicle Catalog admin import section:
  - CSV upload
  - dry-run preview
  - confirm import of valid rows
- Dry-run report includes:
  - total rows
  - valid rows
  - invalid rows
  - header errors
  - row errors
  - preview rows
- Confirmed import creates valid catalog records via `Vehicle_Catalog_Service::create_catalog_vehicle(...)`.

## CSV Format
Required columns:
- `make`
- `model`
- `year`

Optional columns:
- `trim_version`
- `body_type`
- `fuel_type`
- `transmission`
- `engine`
- `notes`
- `status`

## Security
- Existing catalog page capability guard applies: `sm_manage_vehicles`.
- Dry-run nonce: `sm_vehicle_catalog_import`.
- Confirm nonce: `sm_vehicle_catalog_import_confirm`.
- Uploaded filename must use `.csv`.
- Business ID is checked against current user business access before dry-run/import.
- Output is escaped in the admin UI.

## Validation
- `php scripts/php-lint.php --all` -> PASS
  - files checked: 288
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P12-D-validation.md --output=text` -> PASS automated checks
  - PASS: 5
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

## Runtime Manual Checklist
- Upload a valid CSV in Vehicle Catalog admin and run dry-run.
- Confirm dry-run displays total/valid/invalid rows and preview.
- Upload invalid CSV and confirm validation errors are visible.
- Confirm import and verify catalog records are created for the selected business.
- Verify no customer vehicles are created from CSV import.

## Deferred
- Browser-admin runtime validation.
- Duplicate detection beyond current service validation.
- Large-file/background import.
- External inventory connector and scheduled sync.

## Final Status
- PARCIAL until runtime real admin CSV dry-run/import is validated.
