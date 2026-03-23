# Fase 18 - Portal mecanico real

Estado: completado

## Alcance implementado

- portal mecanico admin-side reutilizando la arquitectura activa
- listado de procesos accesibles para mecanico
- detalle operativo por proceso
- cambio controlado de estado
- cambio controlado de paso
- nota tecnica interna
- lectura de timeline, comentarios, adjuntos y mantenimiento

## Archivos principales

- `includes/dashboard/class-mechanic-dashboard-controller.php`
- `includes/class-plugin.php`
- `includes/attachments/class-process-timeline-service.php`

## Tablas reutilizadas sin cambios de schema

- `sm_processes`
- `sm_process_step_logs`
- `sm_maintenance`
- `sm_maintenance_parts`
- `sm_maintenance_labor`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

## Notas tecnicas

- el portal mecanico no replica el flujo admin completo del proceso
- las acciones operativas siguen pasando por `Process_Service` y `Comment_Service`
- las descargas siguen usando la ruta segura comun
- no se tocaron `includes/modules/*` ni el schema

## Deuda tecnica abierta

- mantener acotado el portal mecanico para que no crezca como una copia de `Process_Admin_Controller`
- si en una fase futura se habilita carga de adjuntos desde mecanico, debe seguir la misma politica documental y de acceso central
