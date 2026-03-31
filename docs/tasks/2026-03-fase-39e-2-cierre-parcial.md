# Fase 39E-2 — Cierre Parcial Documental

Fecha: 2026-03-31

## Estado

- 39E-2: PARCIAL

## Alcance consolidado

- Persistencia de alertas CRM en `sm_crm_alerts`.
- Tipos base:
  - `overdue_task`
  - `inactive_opportunity`
  - `follow_up_needed`
  - `conversion_pending`
- Recalculo por lotes desde `sm_crm_scheduler_tick`.
- Deduccion funcional para evitar duplicacion de alertas `active` por tipo/pipeline/negocio.
- Resolucion de alertas a `resolved` cuando la condicion deja de aplicar.

## Validaciones ejecutadas

- `php scripts/php-lint.php --all` (OK).

## Validaciones pendientes

- Runtime WordPress manual formal del checklist 39E-2:
  - tick scheduler real
  - creacion de alertas activas por tipo
  - no duplicacion de `active` en ticks repetidos
  - transicion a `resolved`
  - no regresion transversal (pipeline/tasks/kanban/calendar/scheduler)

## Notas

- Hotfix i18n aplicado en paralelo:
  - `load_plugin_textdomain('super-mechanic', ...)` movido a `init` prioridad `0`.
  - Bootstrap funcional en `plugins_loaded` preservado.
