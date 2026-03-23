# Fase 12D — Reportes Avanzados Base

## 1. Titulo de la tarea

- Fase 12D — Reportes avanzados base

## 2. Objetivo

- Extender el modulo `Reports` con comparativas simples por rango y un bloque avanzado admin-only sin introducir BI pesado ni cambios de schema.

## 3. Alcance

- Modulo `reports`
- comparativas por rango para procesos, quotes, invoices y payments
- calculo de periodo anterior equivalente cuando existe rango completo
- resumen ejecutivo simple
- agrupaciones avanzadas reutilizables para bloque admin

## 4. Fuera de alcance

- charts
- dashboards ejecutivos avanzados
- BI pesado
- analytics predictivo
- cron para KPIs
- cache avanzada
- exportacion PDF de reportes
- frontend cliente

## 5. Archivos a crear

- Ninguno en el codigo real implementado de 12D

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

- `sm_processes`
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

- cargar datasets completos en lugar de agregados
- duplicar responsabilidades de `Dashboard_Service`
- mostrar comparativas engañosas cuando no existe baseline comparable

## 10. Criterios de aceptacion

- comparativas por rango visibles en admin
- periodo anterior equivalente calculado solo con rango completo
- `N/A` cuando no existe baseline comparable
- resumen ejecutivo simple disponible
- sin cambios de schema
- sin tocar frontend cliente

## 11. Estado

- `completada`

## 12. Notas tecnicas

- `Report_Service` expone un bloque `advanced`
- `Report_Repository` resuelve comparativas mediante consultas agregadas por rango
- La implementacion preserva el caracter admin-only del modulo
