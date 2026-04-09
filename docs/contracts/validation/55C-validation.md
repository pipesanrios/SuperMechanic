QA_CONTRACT_START
{
  "phase": "55C",
  "validation_contract_id": "55C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "webhook_service_exists",
      "type": "file_exists",
      "target": "includes/webhooks/class-webhook-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "process_events_dispatch",
      "description": "process.created y process.updated se despachan correctamente"
    },
    {
      "id": "quote_invoice_payment_events_dispatch",
      "description": "quote.approved, invoice.paid y payment.created se despachan correctamente"
    },
    {
      "id": "payload_structure_correct",
      "description": "payload de evento tiene estructura estable y util"
    },
    {
      "id": "no_regression_webhooks",
      "description": "webhooks siguen estables"
    },
    {
      "id": "no_regression_system",
      "description": "sistema sigue estable"
    }
  ],
  "acceptance_criteria": [
    "event_dispatch_exists",
    "event_payload_stable",
    "webhooks_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
