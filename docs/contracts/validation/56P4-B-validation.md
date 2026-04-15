QA_CONTRACT_START
{
  "validation_contract_id": "56P4-B-validation",
  "phase": "56P4-B",
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
      "id": "reporting_renders",
      "description": "Reporting page loads correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_cards_layout_applied",
      "description": "Reporting metrics are displayed as cards/grid instead of rows",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors in reporting page",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "reporting uses card/grid layout",
    "metrics readable",
    "admin stable"
  ]
}
QA_CONTRACT_END