PHASE: 54B VALIDATION

QA_CONTRACT_START
{
  "phase": "54B",
  "validation_contract_id": "54B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
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
      "id": "export_filters_visible",
      "description": "filtros visibles en Export"
    },
    {
      "id": "range_filter_works",
      "description": "rango funciona correctamente"
    },
    {
      "id": "business_scope_works",
      "description": "business_id filtra correctamente"
    },
    {
      "id": "datasets_export_well",
      "description": "datasets exportan correctamente"
    },
    {
      "id": "no_regression_export",
      "description": "Export sigue estable"
    }
  ],
  "acceptance_criteria": [
    "export_layer_extended",
    "filters_operational",
    "datasets_valid",
    "stable_system"
  ]
}
QA_CONTRACT_END
