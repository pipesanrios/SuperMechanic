PHASE: 41A VALIDATION

QA_CONTRACT_START
{
  "phase": "41A",
  "validation_contract_id": "41A-validation-v1",
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
      "id": "automation_flags_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_automation_flags",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_method_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_automation_flags",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_automation_flags_visible",
      "description": "Bloque de automatizacion interna visible con flags y estado global."
    },
    {
      "id": "runtime_overdue_rule_triggered",
      "description": "Regla de tareas overdue abiertas se activa cuando hay backlog vencido."
    },
    {
      "id": "runtime_user_saturation_rule_triggered",
      "description": "Regla de saturacion por usuario se activa solo con carga critica alta."
    },
    {
      "id": "runtime_no_regression_dashboard_pipeline_workload",
      "description": "Sin regresion en dashboard, CRM Pipeline y workload 40A/40B/40C."
    }
  ]
}
QA_CONTRACT_END
