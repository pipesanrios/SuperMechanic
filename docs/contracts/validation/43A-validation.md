PHASE: 43A VALIDATION

QA_CONTRACT_START
{
  "phase": "43A",
  "validation_contract_id": "43A-validation-v1",
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
      "id": "guided_actions_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_guided_rule_actions",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "guided_actions_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_guided_rule_actions",
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
      "id": "guided_actions_block_visible",
      "description": "bloque de acciones guiadas visible"
    },
    {
      "id": "triggered_rules_show_actions",
      "description": "reglas activadas muestran accion manual correspondiente"
    },
    {
      "id": "guided_execution_works",
      "description": "ejecucion manual guiada reutiliza handlers existentes correctamente"
    },
    {
      "id": "no_auto_execution",
      "description": "no existe autoejecucion de reglas"
    },
    {
      "id": "no_regression_dashboard",
      "description": "40A-42E siguen funcionando"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
