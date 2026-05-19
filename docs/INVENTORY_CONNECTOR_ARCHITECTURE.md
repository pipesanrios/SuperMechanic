# Inventory Connector Architecture Decision

Decision phase: 56P13-A

## Purpose
Define the canonical strategy for future external inventory connectors that feed reusable vehicle catalog records.

This is an architecture decision only. It does not implement connector runtime, schema, jobs, credentials, API endpoints, or UI.

## Connector Philosophy
Inventory connectors must be provider-agnostic. The internal vehicle catalog remains the canonical model, and providers are treated as adapters that translate external inventory into catalog-compatible payloads.

Core principles:
- provider logic is isolated outside `Vehicle_Catalog_Service`
- the catalog model is not shaped around a single marketplace or DMS
- connector code must not create customer vehicles directly
- connector sync must preserve `business_id`
- SQL remains in repository/database layers only
- manual catalog edits must remain possible and must not be overwritten without explicit policy

The current CSV import base is the simplest local input path. Future inventory connectors should reuse the same conceptual flow: parse/fetch external rows, normalize, validate, preview/sync, then write through catalog services.

## Proposed Architecture

Recommended future layers:

1. Connector Controller
   - WordPress/admin entrypoint for configuration, sync trigger, preview and status display.
   - Owns nonce/capability checks and request handling only.
   - Does not contain provider mapping or SQL.

2. Connector Service
   - Orchestrates sync lifecycle for one business and one connector.
   - Loads connector config, invokes adapter, invokes mapper, validates normalized inventory and delegates catalog writes.
   - Owns high-level policies: dry-run, confirm sync, stale handling, conflict mode.

3. Provider Adapter
   - Provider-specific fetch/parse client.
   - Examples: mobile.de adapter, AutoScout24 adapter, DealerCenter adapter, generic CSV/API adapter.
   - Returns raw provider inventory records and provider metadata.
   - Does not write to Super Mechanic tables.

4. Sync Mapper
   - Converts provider records into canonical inventory payloads.
   - Maps external make/model/year/trim/body/fuel/transmission/engine/notes/status into catalog-compatible fields.
   - Produces stable external identity fields for matching, conflict detection and stale detection.

5. Sync Repository
   - Persists connector config, sync mappings, external IDs, last hashes, last status, last errors and timestamps.
   - All SQL for connector sync belongs here or in database/migration layer.
   - Must always include `business_id` filters for reads and writes.

## Canonical Inventory Flow

External provider
-> provider adapter
-> raw provider records
-> sync mapper
-> normalized catalog payload
-> sync validation
-> catalog sync service
-> `Vehicle_Catalog_Service`
-> vehicle catalog

The catalog sync service should remain separate from `Vehicle_Catalog_Service`. It can decide whether a normalized provider record becomes create, update, deactivate, skipped or conflict. Actual catalog create/update/deactivate must still go through `Vehicle_Catalog_Service`.

## Canonical Internal Catalog Model

The internal target remains the reusable vehicle catalog record:
- `business_id`
- `make`
- `model`
- `year`
- `trim_version`
- `body_type`
- `fuel_type`
- `transmission`
- `engine`
- `notes`
- `status`

Provider-only details must not be forced into the core catalog unless a later contract explicitly expands schema. Until then, provider metadata belongs in connector sync mapping/config storage.

## Provider Abstraction

Future providers are adapters, not core catalog logic.

Recommended adapter types:
- `mobile_de`
  - Pulls marketplace inventory and maps provider vehicle identity to catalog identity.
- `autoscout24`
  - Pulls marketplace listings and maps localized provider fields to canonical fields.
- `dealercenter`
  - Pulls DMS/dealer inventory and maps technical fields plus stock status.
- `generic_csv_api`
  - Reuses generic CSV/API shape for custom dealer feeds and low-cost integrations.

Adapters must implement the same conceptual contract:
- identify provider key
- validate connector config
- fetch or parse remote/source records
- return raw records with stable external IDs when available
- never call catalog repositories directly

## Sync Lifecycle

Initial import:
- fetch provider records
- map to normalized payloads
- dry-run validate by business
- preview creates/updates/conflicts
- confirm import writes through catalog services

Update sync:
- fetch current provider state
- match by provider external ID first
- fall back to business-scoped catalog identity only if a future phase defines safe matching rules
- update catalog fields only according to configured ownership policy

Deactivate stale inventory:
- records previously linked to provider but missing from latest provider state may be marked inactive
- stale deactivation must be previewable and reversible by normal admin edit/reactivation
- stale logic must be business-scoped and provider-scoped

Conflict handling:
- conflict when provider payload differs from local manually-owned fields
- conflict state must be recorded, not silently overwritten
- admin should choose: keep local, accept provider, or keep as unresolved

Manual override ownership:
- fields can be provider-owned, local-owned or mixed
- local-owned fields are never overwritten by scheduled sync
- provider-owned fields may be updated by confirmed sync
- ownership policy must be per connector or per field in a future contract

## Multi-Business Strategy

Inventory connectors are business-scoped.

Rules:
- every connector config belongs to one `business_id`
- credentials/config are stored per business
- sync mappings include `business_id`
- provider fetch and sync execution must resolve a single explicit business
- no connector may read, update or deactivate catalog records for another business
- global admins may manage multiple business connectors, but runtime sync still executes one business context at a time

Required isolation:
- external IDs are unique only inside `(business_id, provider_key, connector_id)`
- duplicate provider IDs across businesses must not collide
- dry-run previews must display the selected business
- logs/errors must include business scope

## Relationship To Existing Integrations

Existing outbound connectors from phase 55D are event dispatch connectors for operational/commercial events. They should not be reused as the inventory sync core.

Useful existing patterns:
- controller/service/repository boundaries from current connectors
- standardized payload discipline from webhooks/API
- sync mapping and hash/conflict patterns from Google Calendar
- business scope resolution from `Business_Context_Service`

Inventory connectors should be a new inbound/sync family under the integration architecture, while preserving the same layering rules.

## Deferred Areas

Deferred by this decision:
- OAuth
- scheduled sync
- webhook sync
- queue workers
- retry/backoff strategy
- external media sync
- provider credential encryption policy
- schema for connector config/mappings
- connector admin UI
- runtime provider prototype

## Roadmap Alignment

Recommended future phases:

- 56P13-B Connector Contract
  - define adapter interfaces, connector config model, sync result model and validation contract

- 56P13-C First Provider Prototype
  - implement one low-risk provider or generic CSV/API adapter against the canonical flow

- 56P13-D Scheduled Sync Engine
  - add controlled scheduled/background sync, stale detection and retry policy

- 56P13-E Connector Admin UI
  - add business-scoped connector configuration, dry-run, conflict review and sync history UI

## Decision
Adopt an isolated inbound inventory connector architecture.

External providers must normalize into the internal vehicle catalog model and must write through catalog services. Provider-specific logic belongs in adapters and mappers, not in the catalog service, catalog repository or admin catalog controller.
