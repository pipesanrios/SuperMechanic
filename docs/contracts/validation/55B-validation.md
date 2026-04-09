QA_CONTRACT_START
{
  "phase": "55B",
  "validation_contract_id": "55B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "api_loader_exists",
      "type": "file_exists",
      "target": "includes/api/class-api-loader.php"
    },
    {
      "id": "api_controllers_folder_exists",
      "type": "file_exists",
      "target": "includes/api/controllers"
    }
  ],
  "manual_checks": [
    {
      "id": "namespace_active",
      "description": "namespace /wp-json/sm/v1/ activo"
    },
    {
      "id": "endpoints_respond",
      "description": "endpoints minimos responden correctamente"
    },
    {
      "id": "ownership_validation_works",
      "description": "ownership y business scope correctos"
    },
    {
      "id": "reporting_summary_works",
      "description": "reporting summary responde correctamente"
    },
    {
      "id": "no_regression_system",
      "description": "sistema sigue estable"
    }
  ],
  "acceptance_criteria": [
    "api_layer_exists",
    "endpoints_operational",
    "ownership_secure",
    "stable_system"
  ]
}
QA_CONTRACT_END
