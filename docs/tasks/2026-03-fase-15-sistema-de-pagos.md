# Fase 15. Sistema de pagos

## Objetivo

Cerrar el flujo de pagos manuales sobre invoices existentes sin cambiar schema y sin romper el comportamiento actual del modulo `Invoices`.

## Alcance implementado

- endurecimiento de validaciones de pago en `Invoice_Service`
- rechazo de pagos que exceden el saldo pendiente
- resumen reusable de cobranza por invoice con estados visibles `pending`, `partial` y `paid`
- ampliacion de la UI admin de invoices para mostrar estado de cobro
- ampliacion minima de `Reports` con estado de cobro agregado e ingresos basicos por periodo

## Archivos modificados

- `includes/invoices/class-invoice-service.php`
- `includes/invoices/class-invoice-admin-controller.php`
- `includes/reports/class-report-repository.php`
- `includes/reports/class-report-service.php`
- `includes/reports/class-report-admin-controller.php`
- `ARCHITECTURE.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/SYSTEM_MAP.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/DATABASE_MAP.md`

## Tablas reutilizadas

- `sm_invoices`
- `sm_payments`
- `sm_clients`
- `sm_processes`

## Decisiones tecnicas

- no se modifica `sm_payments`; el schema actual ya cubre el alcance de la fase
- los estados internos de invoice no se reemplazan para no romper contratos existentes
- el estado de cobro visible se calcula a nivel de service y reporting
- los ingresos por periodo se calculan sobre `sm_payments.payment_date`

## Riesgos residuales

- el modulo sigue siendo manual; no existe pasarela de pago ni conciliacion automatica
- `Invoice_Admin_Controller` sigue creciendo como punto sensible del admin
- cualquier futura integracion externa de pagos debe reutilizar `Invoice_Service` para no duplicar validaciones de saldo
