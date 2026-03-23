# ARCHITECTURE

## 1. Informacion general del proyecto

- Plugin: Super Mechanic
- Tipo: plugin WordPress modular orientado a talleres mecanicos y operaciones de concesionario.
- Objetivo: centralizar clientes, vehiculos, procesos operativos, mantenimiento, pre-entrega, tramites, cotizaciones, facturacion y portal cliente en una sola base tecnica.
- Stack tecnico:
  - PHP 7.4+
  - WordPress 6.4+
  - Arquitectura orientada a objetos
  - `$wpdb` para persistencia
  - Shortcodes para frontend cliente
  - Bootstrap propio de plugin en `super-mechanic.php`
  - Migraciones y schema versionado en `includes/database/*`

## 2. Reglas de arquitectura

- `Repository` = acceso a base de datos.
- `Service` = logica de negocio.
- `Controller` = UI admin y coordinacion de pantallas.
- `REST Controller` = endpoints desacoplados cuando aplique.
- `Shortcodes` = exposicion frontend para cliente.
- No se permite SQL directo en controllers.
- Toda entrada debe validarse y sanitizarse.
- Toda salida debe escaparse.
- Toda consulta a BD debe usar `$wpdb` y prepared statements cuando corresponda.
- Seguridad obligatoria:
  - `sanitize_text_field`
  - `sanitize_email`
  - `esc_html`
  - `esc_attr`
  - `wp_verify_nonce` o `check_admin_referer`
  - `current_user_can`
- El detalle de proceso es hoy el hub funcional del sistema. Cualquier cambio ahi debe preservar integraciones con maintenance, quote, invoice, attachments y communication.
- El flujo de pagos vive sobre `sm_invoices` y `sm_payments`; las reglas de saldo y estado de cobranza deben resolverse en `Invoice_Service`, no en controllers.

## 3. Estructura real del plugin

