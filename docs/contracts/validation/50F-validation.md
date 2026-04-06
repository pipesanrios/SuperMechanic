PHASE: 50F VALIDATION

QA_CONTRACT_START
{
  "phase": "50F",
  "validation_contract_id": "50F-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "webhooks_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-webhooks-admin-controller.php"
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
    }
  ],
  "manual_checks": [
    {
      "id": "webhooks_page_visible",
      "description": "pagina Webhooks visible en admin"
    },
    {
      "id": "webhook_crud_works",
      "description": "crear/editar/activar/desactivar/eliminar funciona"
    },
    {
      "id": "test_webhook_works",
      "description": "envio de test webhook funciona"
    },
    {
      "id": "no_regression_webhooks",
      "description": "dispatch normal de webhooks sigue estable"
    },
    {
      "id": "no_regression_notifications",
      "description": "notificaciones siguen estables"
    }
  ]
}
QA_CONTRACT_END
