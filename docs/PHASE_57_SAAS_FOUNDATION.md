# Phase 57 — SaaS Foundation

## Purpose

Phase 57 defines the official SaaS Foundation roadmap after the Phase 56P pre-SaaS stable baseline.

This is a roadmap and documentation phase only. It does not implement runtime code, schema changes, API changes, migrations, queue workers, provider integrations, billing providers, or SaaS orchestration.

## Baseline

Phase 56P is the current `PRE-SAAS STABLE BASELINE`.

Established foundations:
- `Controller -> Service -> Repository -> Database`
- business-scoped isolation through `business_id`
- hardened permission and API auth model
- Vehicle Catalog as canonical internal inventory model
- CSV import base for catalog records
- provider-agnostic inventory connector strategy
- generic connector contract
- mock connector prototype

Canonical connector references:
- `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`
- `docs/INVENTORY_CONNECTOR_CONTRACT.md`

## Official Macrophase

Phase 57 — SaaS Foundation prepares Mekvort for SaaS-scale operation while preserving the current plugin runtime.

The phase should evolve tenancy, licensing, async processing, connector execution, media strategy and operational diagnostics without weakening the existing architecture boundaries.

## 57A — SaaS Foundation Bootstrap

Scope:
- passive runtime context
- passive tenant context
- passive license context
- queue placeholder contracts

Intent:
- initialize SaaS foundation classes without wiring them into runtime
- preserve default self-hosted behavior
- define future context objects without schema, API, worker or billing changes

## 57B — Tenant Context Layer

Scope:
- tenant context bridge
- business scope bridge
- runtime context access to passive tenant context
- no runtime takeover

Intent:
- formalize that `tenant_id` is a future SaaS abstraction
- preserve `business_id` as the active runtime scope
- avoid automatic migration, tenant DB split or resolver takeover
- prepare later tenant-aware runtime contracts without changing current behavior

## 57C — SaaS Licensing

Scope:
- activation flow
- plan validation
- subscription state
- license enforcement
- centralized licensing architecture

Intent:
- evolve local licensing into SaaS-ready licensing
- define license state, plan state and subscription state boundaries
- preserve controlled enforcement rather than broad read-blocking
- prepare centralized license validation without implementing a billing provider in this roadmap task

## 57D — Async Jobs / Queues

Scope:
- imports
- notifications
- retries
- sync queues
- async processing strategy

Intent:
- move long-running work out of request/response flows
- support large imports, notification delivery, connector sync and retryable tasks
- define job ownership, idempotency and business/tenant scope
- preserve current synchronous plugin behavior until explicit runtime contracts implement queues

## 57E — Connector Runtime

Scope:
- first real provider
- scheduled sync
- retry handling
- connector execution runtime
- sync orchestration

Intent:
- evolve 56P13 connector architecture into runtime execution
- keep provider logic isolated in adapters
- preserve Vehicle Catalog as the canonical internal target
- support dry-run, confirmed sync, stale detection, conflict handling and retry-safe behavior

## 57F — Queue Persistence Foundation

Scope:
- persistent SaaS queue table
- queue job repository
- opt-in dispatcher persistence
- retry/status persistence foundation
- no worker execution

Intent:
- persist normalized SaaS queue jobs for future workers
- keep dispatcher passive by default
- preserve `business_id` isolation
- prepare retry and status lifecycle storage without cron or workers
- avoid external queue providers until a later phase defines runtime execution

## 57G — Media Architecture

Scope:
- image sync
- external storage strategy
- CDN readiness
- media ownership model
- connector media lifecycle

Intent:
- define how external inventory media is ingested, owned, stored and retired
- avoid direct provider media dependency in operational views
- prepare CDN/storage abstraction for SaaS deployment
- preserve secure file/media access boundaries

## 57H — SaaS Operations

Scope:
- telemetry
- health checks
- centralized admin
- runtime diagnostics
- SaaS operational tooling

Intent:
- add operational observability for SaaS support
- define health/status checks for licensing, queues, connectors, media and API auth
- prepare centralized administration without bypassing tenant isolation
- provide diagnostics that help support without exposing secrets or cross-tenant data

## Phase 57 Principles

- Preserve current architecture:
  - `Controller -> Service -> Repository -> Database`
- Preserve Vehicle Catalog as the canonical internal inventory model.
- Preserve provider-agnostic connector strategy.
- Preserve business scope isolation and evolve it carefully toward tenant scope.
- Prefer async-first future architecture for long-running work.
- Keep SaaS-ready evolution backward compatible with the current plugin runtime.
- Keep provider-specific logic outside catalog services and repositories.
- Keep SQL in repository/database layers only.
- Keep credentials, media and logs business/tenant scoped.

## Deferred / Not Included

Explicitly not included in this roadmap alignment:

- no full multiserver orchestration yet
- no Kubernetes/container orchestration yet
- no billing provider implementation yet
- no real connector providers yet
- no websocket/live sync yet
- no queue workers implementation yet
- no schema changes
- no runtime API implementation
- no migrations
- no admin UI implementation

## Validation Notes

- Documentation-only phase.
- No `includes/*` changes.
- No `assets/*` changes.
- No schema/database changes.
- No runtime/API changes.
- No SaaS runtime code is implemented by this document.
