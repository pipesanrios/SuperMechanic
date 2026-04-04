PHASE: 44C VALIDATION

QA_CONTRACT_START
{
  "phase": "44C",
  "validation_contract_id": "44C-validation-v1",
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
      "id": "service_exists",
      "type": "file_exists",
      "target": "includes/automation/class-operational-rules-service.php"
    },
    {
      "id": "controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "service_save_method_exists",
      "type": "method_exists",
      "class": "Operational_Rules_Service",
      "method": "save_basic_rule_config",
      "file": "includes/automation/class-operational-rules-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "invalid_execution_mode_rejected",
      "description": "execution_mode invalido es rechazado con error claro"
    },
    {
      "id": "invalid_limits_rejected",
      "description": "max_items_auto negativo o no numerico es rechazado"
    },
    {
      "id": "invalid_enabled_rejected",
      "description": "enabled invalido es rechazado"
    },
    {
      "id": "valid_inputs_saved",
      "description": "inputs validos se guardan correctamente"
    },
    {
      "id": "no_regression_44A_44B_43E",
      "description": "sin regresion en motor de reglas y capas previas"
    }
  ]
}
QA_CONTRACT_END
