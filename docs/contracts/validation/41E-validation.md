PHASE: 41E VALIDATION

QA_CONTRACT_START
{
  "phase": "41E",
  "validation_contract_id": "41E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "automation_console_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_automation_console",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_console_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_automation_console",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_console_block_visible",
      "description": "Consola de Automatizacion visible."
    },
    {
      "id": "runtime_console_data_consistent",
      "description": "Datos coherentes con flags/escalation/recommendations/assignments."
    },
    {
      "id": "runtime_no_regression_dashboard",
      "description": "Sin regresion dashboard."
    },
    {
      "id": "runtime_no_regression_crm_pipeline",
      "description": "Sin regresion CRM Pipeline."
    }
  ]
}
QA_CONTRACT_END
