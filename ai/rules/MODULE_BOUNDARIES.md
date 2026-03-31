MODULE_BOUNDARIES.md

PURPOSE:
Define safe cross-module interaction rules.

SCOPE:
Service/repository boundaries across plugin modules.

WHEN TO USE:
When a change touches more than one module.

WHEN NOT TO USE:
Do not use for UI copy/style-only adjustments.

## Boundary Rules
1. Module-to-module access goes through Services, not repositories.
2. Controllers consume Services only.
3. Repositories are module-local persistence points.
4. Avoid circular dependencies.

## Allowed Interaction Pattern
`Module A Service -> Module B Service -> Module B Repository`

## Disallowed Interaction Pattern
`Module A Repository -> Module B Repository`
