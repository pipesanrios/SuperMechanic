# Fase 15. Hardening final de pagos

## Estado

- completado

## Alcance real cerrado

- endurecimiento final de `Invoice_Service` para usar `sm_payments` como unica fuente de verdad financiera
- correccion de validacion en edicion de pagos excluyendo el `payment_id` actual del calculo disponible
- ajuste quirurgico de labels en `Invoice_Admin_Controller`
- correccion de reporting financiero agregado en `Report_Repository` sin tocar schema

## Archivos modificados

- `includes/invoices/class-invoice-service.php`
- `includes/invoices/class-invoice-admin-controller.php`
- `includes/reports/class-report-repository.php`

## Archivos validados

- `includes/invoices/class-invoice-service.php`
- `includes/invoices/class-invoice-admin-controller.php`
- `includes/reports/class-report-repository.php`
- `includes/reports/class-report-service.php`

## Notas tecnicas finales

- `sm_payments` queda como fuente primaria para validacion, saldo y resumen de cobranza
- `sm_invoices.amount_paid` y `sm_invoices.balance_due` se mantienen solo por compatibilidad operativa
- `income_by_period` no se modifico; ya estaba correctamente basado en `payment_date`
- no hubo cambios de schema, bootstrap ni `class-plugin.php`

## Desviaciones

- ninguna desviacion funcional respecto al alcance pedido

## Riesgos restantes

- todavia existen campos cacheados en `sm_invoices` por compatibilidad; futuras ampliaciones no deben volver a tratarlos como fuente primaria de decision financiera
