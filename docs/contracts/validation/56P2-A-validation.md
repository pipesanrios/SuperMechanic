QA_CONTRACT_START
{
  "validation_contract_id": "56P2-A-validation",
  "phase": "56P2-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "plugin_file_exists",
      "type": "file_exists",
      "target": "super-mechanic.php"
    },
    {
      "id": "users_folder_exists",
      "type": "file_exists",
      "target": "includes/users"
    }
  ],
  "manual_checks": [
    {
      "id": "primary_admin_bootstrapped",
      "description": "Primary WordPress admin becomes Mekvort superadmin on activation/baseline bootstrap",
      "status": "NOT_RUN"
    },
    {
      "id": "other_admins_not_auto_promoted",
      "description": "Other WordPress admins are not automatically promoted to Mekvort superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after superadmin bootstrap wiring",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "superadmin bootstrap exists",
    "primary admin only auto-promoted",
    "admin stable"
  ]
}
QA_CONTRACT_END