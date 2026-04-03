# PLUGIN_ROADMAP.md

## Purpose

Forward continuity only (future planning).  
Historical closure details live elsewhere.

Use:
- `docs/CURRENT_STATE.md` for current confirmed state
- this file only for what comes next

---

## Baseline

- Current delivery baseline: **Fase 40**
- Last completed block: **40D (UI consistency CRM pipeline)**

---

## Phase 40 — Operación Interna (Core) (COMPLETED)

Completed scope:
- 40A: global operational summary
- 40B: per-user workload aggregation
- alignment hotfix: CRM Pipeline signal-policy consistency (`persisted` + `runtime fallback` policy)
- 40C: SLA/operational metrics
- 40D: CRM pipeline UI consistency

---

### Subphases Status

#### 🔹 40B — Workload Operativo por Usuario (COMPLETED)

Goal:
Provide a clear view of:

- pending tasks
- overdue items
- alerts (persisted)
- active processes
- appointments

Characteristics:
- aggregation only (no new logic creation)
- no new tables
- no cron
- no notifications
- based on persisted alerts system (39E)

Delivered:
- `includes/dashboard/class-workload-service.php`
- admin dashboard section: **Mi trabajo**
- per-user aggregation: tasks, persisted alerts, active processes, upcoming appointments
- priority buckets: `critical`, `warning`, `normal`
- validation complete: php lint + QA runner + runtime manual

---

#### 🔹 40A — Control Operativo Unificado (COMPLETED)

- global operational visibility across modules
- system-level awareness
- base for consolidated operations view

---

#### 🔹 40C — SLA / Tiempos / Métricas (COMPLETED)

- operational metrics for:
  - tasks
  - processes
  - alerts
  - appointments

---

#### 🔹 40D — UI Consistency CRM Pipeline (COMPLETED)

- compact and consistent operational UI rendering
- no logical/backend changes

---

## Next Planned Continuity

### Phase 41 — Automatización Operativa (NEXT)

- automate operational execution on top of stable signals and SLA visibility
- reduce repetitive manual actions
- keep strict tenancy and architectural constraints

---

### Phase 42 — SaaS Evolution

- central backend
- independent frontend
- progressive migration from plugin model

---

## Roadmap Rules

- Do not rewrite completed history in this file
- Keep focus on forward continuity only
- Do not mix current state with planning
- Do not skip phases without explicit decision

---

## Priority Rule

If roadmap conflicts with:

- code
- `docs/CURRENT_STATE.md`

→ current state and code win  
→ roadmap must be updated
