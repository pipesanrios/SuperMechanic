PHASE: 51A VALIDATION

QA_CONTRACT_START
{
  "phase": "51A",
  "validation_contract_id": "51A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "license_installer_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-license-installer.php"
    },
    {
      "id": "license_repository_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-license-repository.php"
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
      "id": "license_page_visible",
      "description": "pagina License visible en admin"
    },
    {
      "id": "license_activate_works",
      "description": "activacion local de licencia funciona"
    },
    {
      "id": "domain_resolves_correctly",
      "description": "dominio actual se detecta correctamente"
    },
    {
      "id": "license_status_visible",
      "description": "estado de licencia visible y claro"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    }
  ]
}
QA_CONTRACT_END
