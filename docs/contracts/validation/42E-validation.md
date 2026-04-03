PHASE: 42E VALIDATION

QA_CONTRACT_START
{
  "phase": "42E",
  "validation_contract_id": "42E-validation-v1",
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
      "id": "rules_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "get_operational_rules",
      "file": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "evaluate_rules_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "evaluate_operational_rules",
      "file": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "workload_rules_overview_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_rules_overview",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_rules_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_rules",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "action_center_still_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_action_center",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_rules_block_visible",
      "description": "El bloque Reglas Operativas es visible en dashboard y mantiene layout estable."
    },
    {
      "id": "runtime_rules_evaluation_coherent",
      "description": "Cada regla muestra enabled, triggered, impacto y preview coherente con datos operativos."
    },
    {
      "id": "runtime_no_execution_buttons_in_rules",
      "description": "Bloque de reglas sin botones de ejecucion ni acciones mutables."
    },
    {
      "id": "runtime_no_auto_execution",
      "description": "No hay ejecucion automatica de acciones (sin cron/triggers de 42E)."
    },
    {
      "id": "runtime_no_regression_40a_42d",
      "description": "Sin regresion funcional en dashboard y CRM Pipeline respecto a 40A-42D."
    }
  ]
}
QA_CONTRACT_END
