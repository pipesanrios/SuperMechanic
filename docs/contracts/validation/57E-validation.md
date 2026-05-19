QA_CONTRACT_START
{
  "validation_contract_id": "57E-validation",
  "phase": "57E",
  "type": "validation",
  "automated_checks": [
    {
      "id": "inventory_connector_service_exists",
      "type": "file_exists",
      "target": "includes/integrations/inventory-connectors/class-inventory-connector-service.php"
    },
    {
      "id": "queue_dispatcher_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-dispatcher.php"
    },
    {
      "id": "queue_job_contract_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-job-contract.php"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-57e-connector-runtime-wiring.md"
    },
    {
      "id": "build_sync_job_method",
      "type": "method_exists",
      "file": "includes/integrations/inventory-connectors/class-inventory-connector-service.php",
      "class": "Inventory_Connector_Service",
      "method": "build_sync_job"
    },
    {
      "id": "build_dry_run_job_method",
      "type": "method_exists",
      "file": "includes/integrations/inventory-connectors/class-inventory-connector-service.php",
      "class": "Inventory_Connector_Service",
      "method": "build_dry_run_job"
    },
    {
      "id": "dispatch_sync_intent_method",
      "type": "method_exists",
      "file": "includes/integrations/inventory-connectors/class-inventory-connector-service.php",
      "class": "Inventory_Connector_Service",
      "method": "dispatch_sync_intent"
    },
    {
      "id": "dispatch_dry_run_intent_method",
      "type": "method_exists",
      "file": "includes/integrations/inventory-connectors/class-inventory-connector-service.php",
      "class": "Inventory_Connector_Service",
      "method": "dispatch_dry_run_intent"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_smoke_dry_run_job",
      "description": "Mock connector dry-run builds passive inventory_connector_sync queue job",
      "status": "NOT_RUN"
    },
    {
      "id": "runtime_smoke_sync_intent_job",
      "description": "Mock connector sync simulation builds passive inventory_connector_sync queue job",
      "status": "NOT_RUN"
    },
    {
      "id": "dispatcher_passive_result",
      "description": "Queue result remains passive with writes=0 and executed=false",
      "status": "NOT_RUN"
    },
    {
      "id": "no_real_execution",
      "description": "No DB writes, real import, worker, cron, external HTTP, email, PDF or provider API execution",
      "status": "NOT_RUN"
    },
    {
      "id": "static_safety_checks",
      "description": "Forbidden pattern searches are clean for 57E scope",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "dry-run intent builds passive inventory_connector_sync job",
    "sync intent builds passive inventory_connector_sync job",
    "job payload contains connector identity, operation, dry_run flag, provider_type, normalized item summary and validation summary",
    "dispatcher does not persist or execute jobs",
    "no real providers, cron, workers, DB writes or schema changes"
  ]
}
QA_CONTRACT_END
