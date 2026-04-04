PHASE: 44D VALIDATION

QA_CONTRACT_START
{
  "phase": "44D",
  "validation_contract_id": "44D-validation-v1",
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
    },
    {
      "id": "execution_mode_badge_method_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_execution_mode_badge",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "badges_visible",
      "description": "badges de execution_mode visibles (Manual, Confirmable, Auto)"
    },
    {
      "id": "rules_status_visible",
      "description": "enabled/disabled visible en bloque de reglas"
    },
    {
      "id": "no_ui_regression",
      "description": "dashboard sigue estable sin regresion visual"
    }
  ]
}
QA_CONTRACT_END
