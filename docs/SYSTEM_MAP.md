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
- Dashboard operational aggregation:
  - admin dashboard section **Mi trabajo**
  - service: `includes/dashboard/class-workload-service.php`
  - core methods:
    - `get_user_workload()`
    - `get_global_operational_summary()`
    - `get_operational_metrics()`
    - `get_operational_automation_flags()`
    - `get_operational_escalation_state()`
    - `get_operational_recommendations()`
    - `get_operational_assignments()`
    - `get_operational_assisted_actions()`
    - `get_operational_bulk_actions()`
    - `get_operational_automation_console()`
    - `get_operational_rules_overview()`
  - user workload buckets: `critical`, `warning`, `normal`
  - sources: CRM tasks, operational signals aligned with pipeline policy, active processes, upcoming appointments
  - role: operational dashboard core aggregator and controlled execution orchestrator
- Automation operational rules:
  - service: `includes/automation/class-operational-rules-service.php`
  - role: configurable rules definition and evaluation (preview-only, no auto execution)

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
