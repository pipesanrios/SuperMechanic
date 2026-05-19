# Task 57G-B - Queue Retry Handling

## Objective

Implement controlled retry handling for the manual SaaS queue worker without cron or automatic workers.

## Contract

- Task contract: `docs/contracts/57G-B.md`
- Validation contract: `docs/contracts/validation/57G-B-validation.md`

## Scope

Implemented as manual retry handling only.

Allowed scope used:

- `includes/saas/class-queue-worker.php`
- `includes/saas/class-queue-job-repository.php`
- `includes/saas/class-queue-context.php`
- `docs/*`

No cron, hooks, scheduled workers, REST endpoint, admin UI, frontend/assets changes, external HTTP calls, real provider APIs, catalog imports, email sending, PDF generation or Google Calendar calls were introduced.

## Implementation Summary

Worker retry behavior:

- failed manual processing increments attempts
- failures below `max_attempts` schedule `retry_scheduled`
- failures reaching `max_attempts` become final `failed`
- retry scheduling clears `locked_at` and `lock_token`
- retry scheduling stores `last_error`
- `process_next()` continues to pick only `pending` jobs and due `retry_scheduled` jobs

Repository behavior:

- `claim_job()` no longer increments attempts during claim
- attempts are updated only when processing fails
- `get_next_available_job()` continues to ignore future `available_at`

## Backoff Strategy

Deterministic manual backoff:

- attempt 1: +5 minutes
- attempt 2: +15 minutes
- attempt 3 and above: +30 minutes

## Runtime Smoke Result

Result: PASS

First failing job:

- worker status: `retry_scheduled`
- stored status: `retry_scheduled`
- attempts: `1`
- max attempts: `3`
- available_at future: `true`
- lock_token: `null`
- last_error: `unsafe_non_simulation_payload`
- executed: `false`

Future retry job:

- worker status: `skipped`
- message: `no_available_job`

Due retry job:

- worker status: `retry_scheduled`
- stored status: `retry_scheduled`
- attempts: `2`
- lock_token: `null`
- executed: `false`

Max attempts job:

- worker status: `failed`
- stored status: `failed`
- attempts: `3`
- max attempts: `3`
- lock_token: `null`
- last_error: `unsafe_non_simulation_payload`
- executed: `false`

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 303
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/57G-B-validation.md --output=text` -> PASS
  - PASS: 12
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 6 manual checks
- static forbidden-pattern validation -> PASS
  - no cron/hook registration in worker/repository/context changes
  - no external HTTP execution
  - no real catalog import, email sending, PDF generation or Google Calendar execution
  - SQL remains isolated in `Queue_Job_Repository`

## Runtime State

Runtime manual smoke was executed through WordPress bootstrap.

Runtime real browser/admin validation is not applicable because this phase introduces no UI, REST endpoint, cron, scheduled worker, external service or background execution.

Final status: COMPLETA.

## Deferred

- automatic workers
- scheduler/cron activation
- batch processing
- retry executor daemon
- dead-letter queue
- admin UI
- real connector handlers
- operational observability UI
