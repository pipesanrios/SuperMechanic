PHASE: 44B VALIDATION

QA_CONTRACT_START
{
  "phase": "44B",
  "validation_contract_id": "44B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "rules_repository_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-repository.php"
    },
    {
      "id": "rules_service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "repository_save_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Repository",
      "method": "save_rule_config",
      "file": "includes/automation/class-operational-rules-repository.php"
    },
    {
      "id": "service_save_basic_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "save_basic_rule_config",
      "file": "includes/automation/class-operational-rules-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "edit_form_visible",
      "description": "formulario de edicion visible por regla"
    },
    {
      "id": "save_rule_config",
      "description": "enabled, execution_mode y max_items_auto guardan correctamente"
    },
    {
      "id": "nonce_and_capability_enforced",
      "description": "guardado exige nonce valido y capability sm_manage_plugin"
    },
    {
      "id": "fallback_defaults_preserved",
      "description": "si faltan campos se mantiene fallback/default"
    },
    {
      "id": "no_regression_rules_engine",
      "description": "43E sigue funcionando sin regresion"
    }
  ]
}
QA_CONTRACT_END
