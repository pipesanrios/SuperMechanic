PHASE: 51D VALIDATION

QA_CONTRACT_START
{
  "phase": "51D",
  "validation_contract_id": "51D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "onboarding_service_exists",
      "type": "file_exists",
      "target": "includes/onboarding/class-onboarding-service.php"
    },
    {
      "id": "onboarding_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-onboarding-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "onboarding_page_visible",
      "description": "pagina Onboarding visible en admin"
    },
    {
      "id": "checklist_correct",
      "description": "checklist refleja estado real del sistema"
    },
    {
      "id": "next_step_correct",
      "description": "siguiente paso recomendado es correcto"
    },
    {
      "id": "incomplete_install_detected",
      "description": "detecta instalacion incompleta"
    },
    {
      "id": "no_regression_admin_pages",
      "description": "admin pages siguen estables"
    }
  ]
}
QA_CONTRACT_END
