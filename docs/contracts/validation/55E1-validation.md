QA_CONTRACT_START
{
  "phase": "55E1",
  "validation_contract_id": "55E1-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "commercial_hooks_service_exists",
      "type": "file_exists",
      "target": "includes/commercial/class-commercial-hooks-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "hooks_are_fired",
      "description": "los hooks comerciales se disparan en eventos reales"
    },
    {
      "id": "payload_shape_valid",
      "description": "payload contiene business_id/entity_id/entity_type/data"
    },
    {
      "id": "system_stable",
      "description": "no hay regresion funcional en flujos comerciales"
    }
  ],
  "acceptance_criteria": [
    "commercial_hooks_layer_exists",
    "commercial_hooks_integrated",
    "payload_standard_applied",
    "stable_system"
  ]
}
QA_CONTRACT_END
