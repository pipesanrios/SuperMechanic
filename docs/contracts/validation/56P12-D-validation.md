QA_CONTRACT_START
{
  "validation_contract_id": "56P12-D-validation",
  "phase": "56P12-D",
  "type": "validation",
  "automated_checks": [
    {
      "id": "vehicles_folder_exists",
      "type": "file_exists",
      "target": "includes/vehicles"
    },
    {
      "id": "catalog_service_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-catalog-service.php"
    },
    {
      "id": "catalog_repository_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-catalog-repository.php"
    },
    {
      "id": "catalog_import_service_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-catalog-import-service.php"
    },
    {
      "id": "catalog_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-vehicle-catalog-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "valid_csv_dry_run_works",
      "description": "A valid CSV can be parsed and previewed without importing",
      "status": "NOT_RUN"
    },
    {
      "id": "invalid_csv_reports_errors",
      "description": "An invalid CSV reports validation errors clearly",
      "status": "NOT_RUN"
    },
    {
      "id": "csv_import_creates_catalog_records",
      "description": "CSV import creates vehicle catalog records",
      "status": "NOT_RUN"
    },
    {
      "id": "business_scope_respected",
      "description": "Imported records are scoped to the selected business",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "CSV dry-run works",
    "CSV validation works",
    "CSV import creates catalog records",
    "business scope respected"
  ]
}
QA_CONTRACT_END
