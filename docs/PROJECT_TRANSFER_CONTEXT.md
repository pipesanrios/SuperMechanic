# PROJECT_TRANSFER_CONTEXT.md

Long-form handoff context for multi-AI continuity.
Date: 2026-03-31

## This File Is
- A continuity/handoff layer.
- Not the source of truth for schema or exact runtime state.

## System Hierarchy (Reference)
1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md` + Prompt Master
4. `ai/rules/*`
5. `ai/context/*`
6. `docs/*`

## Current Baseline Summary
- Plugin `0.1.0`
- Schema `1.19.0`
- Phase baseline: Fase 39
- Block 39E: COMPLETE
  - internal scheduler
  - persisted CRM alerts
  - persisted-alert UI consumption

## Architectural Memory
- Active runtime path is `includes/*`.
- Legacy tree `includes/modules/*` is not an active architecture surface.
- Tenancy relies on `business_id` in tenant-aware modules.

## Key Continuity Risks
- Reintroducing duplicated rule files or conflicting doc authority.
- Mixing state/roadmap/architecture in the same document.
- Bypassing reading order and coding without context.

## Recommended Next Continuity
- Continue Phase 39 after 39E on top of persisted-alert foundation.

## Canonical References
- State: `docs/CURRENT_STATE.md`
- Architecture: `ARCHITECTURE.md`
- Roadmap: `docs/PLUGIN_ROADMAP.md`
- DB map: `docs/DATABASE_MAP.md`
- Rule hierarchy: `docs/RULE_SYSTEM.md`

## Task Execution Model
- The system now uses Task Contracts for all non-trivial tasks.
- Future AI sessions must load the contract before analysis and implementation.
- Contracts make scope, file boundaries, validation, and outputs explicit.
- This reduces regressions and prevents scope creep across multi-AI handoffs.
- Validation Contracts are the second layer for non-trivial phases.
- They define measurable verification and must run before closure when linked.
