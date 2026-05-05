QA_CONTRACT_START
{
  "validation_contract_id": "56P9-B-validation",
  "phase": "56P9-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "calendar_sync_service_exists",
      "type": "file_exists",
      "target": "includes/services/class-google-calendar-sync-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "appointment_payload_builds",
      "description": "Appointment data builds a normalized Google Calendar-ready payload",
      "status": "NOT_RUN"
    },
    {
      "id": "process_payload_builds",
      "description": "Process data builds a normalized Google Calendar-ready payload",
      "status": "NOT_RUN"
    },
    {
      "id": "missing_fields_detected",
      "description": "Missing required calendar payload fields are detected safely",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "sync service exists",
    "calendar payloads build correctly",
    "no Google API calls performed"
  ]
}
QA_CONTRACT_END