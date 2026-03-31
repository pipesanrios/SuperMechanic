# RULE_SYSTEM.md

## Purpose

Explain the **unified AI rule hierarchy** and how to resolve conflicts across:

- rules
- contracts
- context
- documentation

This file defines **how decisions are made when multiple sources exist**.

---

## System Hierarchy

The repository is governed by the following order:

1. `AGENTS_BOOTSTRAP.md`
   - entrypoint
   - mandatory reading order

2. `AGENTS.md`
   - hard rules
   - architectural constraints
   - enforcement layer

3. `.vscode/AI_CONTEXT.md` + Prompt Master
   - execution flow
   - runtime behavior

4. **Task Contracts**
   - define scope
   - define allowed files
   - define outputs

5. **Validation Contracts**
   - define how correctness is verified

6. `ai/rules/*`
   - specialized rule system

7. `ai/context/*`
   - support context only (not authoritative)

8. `docs/*`
   - system documentation (by scope)

---

## Rule Priority Inside `ai/rules/*`

When multiple rule files apply:

1. `AGENTS.md`
2. `ai/rules/AI_RULES.md`
3. `ai/rules/GUARDRAILS.md`
4. `ai/rules/MODULE_BOUNDARIES.md`
5. `ai/rules/AGENTS_RUNTIME_RULES.md`
6. Support:
   - `ERROR_RECOVERY_PROTOCOL.md`
   - `WP_PLUGIN_PATTERNS.md`

---

## Contract Authority

Contracts override interpretation, not rules.

### Task Contracts define:

- what is allowed
- what is in scope
- which files can be modified

### Validation Contracts define:

- what must be verified
- how completion is determined

### Critical Rule

- No work outside contract scope
- No file modification outside contract boundaries
- No phase closure without validation alignment

---

## Conflict Resolution

If there is a conflict between any elements:

### Priority Order

1. **Code**
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Task Contract (scope authority)
7. Validation Contract (validation authority)
8. Remaining docs and rules

---

## Interpretation Rules

- Rules define **how to behave**
- Contracts define **what to do**
- Context defines **how to execute**
- Docs define **what exists**

No single layer replaces the others.

---

## Operational Guarantees

Following this system ensures:

- safe multi-AI continuity
- no scope creep
- no rule duplication
- no architectural drift
- no uncontrolled modifications

---

## What Is Not Allowed

- Ignoring Task Contracts
- Ignoring Validation Contracts
- Mixing rule authority with documentation
- Treating `ai/context/*` as source of truth
- Overriding `AGENTS.md`
- Implementing without entrypoint context

---

## Final Rule

If this hierarchy is not followed:

→ the AI execution is considered **invalid**  
→ and must not modify the system