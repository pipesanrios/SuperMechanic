PHASE: 50D VALIDATION

QA_CONTRACT_START
{
  "phase": "50D",
  "validation_contract_id": "50D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "webhook_service_exists",
      "type": "file_exists",
      "target": "includes/webhooks/class-webhook-service.php"
    },
    {
      "id": "webhook_repository_exists",
      "type": "file_exists",
      "target": "includes/webhooks/class-webhook-repository.php"
    },
    {
      "id": "webhook_installer_exists",
      "type": "file_exists",
      "target": "includes/webhooks/class-webhook-installer.php"
    }
  ],
  "manual_checks": [
    {
      "id": "webhook_dispatch_works",
      "description": "webhook se dispara correctamente"
    },
    {
      "id": "payload_valid",
      "description": "payload contiene estructura correcta"
    },
    {
      "id": "external_failure_safe",
      "description": "fallo externo no rompe sistema"
    },
    {
      "id": "no_regression_notifications",
      "description": "sistema de notificaciones sigue funcionando"
    }
  ]
}
QA_CONTRACT_END
