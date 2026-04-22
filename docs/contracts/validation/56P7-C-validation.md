QA_CONTRACT_START
{
  "validation_contract_id": "56P7-C-validation",
  "phase": "56P7-C",
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
      "id": "mechanic_panel_loads",
      "description": "Mechanic panel loads correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "mechanic_labels_clear",
      "description": "Mechanic panel labels/sections are clear and usable",
      "status": "NOT_RUN"
    },
    {
      "id": "mechanic_actions_work",
      "description": "Mechanic panel actions remain understandable and usable",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors on mechanic panel",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "mechanic panel clearer",
    "ux improved",
    "frontend stable"
  ]
}
QA_CONTRACT_END