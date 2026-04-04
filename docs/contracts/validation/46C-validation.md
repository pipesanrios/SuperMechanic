PHASE: 46C VALIDATION

QA_CONTRACT_START
{
  "phase": "46C",
  "validation_contract_id": "46C-validation-v1",
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
      "id": "admin_js_exists",
      "type": "file_exists",
      "target": "assets/js/admin-dashboard.js"
    }
  ],
  "manual_checks": [
    {
      "id": "above_the_fold_first",
      "description": "bloques prioritarios cargan primero"
    },
    {
      "id": "heavy_blocks_lazy_loaded",
      "description": "bloques pesados cargan después"
    },
    {
      "id": "placeholders_visible",
      "description": "placeholders visibles mientras cargan"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue funcional"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
