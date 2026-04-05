PHASE: 48A VALIDATION

QA_CONTRACT_START
{
  "phase": "48A",
  "validation_contract_id": "48A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "config_service_exists",
      "type": "file_exists",
      "target": "includes/config/class-operational-config-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "defaults_work",
      "description": "sistema funciona sin config explícita"
    },
    {
      "id": "thresholds_override",
      "description": "cambiar threshold afecta comportamiento esperado"
    },
    {
      "id": "flags_disable_features",
      "description": "flags desactivan features visuales correctamente"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_rules",
      "description": "reglas siguen funcionando"
    }
  ]
}
QA_CONTRACT_END
