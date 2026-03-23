# AGENTS.md
Super Mechanic — AI Agent Development Guide

This file defines the fundamental rules and architecture expectations for AI agents working in this repository.

The goal is to ensure all generated code follows the real structure of the Super Mechanic plugin and respects the project's architectural decisions.

This file intentionally stays concise. Detailed documentation lives in `/docs`.

---

# Project Overview

Super Mechanic is a modular WordPress plugin designed for:

- Automotive workshops
- Dealerships
- Vehicle maintenance tracking
- Process workflows
- Billing and documents
- Customer portal

The system is designed as a **modular monolith** built around services and repositories.

---

# Core Architecture

The active runtime architecture lives in:

/includes/*

Legacy experimental modules may exist in:

/includes/modules/*

These legacy modules are **not part of the active architecture** and must not be extended.

---

## Architectural Layers

Modules follow this structure:

Controller  
↓  
Service  
↓  
Repository  
↓  
Database  

### Controller Layer

Handles WordPress integration:

- admin controllers
- dashboard controllers
- shortcodes
- REST controllers

Controllers must **not contain business logic** or direct database queries.

### Service Layer

Contains business logic.

Responsibilities:

- coordinate repositories
- enforce domain rules
- orchestrate operations across modules

Services must **not contain direct SQL queries**.

### Repository Layer

Responsible for database interaction.

Repositories must encapsulate:

- SELECT
- INSERT
- UPDATE
- DELETE

Direct use of `$wpdb` is **forbidden outside repositories**.

### Database Layer

Database schema and migrations are defined in:

includes/database/

This includes:

- schema
- migrations
- seeding utilities

---

# Active Modules

Primary modules:

clients  
vehicles  
relations  
processes  
flows  
maintenance  
paperwork  
predelivery  
quotes  
invoices  
payments  
reports  
attachments  
communication  
dashboard  

Infrastructure modules:

helpers  
integrations  
database  

---

## Module Structure

Each module should contain:

admin-controller  
service  
repository  

Optional components:

list-table  
shortcodes  
REST controllers  

Module boundaries must be respected.

Cross-module dependencies should happen **through services only**, never repositories.

---

# Legacy Code

Legacy experimental modules exist in:

includes/modules/*

Rules:

- DO NOT extend legacy modules
- DO NOT create dependencies to them
- DO NOT import classes from them
- New development must live in `/includes/*`

Legacy modules remain only for reference.

---

# Secure File Handling

All file access must go through the secure service layer:

Download_Service  
Document_Service  
Attachment_Service  

Direct file URLs must **never be exposed**.

Client downloads must validate:

- authentication
- ownership
- visibility permissions

Controllers and shortcodes must never output raw file paths.

---

# Database Rules

All SQL must exist in repositories.

Forbidden locations for SQL:

controllers  
services  
shortcodes  

Direct use of `$wpdb` is forbidden outside repositories.

Repositories must be the **single source of database interaction**.

---

# Transactions

Complex multi-write operations must use repository transaction boundaries.

Example:

Invoice_Transaction_Repository

Services must not directly control transactions.

Transactions should be encapsulated in dedicated transaction repositories.

---

# Source of Truth

Architectural truth lives in the following files:

ARCHITECTURE.md  
docs/SYSTEM_MAP.md  
docs/FINAL_ARCHITECTURE_MAP.md  
docs/CURRENT_STATE.md  
docs/MODULE_REGISTRY.md  
docs/DATABASE_MAP.md  

If code and documentation diverge:

**Code is the source of truth.**

Documentation must then be updated to reflect the code.

---

# Development Rules

AI agents must follow the rule system located in:

ai/rules/

Key rule files include:

AI_RULES.md  
GUARDRAILS.md  
MODULE_BOUNDARIES.md  
WP_PLUGIN_PATTERNS.md  
ERROR_RECOVERY_PROTOCOL.md  

These rules define:

- architecture safety constraints
- module boundaries
- WordPress coding conventions
- safe recovery procedures

---

# Development Workflow

The AI-assisted development workflow is documented in:

ai/context/WORKFLOW.md

Project mental model and development memory are defined in:

ai/context/AGENTS_QUICK_CONTEXT.md  
ai/context/PROJECT_MEMORY.md  

---

# Task Tracking

Development tasks and implementation logs are stored in:

docs/tasks/

These files track work completed in each development phase.

---

# Phase Development

The plugin evolves through structured development phases defined in:

docs/PLUGIN_ROADMAP.md

The current implementation status is tracked in:

docs/CURRENT_STATE.md

Agents should consult these files before implementing new features.

---

# Agent Entry Point

Before performing any development work, agents must read:

AGENTS_BOOTSTRAP.md

This file defines the required reading order for the entire project context.

Agents must not implement or modify code before loading the bootstrap context.
