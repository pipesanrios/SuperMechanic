QA_CONTRACT_START
{
  "validation_contract_id": "57D-validation",
  "phase": "57D",
  "type": "validation",
  "automated_checks": [
    {
      "id": "saas_folder_exists",
      "type": "file_exists",
      "target": "includes/saas"
    },
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
      "id": "async_queue_doc_exists",
      "type": "file_exists",
      "target": "docs/ASYNC_QUEUE_FOUNDATION.md"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-57d-async-queue-foundation.md"
    },
    {
      "id": "queue_job_contract_to_array_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-contract.php",
      "class": "Queue_Job_Contract",
      "method": "to_array"
    },
    {
      "id": "queue_context_job_types_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-context.php",
      "class": "Queue_Context",
      "method": "get_supported_job_types"
    },
    {
      "id": "queue_context_statuses_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-context.php",
      "class": "Queue_Context",
      "method": "get_supported_statuses"
    },
    {
      "id": "queue_dispatcher_build_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-dispatcher.php",
      "class": "Queue_Dispatcher",
      "method": "build_job"
    },
    {
      "id": "queue_dispatcher_dispatch_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-dispatcher.php",
      "class": "Queue_Dispatcher",
      "method": "dispatch"
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
      "id": "no_wpdb_usage",
      "description": "No $wpdb/SQL usage introduced in includes/saas/*",
      "status": "NOT_RUN"
    },
    {
      "id": "no_cron_registration",
      "description": "No wp_schedule_event, add_action, add_filter or worker hook registration introduced by 57D",
      "status": "NOT_RUN"
    },
    {
      "id": "no_external_http_calls",
      "description": "No wp_remote_*, cURL or external queue provider calls introduced",
      "status": "NOT_RUN"
    },
    {
      "id": "no_runtime_hooks_execute_jobs",
      "description": "Dispatcher builds normalized jobs only and never executes them",
      "status": "NOT_RUN"
    },
    {
      "id": "no_schema_changes",
      "description": "No schema/database files modified",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "passive queue contract classes exist",
    "future job types are documented and exposed",
    "status and retry model are documented and exposed",
    "dispatcher validates and normalizes without persisting or executing",
    "no cron/workers/schema/external calls are introduced"
  ]
}
QA_CONTRACT_END
