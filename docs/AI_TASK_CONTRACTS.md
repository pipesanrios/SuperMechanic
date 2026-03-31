# AI_TASK_CONTRACTS.md

## What is a Task Contract
A Task Contract is a mandatory execution agreement for a specific non-trivial task.
It defines scope, constraints, files, validation, outputs, and acceptance criteria before implementation starts.

## Why it exists
- Prevent scope creep
- Standardize execution quality
- Enforce input/output discipline
- Improve multi-AI continuity

## When it is required
Required for ALL non-trivial tasks.

## Relationship with existing governance
- `AGENTS.md`: hard rules authority
- `ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt`: execution engine and step order
- `docs/RULE_SYSTEM.md`: hierarchy and conflict resolution for rule layers

## Mandatory Task Contract Structure
Every contract MUST include:
1. Objective
2. Scope
3. Out of Scope
4. Allowed files
5. Forbidden files
6. Dependencies
7. Inputs required
8. Expected outputs
9. Validation rules
10. Acceptance criteria
11. Required documentation updates
12. Risks

Optional but recommended for non-trivial phases:
13. `validation_contract` (path reference)

## Enforcement
- No non-trivial task execution without contract.
- If contract is missing, create it before coding.
- If execution exceeds contract scope, stop and request clarification.

## Contract location
- Contracts live in `docs/contracts/`.
- Use naming convention `phase-subphase.md` (examples: `39E-2.md`, `40B-1.md`).

## Validation Contract link
- Task contracts may link a validation contract using:
  - `validation_contract: docs/contracts/validation/<phase>-validation.md`
- This keeps execution scope (task contract) separate from verification logic (validation contract).
