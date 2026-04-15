QA_CONTRACT_START
{
  "validation_contract_id": "56P5-B-validation",
  "phase": "56P5-B",
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
      "id": "single_delete_cascades_tasks",
      "description": "Deleting a single CRM opportunity also deletes its related CRM tasks",
      "status": "NOT_RUN"
    },
    {
      "id": "bulk_delete_cascades_tasks",
      "description": "Bulk deleting CRM opportunities also deletes related CRM tasks",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "CRM/admin still loads correctly after delete cascade support",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "single delete cascades",
    "bulk delete cascades",
    "admin stable"
  ]
}
QA_CONTRACT_END