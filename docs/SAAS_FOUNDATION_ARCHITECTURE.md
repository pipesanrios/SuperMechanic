# SaaS Foundation Architecture

Phase: 57A-57F

## Purpose

Define the first SaaS foundation layer for Mekvort without changing the current self-hosted plugin runtime.

This foundation introduces passive context abstractions and opt-in queue persistence only. It does not enable billing, external infrastructure, tenant database split, queue workers, real connector execution, API redesign or runtime rewiring.

## Strategy

The current architecture remains the source of truth:

`Controller -> Service -> Repository -> Database`

The SaaS foundation layer is isolated under `includes/saas/*` and prepares future runtime decisions around:

- runtime mode
- tenant context
- license/subscription context
- async job contracts

The current business-scoped model remains active. Existing services continue to use `business_id` and current permission/business ownership rules.

## Runtime Modes

`Runtime_Context` supports three explicit modes:

| Mode | Meaning | Current Behavior |
|---|---|---|
| `self_hosted` | Current plugin deployment model. | Default mode. |
| `saas_future` | Reserved for future SaaS-hosted execution. | Defined only, not active. |
| `local_development` | Local/dev diagnostic mode. | Defined only, not auto-wired. |

Unknown modes normalize to `self_hosted` to preserve backward compatibility.

## Tenant Evolution

`Tenant_Context` introduces a future-safe abstraction with:

- `tenant_id`: reserved for future SaaS tenancy.
- `business_id`: current active scope.
- `source`: currently `business_scope`.

Rules:

- `business_id` remains the active isolation boundary.
- future `tenant_id` does not replace business scope in this phase.
- no tenant database split is introduced.
- no existing ownership checks are bypassed.
- when a `Business_Context_Service` is provided, business resolution delegates to the existing business scope resolver.

## 57B Tenant Context Bridge

`Tenant_Context` now acts as a passive bridge between future SaaS tenancy and the current plugin runtime:

- `business_id` remains the canonical active scope.
- `tenant_id` is nullable by default.
- `tenant_id` may be represented as a derived placeholder only when explicitly built from a future SaaS runtime context.
- `from_business_id($business_id)` creates a context from current business scope.
- `from_runtime_context(...)` creates a passive future-aware context without taking over runtime behavior.
- `Runtime_Context::get_tenant_context()` exposes the bridge for future composition layers.

This does not introduce hooks, schema changes, runtime takeover, API changes, frontend changes, billing or tenant DB split.

## Licensing Strategy

`License_Context` prepares a passive SaaS-ready license/subscription payload:

- `license_key`
- `subscription_status`
- `plan_type`
- `instance_id`

It does not:

- activate billing providers
- call Stripe or external billing APIs
- enforce subscriptions
- alter current local license behavior
- write options or database rows

Current local license enforcement remains owned by the existing licensing service.

## 57C Licensing And Subscription Core

`License_Context` now bridges current local license state into a future SaaS subscription abstraction without changing enforcement.

Passive bridge behavior:

- current `License_Service` can be read through `License_Context::from_license_service(...)`
- current license activation, deactivation, trial and plan-limit behavior remains owned by existing licensing services
- no billing provider is active
- no external subscription authority is called
- no schema is added
- no enforcement takeover occurs

`Subscription_Context` exposes:

- `status`
- `plan`
- `source`
- `renewal_at`
- `expires_at`
- `entitlements`

Entitlement snapshots use the normalized keys:

- `max_businesses`
- `max_users`
- `max_vehicles`
- `max_webhooks`
- `feature_flags`

Instance identity is generated locally from the WordPress site URLs and is not registered externally.

## Queue And Async Strategy

`Saas_Bootstrap` exposes passive contracts for future async execution:

- `queue_jobs`
- `retry_jobs`
- `scheduled_sync_jobs`

57D adds a dedicated passive queue foundation under `includes/saas/*`:

- `Queue_Job_Contract`
- `Queue_Context`
- `Queue_Dispatcher`
- `Queue_Result`

Supported future job types:

- `inventory_import`
- `inventory_connector_sync`
- `email_delivery`
- `webhook_delivery`
- `google_calendar_sync`
- `pdf_generation`

Supported future statuses:

- `pending`
- `running`
- `completed`
- `failed`
- `retry_scheduled`
- `cancelled`

57F adds persistent storage for future SaaS jobs:

- table: `sm_saas_queue_jobs`
- repository: `Queue_Job_Repository`
- schema version: `1.22.0`
- opt-in dispatcher persistence through `Queue_Context::persistence_enabled`

The dispatcher default still validates and normalizes job payloads without persistence. When persistence is explicitly enabled, it stores the normalized job and still does not execute it.

57G-A adds manual-only queue processing:

- class: `Queue_Worker`
- invocation: manual/test harness only
- processing: one persisted job per call
- supported handler: simulation-only `inventory_connector_sync`
- lock model: repository claim with `lock_token`
- completion/failure: persisted status updates only

57G-A does not activate cron, scheduled workers, external providers, catalog imports, email sending, PDF generation, Google Calendar calls, REST endpoints or admin UI.

57G-B adds manual retry handling to the persisted SaaS queue foundation:

- failed manual processing increments attempts
- failures below max attempts are scheduled as `retry_scheduled`
- final failures are marked `failed` once max attempts are reached
- retry delay is deterministic:
  - attempt 1: +5 minutes
  - attempt 2: +15 minutes
  - attempt 3 and above: +30 minutes
- future retries are ignored until `available_at <= now`
- lock state is cleared after retry scheduling or final failure

57G-B still does not activate cron, scheduled workers, automatic retry execution, batch workers, external providers, catalog imports, email sending, PDF generation, Google Calendar calls, REST endpoints or admin UI.

This phase does not register cron, queue consumers, external workers or scheduled provider sync.

## Relationship To Vehicle Catalog And Connectors

The Vehicle Catalog remains the canonical internal inventory model.

Inventory connectors remain provider-agnostic and must continue to normalize provider payloads into catalog-compatible records. The SaaS foundation only prepares future runtime and async context for those connectors.

## Non-Goals

Not included in 57A:

- SaaS billing
- Stripe integration
- OAuth provider integration
- tenant database split
- external infrastructure
- real queue workers
- scheduled sync runtime
- API redesign
- runtime bootstrap rewiring
- schema changes

## Current Technical State

57A is a passive bootstrap layer. It is safe to autoload and instantiate for diagnostics or future composition work, but it is not wired into plugin startup.

The default runtime mode remains self-hosted and the active tenant boundary remains `business_id`.
