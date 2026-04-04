PHASE: 45C VALIDATION

QA_CONTRACT_START
{
  "phase": "45C",
  "validation_contract_id": "45C-validation-v1",
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
    }
  ],
  "manual_checks": [
    {
      "id": "rule_update_logged",
      "description": "update de regla genera log"
    },
    {
      "id": "old_new_visible_in_payload",
      "description": "old/new básico queda guardado"
    }
  ]
}
QA_CONTRACT_END
