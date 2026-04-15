QA_CONTRACT_START
{
  "validation_contract_id": "56P5-C-validation",
  "phase": "56P5-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "crm_folder_exists",
      "type": "file_exists",
      "target": "includes/crm"
    }
  ],
  "manual_checks": [
    {
      "id": "single_delete_state_consistent",
      "description": "CRM remains consistent after single delete flow",
      "status": "NOT_RUN"
    },
    {
      "id": "bulk_delete_state_consistent",
      "description": "CRM remains consistent after bulk delete flow",
      "status": "NOT_RUN"
    },
    {
      "id": "update_flows_consistent",
      "description": "CRM update/status flows remain consistent after hardening",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "CRM/admin still loads correctly after consistency hardening",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "crm consistency improved",
    "delete/bulk flows coherent",
    "admin stable"
  ]
}
QA_CONTRACT_END