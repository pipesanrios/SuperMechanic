QA_CONTRACT_START
{
  "validation_contract_id": "56P4-A-validation",
  "phase": "56P4-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "admin_folder_exists",
      "type": "file_exists",
      "target": "includes/admin"
    },
    {
      "id": "css_folder_exists",
      "type": "file_exists",
      "target": "assets/css"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_renders",
      "description": "Dashboard loads correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "cards_layout_applied",
      "description": "Metrics are displayed as cards/grid instead of rows",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors in dashboard",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "dashboard uses card/grid layout",
    "metrics readable",
    "admin stable"
  ]
}
QA_CONTRACT_END