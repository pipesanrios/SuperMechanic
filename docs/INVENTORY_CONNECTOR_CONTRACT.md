# Inventory Connector Contract

## Purpose

This document defines the generic technical contract that every future inventory connector must follow.

Inventory connectors are inbound integrations that normalize external inventory into the internal Vehicle Catalog model. They must stay provider-agnostic, business-scoped, and isolated from customer vehicle creation, CRM, payment, users, and frontend portal logic.

This is an architecture and documentation contract only. It does not implement runtime connector code, schema changes, credentials storage, queues, scheduled sync, webhooks, or provider adapters.

## Source Of Truth

- Architecture decision: `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md`
- Current catalog service layer: `Vehicle_Catalog_Service`
- CSV import base: `Vehicle_Catalog_Import_Service`
- Current state: `docs/CURRENT_STATE.md`

## Connector Identity

Every connector must define and preserve the following identity fields:

| Field | Required | Description |
|---|---:|---|
| `connector_key` | yes | Stable machine key for the connector, for example `mobile_de`, `autoscout24`, `dealercenter`, or `generic_csv_api`. |
| `provider_name` | yes | Human-readable provider name. |
| `provider_type` | yes | Provider family, for example `marketplace`, `dms`, `csv`, `api`, or `custom`. |
| `version` | yes | Connector contract or adapter version used for mapping and compatibility checks. |
| `business_id` | yes | Internal business scope. All connector reads, writes, credentials, logs, and sync reports must be isolated by this value. |

Connector identity must be available in dry-run reports, sync reports, logs, validation errors, and conflict records.

## Adapter Responsibilities

Each provider adapter must expose or document the following methods:

| Method | Responsibility |
|---|---|
| `get_connector_key()` | Return the stable connector key used for logs, configuration, and sync ownership. |
| `validate_credentials()` | Verify that the configured credentials are present, scoped to the current business, and accepted by the provider. |
| `fetch_inventory()` | Retrieve provider inventory without writing to internal catalog tables. |
| `normalize_item()` | Convert one provider item into the normalized inventory payload defined in this contract. |
| `dry_run()` | Validate credentials, fetch or receive inventory, normalize records, validate rows, and report expected operations without writes. |
| `sync()` | Execute approved sync operations through connector services and ultimately through `Vehicle_Catalog_Service` for catalog writes. |

Adapters must not contain SQL, direct `$wpdb` access, customer vehicle creation, CRM logic, payment logic, user/role logic, or frontend portal behavior.

## Normalized Inventory Payload

The normalized payload is the canonical provider-neutral format used between adapters, validation, and catalog sync.

### Required Fields

| Field | Type | Description |
|---|---|---|
| `external_id` | string | Provider-owned stable inventory identifier. Must be unique per `business_id` and `connector_key`. |
| `business_id` | integer | Internal business scope. Must match the active connector configuration. |
| `make` | string | Vehicle make. |
| `model` | string | Vehicle model. |
| `year` | integer/string | Four-digit vehicle year. |

### Optional Fields

| Field | Type | Description |
|---|---|---|
| `trim_version` | string | Trim, version, package, or variant. |
| `body_type` | string | Body type, for example sedan, SUV, van, hatchback. |
| `fuel_type` | string | Fuel type. |
| `transmission` | string | Transmission type. |
| `engine` | string | Engine description. |
| `vin` | string | Vehicle identification number from provider. |
| `plate` | string | Plate or license plate when supplied by provider. |
| `color` | string | Exterior color or provider color label. |
| `mileage` | integer/decimal | Mileage value. |
| `price` | decimal | Listed price. |
| `currency` | string | ISO-style three-letter currency code. |
| `stock_status` | string | Provider-neutral stock status. |
| `media` | array | Provider media references or normalized media metadata. No external media sync is implemented by this contract. |
| `notes` | string | Provider or mapper notes suitable for catalog context. |
| `raw_payload` | array/object | Sanitized provider source payload for diagnostics or future conflict analysis. Must not contain credentials or secrets. |

The current catalog write path supports catalog fields such as `business_id`, `make`, `model`, `year`, `trim_version`, `body_type`, `fuel_type`, `transmission`, `engine`, `notes`, and `status`. Other normalized fields are contract-level future sync metadata until a later phase defines persistence.

## Validation Rules

All connectors must validate normalized payloads before import or update operations.

| Rule | Requirement |
|---|---|
| Required fields | `external_id`, `business_id`, `make`, `model`, and `year` must be present and non-empty. |
| Year format | `year` must be a four-digit year and must be safe to cast to an integer. |
| Mileage numeric | `mileage`, when present, must be numeric and non-negative. |
| Price numeric | `price`, when present, must be numeric and non-negative. |
| Currency format | `currency`, when present, must be a three-letter uppercase currency code such as `USD`, `EUR`, or `MXN`. |
| Stock status enum | `stock_status`, when present, must map to a known internal status. Recommended enum: `available`, `reserved`, `sold`, `inactive`, `unknown`. |
| Business scope | Payload `business_id` must match the connector configuration business. Cross-business imports are forbidden. |

