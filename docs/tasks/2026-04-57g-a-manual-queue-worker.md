# Task 57G-A - Manual Queue Worker

## Objective

Implement a controlled manual queue worker for persisted SaaS jobs without cron or scheduled execution.

## Contract

- Task contract: `docs/contracts/57G-A.md`
- Validation contract: `docs/contracts/validation/57G-A-validation.md`

## Scope

Implemented as manual one-job worker foundation only.

Allowed scope used:

- `includes/saas/class-queue-worker.php`
- `includes/saas/class-queue-job-repository.php`
- `docs/*`

No cron, hooks, scheduled workers, REST endpoint, admin UI, frontend/assets changes, external HTTP calls, real provider APIs, catalog imports, email sending, PDF generation or Google Calendar calls were introduced.

## Implementation Summary

Created:

- `Queue_Worker`

Repository helpers added:

- `get_next_available_job(array $filters = array())`
- `claim_job($job_id, $lock_token)`
- `release_lock($job_id)`
- `update_attempts($job_id, $attempts)`
- `mark_running($job_id, $lock_token)`

Worker methods:

- `process_next(array $filters = array())`
- `process_job(array $job)`
- `handle_inventory_connector_sync(array $job)`
- `fail_job($job_id, $error)`
- `complete_job($job_id, array $result)`

## Supported Job Types

57G-A supports only:

- `inventory_connector_sync`

The handler is simulation-only. It accepts payloads that explicitly indicate:

- `dry_run = true`
- operation `dry_run`
- operation `sync_simulation`
- operation `simulation`
- `simulation = true`
- passive execution flags with no worker, connector, import or external API execution

Unsafe non-simulation payloads fail without real execution.

## Runtime Smoke Result

Result: PASS

Valid simulation job:

- before: `pending`
- after: `completed`
- worker result: `completed`
- handler code: `simulation_completed`
- handler writes: `0`
- executed: `false`
- lock after completion: `null`

Invalid/unsafe job:

- after: `failed`
- last_error: `unsafe_non_simulation_payload`
- worker result: `failed`
- executed: `false`

Lock token behavior:

- claimed status: `running`
- claimed lock token: `57ga-lock-token`
- after processing: `completed`
- lock after completion: `null`
- executed: `false`

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 303
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/57G-A-validation.md --output=text` -> PASS
  - PASS: 13
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static forbidden-pattern validation -> PASS
  - no cron/hook registration in worker/repository changes
  - no external HTTP/provider execution
  - no real catalog import, email sending, PDF generation or Google Calendar execution
  - SQL remains isolated in `Queue_Job_Repository`

## Runtime State

Runtime manual smoke was executed through WordPress bootstrap.

Runtime real browser/admin is not applicable because this phase introduces no UI, REST endpoint, cron, scheduled worker, external service or background execution.

Final status: COMPLETA.

## Deferred

- automatic workers
- scheduler/cron activation
- batch processing
- retry executor
- dead-letter queue
- admin UI
- real connector handlers
- operational observability UI
