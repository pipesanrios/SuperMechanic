PHASE: 49C VALIDATION

QA_CONTRACT_START
{
  "phase": "49C",
  "validation_contract_id": "49C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "roles_access_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-roles-access-controller.php"
    },
    {
      "id": "roles_access_js_exists",
      "type": "file_exists",
      "target": "assets/js/admin-roles-access.js"
    }
  ],
  "manual_checks": [
    {
      "id": "membership_created",
      "description": "se puede crear membership desde UI"
    },
    {
      "id": "membership_updated",
      "description": "se puede cambiar rol operativo"
    },
    {
      "id": "membership_primary_unique",
      "description": "solo una membership primaria por usuario"
    },
    {
      "id": "membership_activation_toggle",
      "description": "activar y desactivar funciona correctamente"
    },
    {
      "id": "warnings_resolved",
      "description": "warnings desaparecen al asignar membership valida"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    }
  ]
}
QA_CONTRACT_END
