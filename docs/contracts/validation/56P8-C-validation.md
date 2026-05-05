QA_CONTRACT_START
{
  "validation_contract_id": "56P8-C-validation",
  "phase": "56P8-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "services_folder_exists",
      "type": "file_exists",
      "target": "includes/services"
    }
  ],
  "manual_checks": [
    {
      "id": "process_email_sent",
      "description": "Process status change triggers a real email delivery attempt",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_email_sent",
      "description": "Quote approval/rejection triggers a real email delivery attempt",
      "status": "NOT_RUN"
    },
    {
      "id": "invoice_email_sent",
      "description": "Invoice/payment event triggers a real email delivery attempt",
      "status": "NOT_RUN"
    },
    {
      "id": "delivery_logs_recorded",
      "description": "Delivery success/failure is logged correctly",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "delivery layer exists",
    "emails are attempted with real delivery",
    "delivery result logged"
  ]
}
QA_CONTRACT_END