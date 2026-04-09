QA_CONTRACT_START
{
  "phase": "55E2",
  "validation_contract_id": "55E2-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "license_service_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-license-service.php"
    },
    {
      "id": "plan_limits_service_exists",
      "type": "file_exists",
      "target": "includes/licensing/class-plan-limits-service.php"
    },
    {
      "id": "license_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-license-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "trial_state_visible",
      "description": "trial se muestra correctamente"
    },
    {
      "id": "trial_expiration_works",
      "description": "expiración de trial/licencia funciona"
    },
    {
      "id": "enforcement_blocks_creation",
      "description": "límites bloquean creación cuando corresponde"
    },
    {
      "id": "messages_clear",
      "description": "mensajes de bloqueo son claros"
    },
    {
      "id": "no_regression_system",
      "description": "sistema sigue estable"
    }
  ],
  "acceptance_criteria": [
    "monetization_core_exists",
    "trial_operational",
    "enforcement_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