Invalid rows must not block valid rows unless the operation explicitly requires all-or-nothing behavior in a future contract. Dry-run reports must show invalid rows and reasons without writing to the database.

## Sync Operations

Every connector sync report must classify intended or executed work with these operation names:

| Operation | Meaning |
|---|---|
| `dry_run` | Validate and preview operations without writes. |
| `import_new` | Create a new vehicle catalog record from a valid normalized payload. |
| `update_existing` | Update a connector-owned or connector-mapped catalog record. |
| `deactivate_stale` | Deactivate inventory previously imported from the connector but no longer present in provider data. |
| `skip_invalid` | Skip a row that failed validation. |
| `conflict_detected` | Stop or defer an operation because provider data conflicts with manual ownership or local rules. |

Catalog writes must go through `Vehicle_Catalog_Service`. SQL remains restricted to repository/database layers.

## Error Model

Connectors must use standard error codes in dry-run results, sync results, logs, and user-facing admin summaries.

| Error Code | Meaning |
|---|---|
| `invalid_credentials` | Credentials are missing, invalid, expired, revoked, or outside the current business scope. |
| `provider_unavailable` | Provider API, export source, or remote service cannot be reached or returns an unavailable state. |
| `invalid_payload` | Provider data cannot be normalized into the contract payload. |
| `missing_required_field` | Required normalized field is absent or empty. |
| `duplicate_external_id` | Multiple provider records share the same `external_id` within the same `business_id` and `connector_key`. |
| `business_scope_violation` | Payload, credentials, or target catalog operation attempts to cross business boundaries. |
| `rate_limited` | Provider rejected or delayed requests due to rate limits. |
| `sync_conflict` | Provider update conflicts with local manual ownership or connector ownership rules. |

Errors must include enough context for diagnostics without logging credentials, tokens, secrets, or unsafe raw payload values.

## Logging Expectations

Connector logs and sync reports must include:

| Field | Required | Notes |
|---|---:|---|
| `connector_key` | yes | Stable connector key. |
| `business_id` | yes | Business scope. |
| `operation` | yes | One of the sync operation names where applicable. |
| `external_id` | when available | Provider item identifier. |
| `result` | yes | `success`, `failed`, `skipped`, `conflict`, or `not_run`. |
| `error_code` | when failed/skipped/conflict | Standard error code from this contract. |
| `timestamps` | yes | At minimum, operation creation/completion or event timestamp. |

Logs must be business-scoped and must not leak credentials, raw access tokens, provider secrets, filesystem paths, or unsanitized payloads.

## Conflict Handling

Conflict handling must follow these ownership rules:

- Manual override wins over connector updates.
- Connector-owned fields may be updated only when local ownership rules allow it.
- Stale detection must use connector identity and provider external IDs, not make/model/year matching alone.
- Retry behavior must be safe. Retrying a sync must not duplicate catalog records or reactivate stale records unless the provider data explicitly supports that operation.
- Conflicts must be reported as `conflict_detected` operations with `sync_conflict` error code when applicable.

Future implementation phases must define field-level ownership before automated updates or stale deactivation are enabled.

## Security Requirements

All connector implementations must enforce:

- Per-business credentials and configuration.
- No credential leakage in logs, reports, exceptions, admin notices, or raw payload snapshots.
- Sanitized payloads before validation, logging, or persistence.
- No cross-business imports or reads.
- Nonce and capability checks in any future admin UI.
- Service/repository layering with no SQL in controllers or adapters.

## Future Implementation Phases

Recommended next phases:

| Phase | Purpose |
|---|---|
| `56P13-C` | First provider prototype using this contract. |
| `56P13-D` | Scheduled sync engine, including execution lifecycle and retry strategy. |
| `56P13-E` | Connector admin UI for configuration, dry-run, sync review, and status visibility. |

Deferred areas remain outside this contract: OAuth flows, encrypted credentials schema, webhooks, queue workers, retry orchestration, external media synchronization, provider-specific admin settings, and automated customer vehicle creation.

## 56P13-B Validation Notes

- This phase is documentation-only.
- No `includes/*` files are modified.
- No `assets/*` files are modified.
- No runtime connector implementation is introduced.
- The contract aligns with `docs/INVENTORY_CONNECTOR_ARCHITECTURE.md` and preserves the current Vehicle Catalog and CSV import boundaries.

## 57E Passive Queue Intent Bridge

The first runtime bridge is passive and local-only:

- mock connector dry-run and sync simulation can build `inventory_connector_sync` queue jobs
- queue jobs are created through the passive SaaS `Queue_Dispatcher`
- payloads include connector identity, operation, `dry_run`, provider type, normalized item preview/count and validation summary
- queue results remain passive with `writes = 0` and `executed = false`

57E does not introduce real provider APIs, OAuth, scheduled sync, queue workers, persistence, catalog writes, admin UI or schema changes.
