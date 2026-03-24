# Fase 13 — Integridad transaccional y endurecimiento del nucleo

## 1. Titulo de la tarea

- Fase 13 — Integridad transaccional y endurecimiento del nucleo

## 2. Objetivo

- Reducir el riesgo principal de integridad del sistema coordinando de forma atomica la mutacion de `sm_processes` y la escritura de `sm_process_step_logs`.

## 3. Alcance

- Modulo `processes`
- frontera transaccional dedicada del modulo
- atomicidad basica para creacion, actualizacion y cambio directo de paso
- mantenimiento de compatibilidad con timeline, dashboards y modulos dependientes

## 4. Fuera de alcance

- refactor global de `processes`
- rediseño de `Process_Admin_Controller`
- cambios de schema
- indices nuevos
- REST
- Client Portal
- reports
- PDFs
- WooCommerce

## 5. Archivos a crear

- `includes/processes/class-process-transaction-repository.php`
- `docs/tasks/2026-03-fase-13-integridad-transaccional-endurecimiento-nucleo.md`

## 6. Archivos a modificar

- `includes/processes/class-process-service.php`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## 7. Tablas involucradas

- `sm_processes`
- `sm_process_step_logs`

## 8. Dependencias

- `Process_Service`
- `Process_Repository`
- `Process_Transaction_Repository`
- `Flow_Service`
- `Flow_Step_Service`
- `Flow_Step_Repository`
- `Event_Dispatcher`

## 9. Riesgos

- romper compatibilidad de create/update del modulo
- disparar eventos antes de commit
- dejar rollback incompleto si falla un log relacionado
- introducir acoplamiento indebido entre service y repository

## 10. Criterios de aceptacion

- `create_process()` persiste proceso y `step_initialized` atomica y limpiamente
- `update_process()` persiste proceso y logs asociados de forma atomica cuando corresponde
- `update_current_step()` persiste cambio de paso y `step_transition` de forma atomica
- sin cambios de schema
- sin cambios en bootstrap
- sin mover logica de negocio fuera de `Process_Service`

## 11. Estado

- `completada`

## 12. Notas tecnicas

- `Process_Transaction_Repository` encapsula `START TRANSACTION`, `COMMIT` y `ROLLBACK`
- la implementacion final valida inicio y confirmacion real de transaccion antes de reportar exito
- `Process_Service` sigue resolviendo flow, step, validaciones y dispatch de eventos
- los eventos del proceso se mantienen despues de commit exitoso
