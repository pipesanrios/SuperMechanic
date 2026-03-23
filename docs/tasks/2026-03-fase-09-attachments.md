# Tarea: Fase 9 — Attachments + Timeline del Proceso

## Objetivo

Implementar un sistema centralizado de documentos adjuntos que permita:

- subir archivos asociados a procesos
- registrar metadatos de documentos
- controlar visibilidad interna o cliente
- vincular documentos con procesos, vehiculos o clientes
- generar una timeline consolidada del proceso

## Alcance

Esta subfase implementa:

- tabla `sm_attachments`
- `Attachment Repository`
- `Attachment Service`
- `Attachment Admin Controller`
- `Process Timeline Service`
- shortcodes cliente para documentos y timeline
- integracion con procesos existentes
- control de visibilidad cliente/interno

## Fuera de alcance

No implementar todavia:

- almacenamiento externo (S3, etc.)
- firma digital
- OCR
- compresion avanzada
- editor PDF
- envio automatico por email
- notificaciones push
- subida desde frontend cliente
- modulo formal de reportes
- auditoria avanzada dedicada

## Archivos creados

- `includes/attachments/class-attachment-repository.php`
- `includes/attachments/class-attachment-service.php`
- `includes/attachments/class-attachment-admin-controller.php`
- `includes/attachments/class-process-timeline-service.php`
- `includes/attachments/class-client-attachment-shortcodes.php`

## Archivos modificados

- `includes/class-plugin.php`
- `includes/processes/class-process-admin-controller.php`
- `includes/processes/class-process-repository.php`
- `includes/processes/class-process-service.php`
- `includes/dashboard/class-client-dashboard-controller.php`
- `includes/database/class-schema.php`
- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## Tablas involucradas

- `sm_attachments`
- lectura de `sm_processes`
- lectura de `sm_process_step_logs`
- lectura de `sm_quotes`
- lectura de `sm_invoices`
- lectura de `sm_payments`

## Dependencias

- processes
- dashboard cliente
- quotes
- invoices
- payments
- ownership cliente-vehiculo

## Riesgos tecnicos

- exposicion de documentos internos al cliente
- subida de archivos sin validacion MIME
- ownership incorrecto del cliente
- timeline duplicada o inconsistente
- eliminacion de documentos sin control de integridad
- alta concentracion de integracion en `Process_Admin_Controller`

## Criterios de aceptacion

- se pueden subir documentos en el detalle del proceso
- documentos quedan asociados correctamente al proceso
- cliente solo ve documentos permitidos
- timeline muestra eventos relevantes del proceso
- consultas a BD solo desde repository
- no se rompe integracion con quotes ni invoices

## Estado

- `completada`
- estado global de la Fase 9 del roadmap: `parcial`

## Notas tecnicas

- se usa `wp_handle_upload()` para subida segura
- se validan MIME types permitidos
- se usan nonces en acciones admin
- se usa `current_user_can()` para permisos
- se valida ownership del cliente en shortcodes
- el schema real se actualizo a `1.8.0` al cerrar esta subfase
- se implemento la tabla real `sm_attachments`
- se integraron hooks y shortcodes nuevos en el bootstrap activo
- la documentacion base del proyecto fue alineada despues con el estado real consolidado del plugin
- desviacion respecto al alcance original: la subfase de adjuntos y timeline queda completada, pero Fase 9 global sigue parcial porque PDF real, reportes y auditoria avanzada siguen pendientes

## Cierre tecnico 2026-03-13

- Se implemento `sm_attachments` en `includes/database/class-schema.php`.
- Se crearon las clases activas del modulo en `includes/attachments/`.
- Se integro admin en `Process_Admin_Controller` con pestana `documents`.
- Se registraron shortcodes cliente para documentos y timeline.
- `Plugin` incorporo el wiring del modulo de adjuntos y timeline en la arquitectura activa `includes/*`.
- Se verifico sintaxis PHP de los archivos nuevos y modificados.

## Endurecimiento documental 2026-03-14

- Estado adicional: `cerrado`
- No hubo cambios de schema.
- Se ajusto `includes/dashboard/class-client-dashboard-controller.php` para dejar de renderizar `file_url` directo en documentos del proceso.
- Se ajusto `includes/attachments/class-client-attachment-shortcodes.php` para reutilizar la misma ruta segura de descarga y no renderizar enlaces cuando el adjunto no sea descargable para cliente.
- `includes/attachments/class-attachment-service.php` incorpora helper reusable para decidir si un adjunto puede exponerse como descargable en portal cliente.
- Desviacion positiva respecto al alcance original: la deuda de enlaces documentales inseguros en portal cliente queda cerrada sin crear una segunda arquitectura de descargas.
