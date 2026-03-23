# Fase 14B — Endurecimiento de quotes, timeline y cierre de escenarios

## 1. Objetivo

- endurecer con cambios mínimos el flujo maintenance -> quote
- alinear la timeline del proceso con el estado real de invoices
- cerrar desalineaciones funcionales detectadas en escenarios críticos sin ampliar alcance

## 2. Alcance ejecutado

- encapsulación transaccional mínima de `quote + items` al generar cotización desde maintenance
- corrección del contrato de retorno usado por admin al generar cotización
- alineación de eventos de invoice en timeline consolidada
- confirmación del wiring real de aprobación/rechazo de quote desde frontend cliente

## 3. Módulos tocados

- `quotes`
- `attachments`
- afectación funcional validada sobre `dashboard`, `processes` y `communication`

## 4. Archivos modificados

- `includes/quotes/class-quote-transaction-repository.php`
- `includes/quotes/class-quote-service.php`
- `includes/quotes/class-quote-admin-controller.php`
- `includes/attachments/class-process-timeline-service.php`
- `docs/TEST_SCENARIOS.md`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`

## 5. Tablas involucradas

- `sm_quotes`
- `sm_quote_items`
- `sm_invoices`
- `sm_payments`
- sin cambios de schema

## 6. Resultado técnico

- `Quote_Transaction_Repository` aporta frontera transaccional mínima para `create_quote_from_maintenance()`
- `Quote_Service` devuelve `quote_id` consistente para el flujo admin
- `Process_Timeline_Service` deja de tipar todas las facturas como `invoice_issued` y usa el estado real
- los escenarios críticos 7, 8 y 14 quedan cerrados en Fase 14B

## 7. Estado final

- completada
- sin cambios de bootstrap
- sin cambios en `includes/modules/*`
- sin cambios de schema
