# 56P12-B - Vehicle Catalog Admin UI

## Objective
Create the admin UI for managing business-scoped vehicle catalog records.

## Implemented
- Vehicle Catalog submenu under Super Mechanic.
- Table-style catalog list with:
  - ID
  - Business
  - Make
  - Model
  - Year
  - Trim/Version
  - Body Type
  - Fuel
  - Transmission
  - Status
  - Actions
- Create/edit form for:
  - business_id
  - make
  - model
  - year
  - trim_version
  - body_type
  - fuel_type
  - transmission
  - engine
  - notes
  - status
- Deactivate action.
- Catalog count method exposed through `Vehicle_Catalog_Service`.

## Files
- `includes/admin/class-vehicle-catalog-admin-controller.php`
- `includes/vehicles/class-vehicle-catalog-service.php`
- `includes/class-admin-menu.php`

## Scope Safeguards
- no CSV import
- no catalog-to-customer-vehicle creation
- no schema changes
- no REST/API changes
- no CRM/users/process/payment/frontend changes
- admin controller uses `Vehicle_Catalog_Service` for catalog persistence
- no SQL added outside repository/database layers
- business options and requested business scope are filtered by current user business access

## Validation
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P12-B-validation.md --output=text` -> PASS automated checks
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3 manual checks

## Runtime / Manual
- WordPress admin browser validation was not executed in this session.
- WP-CLI runtime validation was attempted but `wp` is not available in the current shell.

Required runtime real steps:
1. Open WordPress admin.
2. Go to `Super Mechanic -> Vehicle Catalog`.
3. Confirm the page loads without fatal errors.
4. Select a business and create a catalog record with make/model/year.
5. Edit the same record and change trim/version or status.
6. Deactivate the record from the list.
7. Confirm the row remains scoped to the selected business and no records from another business appear.

## Deferred
- CSV import
- selecting a catalog record during customer vehicle creation
- duplicate catalog detection
- external inventory connector

## Status
PARCIAL
