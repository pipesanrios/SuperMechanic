# Fase 16. Automatizaciones y eventos operativos

## Estado

- completado

## Alcance real cerrado

- ampliacion minima del catalogo real de eventos internos sin introducir cron, colas ni integraciones externas
- estandarizacion de dispatch en `Process_Service`, `Quote_Service` e `Invoice_Service`
- ampliacion de `Notification_Service` como consumidor del bus interno
- ajuste semantico de `Process_Timeline_Service` para reflejar mejor quotes e invoices segun estado real y cobranza

## Archivos modificados

- `includes/communication/class-event-dispatcher.php`
- `includes/communication/class-notification-service.php`
- `includes/processes/class-process-service.php`
- `includes/quotes/class-quote-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/attachments/class-process-timeline-service.php`

## Archivos validados

- `includes/communication/class-event-dispatcher.php`
- `includes/communication/class-notification-service.php`
- `includes/processes/class-process-service.php`
- `includes/quotes/class-quote-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/attachments/class-process-timeline-service.php`
- `includes/class-plugin.php`

## Notas tecnicas finales

- `Event_Dispatcher` ahora registra y enruta eventos especificos de procesos, quotes e invoices ademas del catalogo previo
- `process_updated` queda reservado para cambios generales y deja de duplicar creacion o cambios de paso
- los eventos nuevos se disparan despues de persistencia exitosa y despues de fronteras transaccionales existentes cuando aplica
- `invoice_paid` se despacha solo cuando la cobranza cambia realmente a `paid`, no en cada alta o edicion de pago
- la timeline sigue siendo composicion por lectura y no se convierte en una segunda proyeccion basada en el bus

## Desviaciones

- no se introdujeron jobs, cron, colas, webhooks ni integraciones externas
- no se modifico schema

## Riesgos restantes

- el bus sigue centrado en automatizacion interna y notificaciones; si crece en futuras fases convendra formalizar un catalogo documental aun mas estricto por modulo
- los estados visibles al cliente siguen dependiendo de no exponer logs internos de proceso fuera de los entry points ya controlados
