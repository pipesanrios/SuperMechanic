# SYSTEM_OVERVIEW.md

## Purpose

This document explains how Super Mechanic works as a system.

It is intended for developers who need to:
- understand system behavior
- navigate modules
- extend functionality safely

This is a conceptual and structural overview, not a code reference.

---

## System Nature

Super Mechanic is an **operational platform** built on WordPress.

It is not just a plugin UI — it is a system that manages:

- workshop operations
- customer lifecycle
- vehicle tracking
- processes and workflows
- CRM follow-up
- scheduling
- commercial activity

---

## Core Architecture

The system follows a strict layered architecture:

Controller → Service → Repository → Database

### Layers

- Controller → handles requests and UI
- Service → business logic and orchestration
- Repository → data access
- Database → schema and storage

SQL is only allowed in Repository/Database.

---

## Core System Domains

The system is divided into functional domains:

### 1. Master Data

Entities:
- Clients
- Vehicles
- Ownership relations

Purpose:
Store base information for all operations.

---

### 2. Process Engine

Entities:
- Processes
- Flow definitions
- Steps

Purpose:
Track all operational workflows.

Examples:
- maintenance
- pre-delivery
- paperwork

---

### 3. CRM System

Entities:
- Pipeline
- Tasks
- Alerts

Purpose:
Track commercial and operational follow-up.

---

### 4. Scheduling System

Entities:
- Appointments
- Calendar integrations

Purpose:
Manage time-based events.

---

### 5. Commercial System

Entities:
- Quotes
- Invoices
- Payments

Purpose:
Handle financial interactions.

---

### 6. Document System

Services:
- Document_Service
- Download_Service

Purpose:
Secure file handling and delivery.

---

## Key System Concept: Processes

Everything revolves around **processes**.

A process represents:
- a workflow
- a lifecycle
- a sequence of steps

Each process:
- belongs to a vehicle
- belongs to a client
- follows a flow definition
- progresses through steps

---

## Key System Concept: Persisted Alerts

Introduced in Phase 39E.

Before:
- alerts were computed at runtime

Now:
- alerts are persisted in database (`sm_crm_alerts`)

### Benefits

- consistent UI behavior
- predictable state
- no recomputation cost
- cross-module usage

---

## Scheduler

The system includes an internal scheduler:

- Hook: `sm_crm_scheduler_tick`

Purpose:
- recalculate alerts
- update states
- maintain system signals

---

## Data Flow Overview

1. Data is created (client, vehicle, process)
2. Processes evolve through steps
3. CRM tasks are created
4. Scheduler evaluates state
5. Alerts are persisted
6. UI consumes persisted alerts
7. Dashboard aggregates everything

---

## Operational Layer (Phase 40)

The system is evolving into an operational platform.

Key component:

### Workload Aggregation

- combines:
  - tasks
  - alerts
  - processes
  - appointments

- produces:
  - prioritized workload per user

This layer:
- does not create new data
- does not modify core logic
- only aggregates and prioritizes

---

## Tenancy Model

The system supports multi-business environments.

Key:
- `business_id`

Rules:
- all data must be filtered by tenant
- no cross-tenant access

---

## Security Model

- capability checks
- nonce validation
- ownership enforcement
- secure file access

---

## Integration Points

### REST API

- Public: `super-mechanic-public/v1`
- Internal: `super-mechanic/v1`

### Webhooks

- event-based external integrations

### Calendar

- unified event layer

---

## System Evolution

The system evolved through:

1. Data management
2. Workflow engine
3. Operational platform
4. CRM integration
5. Alert persistence
6. Operational aggregation (current phase)

---

## Extension Guidelines

When extending the system:

- follow architecture pattern strictly
- do not duplicate logic
- reuse services
- respect tenancy
- avoid N+1 queries
- do not recompute persisted data

---

## Important Rule

If documentation and code conflict:

- code is the source of truth
- documentation must be updated