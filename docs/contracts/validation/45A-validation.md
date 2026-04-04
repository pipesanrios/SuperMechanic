PHASE: 45A VALIDATION

QA_CONTRACT_START
{
  "phase": "45A",
  "validation_contract_id": "45A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "installer_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-installer.php"
    },
    {
      "id": "repository_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-repository.php"
    },
    {
      "id": "service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "table_created",
      "description": "tabla de logs creada correctamente"
    },
    {
      "id": "execution_logged",
      "description": "una ejecucion queda registrada"
    },
    {
      "id": "no_regression",
      "description": "43A-44E siguen funcionando"
    }
  ]
}
QA_CONTRACT_END
