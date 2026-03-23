# Tarea: Fase 10 — Communication / Comments / Notifications

## Objetivo

Implementar una base operativa de comunicacion interna y cliente que permita:

- comentarios internos por proceso u objeto funcional
- mensajes cliente/staff
- notificaciones internas por usuario o cliente
- dispatcher de eventos internos reutilizable
- integracion con timeline y dashboard cliente

## Alcance

Esta fase implementa:

- tabla `sm_comments`
- tabla `sm_notifications`
- `Comment Repository`
- `Comment Service`
- `Notification Repository`
- `Notification Service`
- `Event Dispatcher`
- shortcodes cliente para comentarios y notificaciones
- integracion con procesos, quotes, invoices, pagos y adjuntos
- feed basico de notificaciones en admin y cliente

## Fuera de alcance

No implementar todavia:

- email real
- WhatsApp
- push notifications
- colas asincronas
- cron complejo
- WebSockets
- app movil
- IA
- integraciones externas

## Archivos creados

- `includes/communication/class-comment-repository.php`
- `includes/communication/class-comment-service.php`
- `includes/communication/class-notification-repository.php`
- `includes/communication/class-notification-service.php`
- `includes/communication/class-event-dispatcher.php`
- `includes/communication/class-client-comment-shortcodes.php`

## Archivos modificados

- `includes/class-plugin.php`
- `includes/database/class-schema.php`
- `includes/processes/class-process-service.php`
- `includes/processes/class-process-admin-controller.php`
- `includes/quotes/class-quote-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/attachments/class-attachment-service.php`
- `includes/attachments/class-process-timeline-service.php`
- `includes/dashboard/class-client-dashboard-controller.php`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## Tablas involucradas

- `sm_comments`
- `sm_notifications`
- lectura de `sm_processes`
- lectura de `sm_process_step_logs`
- lectura de `sm_quotes`
- lectura de `sm_invoices`
- lectura de `sm_payments`
- lectura de `sm_attachments`

## Dependencias

- processes
- quotes
- invoices
- payments
- attachments
- dashboard cliente
- ownership cliente-vehiculo

## Riesgos tecnicos

- exposicion de comentarios internos al cliente
- notificaciones ajenas visibles por fallo de ownership
- duplicacion de eventos en timeline
- alta concentracion de integracion en `Process_Admin_Controller`
- desincronizacion documental si no se actualiza la base tecnica

## Criterios de aceptacion

- se pueden registrar comentarios desde el detalle del proceso
- cliente puede ver comentarios visibles y enviar mensajes propios
- se generan notificaciones internas reutilizables
- timeline integra comentarios y eventos operativos relevantes
- consultas a BD solo desde repositories
- no se rompe integracion con quotes, invoices ni adjuntos

## Estado

- `completada`
- estado global de la Fase 10 del roadmap: `implementada en su base operativa`

## Notas tecnicas

- se usa `Event_Dispatcher` como dispatcher interno del plugin
- `Quote_Service`, `Invoice_Service`, `Attachment_Service` y `Process_Service` disparan eventos base
- `Comment_Service` dispara evento al registrar comentario
- cliente solo ve comentarios no internos y notificaciones propias
- el schema real se actualizo a `1.9.0`
- la documentacion base del proyecto fue alineada despues con el estado real consolidado del plugin
- desviacion respecto al alcance original: la base operativa de comunicacion queda completada, pero email real, WhatsApp, push notifications, colas y automatizaciones externas siguen fuera de alcance

## Cierre tecnico 2026-03-13

- Se implementaron `sm_comments` y `sm_notifications` en `includes/database/class-schema.php`.
- Se crearon las clases activas del modulo en `includes/communication/`.
- `Plugin` incorporo el wiring del modulo de comunicacion y del dispatcher en la arquitectura activa `includes/*`.
- `Process_Admin_Controller` integro la pestana `communication` en el detalle del proceso.
- `Process_Timeline_Service` incorporo comentarios y eventos operativos relacionados.
- Se registraron shortcodes cliente para comentarios y notificaciones.
- Se verifico sintaxis PHP de los archivos nuevos y modificados.
- Se corrigio la documentacion de `sm_comments` para reflejar las columnas reales `vehicle_id`, `author_user_id`, `author_client_id` y `status`.