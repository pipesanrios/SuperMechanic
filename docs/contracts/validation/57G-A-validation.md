QA_CONTRACT_START
{
  "validation_contract_id": "57G-A-validation",
  "phase": "57G-A",
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
      "target": "docs/tasks/2026-04-57g-a-manual-queue-worker.md"
    },
    {
      "id": "worker_process_next_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "process_next"
    },
    {
      "id": "worker_process_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "process_job"
    },
    {
      "id": "worker_inventory_connector_handler_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "handle_inventory_connector_sync"
    },
    {
      "id": "worker_fail_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "fail_job"
    },
    {
      "id": "worker_complete_job_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-worker.php",
      "class": "Queue_Worker",
      "method": "complete_job"
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
      "id": "repository_release_lock_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "release_lock"
    },
    {
      "id": "repository_update_attempts_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "update_attempts"
    },
    {
      "id": "repository_mark_running_method",
      "type": "method_exists",
      "file": "includes/saas/class-queue-job-repository.php",
      "class": "Queue_Job_Repository",
      "method": "mark_running"
    }
  ],
  "manual_checks": [
    {
      "id": "manual_worker_valid_simulation",
      "description": "Persisted simulation inventory_connector_sync job transitions pending -> running -> completed",
      "status": "NOT_RUN"
    },
    {
      "id": "manual_worker_invalid_job",
      "description": "Invalid or unsafe job transitions to failed",
      "status": "NOT_RUN"
    },
    {
      "id": "lock_token_behavior",
      "description": "Lock token is stored during claim/running and released after completion/failure",
      "status": "NOT_RUN"
    },
    {
      "id": "single_job_only",
      "description": "process_next claims and processes at most one job",
      "status": "NOT_RUN"
    },
    {
      "id": "no_real_execution",
      "description": "No cron, schedule, external HTTP, provider execution, catalog import, email, PDF or Google Calendar execution",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "manual worker class exists",
    "repository claim helpers exist",
    "valid simulation job completes",
    "invalid job fails safely",
    "lock token behavior verified",
    "no automatic execution or external calls"
  ]
}
QA_CONTRACT_END
