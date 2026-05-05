QA_CONTRACT_START
{
  "validation_contract_id": "56P8-B-validation",
  "phase": "56P8-B",
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
      "id": "process_template_builds",
      "description": "Process status change builds a notification template correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_template_builds",
      "description": "Quote approved/rejected builds a notification template correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "invoice_template_builds",
      "description": "Invoice/payment event builds a notification template correctly",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "template service exists",
    "event templates build correctly",
    "ready for delivery integration"
  ]
}
QA_CONTRACT_END