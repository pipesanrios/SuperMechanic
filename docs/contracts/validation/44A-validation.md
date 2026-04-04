PHASE: 44A VALIDATION

QA_CONTRACT_START
{
  "phase": "44A",
  "validation_contract_id": "44A-validation-v1",
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
      "id": "repository_business_listing_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Repository",
      "method": "get_rule_configs_by_business",
      "file": "includes/automation/class-operational-rules-repository.php"
    },
    {
      "id": "service_admin_listing_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "get_operational_rules_admin_listing",
      "file": "includes/automation/class-operational-rules-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "rules_list_visible",
      "description": "listado de reglas visible en admin"
    },
    {
      "id": "business_scope_correct",
      "description": "reglas mostradas para business actual"
    },
    {
      "id": "fallback_defaults_visible",
      "description": "si no hay persistidas se muestra fallback/default claramente"
    },
    {
      "id": "no_mutation",
      "description": "la vista no ejecuta mutaciones"
    },
    {
      "id": "no_regression_dashboard",
      "description": "40A-43E siguen funcionando"
    }
  ]
}
QA_CONTRACT_END
