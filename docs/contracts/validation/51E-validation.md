PHASE: 51E VALIDATION

QA_CONTRACT_START
{
  "phase": "51E",
  "validation_contract_id": "51E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "export_service_exists",
      "type": "file_exists",
      "target": "includes/export/class-export-service.php"
    },
    {
      "id": "export_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-export-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "export_page_visible",
      "description": "pagina Export visible en admin"
    },
    {
      "id": "json_export_works",
      "description": "exportacion JSON funciona correctamente"
    },
    {
      "id": "datasets_export_correctly",
      "description": "datasets soportados exportan correctamente"
    },
    {
      "id": "no_regression_admin_pages",
      "description": "paginas admin siguen estables"
    }
  ],
  "acceptance_criteria": [
    "export_layer_exists",
    "json_export_operational",
    "datasets_supported",
    "stable_system"
  ]
}
QA_CONTRACT_END
