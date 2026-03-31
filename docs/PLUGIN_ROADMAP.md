# PLUGIN_ROADMAP.md

## Purpose

Forward continuity only (future planning).  
Historical closure details live elsewhere.

Use:
- `docs/CURRENT_STATE.md` for current confirmed state
- this file only for what comes next

---

## Baseline

- Current delivery baseline: **Fase 39**
- Last completed block: **39E (alerts persistidas + scheduler + UI consumo estable)**

---

## Next Planned Continuity

### Phase 40 — Operación Interna (Core)

Objective:
Transform the system into a **daily operational tool**, reducing friction, manual effort, and cognitive load.

This phase focuses on:
- usability
- prioritization
- visibility
- operational control

Not focused on:
- marketing
- UI aesthetics
- external automation
- SaaS yet

---

### Subphases

#### 🔹 40B — Workload Operativo por Usuario (ENTRYPOINT)

First implementation step.

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

---

#### 🔹 40A — Control Operativo Unificado

Goal:
Global operational visibility across modules.

- cross-module state overview
- system-level awareness
- builds on 40B aggregation layer

---

#### 🔹 40C — SLA / Tiempos / Métricas

Goal:
Measure real system performance.

- process durations
- bottlenecks
- delays
- time-to-completion

---

#### 🔹 40D — Auditoría Operativa Profunda

Goal:
Traceability and accountability.

- who did what
- when
- why

---

#### 🔹 40E — Optimización de Procesos Internos

Goal:
Remove real friction.

- simplify flows
- reduce manual steps
- eliminate redundant actions

---

## Future Phases (After Operational Maturity)

### Phase 41 — Managed Hosting / Centralized Deployment

- standardized environments
- centralized support
- deployment control

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