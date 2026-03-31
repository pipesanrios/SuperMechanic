## Fase 39E-3 — Cierre documental

Fecha: 2026-03-31
Estado: COMPLETA

### Alcance cerrado

- Consumo UI de alertas persistidas (`sm_crm_alerts`) como fuente principal en:
  - list
  - kanban
  - view
- Consulta por lote sin N+1 para alertas activas por múltiples `crm_pipeline_id`.
- Fallback runtime controlado solo cuando no existen alertas persistidas para una oportunidad.
- Jerarquía visual preservada:
  - `overdue_task` crítico
  - resto de alertas en warning
- Consolidación de alertas en view para evitar duplicación de notices.

### Validación

- `php scripts/php-lint.php --all`: OK
- Runtime WordPress manual real: CONFIRMADA POR USUARIO
  - list: OK
  - kanban: OK
  - view: OK
  - no regresión: OK

### Restricciones preservadas

- Sin cambio de schema.
- Sin nuevas automatizaciones.
- Sin cron/email/notificaciones externas adicionales.
