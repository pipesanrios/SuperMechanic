WP_PLUGIN_PATTERNS.md

PURPOSE:
Capture WordPress plugin implementation patterns used by Super Mechanic.

SCOPE:
Bootstrap, hooks, controllers, services, repositories.

WHEN TO USE:
When implementing or reviewing WordPress integration points.

WHEN NOT TO USE:
Do not use as authority for phase status or roadmap.

## Patterns
1. Bootstrap entry: `super-mechanic.php`.
2. Runtime composition root: `includes/class-plugin.php`.
3. Business logic in Services.
4. Persistence logic in Repositories.
5. Admin/frontend integration in Controllers/Shortcodes.

## Security Basics
- Capability checks
- Nonces
- Sanitize input / escape output
