PHASE: 45E VALIDATION

QA_CONTRACT_START
{
  "phase": "45E",
  "validation_contract_id": "45E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "rules_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "execution_log_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-execution-log-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "blocked_reason_visible",
      "description": "razón de bloqueo visible"
    },
    {
      "id": "skipped_reason_visible",
      "description": "razón de skip visible"
    },
    {
      "id": "trigger_reason_visible",
      "description": "razón de trigger/no trigger visible"
    }
  ]
}
QA_CONTRACT_END
