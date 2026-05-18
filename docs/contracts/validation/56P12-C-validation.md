QA_CONTRACT_START
{
  "validation_contract_id": "56P12-C-validation",
  "phase": "56P12-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "vehicle_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-admin-controller.php"
    },
    {
      "id": "vehicle_catalog_service_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-catalog-service.php"
    },
    {
      "id": "vehicle_catalog_js_exists",
      "type": "file_exists",
      "target": "assets/js/vehicle-catalog.js"
    }
  ],
  "manual_checks": [
    {
      "id": "vehicle_create_catalog_selector_loads",
      "description": "Vehicle create/edit admin UI renders the optional catalog selector",
      "status": "NOT_RUN"
    },
    {
      "id": "catalog_selection_prefills_fields",
      "description": "Selecting a catalog record auto-fills compatible vehicle fields",
      "status": "NOT_RUN"
    },
    {
      "id": "manual_override_preserved",
      "description": "User can manually edit auto-filled fields before saving",
      "status": "NOT_RUN"
    },
    {
      "id": "business_scope_preserved",
      "description": "Catalog selector does not expose records outside the selected business scope",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "vehicle admin UI exposes optional catalog selector",
    "catalog options are active and business-scoped",
    "catalog selection auto-fills compatible fields",
    "manual vehicle-specific fields remain manual",
    "manual override remains possible",
    "no CSV import, external connector, CRM/reset/API changes"
  ]
}
QA_CONTRACT_END
