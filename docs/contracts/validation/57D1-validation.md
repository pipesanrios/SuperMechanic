QA_CONTRACT_START
{
  "validation_contract_id": "57D1-validation",
  "phase": "57D1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "queue_job_contract_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-job-contract.php"
    },
    {
      "id": "queue_context_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-context.php"
    },
    {
      "id": "queue_dispatcher_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-dispatcher.php"
    },
    {
      "id": "queue_result_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-result.php"
    },
    {
      "id": "async_queue_foundation_doc_exists",
      "type": "file_exists",
      "target": "docs/ASYNC_QUEUE_FOUNDATION.md"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-57d1-async-queue-runtime-smoke-validation.md"
    },
    {
      "id": "queue_job_is_valid_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-contract.php",
      "class": "Queue_Job_Contract",
      "method": "is_valid"
    },
    {
      "id": "queue_job_validation_errors_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-contract.php",
      "class": "Queue_Job_Contract",
      "method": "get_validation_errors"
    },
    {
      "id": "queue_context_statuses_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-context.php",
      "class": "Queue_Context",
      "method": "get_supported_statuses"
    },
    {
      "id": "queue_dispatcher_dry_run_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-dispatcher.php",
      "class": "Queue_Dispatcher",
      "method": "dry_run"
    },
    {
      "id": "queue_result_to_array_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-result.php",
      "class": "Queue_Result",
      "method": "to_array"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_smoke_valid_jobs",
      "description": "Valid queue jobs instantiate and normalize for all supported future job types",
      "status": "NOT_RUN"
    },
    {
      "id": "runtime_smoke_invalid_jobs",
      "description": "Invalid queue jobs return expected validation errors",
      "status": "NOT_RUN"
    },
    {
      "id": "dispatcher_passive_behavior",
      "description": "Dispatcher returns passive result without writes or execution",
      "status": "NOT_RUN"
    },
    {
      "id": "no_runtime_side_effects",
      "description": "No persistence, cron, HTTP calls, schema changes, connector execution, email sending or PDF generation",
      "status": "NOT_RUN"
    },
    {
      "id": "static_safety_checks",
      "description": "Forbidden pattern searches are clean for 57D1 scope",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "valid queue jobs normalize for six supported job types",
    "invalid queue jobs fail with expected errors",
    "dispatcher remains passive",
    "status model exposes all required statuses",
    "no real workers, cron, DB writes, external calls or schema changes"
  ]
}
QA_CONTRACT_END
