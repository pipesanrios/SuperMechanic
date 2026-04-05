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
    - `get_guided_rule_actions()`
    - `get_confirmable_rule_actions()`
    - `run_controlled_auto_execution()`
    - `get_execution_guardrails()`
    - `rollback_controlled_execution()`
  - Admin Dashboard Controller:
    - global operational summary view
    - user workload view (`Mi trabajo`)
    - operational/SLA metrics view
    - Operational Action Center (42D)
    - Bulk Actions Layer (42C)
    - Assisted Actions Layer (42A)
    - Assignment Engine UI surface (41D/42B)
    - Rules Engine UI surface (42E)
    - Guided Rule Actions (43A)
    - Confirmable Rule Actions (43B)
    - Controlled Auto Execution visibility (43C)
    - Guardrails and Rollback surface (43D)

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
- users (multi-business membership + access)
  - `class-business-membership-installer`
  - `class-business-membership-repository`
  - `class-business-membership-service`
  - `class-role-access-service`
  - `class-admin-roles-controller`
  - status: ACTIVE

## Automation Modules
- automation rule engine (existing events layer)
- operational rules service:
  - `includes/automation/class-operational-rules-service.php`
  - configurable rules + evaluation + execution mode source
  - persistent tenant config via repository + defaults fallback
- Automation Execution Layer:
  - Description:
    - progressive execution orchestrator (guided -> confirmable -> auto controlled)
  - Inputs:
    - evaluated rules, workload context, actionable operational payloads
  - Outputs:
    - guided actions, confirmable actions, controlled auto execution dispatch
  - Status:
    - ACTIVE
- Automation Safety Layer:
  - Description:
    - execution guardrails and controlled rollback for supported actions
  - Inputs:
    - execution payloads, tenant context, capability/nonce validated requests
  - Outputs:
    - allow/deny decisions, rollback availability and execution results
  - Status:
    - ACTIVE
- Rules Persistence Layer:
  - Description:
    - persistent operational rules config per tenant (`business_id`)
  - Inputs:
    - rule_key, thresholds, limits, execution_mode, enabled state
  - Outputs:
    - DB-backed rule configuration consumed by service layer
  - Status:
    - ACTIVE

## Multi-Business Access Layer
- Description:
  - business membership model plus global-vs-membership access resolution
- Inputs:
  - WP users, businesses, memberships (`sm_business_user_roles`)
- Outputs:
  - default business resolution
  - accessible businesses per user
  - consistency warnings
  - safe consistency repairs
- Status:
  - ACTIVE

## Boundary Rule
Module-to-module interactions must go through Services, not repositories.

## Cross-reference
- Architecture: `ARCHITECTURE.md`
- Database: `docs/DATABASE_MAP.md`
- Current state: `docs/CURRENT_STATE.md`