```text
super-mechanic/
|-- super-mechanic.php
|-- uninstall.php
|-- ARCHITECTURE.md
|-- docs/
|   |-- CURRENT_STATE.md
|   |-- FINAL_ARCHITECTURE_MAP.md
|   |-- MODULE_REGISTRY.md
|   |-- DATABASE_MAP.md
|   `-- SYSTEM_MAP.md
|-- includes/
|   |-- autoloader.php
|   |-- class-plugin.php
|   |-- class-activator.php
|   |-- class-deactivator.php
|   |-- class-installer.php
|   |-- class-admin-menu.php
|   |-- class-settings.php
|   |-- class-roles.php
|   |-- class-capabilities.php
|   |-- class-rest-api.php
|   |-- database/
|   |-- helpers/
|   |-- clients/
|   |-- vehicles/
|   |-- relations/
|   |-- flows/
|   |-- processes/
|   |-- maintenance/
|   |-- predelivery/
|   |-- paperwork/
|   |-- dashboard/
|   |-- quotes/
|   |-- invoices/
|   |-- attachments/
|   |-- communication/
|   |-- integrations/woocommerce/
|   `-- modules/
`-- assets/
```

### Arquitectura activa

- La arquitectura activa real del plugin vive en `includes/*`.
- `includes/modules/*` existe en disco como arbol legacy/scaffolding y no forma parte del bootstrap real.

### Bootstrap real

1. `super-mechanic.php` define constantes del plugin.
2. Carga `includes/autoloader.php`.
3. Registro de activacion: `Super_Mechanic\Activator::activate()`.
4. Registro de desactivacion: `Super_Mechanic\Deactivator::deactivate()`.
5. En `plugins_loaded` se ejecuta `sm_run_plugin()`.
6. `sm_run_plugin()` instancia `Super_Mechanic\Plugin`.
7. `Plugin::init()` ejecuta `maybe_upgrade_schema()` y `register_hooks()`.

### Modulos activos en el bootstrap

- Core / Bootstrap
- Security
- Settings
- Clients
- Vehicles
- Client-Vehicle Relations
- Flows
- Processes
- Maintenance
- Pre-Delivery
- Paperwork
- Dashboards
- Quotes
- Invoices / Payments
- Reports
- Attachments / Timeline
- Communication / Notifications
- Documents / PDF / Secure Downloads
- Client Portal por shortcodes

### Codigo presente pero no integrado como capa principal

- `includes/modules/*`: arquitectura paralela legacy, no usada por `Plugin`.
- `includes/class-rest-api.php`: placeholder, sin rutas productivas.
- `includes/integrations/woocommerce/*`: scaffolding tecnico, no conectado al flujo principal.
- `includes/helpers/class-pdf.php`: helper placeholder legacy.
- `includes/helpers/class-document-service.php`: orquestador reusable para resolver tipo documental, permisos de acceso y payload de descarga.
- `includes/helpers/class-pdf-service.php`: servicio reusable especializado en renderizar y generar PDF real de invoices y quotes.
- `includes/helpers/class-download-service.php`: servicio reusable especializado en exponer entry points seguros y servir descargas de PDFs y adjuntos visibles al cliente.

## 4. Base de datos actual

- Version interna de schema: `1.9.0`
- Opcion de version: `sm_db_version`
- Prefijo de tablas: `{$wpdb->prefix}sm_`

### Tablas actuales

- `sm_clients`
- `sm_vehicles`
- `sm_client_vehicles`
- `sm_flows`
- `sm_flow_steps`
- `sm_processes`
- `sm_process_step_logs`
- `sm_process_parts`
- `sm_process_meta`
- `sm_maintenance`
- `sm_maintenance_parts`
- `sm_maintenance_labor`
- `sm_pre_delivery`
- `sm_paperwork`
- `sm_paperwork_items`
- `sm_quotes`
- `sm_quote_items`
- `sm_invoices`
- `sm_invoice_items`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

### Notas de esquema relevantes

- La tabla real de relacion es `sm_client_vehicles`, no `sm_client_vehicle`.
- La tabla real de logs es `sm_process_step_logs`, no `sm_process_logs`.
- Las tablas reales de flows son `sm_flows` y `sm_flow_steps`, no `sm_process_flows` ni `sm_process_steps`.
- El schema real ya incluye adjuntos, comentarios y notificaciones.
- `sm_process_step_logs` cubre trazabilidad base, no auditoria avanzada dedicada.
- Las subfases 11A, 11B y 11C no agregaron tablas ni modificaron schema; reutilizan `sm_quotes`, `sm_quote_items`, `sm_invoices`, `sm_invoice_items`, `sm_attachments`, `sm_processes` y ownership existente.

## 5. Roles y capabilities

### Roles registrados

- `administrator`
- `sm_admin`
- `sm_mechanic`
- `sm_client`

### Capabilities del plugin

- `sm_manage_plugin`
- `sm_manage_clients`
- `sm_manage_vehicles`
- `sm_manage_processes`
- `sm_manage_flows`
- `sm_manage_settings`
- `sm_view_own_vehicles`
- `sm_view_own_processes`

### Mapa actual de permisos

- `administrator`: todas las capabilities del plugin.
- `sm_admin`: todas las capabilities del plugin.
- `sm_mechanic`:
  - `sm_manage_processes`
  - `sm_manage_vehicles`
  - `sm_view_own_processes`
- `sm_client`:
  - `sm_view_own_vehicles`
  - `sm_view_own_processes`

## 6. Estado por fases

- Fase 1. Esqueleto del plugin: implementada.
- Fase 2. Datos maestros: implementada.
- Fase 3. Motor de procesos: implementada.
- Fase 4. Mantenimiento: implementada.
- Fase 5. Compra / pre-entrega: parcial.
- Fase 6. Tramites: implementada en su base actual de paperwork.
- Fase 7. Integracion WooCommerce: parcial, solo scaffolding tecnico.
- Fase 8. Portal cliente: implementada.
- Fase 9. PDFs / reportes / auditoria: parcial.
- Fase 10. Communication / Comments / Notifications: implementada en su base operativa.
- Fase 17. Control de acceso, visibilidad y ownership: implementada.
- Fase 16. Automatizaciones y eventos operativos: implementada.
- Subfases tecnicas recientes:
  - 11A. PDF real de invoices: implementada.
  - 11B. PDF real de quotes: implementada.
  - 11C. Descarga segura de PDFs y documentos: implementada.
  - 11D. Abstraccion final de document / PDF service: implementada.
- Audit P12-R. Auditoria corta de integridad pre-Fase 12: completada.
- Refactor A. Extraccion de SQL de `Dashboard_Service`: implementada.
- Refactor B-R. Encapsulacion transaccional real de invoices: implementada.
- Fix D-R. Descarga segura de attachments en portal cliente: implementada.
- Fase 12C. Consolidacion del modulo Reports: implementada.
- Fase 12D. Reportes avanzados base: implementada.

## 7. Bitacora de fases

### Fase 1. Esqueleto del plugin

- Estado: implementada.
- Componentes reales:
  - bootstrap del plugin
  - autoloader
  - activacion y desactivacion
  - installer y migrator
  - roles y capabilities
  - menu admin
  - settings base

### Fase 2. Datos maestros

- Estado: implementada.
- Componentes reales:
  - clientes
  - vehiculos
  - relacion cliente-vehiculo
  - listados y formularios admin

### Fase 3. Motor de procesos

- Estado: implementada.
- Componentes reales:
  - procesos
  - flows
  - flow steps
  - logs basicos de proceso
  - tabs contextuales en detalle del proceso

### Fase 4. Mantenimiento

- Estado: implementada.
- Componentes reales:
  - diagnostico
  - repuestos
  - mano de obra
  - asignacion de mecanico
  - base para generar cotizacion

### Fase 5. Compra / Pre-Delivery

- Estado: parcial.
- Componentes reales:
  - checklist de seguro
  - placa
  - revision final
  - readiness de entrega
- Pendiente:
  - un flujo mas amplio de compra/concesionario fuera de la parte operativa de pre-delivery

### Fase 6. Paperwork

- Estado: implementada.
- Componentes reales:
  - tramite principal
  - items de paperwork
  - fechas objetivo
  - estados administrativos

### Fase 7. Billing y comercio

- Estado: parcial.
- Componentes reales:
  - quotes
  - invoices
  - payments
  - scaffolding WooCommerce sin integrar

### Fase 8. Portal cliente

- Estado: implementada.
- Componentes reales:
  - dashboard cliente
  - listado y detalle de quotes
  - aprobacion o rechazo de quote
  - listado y detalle de invoices
  - documentos del proceso
  - timeline del proceso
  - comentarios del proceso
  - notificaciones del cliente

### Fase 9. PDFs / reportes / auditoria

- Estado: parcial.
- Componentes reales:
  - printable HTML de invoice y quote
  - PDF real reusable para invoices
  - PDF real reusable para quotes
  - `Document_Service` como orquestador reusable para invoice PDF, quote PDF y attachments visibles
  - `Download_Service` como entry point seguro y capa de streaming reutilizable
  - adjuntos por proceso
  - timeline consolidada del proceso
- Pendiente:
  - modulo formal de reportes
  - auditoria avanzada dedicada
  - automatizaciones documentales avanzadas
  - firma digital y almacenamiento externo

### Fase 10. Communication

- Estado: implementada en su base operativa.
- Componentes reales:
  - comentarios internos y cliente/staff
  - notificaciones internas por usuario o cliente
  - dispatcher de eventos reutilizable
  - feed de comentarios y notificaciones en proceso y portal cliente
- Cierre tecnico-documental 2026-03-13:
  - wiring activo del modulo en `includes/class-plugin.php`
  - tab `communication` en `Process_Admin_Controller`
  - shortcodes cliente `[sm_client_process_comments]`, `[sm_client_process_comment_form]` y `[sm_client_notifications]`
  - eventos internos registrados por `Event_Dispatcher` para procesos, quotes, invoices, pagos, adjuntos y comentarios
  - schema validado contra `sm_comments` y `sm_notifications` en version `1.9.0`
- Pendiente:
  - email real
  - WhatsApp
  - push notifications
  - automatizaciones externas

## 19. Fase 16. Automatizaciones y eventos operativos

- Estado: implementada.
- Archivos modificados:
  - `includes/communication/class-event-dispatcher.php`
  - `includes/communication/class-notification-service.php`
  - `includes/processes/class-process-service.php`
  - `includes/quotes/class-quote-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/attachments/class-process-timeline-service.php`
- Archivos creados:
  - `docs/tasks/2026-03-fase-16-automatizaciones-y-eventos-operativos.md`
- Integracion real:
  - `Event_Dispatcher` estandariza eventos operativos para procesos, quotes, invoices, pagos, adjuntos y comentarios sin introducir cron, colas ni integraciones externas
  - `Process_Service` despacha `process_created`, `process_step_changed` y `process_finalized` despues de persistencia confirmada; `process_updated` queda reservado para actualizaciones generales sin duplicar cambios de paso o estado
  - `Quote_Service` agrega dispatch real para `quote_created_from_maintenance` y `quote_cancelled`
  - `Invoice_Service` agrega dispatch real para `invoice_created_from_quote` y `invoice_paid`, manteniendo `payment_registered` separado del cambio de cobranza a `paid`
  - `Notification_Service` consume el catalogo ampliado sin absorber logica de negocio del resto de modulos
  - `Process_Timeline_Service` alinea el tipado visible de quotes e invoices con estados reales y con el estado de cobranza agregado
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- Validacion tecnica esperada:
  - dispatch solo despues de operaciones exitosas y despues de fronteras transaccionales existentes cuando aplica
  - sin cambios de schema
  - sin cambios en `includes/modules/*`

## 20. Fase 17. Control de acceso, visibilidad y ownership

- Estado: implementada.
- Archivos creados:
  - `includes/helpers/class-access-control-service.php`
  - `docs/tasks/2026-03-fase-17-control-de-acceso-visibilidad-y-ownership.md`
- Archivos modificados:
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
- Clases nuevas o ampliadas:
  - `Access_Control_Service`
  - `Dashboard_Service`
  - `Process_Service`
  - `Quote_Service`
  - `Invoice_Service`
  - `Attachment_Service`
  - `Comment_Service`
  - `Notification_Service`
- Integracion real:
  - `Access_Control_Service` centraliza ownership y visibilidad para `vehicle`, `process`, `quote`, `invoice` y `attachment`
  - `Dashboard_Service` deja de ser la fuente primaria del ownership cliente y delega acceso a la capa central
  - `Quote_Service` e `Invoice_Service` agregan filtrado de listados por usuario además de los checks de detalle
  - `Attachment_Service`, `Comment_Service` y `Notification_Service` endurecen acceso sobre proceso u objeto real
  - `Document_Service` y `Download_Service` mantienen compatibilidad con la politica central porque delegan acceso documental a los services endurecidos
- Tablas afectadas sin cambios de schema:
  - `sm_client_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- Validacion tecnica:
  - `php -l` OK en `includes/helpers/class-access-control-service.php`
  - sin cambios de schema
  - sin cambios en `includes/modules/*`

### Subfases 11A, 11B, 11C y 11D. Frente documental seguro

- Estado: implementadas.
- Archivos creados:
  - `includes/helpers/class-download-service.php`
  - `includes/helpers/class-document-service.php`
- Archivos modificados:
  - `includes/helpers/class-pdf-service.php`
  - `includes/attachments/class-attachment-service.php`
  - `includes/quotes/class-quote-service.php`
  - `includes/quotes/class-quote-admin-controller.php`
  - `includes/invoices/class-client-invoice-shortcodes.php`
  - `includes/quotes/class-client-quote-shortcodes.php`
  - `includes/attachments/class-client-attachment-shortcodes.php`
  - `includes/class-plugin.php`
- Clases nuevas o ampliadas:
  - `Document_Service`
  - `Download_Service`
  - `PDF_Service` ampliado a resolucion documental generica para PDFs
  - `Quote_Service` ampliado para contexto imprimible/PDF
- Integraciones reales:
  - `Plugin` comparte `PDF_Service`, `Document_Service` y `Download_Service` como capa documental comun
  - `Quote_Admin_Controller` expone descarga admin segura de PDF
  - `Invoice_Admin_Controller` y `Quote_Admin_Controller` mantienen descarga admin por nonce
  - shortcodes cliente de invoices y quotes exponen boton de descarga PDF segura
  - shortcodes cliente de adjuntos exponen descargas seguras de documentos visibles
  - ownership y visibilidad se validan antes de servir cualquier recurso
  - `Document_Service` resuelve tipo, acceso, filename y payload de descarga antes de delegar el stream
- Tablas afectadas sin cambios de schema:
  - `sm_quotes`
  - `sm_quote_items`
  - `sm_invoices`
  - `sm_invoice_items`
  - `sm_attachments`
  - `sm_processes`
  - `sm_client_vehicles`

### Refactor A. Extraccion de SQL de Dashboard_Service

- Estado: implementada.
- Archivos modificados:
  - `includes/dashboard/class-dashboard-service.php`
  - `includes/processes/class-process-service.php`
  - `includes/processes/class-process-repository.php`
- Clases ampliadas:
  - `Dashboard_Service`
  - `Process_Service`
  - `Process_Repository`
- Integraciones reales:
  - `Dashboard_Service` deja de ejecutar SQL directo y delega agregaciones a `Process_Service`
  - `Process_Service` expone wrappers especificos para dashboards sin mover logica de UI
  - `Process_Repository` concentra conteo de procesos abiertos, agrupaciones por estado/tipo, actividad reciente y consulta de procesos asignados a mecanico
  - los dashboards admin, mechanic y client mantienen compatibilidad funcional sin cambios de controller
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`

### Refactor B-R. Encapsulacion transaccional real de invoices

- Estado: implementada.
- Archivos creados:
  - `includes/invoices/class-invoice-transaction-repository.php`
- Archivos modificados:
  - `includes/invoices/class-invoice-service.php`
- Clases nuevas o ampliadas:
  - `Invoice_Transaction_Repository`
  - `Invoice_Service`
- Integraciones reales:
  - `Invoice_Service` conserva la orquestacion del caso de uso de invoices, pagos, balance y PDF
  - `Invoice_Transaction_Repository` encapsula `START TRANSACTION`, `COMMIT` y `ROLLBACK` para el flujo `create_invoice_from_quote()`
  - la API publica de `Invoice_Service` se mantiene compatible con admin, dashboard, PDF y shortcodes cliente
- Tablas afectadas sin cambios de schema:
  - `sm_invoices`
  - `sm_invoice_items`
  - `sm_payments`
  - `sm_quotes`
  - `sm_quote_items`

## 17. Fase 15. Sistema de pagos

- Estado: implementada.
- Archivos modificados:
  - `includes/invoices/class-invoice-service.php`
  - `includes/invoices/class-invoice-admin-controller.php`
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Integracion real:
  - `Invoice_Service` valida invoice existente, monto positivo y que el pago no exceda el saldo disponible al crear o editar pagos
  - el modulo mantiene estados internos de invoice para compatibilidad, pero expone un estado de cobranza visible `pending`, `partial` y `paid`
  - `Invoice_Admin_Controller` muestra estado de invoice y estado de cobro por separado en la UI admin
  - `Report_Repository` y `Report_Service` exponen estado de cobro agregado e ingresos basicos por periodo sin tocar schema
- Tablas afectadas sin cambios de schema:
  - `sm_invoices`
  - `sm_payments`

### Fix D-R. Descarga segura de attachments en portal cliente

- Estado: implementada.
- Archivos modificados:
  - `includes/attachments/class-attachment-service.php`
  - `includes/dashboard/class-client-dashboard-controller.php`
  - `includes/attachments/class-client-attachment-shortcodes.php`
- Clases ampliadas:
  - `Attachment_Service`
  - `Client_Dashboard_Controller`
  - `Client_Attachment_Shortcodes`
- Integraciones reales:
  - dashboard cliente y shortcode de documentos del proceso dejan de depender de `file_url` directo para adjuntos protegidos
  - ambos entry points cliente reutilizan `Download_Service` como ruta unica de descarga segura
  - `Attachment_Service` expone helper reusable para decidir si un adjunto puede renderizarse como descargable para cliente
- Tablas afectadas sin cambios de schema:
  - `sm_attachments`
  - `sm_processes`
  - `sm_client_vehicles`

### Audit P12-R. Auditoria corta de integridad pre-Fase 12

- Estado: completada.
- Archivos auditados:
  - `includes/processes/class-process-service.php`
  - `includes/processes/class-process-repository.php`
  - `includes/dashboard/class-dashboard-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/class-plugin.php`
  - `includes/dashboard/class-client-dashboard-controller.php`
  - `includes/attachments/class-attachment-service.php`
  - `includes/helpers/class-document-service.php`
  - `includes/helpers/class-download-service.php`
- Tablas auditadas sin cambios de schema:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_quotes`
  - `sm_quote_items`
  - `sm_invoices`
  - `sm_invoice_items`
  - `sm_payments`
  - `sm_attachments`
  - `sm_client_vehicles`
- Integraciones validadas:
  - `Process_Service` resuelve `flow_id` y `current_step_id` validos antes de persistir procesos.
  - `Process_Repository` concentra escritura operativa de `sm_process_step_logs` y consultas reutilizadas por dashboards.
  - `Dashboard_Service` mantiene rol agregador sin SQL directo.
  - `Invoice_Service` conserva la logica de negocio y delega la frontera transaccional de `create_invoice_from_quote()` en `Invoice_Transaction_Repository`.
  - `Client_Dashboard_Controller`, `Attachment_Service`, `Document_Service` y `Download_Service` mantienen la descarga protegida de documentos visibles en portal cliente.
- Resultado tecnico:
  - clasificacion general: `ESTABLE CON RIESGOS`
  - sin errores de sintaxis detectados en `class-plugin.php`, `class-process-service.php` y `class-invoice-service.php`
  - bootstrap real confirmado en `includes/class-plugin.php`
- Riesgo principal confirmado:
  - la persistencia de procesos y la escritura de step logs siguen sin compartir una frontera transaccional comun; si falla el log posterior, el proceso puede quedar actualizado aunque el metodo devuelva error.

### Observacion pre-Fase 12

- La auditoria corta previa a Reportes confirma estabilidad general con riesgos.
- `Process_Service` ya no persiste procesos con flow o step inconsistentes cuando aplica flujo, y `sm_process_step_logs` ya recibe escritura operativa real.
- el refactor transaccional de invoices ya quedo encapsulado en `Invoice_Transaction_Repository`, pero el modulo sigue siendo sensible por balance, pagos y compatibilidad documental
- el riesgo tecnico dominante antes de Fase 12 queda concentrado en la falta de atomicidad comun entre mutacion de `sm_processes` y escritura posterior en `sm_process_step_logs`

## 8. Convenciones del proyecto

- Namespace principal: `Super_Mechanic`
- Prefijo funcional: `sm_`
- Archivos de clase: `class-*.php`
- Ubicacion principal de clases: `/includes`
- Naming: lowercase con guion para archivo, PascalCase separado por `_` en namespace WordPress-style.
- Flujo arquitectonico esperado:
  - Bootstrap
  - Services
  - Repositories
  - Controllers
  - REST Controllers
- Los shortcodes cliente actuales son:
  - `[sm_client_dashboard]`
  - `[sm_client_vehicles]`
  - `[sm_client_processes]`
  - `[sm_client_quotes]`
  - `[sm_client_quote_detail]`
  - `[sm_client_quote_action]`
  - `[sm_client_invoices]`
  - `[sm_client_invoice_detail]`
  - `[sm_client_process_documents]`
  - `[sm_client_process_timeline]`
  - `[sm_client_process_comments]`
  - `[sm_client_process_comment_form]`
  - `[sm_client_notifications]`

## 9. Instrucciones para futuras sesiones de desarrollo

- Leer primero este archivo.
- Leer despues:
  - `docs/FINAL_ARCHITECTURE_MAP.md`
  - `docs/SYSTEM_MAP.md`
  - `docs/CURRENT_STATE.md`
- Tratar estos cuatro documentos como fuente de verdad tecnica.
- Antes de marcar una fase como implementada, validar contra el codigo real.
- No duplicar historial ni registrar cambios hipoteticos.
- Actualizar estos cuatro archivos al cerrar cualquier fase o subfase real.
- Si `includes/modules/*` pasara a formar parte del bootstrap, documentarlo explicitamente.
- Si se agregan tablas nuevas, actualizar:
  - esquema actual
  - mapas modulares
  - estado actual del proyecto
- Si se agregan modulos de frontend o REST, documentar clases reales, dependencias y estado.

## 10. Fase 12A. Reportes base operativos

- Estado: implementada.
- Nuevo modulo activo: `includes/reports/`
- Clases nuevas:
  - `Report_Repository`
  - `Report_Service`
  - `Report_Admin_Controller`
- Integracion real:
  - `Plugin` registra el wiring del modulo de reportes dentro de la arquitectura activa
  - `Admin_Menu` expone la pantalla `Super Mechanic -> Reportes`
  - `Report_Admin_Controller` renderiza filtros admin por fecha, estado y tipo
  - `Report_Service` valida filtros y delega consultas reutilizables a `Report_Repository`
  - `Report_Repository` concentra consultas base para procesos, mantenimientos, clientes y vehiculos recientes
- Tablas reutilizadas sin cambios de schema:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
- Alcance explicitamente fuera de 12A:
  - BI avanzado
  - graficos JS complejos
  - reportes financieros avanzados
  - exportacion PDF avanzada
  - cron
  - cache avanzada

## 11. Fase 12B. Reportes financieros base

- Estado: implementada.
- Modulo activo ampliado: `includes/reports/`
- Archivos modificados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Integracion real:
  - `Report_Repository` agrega consultas reutilizables para quotes, invoices, payments y totales financieros base
  - `Report_Service` valida filtros financieros por fechas, `quote_status` e `invoice_status`
  - `Report_Admin_Controller` agrega una seccion separada de reportes financieros sin romper 12A
  - el modulo de reportes sigue sin mover SQL a `Dashboard_Service` ni tocar la logica de negocio de quotes, invoices o payments
- Tablas reutilizadas sin cambios de schema:
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Reportes financieros base cubiertos:
  - quotes por estado
  - quotes recientes
  - invoices por estado
  - invoices recientes
  - payments recientes
  - total facturado por rango agrupado por moneda
  - total cobrado por rango agrupado por moneda
  - balance pendiente total por rango agrupado por moneda
- Criterio temporal actual:
  - en 12B, el total facturado usa `sm_invoices.created_at` como fecha operativa de reporte
- Alcance explicitamente fuera de 12B:
  - BI avanzado
  - graficos complejos
  - dashboards ejecutivos
  - exportacion PDF avanzada
  - cron
  - cache avanzada
  - proyecciones y forecasting
  - conciliacion contable
  - integracion bancaria
  - analytics de WooCommerce

## 12. Fase 12C. Consolidacion del modulo Reports

- Estado: implementada.
- Modulo activo consolidado: `includes/reports/`
- Archivos modificados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Clases ampliadas:
  - `Report_Repository`
  - `Report_Service`
  - `Report_Admin_Controller`
- Integracion real:
  - `Report_Service` centraliza filtros compartidos y separa filtros operativos de filtros financieros
  - `Report_Service` expone datasets agrupados por bloque para la UI admin del modulo
  - `Report_Admin_Controller` separa la pantalla en bloques operativos y financieros sin tocar bootstrap ni otros modulos
  - `Report_Admin_Controller` registra exportacion CSV admin segura mediante `admin_post_sm_export_report_csv`
  - la exportacion CSV queda acotada a `recent_processes`, `recent_quotes`, `recent_invoices` y `recent_payments`
  - los listados recientes del modulo quedan acotados por `DEFAULT_RECENT_LIMIT` y `MAX_RECENT_LIMIT`
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Validacion tecnica:
  - sin errores de sintaxis PHP en `class-report-repository.php`, `class-report-service.php` y `class-report-admin-controller.php`
  - compatibilidad de 12A y 12B preservada
  - sin cambios en `class-plugin.php` ni `class-admin-menu.php`
- Alcance explicitamente fuera de 12C:
  - BI avanzado
  - dashboards ejecutivos
  - cron
  - cache avanzada
  - exportacion PDF de reportes
- frontend cliente
- cambios de schema

## 13. Fase 12D. Reportes avanzados base

- Estado: implementada.
- Modulo activo ampliado: `includes/reports/`
- Archivos modificados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Clases ampliadas:
  - `Report_Repository`
  - `Report_Service`
  - `Report_Admin_Controller`
- Integracion real:
  - `Report_Repository` agrega consultas agregadas base para comparativas de procesos, quotes, invoices y payments por rango
  - `Report_Repository` expone agrupaciones reutilizables por estado y tipo para el bloque avanzado sin duplicar SQL fuera del modulo
  - `Report_Service` calcula comparacion entre periodo actual y periodo anterior equivalente cuando existe un rango completo con `date_from` y `date_to`
  - cuando el rango es parcial o no existe baseline comparable, `Report_Service` devuelve comparacion no disponible y la UI renderiza `N/A`
  - `Report_Service` consolida un bloque `advanced` separado del bloque operativo y del financiero
  - `Report_Service` expone resumen ejecutivo simple con metricas de procesos, quotes, invoices y payments del periodo
  - `Report_Admin_Controller` agrega una seccion admin separada `Reportes avanzados base`
  - la UI avanzada permanece admin-only, sin charts, sin BI pesado y sin tocar frontend cliente
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Validacion tecnica:
  - sin errores de sintaxis PHP en `class-report-repository.php`, `class-report-service.php` y `class-report-admin-controller.php`
  - sin cambios en `class-plugin.php`
  - sin cambios de schema
- Alcance explicitamente fuera de 12D:
  - charts JS
  - dashboards ejecutivos avanzados
  - BI pesado
  - analytics predictivo
  - cron para KPIs
  - cache avanzada
  - exportacion PDF de reportes
- frontend cliente

## 14. Fase 12E. Endurecimiento / Performance / Task Files de Reports

- Estado: implementada.
- Modulo activo endurecido: `includes/reports/`
- Archivos modificados:
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Archivos creados:
  - `docs/tasks/2026-03-fase-12b-reportes-financieros-base.md`
  - `docs/tasks/2026-03-fase-12d-reportes-avanzados-base.md`
  - `docs/tasks/2026-03-fase-12e-endurecimiento-performance-task-files-reports.md`
- Clases ampliadas:
  - `Report_Service`
  - `Report_Admin_Controller`
- Integracion real:
  - `Report_Service` deja de duplicar los limites operativos del modulo y reutiliza los limites del repository como fuente unica de configuracion para listados recientes
  - `Report_Admin_Controller` endurece la lectura de filtros admin con `wp_unslash()` antes de validar
  - la UI avanzada deja de fabricar una fila monetaria sintetica cuando no hay datos comparables y muestra estado vacio controlado
  - la exportacion CSV mantiene las mismas vistas soportadas y conserva validacion estricta de `export_view`
  - se completa la trazabilidad documental de Fase 12 con task files faltantes de `12B`, `12D` y `12E`
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Deuda tecnica documentada sin implementar:
  - evaluar indices futuros sobre columnas de fechas y estados si el volumen real de reportes crece
  - evitar crecimiento desordenado de renderizado en `Report_Admin_Controller`
  - evaluar cache selectivo futuro solo para agregados estables, sin tocar datos operativos vivos

## 15. Fase 13. Integridad transaccional y endurecimiento del nucleo

- Estado: implementada.
- Modulo activo endurecido: `includes/processes/`
- Archivos creados:
  - `includes/processes/class-process-transaction-repository.php`
  - `docs/tasks/2026-03-fase-13-integridad-transaccional-endurecimiento-nucleo.md`
- Archivos modificados:
  - `includes/processes/class-process-service.php`
- Clases nuevas o ampliadas:
  - `Process_Transaction_Repository`
  - `Process_Service`
- Integracion real:
  - `Process_Service` mantiene la orquestacion de negocio y delega la frontera transaccional del modulo en `Process_Transaction_Repository`
  - la creacion de procesos coordina de forma atomica `sm_processes` y el log `step_initialized`
  - la actualizacion de procesos coordina de forma atomica la mutacion principal y los logs `step_transition` y `status_changed` cuando corresponden
  - `update_current_step()` coordina de forma atomica el cambio de `current_step_id` y su `step_transition`
  - los eventos `process_updated` y `process_status_changed` siguen disparandose solo despues de una persistencia exitosa
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_process_step_logs`
- Deuda tecnica documentada sin implementar:
  - validar en una fase futura si conviene endurecer manejo de errores de inicio de transaccion segun el motor real de base de datos
  - evaluar si otras mutaciones del modulo `processes` deben migrarse al mismo patron transaccional si crece el flujo operativo

### Ajuste final de cierre Fase 13
- la implementacion final valida `START TRANSACTION` y `COMMIT` antes de reportar exito
- ante fallo o excepcion se fuerza `ROLLBACK` seguro y la operacion retorna error

## 16. Fase 14. Validacion funcional, escenarios y estabilizacion

- Estado: completa.
- Auditoria aplicada contra `docs/TEST_SCENARIOS.md` usando el codigo real como fuente de verdad.
- Ajuste funcional minimo implementado:
  - `Dashboard_Service` deja de exponer al portal cliente actividad reciente basada en logs internos no visibles y delega lectura filtrada por `customer_visible`.
- Resultado documental:
  - `docs/TEST_SCENARIOS.md` clasifica escenarios como `OK`, `PARCIAL`, `DESALINEADO` o `FRAGIL`.
- Resultado final:
  - los escenarios criticos 4, 7, 8 y 14 quedan cerrados y alineados con el codigo real.

### Ajuste 14B
- `Quote_Transaction_Repository` endurece la generacion de quote desde maintenance con persistencia atomica de quote + items.
- `Quote_Admin_Controller` vuelve consistente el flujo de admin que genera cotizacion desde maintenance usando `quote_id` real.
- `Process_Timeline_Service` deja de etiquetar toda factura como `invoice_issued` y usa tipos de evento alineados al estado real.
- Los escenarios criticos 4, 7, 8 y 14 quedan alineados al comportamiento real del codigo.
- Validacion final:
  - `php -l` sin errores en `class-quote-transaction-repository.php`, `class-quote-service.php`, `class-quote-admin-controller.php`, `class-process-timeline-service.php` y `class-plugin.php`

### Deudas tecnicas activas posteriores a 14B
- `Client_Vehicle_Service::transfer_vehicle()` sigue sin frontera transaccional dedicada para cierre + reasignacion de ownership.
- `Flow_Service::delete_flow()` y `Flow_Step_Service::reorder_steps()` siguen sin atomicidad dedicada.
- `Report_Service` y `Report_Admin_Controller` deben vigilarse si el modulo `Reports` crece en nuevas subfases.
- `includes/class-rest-api.php`, `includes/class-assets.php`, `includes/class-hooks.php` y `includes/class-post-types.php` siguen presentes como placeholders/no activos.
## 18. Hardening final Fase 15. Integridad financiera de pagos

- Estado: completado.
- Archivos modificados:
  - `includes/invoices/class-invoice-service.php`
  - `includes/invoices/class-invoice-admin-controller.php`
  - `includes/reports/class-report-repository.php`
- Clases ampliadas:
  - `Invoice_Service`
  - `Invoice_Admin_Controller`
  - `Report_Repository`
- Integracion real:
  - `sm_payments` queda como unica fuente de verdad financiera para validacion, saldo y resumen de cobranza
  - `Invoice_Service` expone `get_invoice_payment_summary()` y soporta exclusion de `payment_id` al editar pagos para evitar doble conteo
  - `Invoice_Service` sigue recalculando `amount_paid` y `balance_due` en `sm_invoices` por compatibilidad, pero esos campos quedan como cache legado y no como fuente primaria de decision
  - `Invoice_Admin_Controller` mantiene la UI admin y aclara `Estado de factura` y `Estado de pago`
  - `Report_Repository` calcula pendiente agregado y estado de cobranza por invoice a partir de pagos agregados, sin apoyarse en cache persistido
- Tablas afectadas sin cambios de schema:
  - `sm_invoices`
  - `sm_payments`

## 21. Fase 18. Portal mecanico real

- Estado: implementada.
- Archivos modificados:
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
  - `includes/class-plugin.php`
  - `includes/attachments/class-process-timeline-service.php`
  - `docs/CURRENT_STATE.md`
  - `docs/SYSTEM_MAP.md`
  - `docs/MODULE_REGISTRY.md`
  - `docs/FINAL_ARCHITECTURE_MAP.md`
  - `docs/TEST_SCENARIOS.md`
- Archivos creados:
  - `docs/tasks/2026-03-fase-18-portal-mecanico-real.md`
- Clases nuevas o ampliadas:
  - `Mechanic_Dashboard_Controller`
  - `Plugin`
  - `Process_Timeline_Service`
- Integracion real:
  - el submenu `Panel mecanico` evoluciona a portal operativo real dentro del admin actual
  - el portal lista procesos accesibles para mecanico y permite abrir un detalle operativo propio
  - el detalle expone resumen, timeline, comentarios, adjuntos y ficha de mantenimiento cuando aplica
  - los cambios de estado y paso reutilizan `Process_Service`, preservando transaccion y wiring de eventos ya existentes
  - las notas tecnicas internas reutilizan `Comment_Service`, sin SQL directo ni flujo paralelo
  - las descargas de adjuntos reutilizan la ruta segura comun
  - se corrigen errores menores de timeline para sostener la vista operativa del portal
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_maintenance`
  - `sm_maintenance_parts`
  - `sm_maintenance_labor`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`

## 22. Fase 19. Workflow operativo configurable avanzado

- Estado: implementada.
- Archivos modificados:
  - `includes/flows/class-flow-step-service.php`
  - `includes/processes/class-process-service.php`
- Archivos creados:
  - `docs/tasks/2026-03-fase-19-workflow-operativo-configurable-avanzado.md`
- Clases ampliadas:
  - `Flow_Step_Service`
  - `Process_Service`
- Integracion real:
  - `Flow_Step_Service` valida transiciones lineales minimas entre pasos activos usando `step_order` como fuente de verdad del flujo actual
  - el modelo sigue sin grafo explicito de transiciones; el endurecimiento permite solo avanzar al siguiente paso activo o retroceder al inmediatamente anterior
  - `Process_Service::update_current_step()` deja de aceptar saltos arbitrarios dentro del mismo flujo y bloquea cambios a pasos no alcanzables
  - `Process_Service::update_process()` aplica la misma validacion cuando cambia `current_step_id` dentro de una actualizacion general del proceso
  - al entrar en un `step` final, `Process_Service` sincroniza el estado minimo del proceso a `completed` y registra el log de cambio de estado correspondiente
  - un proceso ya finalizado no puede moverse a un paso no final por la ruta operativa simple de cambio de paso
- Tablas afectadas sin cambios de schema:
  - `sm_flows`
  - `sm_flow_steps`
  - `sm_processes`
  - `sm_process_step_logs`
- Validacion tecnica:
  - `php -l` OK en `includes/flows/class-flow-step-service.php`
  - `php -l` OK en `includes/processes/class-process-service.php`
  - sin cambios de schema
  - sin cambios en `includes/modules/*`
- Deuda tecnica abierta:
  - el workflow sigue siendo lineal por `step_order`; no existe aun un grafo formal de transiciones condicionales
  - `requires_approval`, `requires_note` y `metadata` siguen como base estructural disponible, pero no como motor completo de restricciones por step

## 23. Fase 20. Automatizacion documental y estados derivados

- Estado: implementada.
- Archivos creados:
  - `includes/processes/class-process-derived-state-service.php`
  - `docs/tasks/2026-03-fase-20-automatizacion-documental-y-estados-derivados.md`
- Archivos modificados:
  - `includes/helpers/class-document-service.php`
  - `includes/communication/class-event-dispatcher.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/dashboard/class-dashboard-service.php`
  - `includes/dashboard/class-client-dashboard-controller.php`
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
  - `includes/class-plugin.php`
- Integracion real:
  - la automatizacion documental reutiliza `Document_Service` y `PDF_Service` como fuente unica y no persiste attachments nuevos
  - `quote_approved` e `invoice_issued` preparan disponibilidad documental logica sin crear archivos fisicos redundantes
  - `invoice_paid` mantiene automatizacion de notificacion y coherencia financiera, pero no genera comprobante automatico por falta de ruta documental reusable actual
  - `Process_Derived_State_Service` expone derivados seguros de proceso a partir de datos ya existentes
  - `Invoice_Service` expone enriquecimiento reusable del estado visible de cobranza para dashboard y portal
  - el portal cliente y el portal mecanico muestran derivados seguros sin trasladar logica a controllers
- Tablas afectadas sin cambios de schema:
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_pre_delivery`
- Validacion tecnica:
  - sin cambios de schema
  - sin cambios en `includes/modules/*`
  - `php -l` OK en los archivos modificados
- Deuda tecnica abierta:
  - falta una ruta documental reusable y deduplicada para comprobantes de pago automaticos por `payment_id`
