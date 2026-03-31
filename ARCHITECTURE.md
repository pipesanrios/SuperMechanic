# ARCHITECTURE.md

Purpose:
Define the real technical architecture of Super Mechanic.

Scope:
Runtime structure, layer responsibilities, module wiring, tenancy, and integration surfaces.
This file is not a phase log.

## Runtime Bootstrap
1. Plugin entry: `super-mechanic.php`
2. Autoload: `includes/autoloader.php`
3. Composition root: `includes/class-plugin.php`

## Mandatory Layering
`Controller -> Service -> Repository -> Database`

- Controller: WordPress integration/UI/request handling
- Service: business rules/orchestration
- Repository: SQL/persistence
- Database: schema/migrations

Hard rule: SQL only in Repository/Database layers.

## Runtime Scope
- Active runtime: `includes/*`
- Legacy/reference-only: `includes/modules/*`

## Active Module Families
- Core: plugin/bootstrap/assets/menu/settings/security
- Operations: clients/vehicles/relations/flows/processes
- Process domains: maintenance/predelivery/paperwork
- Commercial: quotes/invoices/payments
- CRM: pipeline/tasks/alerts/scheduler
- Scheduling & integrations: appointments/google calendar/public API/webhooks
- Shared services: helpers/documents/download/communication/reports

## Tenancy Architecture
- Tenant key: `business_id`
- Tenant-aware modules must enforce tenant filtering in read/write paths.

## Security Architecture
- Capability + nonce validation on mutable actions
- Ownership checks for client-facing resources
- Protected file delivery via `Document_Service` + `Download_Service`

## Integration Surfaces
- Public API namespace: `super-mechanic-public/v1`
- Internal admin API namespace: `super-mechanic/v1`
- Webhooks subsystem: `sm_webhooks`, `sm_webhook_deliveries`
- Calendar unification layer for appointment + CRM task events

## Source Priority Reminder
If documentation conflicts with code, code is authoritative.
