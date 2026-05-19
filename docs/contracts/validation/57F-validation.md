QA_CONTRACT_START
{
  "validation_contract_id": "57F-validation",
  "phase": "57F",
  "type": "validation",
  "automated_checks": [
    {
      "id": "queue_job_repository_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-job-repository.php"
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
      "id": "schema_exists",
      "type": "file_exists",
      "target": "includes/database/class-schema.php"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-57f-queue-persistence-foundation.md"
    },
    {
      "id": "repository_create_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "create_job"
    },
    {
      "id": "repository_get_job_by_id_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "get_job_by_id"
    },
    {
      "id": "repository_list_jobs_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "list_jobs"
    },
    {
      "id": "repository_update_status_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "update_status"
    },
    {
      "id": "repository_mark_failed_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "mark_failed"
    },
    {
      "id": "repository_mark_completed_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "mark_completed"
    },
    {
      "id": "repository_schedule_retry_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "schedule_retry"
    }
  ],
  "manual_checks": [
    {
      "id": "schema_table_registered",
      "description": "sm_saas_queue_jobs schema is registered with required columns and indexes",
      "status": "NOT_RUN"
    },
    {
      "id": "repository_runtime_persistence",
      "description": "Repository can create, retrieve, update, complete, fail and schedule retry for jobs",
      "status": "NOT_RUN"
    },
    {
      "id": "dispatcher_passive_default",
      "description": "Dispatcher default remains passive and non-persistent",
      "status": "NOT_RUN"
    },
    {
      "id": "dispatcher_optional_persistence",
      "description": "Dispatcher persists only when persistence is explicitly enabled",
      "status": "NOT_RUN"
    },
    {
      "id": "no_real_execution",
      "description": "No cron, worker, external HTTP, connector execution, email sending or API endpoint was added",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "queue schema registered",
    "queue repository CRUD/status/retry methods exist",
    "repository persistence works in runtime smoke",
    "dispatcher default does not persist",
    "dispatcher persistence enabled stores job without execution",
    "no workers, cron, external calls or job execution"
  ]
}
QA_CONTRACT_END
