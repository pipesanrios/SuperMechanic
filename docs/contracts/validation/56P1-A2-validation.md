QA_CONTRACT_START
{
  "validation_contract_id": "56P1-A2-validation",
  "phase": "56P1-A2",
  "type": "validation",
  "automated_checks": [
    {
      "id": "admin_menu_file_exists",
      "type": "file_exists",
      "target": "includes/class-admin-menu.php"
    }
  ],
  "manual_checks": [
    {
      "id": "top_level_menu_is_mekvort",
      "description": "Top-level admin menu label shows Mekvort",
      "status": "NOT_RUN"
    },
    {
      "id": "safe_menu_titles_aligned",
      "description": "Visible menu-linked titles are aligned where applicable",
      "status": "NOT_RUN"
    },
    {
      "id": "no_roles_access_regression",
      "description": "Roles & Access and other admin screens still load correctly",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "safe visible menu rename applied",
    "technical identifiers unchanged",
    "admin stable"
  ]
}
QA_CONTRACT_END