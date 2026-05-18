QA_CONTRACT_START
{
  "validation_contract_id": "56P12-A-validation",
  "phase": "56P12-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "vehicles_folder_exists",
      "type": "file_exists",
      "target": "includes/vehicles"
    },
    {
      "id": "database_folder_exists",
      "type": "file_exists",
      "target": "includes/database"
    }
  ],
  "manual_checks": [
    {
      "id": "catalog_table_exists",
      "description": "Vehicle catalog table exists after activation/schema update",
      "status": "NOT_RUN"
    },
    {
      "id": "catalog_service_crud_works",
      "description": "Vehicle catalog service can create/read/update/list records",
      "status": "NOT_RUN"
    },
    {
      "id": "business_scope_respected",
      "description": "Vehicle catalog records are scoped by business",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "vehicle catalog foundation exists",
    "repository/service layer exists",
    "business scope respected"
  ]
}
QA_CONTRACT_END