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
    - `get_guided_rule_actions()`
    - `get_confirmable_rule_actions()`
    - `run_controlled_auto_execution()`
    - `get_execution_guardrails()`
    - `rollback_controlled_execution()`
  - user workload buckets: `critical`, `warning`, `normal`
  - sources: CRM tasks, operational signals aligned with pipeline policy, active processes, upcoming appointments
  - role: operational dashboard core aggregator and controlled execution orchestrator
- Automation operational rules:
  - service: `includes/automation/class-operational-rules-service.php`
  - repository: `includes/automation/class-operational-rules-repository.php`
  - installer: `includes/automation/class-operational-rules-installer.php`
  - role: rule config by tenant + evaluation + execution mode source
- Automation Execution Layer (Phase 43 active):
  - `Operational_Rules_Service`:
    - provides evaluated rules + persistent config by `business_id`
  - `Workload_Service` execution layer:
    - guided actions (43A)
    - confirmable actions (43B)
    - controlled auto execution (43C)
  - `Automation Safety Layer` (43D):
    - execution guardrails (`allowed`, `risk_level`, `reason`)
    - controlled rollback for supported execution payloads
  - `Rules Persistence Layer` (43E):
    - DB-backed rule config with defaults fallback
  - relation flow:
    - CRM / processes / appointments -> `Workload_Service`
    - `Operational_Rules_Service` -> `Workload_Service`
    - `Workload_Service` -> existing bulk/assisted execution handlers
    - `Automation Safety Layer` protects execution/rollback
    - `Operational_Rules_Repository` supplies persistent rule config

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
