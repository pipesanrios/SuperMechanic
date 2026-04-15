QA_CONTRACT_START
{
  "validation_contract_id": "56P3-C-validation",
  "phase": "56P3-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "integrity_service_exists",
      "type": "file_exists",
      "target": "includes/helpers/class-data-integrity-validation-service.php"
    },
    {
      "id": "integrity_repository_exists",
      "type": "file_exists",
      "target": "includes/database/class-data-integrity-validation-repository.php"
    }
  ],
  "manual_checks": [
    {
      "id": "orphan_detection_coherent",
      "description": "Integrity report flags orphaned records coherently after reset scenarios",
      "status": "NOT_RUN"
    },
    {
      "id": "consistency_output_coherent",
      "description": "Integrity report exposes consistent relation-state checks for core entities",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after integrity validation layer addition",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "integrity validation service exists",
    "orphan and consistency detection report is available",
    "admin stable"
  ]
}
QA_CONTRACT_END

