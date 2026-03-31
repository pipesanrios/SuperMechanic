# EXTENDING.md

## Purpose

This document explains how to extend Super Mechanic safely.

---

## Core Principle

All extensions must follow:

Controller → Service → Repository → Database

---

## What You Can Extend

- new services
- new controllers
- new repositories
- UI components
- integrations

---

## What You MUST NOT Do

- write SQL outside repositories
- modify core logic without scope
- duplicate existing logic
- use legacy modules
- bypass services

---

## Creating a New Feature

### Step 1 — Define Scope

- what does the feature do
- what module it belongs to

---

### Step 2 — Create Service

Example:

`includes/<module>/class-<feature>-service.php`

Responsibilities:

- business logic
- orchestration

---

### Step 3 — Create Repository (if needed)

- data access
- SQL queries

---

### Step 4 — Create Controller

- handle requests
- connect UI with service

---

### Step 5 — Wire in Plugin

- register in bootstrap or module loader

---

## Example Flow

1. Controller receives request
2. Calls Service
3. Service calls Repository
4. Repository executes query
5. Response flows back

---

## Reuse Existing Components

Before creating anything:

- check existing services
- check repositories
- avoid duplication

---

## Working With CRM

- use existing task system
- use persisted alerts
- do not recalculate alerts manually

---

## Working With Processes

- use flow definitions
- do not hardcode steps

---

## Performance Guidelines

- avoid N+1 queries
- use batch queries
- reuse services

---

## Security Guidelines

- enforce `business_id`
- check ownership
- validate permissions
- use nonces

---

## File Handling

Use:

- `Document_Service`
- `Download_Service`

Do not expose direct file paths.

---

## Testing

Before completing:

- php lint
- manual validation
- runtime validation

---

## Contract-Driven Development

All non-trivial features must:

- have a Task Contract
- follow Validation Contract

---

## Final Rule

If you break architecture or rules:

→ the system will become unstable

Follow patterns strictly.