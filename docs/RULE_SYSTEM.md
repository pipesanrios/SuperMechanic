# RULE_SYSTEM.md

Purpose:
Explain the unified AI rule hierarchy and how to resolve rule conflicts.

## Rule Hierarchy
1. `AGENTS_BOOTSTRAP.md` (entrypoint and reading order)
2. `AGENTS.md` (hard project rules)
3. `.vscode/AI_CONTEXT.md` + Prompt Master (execution context)
4. `ai/rules/*` (specialized rule set)
5. `ai/context/*` (support context only)
6. `docs/*` (system references by scope)

## AI Rules Priority (inside `ai/rules`)
1. `AGENTS.md`
2. `ai/rules/AI_RULES.md`
3. `ai/rules/GUARDRAILS.md`
4. `ai/rules/MODULE_BOUNDARIES.md`
5. `ai/rules/AGENTS_RUNTIME_RULES.md`
6. Support files (`ERROR_RECOVERY_PROTOCOL`, `WP_PLUGIN_PATTERNS`)

## Conflict Resolution
If two rule files conflict:
1. Code
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Remaining docs/rules

## Operational Guarantee
Following this hierarchy is mandatory for safe multi-AI continuity.
