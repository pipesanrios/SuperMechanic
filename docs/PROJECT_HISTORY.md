# PROJECT_HISTORY.md

## Purpose

Historical record of the system evolution.

This file documents:
- phases executed
- architectural evolution
- key system decisions

This file is NOT:
- current state → see `CURRENT_STATE.md`
- roadmap → see `PLUGIN_ROADMAP.md`
- source of truth → code + CURRENT_STATE

---

# 🧱 PHASE 0 — Foundation

- System concept definition
- WordPress plugin chosen as base
- Decision: custom tables instead of post types
- Modular architecture defined

👉 Result:
Architectural base established

---

# ⚙️ PHASE 1 — Plugin Core

- Plugin bootstrap
- Autoloader
- Activation / deactivation hooks
- Table creation (`dbDelta`)
- Roles and capabilities
- Admin menu
- Base settings

👉 Result:
Functional plugin skeleton

---

# 🧾 PHASE 2 — Master Data

- Clients
- Vehicles
- Client ↔ vehicle relation
- Ownership transfer
- CRUD + filters

👉 Result:
Stable data layer

---

# 🔄 PHASE 3 — Process Engine

- Processes table
- Flow definitions
- Configurable steps
- Status tracking
- Logs

👉 Result:
Dynamic workflow engine

---

# 🔧 PHASE 4 — Maintenance

- Diagnostics
- Technical notes
- Parts and labor
- Mechanic assignment

👉 Result:
Workshop operations supported

---

# 🚗 PHASE 5 — Pre-Delivery

- Insurance
- Plate processing
- Final inspection
- Delivery readiness

👉 Result:
Vehicle delivery tracking

---

# 📑 PHASE 6 — Administrative Processes

- Configurable process types
- Attachments
- Deadlines

👉 Result:
Paperwork system

---

# 🧑‍💻 PHASE 7 — Dashboards & Roles

- Admin dashboard
- Mechanic portal
- Client portal (shortcodes)

👉 Result:
Operational UI layer

---

# 💰 PHASE 8 — Commercial Layer

- Quotes
- Invoices
- Payments

👉 Result:
Monetization capability

---

# 📎 PHASE 9 — Timeline & Attachments

- File uploads
- Timeline tracking
- Visibility control

👉 Result:
Traceability system

---

# 🔐 PHASE 10–17 — Security & Ownership

- Ownership model (client ↔ vehicle ↔ process)
- Access control
- Permissions
- Data integrity

👉 Result:
Secure system foundation

---

# 🧩 PHASE 18–26 — Operational Expansion

- Workflow improvements
- UI improvements
- Reporting base
- Process stabilization

👉 Result:
System usable in real environments

---

# 🧠 PHASE 27–30 — Architecture Consolidation

- Controller / Service / Repository pattern enforced
- Code normalization
- Removal of mixed logic
- Modularization

👉 Result:
Clean and maintainable architecture

---

# 📊 PHASE 31–38 — CRM Layer

- Pipeline system
- CRM tasks
- Follow-ups
- Opportunity tracking

👉 Result:
Commercial + operational CRM integration

---

# 🚨 PHASE 39 — CRM Operational Core

## 🔹 39B — CRM Base Stabilization
- Task system stabilization
- Pipeline consistency

## 🔹 39C — Scheduler Foundation
- Internal scheduler introduced

## 🔹 39D — Alert Logic
- Runtime alert computation
- Early signal system

## 🔹 39E — Persisted Alerts (CRITICAL MILESTONE)

- Scheduler: `sm_crm_scheduler_tick`
- Persisted alerts: `sm_crm_alerts`
- Batch recalculation
- State lifecycle:
  - active → resolved

### UI Integration
- list
- kanban
- view

### Fallback System
- runtime fallback only when no persisted data

👉 Result:

System transitions from:

- ❌ runtime-only signals  
→ ✅ persisted operational signals

---

# 🧠 SYSTEM EVOLUTION SUMMARY

The system evolved through:

1. CRUD system
2. Workflow engine
3. Operational platform
4. CRM system
5. Signal-based system (alerts persisted)

---

# 📍 CURRENT POSITION

The system is now:

- stable
- modular
- multi-module integrated
- contract-driven
- alert-driven (persisted signals)

Ready for:

👉 operational layer

---

# 🚀 PHASE 40 — Operational Layer (CURRENT DIRECTION)

## Objective

Transform system into a **daily operational tool**

Focus:
- visibility
- prioritization
- workload clarity
- friction reduction

---

## 🔹 40B — Workload Operativo por Usuario (NEXT STEP)

- unified workload per user
- aggregation of:
  - tasks
  - alerts (persisted)
  - processes
  - appointments

👉 This is the **entrypoint of real operation**

---

## 🔹 40A — Control Operativo Unificado

- global system visibility

---

## 🔹 40C — SLA / Metrics

- process timing
- bottlenecks

---

## 🔹 40D — Operational Audit

- traceability
- accountability

---

## 🔹 40E — Process Optimization

- remove friction
- simplify workflows

---

# 🔮 FUTURE EVOLUTION

## Phase 41 — Infrastructure

- managed hosting
- centralized deployment

## Phase 42 — SaaS

- independent backend
- custom frontend
- migration path

---

# ⚠️ FINAL RULE

This file is historical.

If conflict exists:

1. Code
2. CURRENT_STATE.md
3. AI_CONTEXT.md

This file never overrides runtime truth.