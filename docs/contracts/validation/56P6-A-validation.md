QA_CONTRACT_START
{
  "validation_contract_id": "56P6-A-validation",
  "phase": "56P6-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "users_folder_exists",
      "type": "file_exists",
      "target": "includes/users"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css"
    }
  ],
  "manual_checks": [
    {
      "id": "roles_access_page_renders",
      "description": "Roles & Access page loads correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "roles_access_readable",
      "description": "Rows/columns/controls are readable and stable",
      "status": "NOT_RUN"
    },
    {
      "id": "superadmin_clearly_differentiated",
      "description": "Superadmin row is clearly differentiated and not confusing",
      "status": "NOT_RUN"
    },
    {
      "id": "roles_actions_still_work",
      "description": "Current actions/flows still work correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors on Roles & Access page",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "roles access stable",
    "ui readable",
    "admin stable"
  ]
}
QA_CONTRACT_END