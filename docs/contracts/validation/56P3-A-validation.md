QA_CONTRACT_START
{
  "validation_contract_id": "56P3-A-validation",
  "phase": "56P3-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "helpers_folder_exists",
      "type": "file_exists",
      "target": "includes/helpers"
    },
    {
      "id": "database_folder_exists",
      "type": "file_exists",
      "target": "includes/database"
    }
  ],
  "manual_checks": [
    {
      "id": "operational_data_reset_works",
      "description": "Clients, vehicles, processes and commercial runtime data are cleared by the reset engine",
      "status": "NOT_RUN"
    },
    {
      "id": "crm_task_notification_reset_works",
      "description": "CRM pipeline, tasks and notifications are cleared by the reset engine",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after reset engine implementation",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "central reset engine exists",
    "operational data reset works",
    "admin stable"
  ]
}
QA_CONTRACT_END