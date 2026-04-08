PHASE: 54D VALIDATION

QA_CONTRACT_START
{
  "phase": "54D",
  "validation_contract_id": "54D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "demo_service_exists",
      "type": "file_exists",
      "target": "includes/demo/class-demo-service.php"
    },
    {
      "id": "demo_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-demo-admin-controller.php"
    },
    {
      "id": "seed_demo_script_exists",
      "type": "file_exists",
      "target": "scripts/seed-demo-commercial.php"
    }
  ],
  "manual_checks": [
    {
      "id": "demo_page_visible",
      "description": "pagina Demo visible en admin"
    },
    {
      "id": "demo_mode_toggle_works",
      "description": "activar/desactivar demo mode funciona"
    },
    {
      "id": "demo_dataset_available",
      "description": "dataset demo se puede sembrar/usar"
    },
    {
      "id": "sensitive_masking_useful",
      "description": "enmascarado de datos sensibles util"
    },
    {
      "id": "no_regression_admin",
      "description": "admin sigue estable"
    }
  ],
  "acceptance_criteria": [
    "demo_layer_exists",
    "demo_mode_operational",
    "demo_dataset_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
