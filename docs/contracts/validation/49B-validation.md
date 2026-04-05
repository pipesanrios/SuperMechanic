PHASE: 49B VALIDATION

QA_CONTRACT_START
{
  "phase": "49B",
  "validation_contract_id": "49B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "membership_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-service.php"
    },
    {
      "id": "role_access_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "super_admin_resolves",
      "description": "super admin global se resuelve correctamente"
    },
    {
      "id": "multi_business_scope_resolves",
      "description": "alcance multi-business se resuelve correctamente"
    },
    {
      "id": "membership_scoped_user_restricted",
      "description": "usuario normal queda restringido a sus negocios"
    },
    {
      "id": "default_business_resolves",
      "description": "business por defecto se resuelve correctamente"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles y capabilities actuales siguen consistentes"
    }
  ]
}
QA_CONTRACT_END
