PHASE: 51B VALIDATION

QA_CONTRACT_START
{
  "phase": "51B",
  "validation_contract_id": "51B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "branding_service_exists",
      "type": "file_exists",
      "target": "includes/branding/class-branding-service.php"
    },
    {
      "id": "branding_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-branding-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "branding_page_visible",
      "description": "pagina Branding visible en admin"
    },
    {
      "id": "branding_save_works",
      "description": "branding se guarda correctamente"
    },
    {
      "id": "branding_applies_visually",
      "description": "branding se refleja en paginas admin del plugin"
    },
    {
      "id": "defaults_work_without_config",
      "description": "defaults funcionan sin configuracion"
    },
    {
      "id": "no_regression_admin_pages",
      "description": "paginas admin del plugin siguen estables"
    }
  ]
}
QA_CONTRACT_END
