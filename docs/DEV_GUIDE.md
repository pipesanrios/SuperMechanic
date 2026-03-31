# DEV_GUIDE.md

Audience:
Human developers.

Purpose:
Practical development guidance without replacing AI governance files.

## Start Here
- Entry point: `AGENTS_BOOTSTRAP.md`
- Hard rules: `AGENTS.md`

## Development Baseline
- Architecture: `Controller -> Service -> Repository -> Database`
- SQL only in Repository/Database layers
- Respect tenancy (`business_id`) in tenant-aware modules
- Keep compatibility unless explicitly approved otherwise

## Minimum Validation
- `php scripts/php-lint.php --all`
- Runtime/manual checks when feature scope requires them

## Documentation Alignment
When closing work, align:
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/TEST_SCENARIOS.md`
- `.vscode/AI_CONTEXT.md`

## Scope Reminder
This file is a dev companion; it is not the source of truth for state/schema.
