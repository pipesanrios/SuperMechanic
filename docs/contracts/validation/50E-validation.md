PHASE: 50E VALIDATION

QA_CONTRACT_START
{
  "phase": "50E",
  "validation_contract_id": "50E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "automation_engine_exists",
      "type": "file_exists",
      "target": "includes/automation/class-automation-engine-service.php"
    },
    {
      "id": "notification_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-service.php"
    },
    {
      "id": "webhook_service_exists",
      "type": "file_exists",
      "target": "includes/webhooks/class-webhook-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "engine_handles_events",
      "description": "engine procesa eventos soportados correctamente"
    },
    {
      "id": "notification_action_works",
      "description": "accion send_notification funciona desde engine"
    },
    {
      "id": "webhook_action_works",
      "description": "accion dispatch_webhook funciona desde engine"
    },
    {
      "id": "no_duplicate_automation",
      "description": "no hay duplicacion evidente de automatizacion"
    },
    {
      "id": "no_regression_notifications",
      "description": "notifications siguen estables"
    },
    {
      "id": "no_regression_webhooks",
      "description": "webhooks siguen estables"
    }
  ]
}
QA_CONTRACT_END
