PHASE: 47A VALIDATION

QA_CONTRACT_START
{
  "phase": "47A",
  "validation_contract_id": "47A-validation-v1",
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
    }
  ],
  "manual_checks": [
    {
      "id": "cleaner_dashboard",
      "description": "dashboard se siente más limpio y escaneable"
    },
    {
      "id": "primary_info_first",
      "description": "información principal domina visualmente"
    },
    {
      "id": "secondary_blocks_compact",
      "description": "bloques secundarios quedan más compactos"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_performance",
      "description": "no empeora performance percibida"
    }
  ]
}
QA_CONTRACT_END
