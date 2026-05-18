QA_CONTRACT_START
{
  "validation_contract_id": "56P12-B-validation",
  "phase": "56P12-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "vehicle_catalog_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-vehicle-catalog-admin-controller.php"
    },
    {
      "id": "vehicle_catalog_service_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-catalog-service.php"
    },
    {
      "id": "admin_menu_exists",
      "type": "file_exists",
      "target": "includes/class-admin-menu.php"
    }
  ],
  "manual_checks": [
    {
      "id": "vehicle_catalog_admin_page_loads",
      "description": "Vehicle Catalog admin page loads under Super Mechanic without fatal errors",
      "status": "NOT_RUN"
    },
    {
      "id": "catalog_ui_crud_works",
      "description": "Create/edit/deactivate actions work through the admin UI",
      "status": "NOT_RUN"
    },
    {
      "id": "business_scope_preserved",
      "description": "Catalog records are listed and mutated only inside the selected business scope",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "Vehicle Catalog admin UI exists",
    "admin UI reuses Vehicle_Catalog_Service",
    "nonce and capability checks protect write actions",
    "CSV import and catalog-to-vehicle creation are not implemented"
  ]
}
QA_CONTRACT_END
