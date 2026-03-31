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

## Boundary Rule
Module-to-module interactions must go through Services, not repositories.

## Cross-reference
- Architecture: `ARCHITECTURE.md`
- Database: `docs/DATABASE_MAP.md`
- Current state: `docs/CURRENT_STATE.md`
