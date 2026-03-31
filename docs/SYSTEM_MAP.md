# SYSTEM_MAP.md

Purpose:
Logical map of modules, entry points, and high-level flows.

Scope:
Visual/logical mapping only (not architecture authority, not phase history).

Source references:
- Architecture truth: `ARCHITECTURE.md`
- State truth: `docs/CURRENT_STATE.md`
- DB truth: `docs/DATABASE_MAP.md`

## Runtime Entry Flow
1. `super-mechanic.php`
2. `includes/autoloader.php`
3. `includes/class-plugin.php` (composition root)

## Active Runtime Area
- Active: `includes/*`
- Legacy/reference only: `includes/modules/*`

## Module Map (Logical)
- Core: bootstrap, assets, settings, menu, roles/capabilities
- Operations: clients, vehicles, relations, processes, flows
- Process domains: maintenance, predelivery, paperwork
- Commercial: quotes, invoices, payments
- CRM: pipeline, tasks, alerts, scheduler
- Comms/docs: attachments, communication, document/download services
- Scheduling/integrations: appointments, google calendar, public API/webhooks
- Reporting: operational and financial views

## UI Entry Points
- Admin menus under `Super Mechanic`
- Client shortcodes (`[sm_*]`)
- Calendar view (appointments + CRM task events)

## Integration Map
- Public API: `super-mechanic-public/v1`
- Internal admin API: `super-mechanic/v1`
- Webhooks outbound via `sm_webhooks`/`sm_webhook_deliveries`
- Google Calendar sync/watch

## Tenancy Map
- Tenant context anchored by `business_id`
- Tenant-aware filtering required across CRM, operations, and scheduling.
