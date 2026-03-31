# CURRENT_STATE.md

Date: 2026-03-31

Purpose:
Current system state only. No historical narrative.

## Runtime Versions
- Plugin: `0.1.0`
- Schema: `1.19.0`

## Active Runtime Architecture
- Active path: `includes/*`
- Legacy path (not active): `includes/modules/*`
- Pattern: `Controller -> Service -> Repository -> Database`

## Current Delivery Baseline
- Phase baseline: `Fase 39`
- Block status:
  - `39B` COMPLETE
  - `39C` COMPLETE
  - `39D` COMPLETE
  - `39E` COMPLETE

## 39E Complete Scope
- Internal scheduler: `sm_crm_scheduler_tick`
- CRM persisted alerts: `sm_crm_alerts`
- Persisted alerts consumed in UI (list/kanban/view)
- Controlled runtime fallback when persisted alerts are absent

## Operational Constraints
- No external notification automation yet (email/whatsapp/etc.)
- No mass automation rollout yet
- Tenancy enforced by `business_id`

## Known Active Debt (Current)
- Legacy placeholder files still present (`class-rest-api`, `class-hooks`, `class-post-types`)
- No full automated WordPress E2E runtime suite
- API key/webhook admin UX can be expanded in next continuity

## Next Continuity
- Continue `Fase 39` after block 39E using persisted-alert foundation.
