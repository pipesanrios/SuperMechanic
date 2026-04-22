QA_CONTRACT_START
{
  "validation_contract_id": "56P6-C-validation",
  "phase": "56P6-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "users_folder_exists",
      "type": "file_exists",
      "target": "includes/users"
    }
  ],
  "manual_checks": [
    {
      "id": "protected_superadmin_backend_blocked",
      "description": "Protected superadmin cannot be modified through normal backend flows",
      "status": "NOT_RUN"
    },
    {
      "id": "invalid_membership_ops_blocked",
      "description": "Invalid membership/role operations are blocked server-side",
      "status": "NOT_RUN"
    },
    {
      "id": "authorized_ops_still_work",
      "description": "Authorized role/membership operations still work",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Roles & Access/admin still load correctly after backend hardening",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "server-side enforcement exists",
    "protected superadmin is safe",
    "authorized flows preserved",
    "admin stable"
  ]
}
QA_CONTRACT_END