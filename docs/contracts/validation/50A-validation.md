PHASE: 50A VALIDATION

QA_CONTRACT_START
{
  "phase": "50A",
  "validation_contract_id": "50A-validation-v1",
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
      "id": "template_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-template-service.php"
    },
    {
      "id": "email_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-email-delivery-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "email_sent_successfully",
      "description": "wp_mail envia email correctamente"
    },
    {
      "id": "template_rendering",
      "description": "variables se renderizan correctamente"
    },
    {
      "id": "notification_types_work",
      "description": "tipos de notificacion funcionan"
    },
    {
      "id": "integration_with_membership",
      "description": "integracion basica con memberships funciona"
    }
  ]
}
QA_CONTRACT_END
