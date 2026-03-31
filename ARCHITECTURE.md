# ARCHITECTURE.md

## Purpose

Define the **real technical architecture** of Super Mechanic.

---

## Scope

This file defines:

- runtime structure
- layer responsibilities
- module families
- tenancy model
- integration surfaces
- architectural constraints

This file is **not**:

- a phase log
- a roadmap
- a historical document

---

## Runtime Bootstrap

1. Plugin entry:
   - `super-mechanic.php`

2. Autoload:
   - `includes/autoloader.php`

3. Composition root:
   - `includes/class-plugin.php`

All runtime wiring must originate from the composition root.

---

## Mandatory Layering

All code must follow:

`Controller -> Service -> Repository -> Database`

### Layer Responsibilities

| Layer | Responsibility |
|------|---------------|
| Controller | WordPress integration, routing, UI handling |
| Service | business logic, orchestration |
| Repository | data access, SQL execution |
| Database | schema, migrations |

### Hard Rule

- SQL is allowed **only** in Repository and Database layers.

---

## Runtime Scope

### Active Runtime

- `includes/*`

### Legacy / Reference Only

- `includes/modules/*`

Legacy code must not be extended or used as active architecture.

---

## Active Module Families

### Core
- plugin bootstrap
- assets
- admin menu
- settings
- security

### Operations
- clients
- vehicles
- ownership relations
- flows
- processes

### Process Domains
- maintenance
- pre-delivery
- paperwork

### Commercial
- quotes
- invoices
- payments

### CRM
- pipeline
- tasks
- alerts (persisted)
- scheduler

### Scheduling & Integrations
- appointments
- Google Calendar integration
- public API
- webhooks

### Shared Services
- helpers
- documents
- download service
- communication
- reporting

### Operational Layer (Phase 40+)

- dashboard aggregation
- workload computation
- cross-module prioritization

This layer must:
- aggregate data
- not duplicate logic
- not recalculate persisted signals

---

## Data Flow Principles

- Persisted data is the source of truth (e.g., CRM alerts)
- No recalculation of already persisted signals
- Aggregation must be:
  - read-only
  - cross-module
  - consistent

---

## Tenancy Architecture

- Tenant key: `business_id`

### Rules

- All tenant-aware modules must:
  - filter reads by `business_id`
  - enforce writes with `business_id`

No cross-tenant leakage is allowed.

---

## Security Architecture

- Capability checks on all mutable operations
- Nonce validation for admin actions
- Ownership enforcement:
  - client
  - vehicle
  - process
- Secure file delivery via:
  - `Document_Service`
  - `Download_Service`

Direct file access is forbidden.

---

## Performance Rules

- No N+1 queries
- Prefer batch queries
- Avoid loops with database calls
- Reuse existing services and repositories
- Do not recompute persisted data (alerts, states, etc.)

---

## Integration Surfaces

### REST API

- Public:
  - `super-mechanic-public/v1`

- Internal:
  - `super-mechanic/v1`

### Webhooks

- Tables:
  - `sm_webhooks`
  - `sm_webhook_deliveries`

### Calendar Layer

- Unified layer for:
  - appointments
  - CRM task events

---

## Contract-Driven Architecture

The system operates under:

### Task Contracts

Define:
- scope
- allowed files
- expected outputs

### Validation Contracts

Define:
- automated checks
- manual validation
- runtime validation

### Rule

No architectural change is valid without respecting contract boundaries.

---

## What Is Not Allowed

- Introducing new architectural patterns
- Mixing layers (e.g., SQL in services/controllers)
- Duplicating business logic across modules
- Recomputing persisted data
- Extending legacy modules
- Bypassing service/repository layers

---

## Source Priority Reminder

If documentation conflicts with code:

1. Code
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`

→ Code is authoritative.  
→ Documentation must be updated accordingly.