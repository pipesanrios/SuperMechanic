AI_RULES.md - Master Rule Layer

PURPOSE:
Define the unified rule hierarchy for AI execution.

SCOPE:
Applies to all AI work in this repository.

WHEN TO USE:
At session start and before any implementation.

WHEN NOT TO USE:
Do not use as a replacement for `AGENTS.md`; this file is subordinate to it.

## Rule Priority (Mandatory)
1. `AGENTS.md`
2. `ai/rules/AI_RULES.md`
3. `ai/rules/GUARDRAILS.md`
4. `ai/rules/MODULE_BOUNDARIES.md`
5. `ai/rules/AGENTS_RUNTIME_RULES.md`
6. Support files:
   - `ai/rules/ERROR_RECOVERY_PROTOCOL.md`
   - `ai/rules/WP_PLUGIN_PATTERNS.md`

## Core Enforcement
- Follow `AGENTS_BOOTSTRAP.md` reading order.
- Never touch code before context load.
- Use `Controller -> Service -> Repository -> Database`.
- SQL only in Repository/Database layers.
- Enforce tenancy by `business_id` where applicable.
- Treat `includes/modules/*` as legacy.

## Conflict Rule
If any rule file conflicts with `AGENTS.md`, `AGENTS.md` wins.
