PHASE: 46A VALIDATION

QA_CONTRACT_START
{
  "phase": "46A",
  "validation_contract_id": "46A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "workload_service_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "rules_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "execution_log_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_faster",
      "description": "dashboard carga perceptiblemente más rápido"
    },
    {
      "id": "logs_page_stable",
      "description": "pantalla de logs sigue estable"
    },
    {
      "id": "same_functional_results",
      "description": "resultados funcionales no cambiaron"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
