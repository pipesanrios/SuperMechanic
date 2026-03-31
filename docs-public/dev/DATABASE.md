# DATABASE.md

## Purpose

This document explains the database structure of Super Mechanic in a developer-friendly way.

It complements the internal technical map (`docs/DATABASE_MAP.md`) by providing:

- conceptual understanding
- table responsibilities
- relationships between entities
- usage patterns

---

## Database Philosophy

The system uses custom tables instead of WordPress default entities.

Reason:

- performance
- flexibility
- control over workflows
- scalability

---

## Core Principles

- SQL is handled ONLY in Repository layer
- Tables are created via plugin activation (`dbDelta`)
- No manual schema modification allowed
- Relationships are handled at application level (not strict FK constraints)

---

## Main Data Domains

The database is organized into the following domains:

1. Clients & Vehicles
2. Processes & Flows
3. CRM (Tasks & Alerts)
4. Documents & Files
5. Commercial (Invoices, Payments)
6. Scheduling & Integrations
7. System Logs & Audit

---

## Clients & Vehicles

### sm_clients

Stores client information.

Key fields:
- id
- name
- document_number
- contact data

---

### sm_vehicles

Stores vehicles.

Key fields:
- id
- client_id
- plate_number
- brand
- model

---

### sm_vehicle_owners_history

Tracks ownership changes.

---

## Processes & Flows

### sm_processes

Core entity of the system.

Represents a workflow applied to a vehicle.

Key fields:
- id
- vehicle_id
- process_type
- current_step_id
- status
- assigned_to

---

### sm_flow_definitions

Defines available workflows.

---

### sm_flow_steps

Defines steps inside a workflow.

---

### sm_process_step_logs

Tracks step transitions and changes.

---

## CRM (Tasks & Alerts)

### sm_crm_tasks

Stores operational tasks.

Key fields:
- id
- assigned_user_id
- related_entity
- due_date
- status

---

### sm_crm_alerts

Stores system-generated alerts (persisted).

Examples:
- overdue tasks
- delayed processes

Important:
- alerts must NOT be recalculated manually
- always use persisted alerts

---

## Documents & Files

### sm_documents

Stores document metadata.

Files are handled via:

- Document_Service
- Download_Service

---

## Commercial

### sm_invoices

Stores invoices.

---

### sm_payments

Stores payments linked to invoices.

---

## Scheduling & Integrations

### sm_appointments

Stores scheduled events.

---

### sm_webhooks

Webhook configuration.

---

### sm_webhook_deliveries

Tracks webhook execution logs.

---

## System Logs & Audit

### sm_audit_log

Tracks system-level actions.

---

## Relationships (Conceptual)

- Client → Vehicles
- Vehicle → Processes
- Process → Steps → Logs
- Process → Tasks
- Process → Alerts
- Client/Process → Documents
- Process → Appointments

---

## Multi-Tenant Model

The system supports multi-tenant architecture.

Key field:

business_id

Rules:

- all tenant-aware tables must include business_id
- all queries must filter by business_id

---

## Data Flow Example

1. Client is created
2. Vehicle is assigned
3. Process is created
4. Steps are executed
5. Tasks are generated
6. Alerts are triggered
7. Documents are attached

---

## Performance Considerations

- avoid N+1 queries
- use batch queries
- reuse repositories
- index frequently queried fields

---

## Indexing Strategy (Recommended)

Fields that should be indexed:

- client_id
- vehicle_id
- process_type
- assigned_user_id
- status
- due_date

---

## Common Mistakes

- writing SQL outside repositories
- duplicating queries in services
- ignoring business_id filtering
- recalculating alerts manually

---

## Debugging Tips

- check repository queries
- inspect database directly
- verify relationships manually
- use logs (audit / alerts)

---

## Schema Reference

For exact structure:

→ see `docs/DATABASE_MAP.md`

---

## Final Note

The database is the backbone of the system.

Understanding relationships and flow is critical for:

- debugging
- extending
- optimizing

Always follow architecture rules when interacting with data.