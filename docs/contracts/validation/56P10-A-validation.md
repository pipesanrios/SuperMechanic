QA_CONTRACT_START
{
  "validation_contract_id": "56P10-A-validation",
  "phase": "56P10-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "api_folder_exists",
      "type": "file_exists",
      "target": "includes/api"
    }
  ],
  "manual_checks": [
    {
      "id": "routes_inventoried",
      "description": "REST API routes are inventoried",
      "status": "NOT_RUN"
    },
    {
      "id": "permission_callbacks_reviewed",
      "description": "Permission callbacks are reviewed",
      "status": "NOT_RUN"
    },
    {
      "id": "auth_gaps_documented",
      "description": "API authentication gaps are documented",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "API auth model understood",
    "routes documented",
    "next hardening phase scoped"
  ]
}
QA_CONTRACT_END