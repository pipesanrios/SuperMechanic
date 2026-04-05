PHASE: 49E VALIDATION

QA_CONTRACT_START
{
  "phase": "49E",
  "validation_contract_id": "49E-validation-v1",
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
    },
    {
      "id": "admin_roles_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "warnings_more_precise",
      "description": "warnings muestran inconsistencias con mayor precision y claridad accionable"
    },
    {
      "id": "safe_repairs_work",
      "description": "safe repairs corrigen inconsistencias permitidas sin crear memberships nuevas"
    },
    {
      "id": "primary_consistency_enforced",
      "description": "se mantiene primaria unica y activa cuando existe al menos una membership activa valida"
    },
    {
      "id": "invalid_access_detected",
      "description": "accesos indebidos o scope invalido se detectan correctamente"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_roles",
      "description": "roles y memberships siguen consistentes"
    }
  ]
}
QA_CONTRACT_END
