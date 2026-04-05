QA_CONTRACT_START
{
  "phase": "48E",
  "validation_contract_id": "48E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "roles_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    },
    {
      "id": "role_access_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "roles_page_visible",
      "description": "pantalla Roles & Access visible"
    },
    {
      "id": "users_listed_correctly",
      "description": "usuarios y accesos visibles correctamente"
    },
    {
      "id": "role_change_works",
      "description": "cambio basico de rol funciona"
    },
    {
      "id": "inconsistencies_visible",
      "description": "inconsistencias de acceso visibles"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles/capabilities siguen consistentes"
    }
  ]
}
QA_CONTRACT_END
