QA_CONTRACT_START
{
  "validation_contract_id": "56P3-B-validation",
  "phase": "56P3-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "helpers_folder_exists",
      "type": "file_exists",
      "target": "includes/helpers"
    },
    {
      "id": "users_folder_exists",
      "type": "file_exists",
      "target": "includes/users"
    }
  ],
  "manual_checks": [
    {
      "id": "protected_superadmins_remain",
      "description": "Protected Mekvort superadmins remain after reset",
      "status": "NOT_RUN"
    },
    {
      "id": "non_protected_users_removed",
      "description": "Non-protected runtime/business users are removed by reset policy",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after reset user handling",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "protected superadmins preserved",
    "non-protected users cleaned",
    "admin stable"
  ]
}
QA_CONTRACT_END