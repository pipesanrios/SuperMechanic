# MODULE_REGISTRY.md

Purpose:
Module inventory only.

Scope:
Active modules, module role, and ownership boundaries.
No phase timeline and no schema details.

## Runtime Boundary
- Active runtime: `includes/*`
- Legacy/reference only: `includes/modules/*`

## Core / Infrastructure
- core bootstrap (`class-plugin`, activator/deactivator, assets, menu)
- settings
- security/capabilities
- helpers
- database

## Business Modules
- clients
- vehicles
- relations
- flows
- processes
- maintenance
- predelivery
- paperwork
- quotes
- invoices
- payments
- attachments
- communication
- reports
- dashboard
  - Workload_Service (core aggregator):
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
  - Admin Dashboard Controller:
    - global operational summary view
    - user workload view (`Mi trabajo`)
    - operational/SLA metrics view
    - Operational Action Center (42D)
    - Bulk Actions Layer (42C)
    - Assisted Actions Layer (42A)
    - Assignment Engine UI surface (41D/42B)
    - Rules Engine UI surface (42E)

## CRM Modules
- crm pipeline
- crm tasks
- crm alerts
- crm scheduler

## Scheduling / Integration Modules
- appointments
- integrations/google-calendar
- integrations/public-api
- integrations/woocommerce (commercial snapshot scope)
- businesses (multi-store context)

## Automation Modules
- automation rule engine (existing events layer)
- operational rules service:
  - `includes/automation/class-operational-rules-service.php`
  - configurable rules + evaluation preview
  - no auto execution and no cron added by this layer

## Boundary Rule
Module-to-module interactions must go through Services, not repositories.

## Cross-reference
- Architecture: `ARCHITECTURE.md`
- Database: `docs/DATABASE_MAP.md`
- Current state: `docs/CURRENT_STATE.md`
