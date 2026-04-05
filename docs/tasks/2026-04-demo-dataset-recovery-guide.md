# Demo Dataset Recovery Guide (Multi-Business)

Date: 2026-04-05  
Scope: Demo dataset bootstrap + recovery after DB loss (XAMPP/WordPress local)

## Objective

Provide a reproducible seed process so any AI, developer, or plugin client can restore a complete operational demo:
- businesses
- users/roles
- clients
- vehicles
- processes
- CRM tasks
- appointments
- quotes
- invoices/payments
- operational execution logs

## Canonical Seeder

Script:
- `scripts/seed-full-demo-multibusiness.php`

Run command:

```bash
php scripts/seed-full-demo-multibusiness.php
```

## Super Admin Rule

Primary superadmin is enforced as:
- email: `admin@mardisom.com`
- role: `administrator`
- capability: `sm_manage_plugin`
- business scope:
  - `sm_active_business_id`
  - `sm_allowed_business_ids` (all seeded businesses)

Notes:
- If email user exists, it is reused.
- If not, seeder creates login `admin` (only when missing) and prints temporary password.

## What The Seeder Creates

Businesses:
- default business (HQ)
- second demo business (Branch 2)

Operational users:
- mechanics for each business (`sm_mechanic`)
- client users (`sm_client`) linked with `sm_client_id`

Operational entities:
- clients + vehicles + client-vehicle relations
- processes (maintenance/pre-delivery)
- CRM opportunities + CRM tasks
- appointments near current date window
- quotes + quote items
- invoices (paid + issued mix) + partial/full payments
- execution logs in `wp_sm_execution_logs`

## Idempotency / Re-run Behavior

Seeder is designed to be safely re-runnable:
- reuses entities by deterministic keys where possible (email, plate, markers)
- updates existing records instead of blind duplication
- can add new log rows per run for realistic history growth

## Core Functions (Seeder Internals)

- `sm_demo_set_user_business_scope(...)`  
  Sets active + allowed business context in user meta.

- `sm_demo_require_admin()`  
  Ensures bootstrap admin context exists before seeding.

- `sm_demo_ensure_primary_super_admin(...)`  
  Enforces `admin@mardisom.com` as global superadmin.

- `sm_demo_ensure_business(...)`  
  Creates/updates business tenant records.

- `sm_demo_ensure_user(...)`  
  Creates/updates WP users and links optional client meta.

- `sm_demo_ensure_flow_catalog(...)`  
  Ensures minimum flow + steps catalog for process execution.

- `sm_demo_seed_business_dataset(...)`  
  Seeds full operational data for one `business_id`.

## Recovery Procedure (After DB Loss)

1. Ensure WordPress + plugin are installed and active.
2. Ensure plugin installers ran at least once (normal plugin activation).
3. Run:
   - `php scripts/seed-full-demo-multibusiness.php`
4. Log in with `admin@mardisom.com` (or existing admin mapped to that email).
5. Validate:
   - dashboard has active operational data
   - mechanic panel has assigned processes/appointments
   - automation center has execution/flags context
   - logs page shows execution entries

## Quick Verification (SQL)

Use DB prefix as configured (`wp_` default):

```sql
SELECT COUNT(*) AS businesses FROM wp_sm_businesses;
SELECT COUNT(*) AS clients FROM wp_sm_clients;
SELECT COUNT(*) AS vehicles FROM wp_sm_vehicles;
SELECT COUNT(*) AS processes FROM wp_sm_processes;
SELECT COUNT(*) AS tasks FROM wp_sm_crm_tasks;
SELECT COUNT(*) AS appointments FROM wp_sm_appointments;
SELECT COUNT(*) AS quotes FROM wp_sm_quotes;
SELECT COUNT(*) AS invoices FROM wp_sm_invoices;
SELECT COUNT(*) AS execution_logs FROM wp_sm_execution_logs;
```

## Roles & Access Clarification

`Roles & Access` screen lists WordPress users, not CRM client records.

Therefore:
- `sm_clients` table may be larger than users shown in Roles page.
- only clients with WP user (`sm_client`) appear in Roles & Access.

## Known Constraints

- No production anonymization policy included (demo/local oriented).
- Seeder focuses on completeness and continuity, not strict fixture minimalism.
- Repeated runs can increase execution log count by design.

## Handoff Note

For any new environment, this guide + seeder is the canonical recovery path.  
Do not handcraft partial records if continuity is required across dashboard, automation, logs, and role views.

