PHASE: 51C VALIDATION

QA_CONTRACT_START
{
  "phase": "51C",
  "validation_contract_id": "51C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "plan_limits_service_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-plan-limits-service.php"
    },
    {
      "id": "license_service_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-license-service.php"
    },
    {
      "id": "license_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-license-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "plan_limits_visible",
      "description": "limites por plan visibles en License"
    },
    {
      "id": "usage_calculated_correctly",
      "description": "uso actual se calcula correctamente"
    },
    {
      "id": "exceeded_limits_detected",
      "description": "excesos se detectan correctamente"
    },
    {
      "id": "starter_fallback_visible",
      "description": "sin licencia activa se ve fallback starter"
    },
    {
      "id": "no_regression_license_ui",
      "description": "pagina License sigue estable"
    }
  ]
}
QA_CONTRACT_END
