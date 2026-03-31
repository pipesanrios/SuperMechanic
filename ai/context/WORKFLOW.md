# WORKFLOW.md
THIS FILE IS NOT SOURCE OF TRUTH.

Role:
Execution method only.

Workflow:
1. Read mandatory context from `AGENTS_BOOTSTRAP.md`.
2. Read context support files.
3. Load task contract.
4. Validate scope boundaries from contract.
5. Run analysis phase.
6. Run implementation phase.
7. Load and execute validation contract (when linked).
8. Run QA Runner for automated checks (when applicable).
9. Run validation phase summary.
10. Run documentation alignment phase.

Do not use it for:
- architecture decisions
- phase status truth
- schema truth
