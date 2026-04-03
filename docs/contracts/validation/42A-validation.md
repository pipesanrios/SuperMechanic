PHASE: 42A VALIDATION

QA_CONTRACT_START
{
  "phase": "42A",
  "validation_contract_id": "42A-validation-v1",
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
      "id": "assisted_actions_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_assisted_actions",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "dashboard_assisted_actions_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_assisted_actions",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_assisted_actions_block_visible",
      "description": "Bloque de acciones asistidas visible."
    },
    {
      "id": "runtime_actions_navigate_correctly",
      "description": "Acciones navegan al modulo o ruta segura esperada."
    },
    {
      "id": "runtime_no_data_mutation",
      "description": "Las acciones no mutan datos por si mismas."
    },
    {
      "id": "runtime_no_regression_dashboard",
      "description": "40A-41E siguen funcionando."
    },
    {
      "id": "runtime_no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente."
    }
  ]
}
QA_CONTRACT_END
