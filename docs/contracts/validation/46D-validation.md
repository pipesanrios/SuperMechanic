PHASE: 46D VALIDATION

QA_CONTRACT_START
{
  "phase": "46D",
  "validation_contract_id": "46D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "execution_log_installer_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-installer.php"
    },
    {
      "id": "rules_installer_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-installer.php"
    },
    {
      "id": "crm_task_repository_exists",
      "type": "file_exists",
      "target": "includes/crm/class-crm-task-repository.php"
    }
  ],
  "manual_checks": [
    {
      "id": "indexes_created",
      "description": "indices creados correctamente"
    },
    {
      "id": "no_install_regression",
      "description": "sin regresion de instalacion/upgrade"
    },
    {
      "id": "dashboard_logs_queries_stable",
      "description": "dashboard y logs siguen estables"
    }
  ]
}
QA_CONTRACT_END
