QA_CONTRACT_START
{
  "validation_contract_id": "56P2-B-validation",
  "phase": "56P2-B",
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
      "id": "superadmin_can_promote_admin",
      "description": "Existing Mekvort superadmin can promote an eligible WordPress administrator to Mekvort superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "superadmin_can_revoke_promoted_superadmin",
      "description": "Existing Mekvort superadmin can revoke a promoted Mekvort superadmin safely",
      "status": "NOT_RUN"
    },
    {
      "id": "non_superadmin_cannot_manage_superadmins",
      "description": "Non-superadmin users cannot assign or revoke Mekvort superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin and Roles & Access still load correctly after superadmin assignment controls",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "controlled superadmin assignment exists",
    "controlled superadmin revocation exists",
    "only superadmins can manage superadmins",
    "admin stable"
  ]
}
QA_CONTRACT_END