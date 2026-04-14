QA_CONTRACT_START
{
  "validation_contract_id": "56P2-A1-validation",
  "phase": "56P2-A1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "superadmin_bootstrap_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-superadmin-bootstrap-service.php"
    },
    {
      "id": "role_access_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "primary_admin_superadmin_real",
      "description": "Primary WordPress admin is materialized as real superadmin (global + admin/mechanic/client operational state)",
      "status": "NOT_RUN"
    },
    {
      "id": "superadmin_global_total_scope",
      "description": "Primary superadmin has all-business scope without depending on normal manual memberships",
      "status": "NOT_RUN"
    },
    {
      "id": "roles_access_locked_superadmin",
      "description": "Roles & Access shows locked superadmin state (Global scope, Admin + Mechanic + Client, no Add membership, no normal membership controls)",
      "status": "NOT_RUN"
    },
    {
      "id": "other_admins_not_auto_promoted",
      "description": "Other WordPress admins are not automatically promoted to superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin screens remain stable after superadmin bootstrap completion fix",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "primary admin is real operational superadmin",
    "locked superadmin state visible in Roles & Access",
    "other admins are not auto-promoted",
    "admin remains stable"
  ]
}
QA_CONTRACT_END

