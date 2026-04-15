QA_CONTRACT_START
{
  "validation_contract_id": "56P5-A-validation",
  "phase": "56P5-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "crm_folder_exists",
      "type": "file_exists",
      "target": "includes/crm"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css"
    }
  ],
  "manual_checks": [
    {
      "id": "multi_select_works",
      "description": "Multiple CRM opportunities can be selected",
      "status": "NOT_RUN"
    },
    {
      "id": "select_all_works",
      "description": "Select-all works correctly in CRM pipeline",
      "status": "NOT_RUN"
    },
    {
      "id": "bulk_action_executes",
      "description": "Bulk action executes correctly for selected opportunities",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "CRM/admin still loads correctly after bulk-action support",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "multi-select exists",
    "bulk action exists",
    "admin stable"
  ]
}
QA_CONTRACT_END