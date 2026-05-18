QA_CONTRACT_START
{
  "validation_contract_id": "56P12-C1-validation",
  "phase": "56P12-C1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "schema_file_exists",
      "type": "file_exists",
      "target": "includes/database/class-schema.php"
    },
    {
      "id": "vehicle_repository_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-repository.php"
    },
    {
      "id": "vehicle_service_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-service.php"
    },
    {
      "id": "vehicle_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/vehicles/class-vehicle-admin-controller.php"
    },
    {
      "id": "vehicle_catalog_js_exists",
      "type": "file_exists",
      "target": "assets/js/vehicle-catalog.js"
    }
  ],
  "manual_checks": [
    {
      "id": "vehicle_schema_columns_exist",
      "description": "sm_vehicles contains nullable catalog_vehicle_id, trim_version, body_type, fuel_type, transmission and engine columns",
      "status": "NOT_RUN"
    },
    {
      "id": "catalog_selection_prefills_technical_fields",
      "description": "Selecting catalog record fills persisted technical fields in admin vehicle create/edit",
      "status": "NOT_RUN"
    },
    {
      "id": "technical_fields_persist",
      "description": "Saving vehicle persists catalog_vehicle_id and technical fields",
      "status": "NOT_RUN"
    },
    {
      "id": "manual_override_preserved",
      "description": "User can override technical fields after catalog selection before save",
      "status": "NOT_RUN"
    },
    {
      "id": "business_scope_preserved",
      "description": "Cross-business catalog ID is rejected or ignored during save",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "vehicle schema includes nullable catalog technical columns",
    "vehicle repository/service persist and return new fields",
    "vehicle admin create/edit fills and saves new fields from catalog",
    "manual override remains possible",
    "catalog ID business scope is validated",
    "no CSV import, external connector, CRM/reset/API changes"
  ]
}
QA_CONTRACT_END
