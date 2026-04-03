PHASE: 40A VALIDATION

QA_CONTRACT_START
{
  "phase": "40A",
  "validation_contract_id": "40A-validation-v1",
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
      "id": "admin_dashboard_controller_file_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "global_summary_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_global_operational_summary",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "global_summary_renderer_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_global_operational_summary",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads_without_errors",
      "description": "Dashboard carga sin errores en WordPress real."
    },
    {
      "id": "runtime_global_summary_visible",
      "description": "Bloque superior \"Resumen Operativo Global\" visible."
    },
    {
      "id": "runtime_global_metrics_coherent",
      "description": "Métricas globales coherentes con datos reales del negocio activo."
    },
    {
      "id": "runtime_no_regression_40b_workload",
      "description": "Sección 40B \"Mi trabajo\" sigue funcionando sin regresión."
    },
    {
      "id": "runtime_alignment_case_2_attention_1_critical",
      "description": "Caso real: si CRM Pipeline muestra 2 attention y 1 critical, 40A y 40B reflejan señales equivalentes según alcance."
    }
  ]
}
QA_CONTRACT_END
