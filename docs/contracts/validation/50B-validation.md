PHASE: 50B VALIDATION

QA_CONTRACT_START
{
  "phase": "50B",
  "validation_contract_id": "50B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "notification_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-service.php"
    },
    {
      "id": "notification_template_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-template-service.php"
    },
    {
      "id": "email_delivery_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-email-delivery-service.php"
    },
    {
      "id": "membership_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-service.php"
    },
    {
      "id": "workload_service_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-workload-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "membership_triggers_work",
      "description": "membership events disparan notificaciones correctas"
    },
    {
      "id": "operational_triggers_work",
      "description": "eventos operativos disparan notificaciones correctas"
    },
    {
      "id": "no_duplicate_notifications",
      "description": "no hay duplicacion evidente de envios"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_operational_flows",
      "description": "flujos operativos siguen estables"
    }
  ]
}
QA_CONTRACT_END
