# Async Queue Foundation

Phase: 57D

## Purpose

Define a passive async queue foundation for future SaaS execution without changing the current Mekvort runtime.

This foundation prepares a normalized job contract, supported job types, status vocabulary, retry concepts and dispatch result structure. It does not persist jobs, execute jobs, register workers, register schedules, call external providers or modify schema.

## Current Boundary

The current active runtime remains unchanged:

`Controller -> Service -> Repository -> Database`

Existing notification/webhook queue behavior under `includes/queue/*` remains separate. Phase 57D does not migrate, replace or rewire that active queue. The new classes under `includes/saas/*` are passive SaaS contracts for future phases.

## Passive Classes

| Class | Role |
|---|---|
| `Queue_Job_Contract` | Normalizes and validates future queue job arrays. |
| `Queue_Context` | Exposes supported job types, statuses and conceptual retry strategy. |
| `Queue_Dispatcher` | Builds normalized job results without persistence or execution. |
| `Queue_Result` | Standardizes passive dispatch results. |

## Job Structure

Every future SaaS queue job must normalize to:

| Field | Required | Notes |
|---|---:|---|
| `job_id` | yes | Local ephemeral identifier in 57D. Future persistence can replace with stored ID. |
| `job_type` | yes | One of the supported future job types. |
| `business_id` | yes | Current active isolation boundary. |
| `tenant_id` | no | Future SaaS tenant identifier; nullable in current runtime. |
| `payload` | yes | Sanitized associative payload. |
| `status` | yes | Current job state. Defaults to `pending`. |
| `attempts` | yes | Attempt count. Defaults to `0`. |
| `max_attempts` | yes | Max retry attempts. Defaults by job type. |
| `scheduled_at` | yes | Planned future execution timestamp. Passive only. |
| `created_at` | yes | Job creation timestamp. |
| `last_error` | no | Last safe error string. |

## Supported Future Job Types

| Job Type | Future Use |
|---|---|
| `inventory_import` | Large catalog CSV/import work. |
| `inventory_connector_sync` | Provider connector sync batches. |
| `email_delivery` | Email delivery retries and async delivery. |
| `webhook_delivery` | Outbound webhook retries. |
| `google_calendar_sync` | Calendar sync retries and scheduled sync. |
| `pdf_generation` | Heavy PDF generation work. |

## Dispatcher Behavior

`Queue_Dispatcher` is passive by design.

It can:
- validate the job type
- require `business_id`
- sanitize payload values
- assign default attempts and status
- return a normalized result

It cannot:
- write to database
- enqueue into active queue storage
- call external providers
- send email/webhooks
- generate PDFs
- register runtime hooks
- execute workers

## Status Model

Supported statuses:

| Status | Meaning |
|---|---|
| `pending` | Job is ready for a future worker. |
| `running` | Future worker has claimed the job. |
| `completed` | Future worker completed the job. |
| `failed` | Job exhausted retries or failed permanently. |
| `retry_scheduled` | Retry is planned after a delay. |
| `cancelled` | Job was cancelled before completion. |

## Retry Model

57D defines retry strategy conceptually only:

- strategy: `exponential_backoff_concept`
- base delay: 60 seconds
- max delay: 3600 seconds
- jitter: enabled conceptually
- scheduler: disabled
- worker: disabled

Future phases must define persistence, claim semantics, retry execution, dead-letter handling and operational visibility before activating runtime workers.

## Relationship To Existing Flows

Potential future mappings:

| Current Flow | Future Job Type |
|---|---|
| Vehicle Catalog CSV import | `inventory_import` |
| Inventory connector sync | `inventory_connector_sync` |
| Email notification delivery | `email_delivery` |
| Outbound webhook delivery | `webhook_delivery` |
| Google Calendar sync payloads | `google_calendar_sync` |
| PDF exports | `pdf_generation` |

These mappings are documentation/contract intent only. No current service is wired to the passive dispatcher in 57D.

## Non-Goals

Not included:

- schema changes
- queue table changes
- cron or scheduler activation
- worker runtime
- external queue providers
- job execution
- connector provider execution
- email/webhook dispatch changes
- API/admin UI changes

## Current Technical State

57D is complete only as a passive foundation. Runtime real is not applicable because no UI, worker, schema, external integration or active background behavior is introduced.

## 57D1 Smoke Validation

57D1 smoke validation confirms:

- all six supported job types instantiate and normalize
- required normalized job fields are present
- invalid jobs report expected validation errors
- non-array payloads remain invalid and return `payload_must_be_array`
- dispatcher results remain passive with `writes = 0` and `executed = false`
- status model exposes `pending`, `running`, `completed`, `failed`, `retry_scheduled` and `cancelled`

57D1 does not activate persistence, workers, cron, external HTTP calls, connector execution, email sending or PDF generation.

## 57E Connector Runtime Bridge

57E wires the mock inventory connector to the passive queue foundation as an intent bridge:

mock connector dry-run/sync simulation -> `inventory_connector_sync` passive queue job

The queue job payload includes connector identity, operation, dry-run flag, provider type, normalized item preview/count and validation summary.

This bridge remains passive:

- no job persistence
- no worker execution
- no cron/scheduled sync
- no external provider API
- no catalog import execution
- no database writes

## 57F Queue Persistence Foundation

57F adds opt-in persistence for future SaaS jobs without activating execution.

New table:

- `sm_saas_queue_jobs`

New repository:

- `Queue_Job_Repository`

Repository capabilities:

- create a normalized job
- retrieve by `job_id`
- list by filters
- update status
- mark completed
- mark failed
- schedule retry

`Queue_Context` now exposes `persistence_enabled`, defaulting to `false`.

`Queue_Dispatcher` behavior:

- default context: validates and normalizes only, no persistence
- persistence-enabled context: persists the normalized job and returns a passive result
- all modes: no worker execution, no cron, no external calls

Persisted queue results may report `writes = 1`, but `executed` remains `false` and `passive` remains `true`.

## 57G-A Manual Queue Worker

57G-A adds a manually invoked worker foundation for persisted SaaS queue jobs.

New class:

- `Queue_Worker`

Repository claim helpers:

- `get_next_available_job()`
- `claim_job($job_id, $lock_token)`
- `release_lock($job_id)`
- `update_attempts($job_id, ...)`
- `mark_running($job_id, $lock_token)`

Worker behavior:

- processes one job per `process_next(...)` call
- requires lock token before processing
- supports only `inventory_connector_sync` in 57G-A
- accepts only simulation/passive connector payloads
- marks safe simulation jobs completed
- marks unsafe or unsupported jobs failed

This worker is manual only. It does not register cron, hooks, scheduled events, REST endpoints, admin UI actions, external provider calls, catalog imports, email sending, PDF generation or Google Calendar calls.

## 57G-B Queue Retry Handling

57G-B adds controlled retry handling to the manual worker without activating automatic execution.

Retry behavior:

- processing failures increment attempts
- attempts are not consumed during claim
- attempts below `max_attempts` become `retry_scheduled`
- attempts reaching `max_attempts` become final `failed`
- retry scheduling clears lock state and stores `last_error`
- `process_next()` ignores future retries until `available_at <= now`

Deterministic backoff:

- attempt 1: +5 minutes
- attempt 2: +15 minutes
- attempt 3 and above: +30 minutes

57G-B does not add:

- cron
- scheduled workers
- automatic retry executor
- batch processing
- external calls
- real provider sync
- catalog import execution
- email/PDF/Google Calendar execution
