# Task 57F - Queue Persistence Foundation

## Objective

Create persistent queue storage foundation for SaaS jobs without executing workers.

## Contract

- Task contract: `docs/contracts/57F.md`
- Validation contract: `docs/contracts/validation/57F-validation.md`

## Scope

Implemented as persistence foundation only.

Allowed scope used:

- `includes/database/class-schema.php`
- `includes/saas/class-queue-job-repository.php`
- `includes/saas/class-queue-context.php`
- `includes/saas/class-queue-dispatcher.php`
- `includes/saas/class-queue-result.php`
- `docs/*`

No workers, cron, background execution, external HTTP calls, connector execution, email sending, API endpoint, admin UI or frontend/assets changes were introduced.

## Schema

Schema version updated:

- from `1.21.0`
- to `1.22.0`

New table registered:

- `sm_saas_queue_jobs`

Columns:

- `id`
- `job_id`
- `job_type`
- `business_id`
- `tenant_id`
- `payload_json`
- `status`
- `attempts`
- `max_attempts`
- `scheduled_at`
- `available_at`
- `locked_at`
- `lock_token`
- `last_error`
- `created_at`
- `updated_at`

Indexes:

- `job_id` unique
- `job_type`
- `business_id`
- `status`
- `available_at`
- `scheduled_at`

## Repository

Created:

- `includes/saas/class-queue-job-repository.php`

Methods:

- `create_job(array $job)`
- `get_job_by_id($job_id)`
- `list_jobs(array $filters = array())`
- `update_status($job_id, $status, array $meta = array())`
- `mark_failed($job_id, $error)`
- `mark_completed($job_id)`
- `schedule_retry($job_id, $available_at, $error = '')`

SQL remains isolated in the repository/database layer.

## Dispatcher And Context

`Queue_Context` now supports:

- `persistence_enabled`
- default: `false`

`Queue_Dispatcher` behavior:

- default context remains passive and non-persistent
- when persistence is explicitly enabled, it persists the normalized job
- it never executes jobs
- persisted results keep `executed = false` and `passive = true`

## Runtime Smoke Result

Result: PASS

Table:

- `wp_sm_saas_queue_jobs` exists: PASS

Repository:

- create job -> PASS (`insert_id = 1`)
- retrieve job -> PASS
- update status to `running` -> PASS
- schedule retry -> PASS (`status = retry_scheduled`)
- mark completed -> PASS (`status = completed`)

Dispatcher:

- default dispatcher -> PASS
  - `persisted = false`
  - `writes = 0`
  - `executed = false`
  - lookup by job ID returns no persisted row
- persistence-enabled dispatcher -> PASS
  - `persisted = true`
  - `writes = 1`
  - `executed = false`
  - lookup by job ID returns persisted row

Post-smoke cleanup:

- pending smoke jobs with payload source prefix `57f_` marked as `completed`
- no worker executed them

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 302
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/57F-validation.md --output=text` -> PASS automated checks
  - PASS: 12
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5 manual checks
- static forbidden-pattern validation -> PASS
  - SQL/`$wpdb` only found in `includes/database/class-schema.php` and `includes/saas/class-queue-job-repository.php`
  - no cron/hook registration found in 57F scope
  - no external HTTP, worker, connector execution, email or PDF execution calls found in 57F scope

## Runtime State

Runtime persistence smoke was executed through WordPress bootstrap.

Runtime real browser/admin is not applicable because this phase introduces no UI, API endpoint, worker, cron, external service or background execution.

Final status: COMPLETA

## Deferred

- worker runtime
- scheduler/cron activation
- job claiming execution lifecycle
- dead-letter queue
- retention/pruning
- queue admin UI
- external queue provider
- high-volume performance tuning beyond initial indexes
