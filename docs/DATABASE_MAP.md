# DATABASE_MAP.md

Purpose:
Database schema map only.

Scope:
Tables, ownership, and structural grouping.
No phase timeline and no architecture narrative.

Schema authority:
- `includes/database/class-schema.php`
- `SM_DB_VERSION` baseline: `1.22.0`

## Core Operational Tables
- `sm_clients`
- `sm_vehicles`
- `sm_client_vehicles`
- `sm_flows`
- `sm_flow_steps`
- `sm_processes`
- `sm_process_step_logs`
- `sm_process_parts`
- `sm_process_meta`
- `sm_maintenance`
- `sm_maintenance_parts`
- `sm_maintenance_labor`
- `sm_pre_delivery`
- `sm_paperwork`
- `sm_paperwork_items`
- `sm_quotes`
- `sm_quote_items`
- `sm_invoices`
- `sm_invoice_items`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

## Scheduling / Integration Tables
- `sm_appointments`
- `sm_appointment_calendar_sync`
- `sm_webhooks`
- `sm_webhook_deliveries`
- `sm_saas_queue_jobs`

## Tenancy / Business Tables
- `sm_businesses`

## CRM Tables
- `sm_client_crm_meta`
- `sm_crm_pipeline`
- `sm_crm_tasks`
- `sm_crm_alerts`

## Key Tenancy Rule
Tenant-aware tables must enforce `business_id` in read/write operations.

## Notes
- For exact columns and indexes, read `includes/database/class-schema.php`.
- If this file conflicts with code, code is authoritative.
