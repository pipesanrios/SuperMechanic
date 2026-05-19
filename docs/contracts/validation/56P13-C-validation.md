QA_CONTRACT_START
{
  "validation_contract_id": "56P13-C-validation",
  "phase": "56P13-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "mock_connector_exists",
      "type": "file_exists",
      "target": "includes/integrations/inventory-connectors/class-mock-inventory-connector.php"
    },
    {
      "id": "mock_adapter_exists",
      "type": "file_exists",
      "target": "includes/integrations/inventory-connectors/class-mock-inventory-adapter.php"
    },
    {
      "id": "connector_service_exists",
      "type": "file_exists",
      "target": "includes/integrations/inventory-connectors/class-inventory-connector-service.php"
    },
    {
      "id": "sync_mapper_exists",
      "type": "file_exists",
      "target": "includes/integrations/inventory-connectors/class-inventory-sync-mapper.php"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-56p13-c-first-connector-prototype.md"
    },
    {
      "id": "current_state_exists",
      "type": "file_exists",
      "target": "docs/CURRENT_STATE.md"
    },
    {
      "id": "qa_report_exists",
      "type": "file_exists",
      "target": "docs/QA_REPORT.md"
    }
  ],
  "manual_checks": [
    {
      "id": "adapter_methods_defined",
      "description": "Mock adapter exposes required connector contract methods",
      "status": "NOT_RUN"
    },
    {
      "id": "normalized_payload_contract_followed",
      "description": "Mock records normalize to the generic connector payload",
      "status": "NOT_RUN"
    },
    {
      "id": "dry_run_no_db_writes",
      "description": "Dry-run reports expected operations without database writes",
      "status": "NOT_RUN"
    },
    {
      "id": "sync_simulation_no_real_import",
      "description": "Sync simulation returns simulated results without real imports or provider calls",
      "status": "NOT_RUN"
    },
    {
      "id": "forbidden_scope_untouched",
      "description": "CRM/users/process/payment/API/schema/admin UI/assets were not modified",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "mock connector prototype exists",
    "adapter exposes required methods",
    "dry-run result shape implemented",
    "sync simulation result shape implemented",
    "business scope preserved",
    "no external provider/API/OAuth/scheduled sync",
    "no forbidden module changes"
  ]
}
QA_CONTRACT_END
