# Fase 17. Control de acceso, visibilidad y ownership

## Estado

COMPLETA

## Objetivo

Endurecer el modelo de ownership y visibilidad del sistema sin tocar schema, evitando exposicion cruzada entre clientes y reduciendo checks dispersos de acceso.

## Archivos creados

- `includes/helpers/class-access-control-service.php`

## Archivos modificados

- `includes/dashboard/class-dashboard-service.php`
- `includes/processes/class-process-service.php`
- `includes/quotes/class-quote-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/attachments/class-attachment-service.php`
- `includes/communication/class-comment-service.php`
- `includes/communication/class-notification-service.php`
- `includes/dashboard/class-client-dashboard-controller.php`
- `includes/quotes/class-client-quote-shortcodes.php`
- `includes/invoices/class-client-invoice-shortcodes.php`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`

## Alcance implementado

- capa central `Access_Control_Service` para resolver ownership y acceso por usuario
- validacion reusable de acceso a `vehicle`, `process`, `quote`, `invoice` y `attachment`
- `Dashboard_Service` deja de ser la fuente primaria del ownership
- listados cliente de quotes e invoices refuerzan filtrado por usuario
- comments y notifications endurecen acceso sobre proceso u objeto real

## Tablas afectadas

Sin cambios de schema.

Tablas reutilizadas:

- `sm_client_vehicles`
- `sm_processes`
- `sm_quotes`
- `sm_invoices`
- `sm_attachments`

## Validacion tecnica

- `php -l` OK en `includes/helpers/class-access-control-service.php`
- `php -l` OK en los archivos modificados validados durante la sesión de implementación
- autoload compatible confirmado: `Super_Mechanic\Helpers\Access_Control_Service` resuelve correctamente a `includes/helpers/class-access-control-service.php` según `includes/autoloader.php`
- `Document_Service` y `Download_Service` no contradicen la política central: delegan acceso documental en `Invoice_Service`, `Quote_Service` y `Attachment_Service`, ya endurecidos en Fase 17
- sin cambios en `includes/modules/*`
- sin SQL nuevo fuera de repositories

## Deuda tecnica abierta

- revisar futuros entry points de staff/mecánico para mantener la restriccion de acceso por proceso asignado cuando aplique
