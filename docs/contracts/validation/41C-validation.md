PHASE: 41C VALIDATION

QA_CONTRACT_START
{
  "phase": "41C",
  "validation_contract_id": "41C-validation-v1",
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
      "id": "workload_service_class_exists",
      "type": "class_exists",
      "class": "Workload_Service",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "suggestion_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_recommendations",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "dashboard_recommendations_render_method_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_recommendations",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_recommendations_block_visible",
      "description": "Bloque de sugerencias visible en dashboard."
    },
    {
      "id": "runtime_recommendations_coherent",
      "description": "Sugerencias coherentes con datos reales."
    },
    {
      "id": "runtime_no_regression_dashboard",
      "description": "40A/40B/40C/41A/41B siguen funcionando."
    },
    {
      "id": "runtime_no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente."
    }
  ]
}
QA_CONTRACT_END
