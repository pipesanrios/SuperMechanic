PHASE: 40B VALIDATION

QA_CONTRACT_START
{
  "phase": "40B",
  "validation_contract_id": "40B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "workload_service_file_exists",
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
      "id": "workload_service_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_user_workload",
      "file": "includes/dashboard/class-workload-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads_without_errors",
      "description": "Dashboard carga sin errores en WordPress real."
    },
    {
      "id": "runtime_workload_section_visible",
      "description": "Sección \"Mi trabajo\" visible en dashboard."
    },
    {
      "id": "runtime_workload_buckets_correct",
      "description": "Buckets critical, warning, normal correctos."
    },
    {
      "id": "runtime_workload_items_visible",
      "description": "Items de tareas, alertas, procesos y citas visibles."
    },
    {
      "id": "runtime_workload_links_functional",
      "description": "Links de items funcionales."
    },
    {
      "id": "runtime_no_regression_crm_calendar_tasks",
      "description": "No regresión en CRM, calendar y tasks."
    }
  ]
}
QA_CONTRACT_END
