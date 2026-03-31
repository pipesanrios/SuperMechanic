# README_DEV.md

## Purpose

This document is the entrypoint for developers working with Super Mechanic.

It provides a high-level understanding of:

- system architecture
- development rules
- extension approach
- key concepts

This file is the starting point before reading any other technical documentation.

---

## What Is Super Mechanic

Super Mechanic is a modular WordPress plugin designed to manage:

- workshop operations
- vehicles and clients
- processes and workflows
- CRM (tasks, alerts, pipeline)
- scheduling and integrations

The system is built for real operational environments.

---

## Core Architecture

The system follows a strict layered architecture:

Controller → Service → Repository → Database

### Layer Responsibilities

- Controller  
  Handles WordPress integration, UI, and requests

- Service  
  Contains business logic and orchestration

- Repository  
  Handles all database interactions (SQL)

- Database  
  Defines schema and migrations

---

## Non-Negotiable Rules

- SQL is allowed ONLY in Repository/Database layers
- Do not use `$wpdb` outside repositories
- Do not modify schema without explicit phase scope
- Do not use legacy modules (`includes/modules/*`)
- Always enforce `business_id` in tenant-aware modules
- Never expose direct file URLs (use Document_Service)

---

## Runtime Structure

Active runtime:

includes/*

Legacy (not active):

includes/modules/*

---

## Main System Domains

- Core system (bootstrap, settings, security)
- Operations (clients, vehicles, relations)
- Processes (maintenance, delivery, paperwork)
- CRM (tasks, alerts, scheduler)
- Commercial (quotes, invoices, payments)
- Scheduling & integrations
- Shared services (documents, communication, reporting)

---

## Key Concepts

### Process

A structured workflow applied to a vehicle.

Examples:
- maintenance
- delivery
- paperwork

---

### Task

A specific action inside the system.

---

### Alert

A system-generated signal:

- overdue
- follow-up needed
- conversion pending

Alerts are persisted and must not be recalculated manually.

---

### Tenancy

All tenant-aware modules must use:

business_id

---

## Development Model

Super Mechanic uses:

### Contract-Driven Development

Every non-trivial task requires:

- Task Contract
- Validation Contract

---

### AI-Assisted Development

The system is designed for:

- Codex
- AI agents
- multi-agent workflows

---

## Before Modifying Code

You must:

1. Read system entrypoint (`AGENTS_BOOTSTRAP.md`)
2. Understand current state (`docs/CURRENT_STATE.md`)
3. Check architecture (`ARCHITECTURE.md`)
4. Load Task Contract (if applicable)

---

## How To Extend The System

See:

→ EXTENDING.md

---

## How To Install

See:

→ INSTALLATION.md

---

## Database Reference

See:

→ DATABASE.md

---

## API Reference

See:

→ API.md

---

## Testing

Minimum required:

- PHP lint
- manual validation
- runtime validation (if applicable)

---

## Common Mistakes

- mixing layers (controller doing business logic)
- duplicating services
- writing SQL in wrong place
- bypassing existing modules
- ignoring tenancy rules

---

## Development Philosophy

- do not over-engineer
- reuse existing logic
- keep modules isolated
- maintain performance
- preserve system stability

---

## Final Rule

If you break architecture or rules:

→ the system will become unstable

Follow patterns strictly.