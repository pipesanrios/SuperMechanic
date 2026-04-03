PHASE: 40D VALIDATION

QA_CONTRACT_START
{
  "phase": "40D",
  "validation_contract_id": "40D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "crm_pipeline_controller_exists",
      "type": "file_exists",
      "target": "includes/crm/class-crm-pipeline-admin-controller.php"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css/admin.css"
    },
    {
      "id": "operational_views_method_exists",
      "type": "method_exists",
      "class": "Crm_Pipeline_Admin_Controller",
      "method": "render_operational_task_views",
      "file": "includes/crm/class-crm-pipeline-admin-controller.php"
    },
    {
      "id": "operational_table_method_exists",
      "type": "method_exists",
      "class": "Crm_Pipeline_Admin_Controller",
      "method": "render_operational_task_table",
      "file": "includes/crm/class-crm-pipeline-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_operational_tasks_compact_layout",
      "description": "Operational CRM tasks usa layout compacto multi-columna en desktop."
    },
    {
      "id": "runtime_kpi_cards_single_row_desktop",
      "description": "Cards KPI Pending/Overdue/Upcoming se muestran en una sola fila en desktop."
    },
    {
      "id": "runtime_operational_tasks_mobile_stack",
      "description": "Operational CRM tasks colapsa a stack vertical en mobile."
    },
    {
      "id": "runtime_operational_tasks_no_functional_regression",
      "description": "Mismos datos, acciones y links funcionales sin regresion."
    },
    {
      "id": "runtime_visual_alignment_with_dashboard",
      "description": "Consistencia visual general con el dashboard."
    }
  ]
}
QA_CONTRACT_END
