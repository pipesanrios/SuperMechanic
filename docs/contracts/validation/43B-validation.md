PHASE: 43B VALIDATION

QA_CONTRACT_START
{
  "phase": "43B",
  "validation_contract_id": "43B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "operational_rules_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "confirmable_actions_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_confirmable_rule_actions",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "confirmable_actions_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_confirmable_rule_actions",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "bulk_handler_reused_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "maybe_handle_operational_bulk_action_request",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_loads",
      "description": "dashboard carga sin errores"
    },
    {
      "id": "confirmable_actions_block_visible",
      "description": "bloque de acciones confirmables visible"
    },
    {
      "id": "confirmation_required_before_execution",
      "description": "la mutacion no ocurre sin confirmacion explicita"
    },
    {
      "id": "confirm_and_run_executes",
      "description": "confirmacion ejecuta accion soportada correctamente"
    },
    {
      "id": "no_auto_execution",
      "description": "no existe autoejecucion"
    },
    {
      "id": "no_regression_dashboard",
      "description": "40A-43A siguen funcionando"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
