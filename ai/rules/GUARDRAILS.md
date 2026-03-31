GUARDRAILS.md - CRITICAL

PURPOSE:
Provide critical safety constraints for architecture, security, and scope.

SCOPE:
Execution-time constraints for any coding or refactor action.

WHEN TO USE:
Before implementation and before merging any change set.

WHEN NOT TO USE:
Do not use as a phase-planning document.

## Critical Guardrails
1. No SQL outside Repository/Database layers.
2. No schema changes without explicit phase requirement.
3. No scope expansion beyond requested work.
4. No use of legacy `includes/modules/*`.
5. No direct `file_url` exposure.
6. No cross-tenant leakage; enforce `business_id`.
7. No bootstrap rewiring unless explicitly required.

## Validation Guardrail
Minimum technical validation: `php scripts/php-lint.php --all`.
