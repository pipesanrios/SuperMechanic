# Fase 20B. Comprobante de pago documental

## Estado

- Completado

## Objetivo

- cerrar la deuda documental pendiente de Fase 20
- agregar comprobante de pago reusable por `payment_id`
- mantener generacion bajo demanda, sin persistencia fisica ni attachments automaticos

## Archivos modificados

- `includes/helpers/class-document-service.php`
- `includes/helpers/class-pdf-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/communication/class-event-dispatcher.php`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`
- `.vscode/AI_CONTEXT.md`
- `ai/context/AGENTS_QUICK_CONTEXT.md`
- `ai/context/PROJECT_MEMORY.md`

## Clases ampliadas

- `Document_Service`
- `PDF_Service`
- `Invoice_Service`
- `Event_Dispatcher`

## Integración real

- `Document_Service` agrega el tipo documental `payment_receipt`
- el documento se resuelve por `payment_id` como identificador lógico único
- `PDF_Service` genera el comprobante bajo demanda reutilizando `Invoice_Service`
- `Invoice_Service` expone helper reusable para obtener pago, contexto consolidado, HTML y filename del receipt
- `Event_Dispatcher` prepara disponibilidad lógica para `payment_registered` e `invoice_paid`
- no se crean attachments
- no se persisten PDFs
- no hay cambios de schema

## Tablas afectadas

- `sm_payments`
- `sm_invoices`
- `sm_processes`
- `sm_clients`

## Validaciones ejecutadas

- `php -l` OK en `includes/invoices/class-invoice-service.php`
- `php -l` OK en `includes/helpers/class-pdf-service.php`
- `php -l` OK en `includes/helpers/class-document-service.php`
- `php -l` OK en `includes/communication/class-event-dispatcher.php`

## Desviaciones respecto al alcance

- no se agregaron entry points UI nuevos en admin ni shortcodes
- el comprobante queda disponible en la capa documental comun, pero su exposición visual explícita queda fuera de esta subfase

## Deuda técnica abierta

- si se decide mostrar el comprobante en UI futura, debe reutilizar `Document_Service` y `Download_Service` sin crear rutas paralelas
