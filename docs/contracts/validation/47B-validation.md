PHASE: 47B VALIDATION

QA_CONTRACT_START
{
  "phase": "47B",
  "validation_contract_id": "47B-validation-v1",
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
    }
  ],
  "manual_checks": [
    {
      "id": "less_visual_noise",
      "description": "dashboard muestra menos ruido visual y menos redundancia"
    },
    {
      "id": "duplicate_messages_reduced",
      "description": "problemas repetidos aparecen menos duplicados entre bloques"
    },
    {
      "id": "critical_actions_still_visible",
      "description": "acciones críticas siguen visibles y claras"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue estable"
    }
  ]
}
QA_CONTRACT_END
