PHASE: 45F VALIDATION

QA_CONTRACT_START
{
  "phase": "45F",
  "validation_contract_id": "45F-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css/admin.css"
    }
  ],
  "manual_checks": [
    {
      "id": "quick_actions_top",
      "description": "quick actions visible en zona alta del dashboard"
    },
    {
      "id": "critical_strip_visible",
      "description": "critical strip visible y compacto en dashboard"
    },
    {
      "id": "automation_center_functional",
      "description": "automation center visible y funcional con bloques técnicos"
    },
    {
      "id": "no_crm_pipeline_regression",
      "description": "sin regresión visual/funcional en CRM pipeline"
    }
  ]
}
QA_CONTRACT_END
