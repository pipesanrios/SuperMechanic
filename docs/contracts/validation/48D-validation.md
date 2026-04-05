QA_CONTRACT_START
{
  "phase": "48D",
  "validation_contract_id": "48D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
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
      "id": "preferences_persist",
      "description": "preferencias persisten tras recargar"
    },
    {
      "id": "secondary_blocks_toggle",
      "description": "bloques secundarios pueden colapsarse/ocultarse"
    },
    {
      "id": "critical_blocks_always_visible",
      "description": "bloques criticos siguen visibles"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles siguen consistentes"
    }
  ]
}
QA_CONTRACT_END
