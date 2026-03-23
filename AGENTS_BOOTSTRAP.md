# AGENTS_BOOTSTRAP.md
Super Mechanic — Agent Bootstrap Context

This file defines the reading order that AI agents must follow before performing any implementation work.

Agents must load these documents in order to understand the system architecture, rules, and current project state.

---

# 1 — Core Architecture

Start by understanding the plugin architecture.

The active runtime architecture lives in:

includes/*

Legacy experimental modules may exist in:

includes/modules/*

These modules are not part of the active architecture and must not be extended.

Read:

ARCHITECTURE.md
docs/SYSTEM_MAP.md
docs/FINAL_ARCHITECTURE_MAP.md
docs/DOMAIN_MODEL.md
docs/MODULE_DEPENDENCY_MAP.md

These documents define:

- module boundaries
- dependency rules
- architectural layers
- system components

---

# 2 — Current Implementation State

Then load the current state of the system.

Read:

docs/CURRENT_STATE.md
docs/MODULE_REGISTRY.md
docs/DATABASE_MAP.md
docs/PLUGIN_ROADMAP.md

These files describe:

- implemented modules
- system maturity
- database schema
- development roadmap

---

# 3 — Security and Performance

Agents must respect the security and performance model.

Read:

docs/SECURITY_MODEL.md
docs/PERFORMANCE_STRATEGY.md

Important areas:

- document access
- ownership validation
- secure downloads
- performance boundaries

---

# 4 — AI Development Rules

All agents must follow the AI rules defined for this project.

Read:

ai/rules/AI_RULES.md
ai/rules/GUARDRAILS.md
ai/rules/MODULE_BOUNDARIES.md
ai/rules/WP_PLUGIN_PATTERNS.md
ai/rules/ERROR_RECOVERY_PROTOCOL.md

These define:

- safe development constraints
- architecture protection
- error recovery rules
- WordPress coding patterns

---

# 5 — Project Context

Agents should then load project context memory.

Read:

ai/context/AGENTS_QUICK_CONTEXT.md
ai/context/PROJECT_MEMORY.md
ai/context/WORKFLOW.md

These files explain:

- system mental model
- development workflow
- project evolution

---

# 6 — Operational Prompts

The project uses standardized prompts for development operations.

Located in:

ai/prompts/

Key prompts:

CONTEXTO DEL PROYECTO.txt  
MODO DESARROLLO SEGURO — SUPER MECHANIC.txt  
SCOPE GUARD — CONTROL DE ALCANCE DE IMPLEMENTACIÓN.txt  
PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt  
AUDITORÍA DE INTEGRIDAD DEL SISTEMA — SUPER MECHANIC.txt  
ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE.txt  

These prompts are used during development sessions to control scope and validate changes.

They are not executed automatically and are intended for manual use with AI coding assistants.

---

# Final Rule

Agents must not implement or modify code before loading the bootstrap context defined in this document.

Failure to follow the bootstrap reading order may lead to:

- architecture violations
- module coupling
- incorrect repository usage
- security regressions
