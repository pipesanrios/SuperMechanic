# 2026-04 — Fase 56P7-B: Client Panel Data Resolution

## Objective
Restore real data visibility in `[mekvort_client_panel]` for authenticated client users when legacy client-user linkage is missing.

## Root Cause
- Client panel access depends on `Permission_Service -> Access_Control_Service::get_client_id_by_user_id(...)`.
- Resolution depended only on user meta `sm_client_id`.
- Existing datasets can have matching WP user and client email but no persisted `sm_client_id`, causing portal denial.

## Applied Fix
- Kept canonical runtime relation as `wp_user_id -> sm_client_id` (user meta).
- Hardened `Access_Control_Service::get_client_id_by_user_id(...)` to resolve in this order:
  1. existing persisted `sm_client_id` (primary path)
  2. safe fallback by exact WP user email match in current tenant scope
- Safe fallback rules:
  - only one exact email match allowed
  - fallback is blocked if matched client is already linked to another WP user
  - on valid unique fallback, `sm_client_id` is persisted automatically for subsequent requests

## Backward Compatibility
- Existing users already linked through `sm_client_id` are unchanged.
- No schema changes were introduced.
- No legacy module usage was introduced.

## Runtime Impact
- `[mekvort_client_panel]` now recovers expected client visibility in legacy/migrated datasets where user/client emails match uniquely.
