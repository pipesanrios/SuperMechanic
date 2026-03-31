# AGENTS.md
Super Mechanic - Core AI Rules

This is the hard-rule file for any AI agent working in this repository.

## Mandatory Architecture Pattern
`Controller -> Service -> Repository -> Database`

## Non-Negotiable Rules
1. SQL only in Repository/Database layers.
2. No `$wpdb` usage outside Repository/Database.
3. Do not change schema unless phase explicitly requires it.
4. Tenant-aware modules must enforce `business_id`.
5. Do not break backward compatibility without explicit instruction.
6. Do not use or extend `includes/modules/*` (legacy).
7. Do not expose direct `file_url`; use `Document_Service` + `Download_Service`.

## Active Runtime Scope
- Runtime architecture: `includes/*`
- Legacy/reference only: `includes/modules/*`, `includes/class-rest-api.php`, `includes/class-hooks.php`, `includes/class-post-types.php`

## Source Of Truth By Topic
| Topic | Source |
|---|---|
| Current state | `docs/CURRENT_STATE.md` |
| Architecture | `ARCHITECTURE.md` |
| Database | `docs/DATABASE_MAP.md` |
| AI hard rules | `AGENTS.md` |
| Entry point | `AGENTS_BOOTSTRAP.md` |
| Continuity | `docs/PLUGIN_ROADMAP.md` |
| Handoff context | `docs/PROJECT_TRANSFER_CONTEXT.md` |

## Document Priority Rule
If documents conflict:
1. Code
2. `docs/CURRENT_STATE.md`
3. `.vscode/AI_CONTEXT.md`
4. `AGENTS.md`
5. `ARCHITECTURE.md`
6. Remaining docs

## Required Validation Baseline
Before declaring completion:
- `php scripts/php-lint.php --all`
- Runtime manual validation (or explicit statement that it was not executed)
- Documentation alignment update

## Task Contract Enforcement
1. AI must follow Task Contracts strictly for non-trivial tasks.
2. No deviation from contract scope.
3. No unauthorized file modification outside contract boundaries.
4. Contract defines allowed files and expected outputs.
5. If task exceeds contract scope, STOP and request clarification.

## Documentation Update Minimum
When phase/subphase closes, update as applicable:
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/TEST_SCENARIOS.md`
- `.vscode/AI_CONTEXT.md`
- `docs/tasks/<task>.md`

## Entry Reminder
Official entrypoint is `AGENTS_BOOTSTRAP.md`.
