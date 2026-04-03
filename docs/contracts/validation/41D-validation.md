PHASE: 41D VALIDATION

QA_CONTRACT_START
{
  "phase": "41D",
  "validation_contract_id": "41D-validation-v1",
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
      "id": "operational_assignments_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_assignments",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "dashboard_assignments_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_assignments",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_assignment_block_visible",
      "description": "Bloque Asignacion Operativa visible."
    },
    {
      "id": "runtime_proposals_coherent",
      "description": "Propuestas coherentes con saturacion/disponibilidad."
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
