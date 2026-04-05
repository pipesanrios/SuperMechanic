PHASE: 49A VALIDATION

QA_CONTRACT_START
{
  "phase": "49A",
  "validation_contract_id": "49A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "membership_installer_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-installer.php"
    },
    {
      "id": "membership_repository_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-repository.php"
    },
    {
      "id": "membership_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "membership_table_created",
      "description": "tabla de membresias creada correctamente"
    },
    {
      "id": "membership_queries_work",
      "description": "consultas basicas de membresia funcionan"
    },
    {
      "id": "primary_membership_resolves",
      "description": "membresia principal se resuelve correctamente"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles y capabilities actuales siguen funcionando"
    }
  ]
}
QA_CONTRACT_END
