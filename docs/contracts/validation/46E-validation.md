PHASE: 46E VALIDATION

QA_CONTRACT_START
{
  "phase": "46E",
  "validation_contract_id": "46E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "workload_service_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-workload-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "profiling_visible_for_admin",
      "description": "profiling visible solo para admin autorizado"
    },
    {
      "id": "profiling_hidden_for_normal_users",
      "description": "profiling no visible para usuarios no autorizados"
    },
    {
      "id": "block_times_visible",
      "description": "tiempos por bloque visibles"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_logs_automation",
      "description": "logs y automation center siguen estables"
    }
  ]
}
QA_CONTRACT_END
