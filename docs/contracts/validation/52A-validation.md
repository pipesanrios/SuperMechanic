PHASE: 52A VALIDATION

QA_CONTRACT_START
{
  "phase": "52A",
  "validation_contract_id": "52A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "queue_installer_exists",
      "type": "file_exists",
      "target": "includes/queue/class-queue-installer.php"
    },
    {
      "id": "queue_repository_exists",
      "type": "file_exists",
      "target": "includes/queue/class-queue-repository.php"
    },
    {
      "id": "queue_service_exists",
      "type": "file_exists",
      "target": "includes/queue/class-queue-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "jobs_enqueued",
      "description": "jobs se encolan correctamente"
    },
    {
      "id": "jobs_processed",
      "description": "worker procesa jobs correctamente"
    },
    {
      "id": "job_status_flow",
      "description": "estados pending -> processing -> completed funcionan"
    },
    {
      "id": "no_regression_notifications",
      "description": "notificaciones siguen funcionando"
    },
    {
      "id": "no_regression_webhooks",
      "description": "webhooks siguen funcionando"
    }
  ],
  "acceptance_criteria": [
    "queue_system_exists",
    "enqueue_operational",
    "processing_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
