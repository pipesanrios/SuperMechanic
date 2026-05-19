QA_CONTRACT_START
{
  "validation_contract_id": "57G-B-validation",
  "phase": "57G-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "queue_worker_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-worker.php"
    },
    {
      "id": "queue_job_repository_exists",
      "type": "file_exists",
      "target": "includes/saas/class-queue-job-repository.php"
    },
    {
      "id": "task_doc_exists",
      "type": "file_exists",
      "target": "docs/tasks/2026-04-57g-b-queue-retry-handling.md"
    },
    {
      "id": "worker_process_next_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "process_next"
    },
    {
      "id": "worker_fail_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "fail_job"
    },
    {
      "id": "worker_retry_available_at_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "get_retry_available_at"
    },
    {
      "id": "worker_retry_delay_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "get_retry_delay_seconds"
    },
    {
      "id": "repository_get_next_available_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "get_next_available_job"
    },
    {
      "id": "repository_claim_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "claim_job"
    },
    {
      "id": "repository_update_attempts_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "update_attempts"
    },
    {
      "id": "repository_schedule_retry_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "schedule_retry"
    },
    {
      "id": "repository_mark_failed_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "mark_failed"
    }
  ],
  "manual_checks": [
    {
      "id": "retry_first_failure",
      "description": "Failing job with attempts 0 becomes retry_scheduled with attempts 1 and future available_at",
      "status": "NOT_RUN"
    },
    {
      "id": "future_retry_ignored",
      "description": "Future retry_scheduled job is not picked by process_next",
      "status": "NOT_RUN"
    },
    {
      "id": "due_retry_picked",
      "description": "Due retry_scheduled job is picked by process_next",
      "status": "NOT_RUN"
    },
    {
      "id": "max_attempts_failed",
      "description": "Job reaching max attempts transitions to failed",
      "status": "NOT_RUN"
    },
    {
      "id": "lock_token_cleared",
      "description": "Lock token is cleared after retry scheduling and final failure",
      "status": "NOT_RUN"
    },
    {
      "id": "no_real_execution",
      "description": "No cron, schedule, external HTTP, provider execution, catalog import, email, PDF or Google Calendar execution",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "retry backoff helpers exist",
    "failing jobs schedule retry before max attempts",
    "future retry jobs are ignored",
    "due retry jobs are picked",
    "max attempts failures become final failed",
    "lock token clears after retry/fail",
    "no automatic execution or external calls"
  ]
}
QA_CONTRACT_END
