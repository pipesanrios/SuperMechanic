PHASE: 52B VALIDATION

QA_CONTRACT_START
{
  "phase": "52B",
  "validation_contract_id": "52B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "queue_service_exists",
      "type": "file_exists",
      "target": "includes/queue/class-queue-service.php"
    },
    {
      "id": "queue_repository_exists",
      "type": "file_exists",
      "target": "includes/queue/class-queue-repository.php"
    }
  ],
  "manual_checks": [
    {
      "id": "retry_scheduled",
      "description": "retry se agenda correctamente tras fallo"
    },
    {
      "id": "next_retry_respected",
      "description": "worker respeta next_retry_at"
    },
    {
      "id": "max_attempts_respected",
      "description": "job pasa a failed al agotar intentos"
    },
    {
      "id": "no_regression_queue",
      "description": "cola base sigue estable"
    },
    {
      "id": "no_regression_notifications_webhooks",
      "description": "notifications/webhooks siguen estables"
    }
  ],
  "acceptance_criteria": [
    "retry_system_exists",
    "retry_flow_operational",
    "stable_queue",
    "no_logic_regression"
  ]
}
QA_CONTRACT_END
