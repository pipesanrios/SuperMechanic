QA_CONTRACT_START
{
  "validation_contract_id": "56P9-C-validation",
  "phase": "56P9-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "sync_service_exists",
      "type": "file_exists",
      "target": "includes/services/class-google-calendar-sync-service.php"
    },
    {
      "id": "integration_service_exists",
      "type": "file_exists",
      "target": "includes/integrations/google-calendar"
    }
  ],
  "manual_checks": [
    {
      "id": "no_duplicate_logic",
      "description": "No duplicated payload-building logic between services and integrations",
      "status": "NOT_RUN"
    },
    {
      "id": "sync_service_is_canonical",
      "description": "Sync service is the only place building calendar payloads",
      "status": "NOT_RUN"
    },
    {
      "id": "integration_is_client_only",
      "description": "Integration layer only acts as external API client (no business logic)",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "single source of truth for sync logic",
    "integration layer clean",
    "ready for OAuth phase"
  ]
}
QA_CONTRACT_END