PHASE: 48C VALIDATION

QA_CONTRACT_START
{
  "phase": "48C",
  "validation_contract_id": "48C-validation-v1",
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
      "id": "empty_states_clear",
      "description": "estados vacíos se entienden mejor"
    },
    {
      "id": "lazy_failures_clear",
      "description": "errores en carga diferida muestran mensaje claro"
    },
    {
      "id": "no_dead_actions",
      "description": "acciones sin destino útil no confunden al usuario"
    },
    {
      "id": "role_empty_states_ok",
      "description": "vistas vacías por rol se entienden correctamente"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue estable"
    }
  ]
}
QA_CONTRACT_END
