QA_CONTRACT_START
{
  "validation_contract_id": "56P8-A-validation",
  "phase": "56P8-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "email_trigger_service_exists",
      "type": "file_exists",
      "target": "includes/services/class-email-trigger-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "process_status_trigger",
      "description": "Email trigger fires on process status change",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_status_trigger",
      "description": "Email trigger fires on quote approval/rejection",
      "status": "NOT_RUN"
    },
    {
      "id": "invoice_status_trigger",
      "description": "Email trigger fires on invoice payment status change",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "events trigger notification system",
    "payload structured correctly",
    "no business logic duplication"
  ]
}
QA_CONTRACT_END