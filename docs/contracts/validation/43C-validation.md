PHASE: 43C VALIDATION

QA_CONTRACT_START
{
  "phase": "43C",
  "validation_contract_id": "43C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "auto_execution_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "run_controlled_auto_execution",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "auto_execution_overview_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_controlled_auto_execution_overview",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "rules_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "auto_execution_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_controlled_auto_execution",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_loads",
      "description": "dashboard carga sin errores"
    },
    {
      "id": "auto_execution_block_visible",
      "description": "bloque de autoejecucion visible"
    },
    {
      "id": "only_safe_rule_executes",
      "description": "solo overdue_tasks_cleanup se autoejecuta"
    },
    {
      "id": "blocked_rules_not_executed",
      "description": "reglas no permitidas quedan bloqueadas"
    },
    {
      "id": "no_regression_dashboard",
      "description": "40A-43B siguen funcionando"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
