QA_CONTRACT_START
{
  "validation_contract_id": "56P9-A-validation",
  "phase": "56P9-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "calendar_service_exists",
      "type": "file_exists",
      "target": "includes/services/class-google-calendar-config-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "config_can_be_saved",
      "description": "Calendar config values can be stored correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "config_validation_works",
      "description": "Validation detects missing or invalid config",
      "status": "NOT_RUN"
    },
    {
      "id": "readiness_status_correct",
      "description": "Readiness method returns correct state based on config completeness",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "config service exists",
    "config stored safely",
    "validation works",
    "ready for OAuth phase"
  ]
}
QA_CONTRACT_END