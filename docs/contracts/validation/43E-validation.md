PHASE: 43E VALIDATION

QA_CONTRACT_START
{
  "phase": "43E",
  "validation_contract_id": "43E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "repository_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-repository.php"
    },
    {
      "id": "installer_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-installer.php"
    },
    {
      "id": "repository_get_rule_config_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Repository",
      "method": "get_rule_config",
      "file": "includes/automation/class-operational-rules-repository.php"
    },
    {
      "id": "service_get_rule_config_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "get_rule_config",
      "file": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "service_operational_rules_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "get_operational_rules",
      "file": "includes/automation/class-operational-rules-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "rules_persist_per_business",
      "description": "reglas persisten por business_id"
    },
    {
      "id": "fallback_to_defaults",
      "description": "si no hay config en DB se usa default"
    },
    {
      "id": "rule_changes_affect_behavior",
      "description": "cambio de config en DB modifica comportamiento"
    },
    {
      "id": "no_regression_43A_43D",
      "description": "sin regresion en fases 43A-43D"
    }
  ]
}
QA_CONTRACT_END
