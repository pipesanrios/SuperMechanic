PHASE: 54A VALIDATION

QA_CONTRACT_START
{
  "phase": "54A",
  "validation_contract_id": "54A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "reporting_service_exists",
      "type": "file_exists",
      "target": "includes/reporting/class-reporting-service.php"
    },
    {
      "id": "reporting_repository_exists",
      "type": "file_exists",
      "target": "includes/reporting/class-reporting-repository.php"
    },
    {
      "id": "reporting_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-reporting-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "reporting_page_visible",
      "description": "pagina Reporting visible en admin"
    },
    {
      "id": "metrics_correct",
      "description": "metricas reflejan datos reales"
    },
    {
      "id": "range_filter_works",
      "description": "selector de rango funciona correctamente"
    },
    {
      "id": "business_scope_correct",
      "description": "filtro por business_id funciona correctamente"
    },
    {
      "id": "no_regression_admin",
      "description": "admin sigue estable"
    }
  ],
  "acceptance_criteria": [
    "reporting_layer_exists",
    "metrics_operational",
    "ui_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
