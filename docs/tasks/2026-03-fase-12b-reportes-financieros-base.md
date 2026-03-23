# Fase 12B — Reportes Financieros Base

## 1. Titulo de la tarea

- Fase 12B — Reportes financieros base

## 2. Objetivo

- Ampliar el modulo `Reports` con reportes financieros base para administracion interna sin tocar schema ni mover SQL fuera del repository.

## 3. Alcance

- Modulo `reports`
- filtros financieros por fechas
- filtros por `quote_status`
- filtros por `invoice_status`
- reportes de quotes por estado
- reportes de invoices por estado
- listados recientes de quotes, invoices y payments
- totales financieros por moneda

## 4. Fuera de alcance

- BI avanzado
- charts
- exportacion PDF de reportes
- cron
- cache avanzada
- cambios de schema
- frontend cliente

## 5. Archivos a crear

- Ninguno en el codigo real implementado de 12B

## 6. Archivos a modificar

- `includes/reports/class-report-repository.php`
- `includes/reports/class-report-service.php`
- `includes/reports/class-report-admin-controller.php`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## 7. Tablas involucradas

- `sm_quotes`
- `sm_invoices`
- `sm_payments`

## 8. Dependencias

- `Report_Repository`
- `Report_Service`
- `Report_Admin_Controller`
- `docs/CURRENT_STATE.md`
- `docs/DATABASE_MAP.md`

## 9. Riesgos

- mezclar importes de distintas monedas
- mover SQL analitico fuera de `Report_Repository`
- degradar claridad del modulo si se mezclan filtros operativos y financieros

## 10. Criterios de aceptacion

- reportes financieros base disponibles en admin
- quotes e invoices agrupados por estado
- listados recientes de quotes, invoices y payments
- totales financieros agrupados por moneda
- sin cambios de schema
- sin SQL en controller

## 11. Estado

- `completada`

## 12. Notas tecnicas

- El criterio temporal real de 12B para `total facturado` usa `sm_invoices.created_at`
- El criterio temporal real de 12B para `total cobrado` usa `sm_payments.payment_date`
- La implementacion real se apoyo en consultas agregadas dentro de `Report_Repository`
