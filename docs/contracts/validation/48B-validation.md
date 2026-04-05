PHASE: 48B VALIDATION

QA_CONTRACT_START
{
  "phase": "48B",
  "validation_contract_id": "48B-validation-v1",
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
    }
  ],
  "manual_checks": [
    {
      "id": "admin_full_visibility",
      "description": "admin mantiene visibilidad necesaria"
    },
    {
      "id": "mechanic_reduced_noise",
      "description": "mecánico ve menos ruido y más foco operativo"
    },
    {
      "id": "client_not_exposed",
      "description": "cliente no ve dashboard interno administrativo"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles/capabilities siguen consistentes"
    }
  ]
}
QA_CONTRACT_END
