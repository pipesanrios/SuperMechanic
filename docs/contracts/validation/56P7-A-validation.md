QA_CONTRACT_START
{
  "validation_contract_id": "56P7-A-validation",
  "phase": "56P7-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "dashboard_folder_exists",
      "type": "file_exists",
      "target": "includes/dashboard"
    },
    {
      "id": "portal_folder_exists",
      "type": "file_exists",
      "target": "includes/portal"
    }
  ],
  "manual_checks": [
    {
      "id": "client_panel_loads",
      "description": "Client panel base loads correctly for authenticated client",
      "status": "NOT_RUN"
    },
    {
      "id": "client_sections_work",
      "description": "Client can access panel sections/resources coherently",
      "status": "NOT_RUN"
    },
    {
      "id": "client_data_renders",
      "description": "Client-facing data still renders correctly inside the new panel base",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors on client panel",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "client panel base exists",
    "client sections are coherent",
    "frontend stable"
  ]
}
QA_CONTRACT_END