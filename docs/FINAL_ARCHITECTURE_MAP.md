# FINAL ARCHITECTURE MAP

## Vision final

Super Mechanic debe consolidarse como un plugin WordPress modular donde `Plugin` actua como composition root, los repositories concentran persistencia, los services la logica de negocio, los controllers la UI administrativa y los shortcodes o REST controllers la exposicion externa. El proceso sigue siendo el eje orquestador del dominio operativo.

## Estado de consolidacion actual

- La arquitectura activa real vive en `includes/*`.
- `includes/modules/*` sigue existiendo como capa legacy/scaffolding y no debe considerarse parte del sistema operativo actual.
- `class-rest-api.php` sigue siendo placeholder.
- WooCommerce sigue parcial y fuera del flujo principal.
- El frente documental ya tiene base reusable operativa para PDF de invoices y quotes, y para descargas seguras de recursos protegidos.
- El frente documental ya cuenta con un orquestador reusable (`Document_Service`) que separa resolucion documental, generacion PDF y streaming seguro.

## Modulos objetivo 

### Core

- Proposito: bootstrap, activacion, instalacion, migraciones, autoload y wiring principal.
- Tablas: no aplica directamente.
- Clases esperadas: `Plugin`, `Assets`, `Activator`, `Deactivator`, `Installer`, `Autoloader`, `Admin_Menu`.
- Dependencias: todos los modulos dependen de Core.
- Estado: implementado.
- Avance real confirmado:
  - `Assets` ya no es placeholder y pasa a registrar la capa visual propia del plugin para admin y frontend
  - en Fase 25, `scripts/` agrega una base local reusable para lint, chequeo estructural y checklist tecnico sin tocar bootstrap ni negocio
  - en Fase 26, `Shortcode_Admin_Controller` agrega una pantalla admin informativa y `Admin_Menu` incorpora el submenu `Shortcodes` sin alterar el runtime de shortcodes existentes

### Security

- Proposito: roles, capabilities, nonces, validaciones de acceso y reglas de seguridad transversales.
- Tablas: usa roles/capabilities de WordPress.
- Clases esperadas: `Roles`, `Capabilities`, helpers de sanitizacion/validacion y control transversal de acceso.
- Dependencias: Core, Settings, todos los controllers admin y frontend cliente.
- Estado: implementado.
- Avance real confirmado:
  - `Access_Control_Service` centraliza ownership y visibilidad para recursos cliente sin mover SQL a controllers ni tocar schema
  - la politica transversal diferencia acceso total admin/staff y acceso restringido por ownership o proceso asignado

### Settings

- Proposito: configuracion global del plugin.
- Tablas: `wp_options` mediante `sm_settings`, `super_mechanic_settings` (legacy UI) y `sm_db_version`.
- Clases esperadas: `Settings`.
- Dependencias: Core, Billing, Dashboards, Processes.
- Estado: implementado.
- Avance real confirmado:
  - `Settings_Service` agrega una capa transversal reusable sobre la option `sm_settings` con fallback de lectura desde `super_mechanic_settings`
  - la configuracion avanzada queda agrupada en `business`, `process`, `financial` y `notifications`
  - los defaults preservan el comportamiento actual y mantienen fallback minimo hacia settings legacy del negocio
  - en Fase 24B, `Settings` reutiliza la shell visual admin sobre la Settings API existente sin crear un flujo paralelo de configuracion

### Clients

- Proposito: CRUD de clientes y relacion con usuarios WordPress.
- Tablas: `sm_clients`.
- Clases esperadas: `Client_Repository`, `Client_Service`, `Client_Admin_Controller`, `Client_List_Table`.
- Dependencias: Core, Security.
- Estado: implementado.
- Avance real confirmado:
  - en Fase 24B, `Client_Admin_Controller` y `Client_List_Table` modernizan listado y formulario con la capa admin comun sin alterar handlers ni persistencia

### Vehicles

- Proposito: CRUD de vehiculos.
- Tablas: `sm_vehicles`.
- Clases esperadas: `Vehicle_Repository`, `Vehicle_Service`, `Vehicle_Admin_Controller`, `Vehicle_List_Table`.
- Dependencias: Core, Clients, Security.
- Estado: implementado.
- Avance real confirmado:
  - en Fase 24B, `Vehicle_Admin_Controller` y `Vehicle_List_Table` modernizan listado y formulario con la misma capa admin comun sin alterar relaciones ni acciones

### Client-Vehicle Relations

- Proposito: ownership, historial basico y resolucion de acceso cliente.
- Tablas: `sm_client_vehicles`.
- Clases esperadas: `Client_Vehicle_Repository`, `Client_Vehicle_Service`.
- Dependencias: Clients, Vehicles.
- Estado: implementado.
- Avance real confirmado:
  - en Fase 26B, `Client_Vehicle_Transaction_Repository` encapsula la transferencia de ownership para evitar estados parciales entre cierre de relacion anterior, nueva relacion y sync del `client_id` legacy del vehiculo

### Processes

- Proposito: eje operacional del sistema.
- Tablas: `sm_processes`, `sm_process_parts`, `sm_process_meta`.
- Clases esperadas: `Process_Repository`, `Process_Service`, `Process_Admin_Controller`, `Process_List_Table`.
- Dependencias: Clients, Vehicles, Flows, Security.
- Estado: implementado.
- Avance real confirmado:
  - `Process_Service` resuelve flujo aplicable y paso inicial valido antes de persistir un proceso
  - `update_current_step()` valida pertenencia del paso al flujo real del proceso antes de actualizarlo
  - en Fase 24B, `Process_Admin_Controller` y `Process_List_Table` modernizan listado, filtros, formulario general, tabs y panel `communication` sin alterar la orquestacion del modulo

### Process Logs

- Proposito: trazabilidad de transiciones y acciones.
- Tablas: `sm_process_step_logs`.
- Clases esperadas: repositorio y servicio dedicados o absorbidos por `Process_Service`.
- Dependencias: Processes, Flows.
- Estado: implementado en su base operativa.
- Avance real confirmado:
  - la escritura operativa ya vive en `Process_Service` para `step_initialized`, `step_transition` y `status_changed`
  - la persistencia y lectura reutilizable viven en `Process_Repository`
- Riesgo actual:
  - aunque la frontera transaccional base ya existe para create/update/step update, cualquier ampliacion futura debe conservar atomicidad y no reintroducir estados parciales

### Flows

- Proposito: definir flujos configurables por tipo de proceso.
- Tablas: `sm_flows`, `sm_flow_steps`.
- Clases esperadas: `Flow_Repository`, `Flow_Service`, `Flow_Step_Repository`, `Flow_Step_Service`, `Flow_Admin_Controller`.
- Dependencias: Core, Processes.
- Estado: implementado.
- Avance real confirmado:
  - en Fase 24B, `Flow_Admin_Controller` y `Flow_List_Table` modernizan listado, formularios y vista de pasos sin alterar reorder ni persistencia del modulo
  - en Fase 26B, `Flow_Transaction_Repository` encapsula atomicidad minima para borrado de flujo y reorder de pasos

### Maintenance

- Proposito: diagnostico, aprobacion, repuestos, mano de obra y asignacion de mecanico.
- Tablas: `sm_maintenance`, `sm_maintenance_parts`, `sm_maintenance_labor`.
- Clases esperadas: `Maintenance_Repository`, `Maintenance_Service`, `Maintenance_Part_Repository`, `Maintenance_Labor_Repository`, `Maintenance_Admin_Controller`.
- Dependencias: Processes, Security.
- Estado: implementado.

### Pre-Delivery

- Proposito: checklist operativo para entrega.
- Tablas: `sm_pre_delivery`.
- Clases esperadas: `Pre_Delivery_Repository`, `Pre_Delivery_Service`, `Pre_Delivery_Admin_Controller`.
- Dependencias: Processes.
- Estado: implementado como modulo.
- Estado de fase relacionada: parcial dentro de la Fase 5 del roadmap.

### Paperwork

- Proposito: tramites, checklist administrativo y fechas objetivo.
- Tablas: `sm_paperwork`, `sm_paperwork_items`.
- Clases esperadas: `Paperwork_Repository`, `Paperwork_Item_Repository`, `Paperwork_Service`, `Paperwork_Admin_Controller`.
- Dependencias: Processes.
- Estado: implementado.

### Dashboards

- Proposito: paneles operativos por rol.
- Tablas: reutiliza tablas de negocio existentes.
- Clases esperadas: `Dashboard_Service`, `Admin_Dashboard_Controller`, `Mechanic_Dashboard_Controller`, `Client_Dashboard_Controller`, `Client_Dashboard_Shortcodes`.
- Dependencias: Clients, Vehicles, Processes, Quotes, Invoices, Attachments, Communication.
- Estado: implementado.
- Avance real confirmado:
  - `Dashboard_Service` opera como capa agregadora sin SQL directo
  - la persistencia usada por dashboards se concentra en `Process_Repository` via `Process_Service`
  - admin dashboard, mechanic dashboard y client dashboard conservaron compatibilidad funcional
  - en Fase 26B, `Client_Process_View_Service` extrae parte de la agregacion pesada del portal cliente sin convertir el controller en backend de negocio
  - en Fase 18, `Mechanic_Dashboard_Controller` agrega un portal operativo real para mecanicos sin duplicar la arquitectura del admin
  - el portal mecanico reutiliza timeline, comments, attachments y mantenimiento en modo lectura, y concentra acciones minimas en `Process_Service` y `Comment_Service`
  - en Fase 24, el admin dashboard y el portal mecanico pasan a reutilizar una capa visual comun sin alterar la logica operativa

### Quotes

- Proposito: cotizaciones, aprobacion del cliente y salida documental PDF.
- Tablas: `sm_quotes`, `sm_quote_items`.
- Clases esperadas: `Quote_Repository`, `Quote_Item_Repository`, `Quote_Service`, `Quote_Admin_Controller`, `Client_Quote_Shortcodes`.
- Dependencias: Processes, Maintenance, Clients, Documents.
- Estado: implementado.

### Invoices

- Proposito: facturacion base y salida documental PDF.
- Tablas: `sm_invoices`, `sm_invoice_items`.
- Clases esperadas: `Invoice_Repository`, `Invoice_Item_Repository`, `Invoice_Transaction_Repository`, `Invoice_Service`, `Invoice_Admin_Controller`, `Client_Invoice_Shortcodes`.
- Dependencias: Quotes, Processes, Clients, Settings, Documents.
- Estado: implementado.
- Observacion tecnica actual:
  - `Invoice_Service` mantiene el caso de uso de invoices, pagos, balance y PDF
  - el detalle transaccional de `create_invoice_from_quote()` ya se encapsula en `Invoice_Transaction_Repository`
  - la auditoria pre-Fase 12 confirma compatibilidad operativa de `create_invoice_from_quote()`, pagos, `recalculate_balance()` y acceso documental del cliente

### Payments

- Proposito: registro de pagos y balance.
- Tablas: `sm_payments`.
- Clases esperadas: `Payment_Repository`, soporte en `Invoice_Service`.
- Dependencias: Invoices.
- Estado: implementado.
- Avance real confirmado:
  - `Invoice_Service` valida que altas y ediciones de pagos no excedan el saldo pendiente de la invoice
  - el modulo expone un estado visible de cobranza (`pending`, `partial`, `paid`) sin reemplazar los estados internos de invoice
  - la UI admin de invoices muestra pagos, saldo y estado de cobro dentro del contexto del proceso
  - en Fase 20B, cada pago puede resolverse como documento logico unico `payment_receipt` por `payment_id` sin persistencia fisica

### Attachments

- Proposito: adjuntos por proceso y soporte documental base.
- Tablas: `sm_attachments`.
- Clases esperadas: `Attachment_Repository`, `Attachment_Service`, `Attachment_Admin_Controller`, `Process_Timeline_Service`, `Client_Attachment_Shortcodes`.
- Dependencias: Processes, Quotes, Invoices, Client Portal, Documents.
- Estado: implementado.

### Communication

- Proposito: comentarios internos y cliente/staff, notificaciones internas y dispatcher de eventos.
- Tablas: `sm_comments`, `sm_notifications`.
- Clases esperadas: `Comment_Repository`, `Comment_Service`, `Notification_Repository`, `Notification_Service`, `Event_Dispatcher`, `Client_Comment_Shortcodes`.
- Dependencias: Processes, Quotes, Invoices, Attachments, Client Portal.
- Estado: implementado en su base operativa.
- Integracion activa confirmada:
  - `Plugin` registra `Event_Dispatcher::register_hooks()`
  - `Process_Admin_Controller` expone panel admin de comunicacion
  - `Client_Comment_Shortcodes` expone comentarios, formulario cliente y notificaciones
  - `Notification_Service` consume eventos de procesos, quotes, invoices, pagos, adjuntos y comentarios
  - en Fase 16, el catalogo operativo incluye eventos especificos de proceso, quote e invoice para reducir duplicidad y endurecer automatizaciones internas

### Documents

- Proposito: generacion, resolucion y exposicion segura de documentos protegidos.
- Tablas: hoy se apoya en `sm_quotes`, `sm_quote_items`, `sm_invoices`, `sm_invoice_items`, `sm_attachments`, `sm_processes` y ownership existente.
- Clases esperadas: `Document_Service`, `PDF_Service`, `Download_Service`, renderers HTML/PDF, integraciones con services de quotes, invoices y attachments.
- Dependencias: Quotes, Invoices, Attachments, Client-Vehicle Relations, Security, Client Portal.
- Estado: implementado en su base operativa, con roadmap aun parcial.
- Avance real confirmado:
  - `Document_Service` reusable para resolver tipo documental, acceso y payload de descarga
  - `PDF_Service` reusable y especializado para invoices y quotes
  - descarga admin segura de invoice PDF y quote PDF
  - `Download_Service` reusable para entry points y descargas seguras en portal cliente
  - proteccion de ownership y visibilidad antes de servir adjuntos cliente
  - dashboard cliente y shortcode de documentos del proceso ya no exponen `file_url` directo para adjuntos protegidos
  - en Fase 17, el acceso documental sigue alineado porque `Document_Service` delega sobre `Invoice_Service`, `Quote_Service` y `Attachment_Service`, ya endurecidos por la capa central
  - en Fase 20, `quote_approved` e `invoice_issued` preparan disponibilidad documental automatica desde la capa comun sin persistir attachments nuevos
  - en Fase 20B, `Document_Service` agrega `payment_receipt` como documento logico reusable por `payment_id`
  - en Fase 20B, `PDF_Service` genera el comprobante de pago bajo demanda sin crear attachments automaticos ni duplicar artefactos
  - en Fase 26B, el admin de adjuntos deja de enlazar `file_url` directo y reutiliza `Download_Service` tambien en la capa admin
- Pendiente:
  - reportes formales
  - auditoria avanzada
  - firma digital
  - almacenamiento externo

### Client Portal

- Proposito: area frontend de cliente.
- Tablas: reutiliza clientes, relaciones, procesos, quotes, invoices, attachments, comments y notifications.
- Clases esperadas: shortcodes y controladores frontend cliente.
- Dependencias: Dashboards, Quotes, Invoices, Client-Vehicle Relations, Security, Attachments, Communication, Documents.
- Estado: implementado.
- Avance real confirmado:
  - descarga segura de invoice PDF desde shortcode cliente
  - descarga segura de quote PDF desde shortcode cliente
  - descarga segura de attachments visibles al cliente
  - listados cliente de quotes e invoices refuerzan filtrado por usuario mediante la politica central de ownership
  - en Fase 24, dashboard cliente y shortcodes cliente de quotes e invoices reutilizan una capa visual frontend comun sin alterar ownership ni descargas seguras
  - en Fase 26, el admin suma un catalogo de shortcodes activos para documentar mejor estos entry points sin cambiar su logica

### Access Control

- Proposito: centralizar ownership, visibilidad y acceso transversal por rol.
- Tablas: reutiliza `sm_client_vehicles`, `sm_processes`, `sm_quotes`, `sm_invoices`, `sm_attachments`, `sm_comments` y `sm_notifications`.
- Clases esperadas: `Access_Control_Service`.
- Dependencias: Clients, Client-Vehicle Relations, Processes, Quotes, Invoices, Attachments, Communication, Client Portal.
- Estado: implementado.
- Avance real confirmado:
  - resuelve `client_id` por usuario WordPress
  - valida acceso a `vehicle`, `process`, `quote`, `invoice` y `attachment`
  - permite reutilizacion desde dashboard, communication y portal cliente sin duplicar ownership

### REST API futura

- Proposito: integraciones externas y soporte headless.
- Tablas: reutiliza tablas de negocio existentes.
- Clases esperadas: REST controller por modulo.
- Dependencias: Services estables por modulo.
- Estado: parcial.
- Situacion real: placeholder / legacy, no operativa en bootstrap.

### Inventory futura

- Proposito: stock, costos y movimientos de repuestos.
- Tablas: futuras tablas de inventario.
- Clases esperadas: repository, service, controller y reportes propios.
- Dependencias: Maintenance, Quotes, Invoices.
- Estado: pendiente.

### WooCommerce Integration

- Proposito: puente comercial futuro con WooCommerce.
- Tablas: potencial uso de tablas WooCommerce.
- Clases esperadas: validacion, sync de productos, sync de ordenes, bridge de billing.
- Dependencias: Quotes, Invoices, Billing futuro.
- Estado: parcial.
- Situacion real: no integrado al flujo principal.

## Dependencias maestras del sistema

- Core inicializa todo.
- Security protege todos los entry points.
- Clients y Vehicles alimentan Relations y Processes.
- Processes depende de Flows cuando existe flujo asignado.
- Maintenance, Pre-Delivery y Paperwork dependen de Processes.
- Quotes depende de Processes y Maintenance.
- Invoices depende de Quotes y Processes.
- Payments depende de Invoices.
- Attachments y Communication dependen del proceso como eje operativo.
- Documents depende de Quotes, Invoices, Attachments y validaciones de ownership.
- Dashboards y Client Portal consumen datos de modulos operativos.
- Inventory sigue como bounded context futuro.
- El modulo `Reports` ya esta implementado y consolidado dentro de la arquitectura activa.

## Estado objetivo de consolidacion

- Mantener una sola arquitectura activa.
- Evitar que `includes/modules/*` y `includes/*` evolucionen en paralelo sin una migracion explicita.
- Solo considerar un modulo como implementado cuando este conectado al bootstrap real.
- No considerar REST ni WooCommerce como integrados mientras no formen parte del flujo principal cableado por `Plugin`.
- Mantener centralizadas las descargas protegidas para no reintroducir enlaces publicos inseguros en portal cliente.
- Fase 14 endurece la actividad reciente del portal cliente para reutilizar solo logs de proceso marcados como visibles al cliente.
- Fase 14B agrega `Quote_Transaction_Repository` como frontera transaccional minima del flujo maintenance -> quote.
- La timeline consolidada mantiene composicion multi-modulo, pero ahora tipa invoices segun su estado real.
- Fase 16 amplía el catalogo real de eventos internos sin introducir cron, colas ni integraciones externas, y mantiene a `Notification_Service` como consumidor principal del bus.

### Reports
- Proposito: reportes operativos, financieros y avanzados base para administracion interna.
- Tablas: reutiliza `sm_processes`, `sm_maintenance`, `sm_clients`, `sm_vehicles`, `sm_quotes`, `sm_invoices` y `sm_payments`.
- Clases reales: `Report_Repository`, `Report_Service`, `Report_Admin_Controller`.
- Dependencias: Processes, Maintenance, Clients, Vehicles, Quotes, Invoices, Payments.
- Estado: implementado y consolidado para Fases 12A, 12B, 12C y 12D.
- Avance real confirmado:
  - `Report_Repository` concentra consultas reutilizables de reportes base
  - `Report_Service` centraliza filtros compartidos, separa filtros operativos y financieros y expone datasets reutilizables para UI admin y exportacion
  - `Report_Admin_Controller` expone filtros por fechas, estado, tipo y limite en `Super Mechanic -> Reportes`
  - el modulo ahora cubre reportes financieros base de quotes, invoices, payments y totales por rango agrupados por moneda
  - en 12B, el total facturado se calcula sobre `sm_invoices.created_at` como criterio operativo
  - en 12C, la exportacion CSV admin queda acotada a `recent_processes`, `recent_quotes`, `recent_invoices` y `recent_payments`
  - en 12D, el modulo agrega comparativas por rango para procesos, quotes, invoices y payments
  - en 12D, `Report_Service` calcula el periodo anterior equivalente cuando existe rango completo y expone un bloque `advanced`
  - en 12D, cuando no existe baseline comparable, la variacion porcentual se presenta como `N/A`
  - en 12D, `Report_Admin_Controller` agrega una seccion `Reportes avanzados base` separada de operativo y financiero
  - la capa de reportes no agrega SQL a `Dashboard_Service`, no modifica schema y no toca frontend cliente
  - en Fase 24, `Report_Admin_Controller` moderniza su presentacion visual sin tocar datasets, filtros ni exportacion


### Processes 13
- Estado: integridad transaccional base implementada para create/update/step update.
- Avance real confirmado:
  - `Process_Transaction_Repository` encapsula la frontera `START TRANSACTION` / `COMMIT` / `ROLLBACK` del modulo
  - `Process_Service` conserva validaciones, resolucion de flow/step y dispatch de eventos, sin mover logica de negocio al repository
  - la escritura de `sm_processes` y `sm_process_step_logs` queda coordinada de forma atomica en creacion de proceso, actualizacion de proceso y cambio directo de paso
- Riesgo residual:
  - sigue siendo recomendable ampliar en fases futuras el mismo patron a otras escrituras relacionadas si el flujo del modulo crece

### Processes 19 / Flows 19
- Estado: workflow operativo minimo endurecido sin cambios de schema.
- Avance real confirmado:
  - `Flow_Step_Service` expone validacion reusable de transiciones lineales por `step_order`
  - `Process_Service` reutiliza esa validacion tanto en `update_current_step()` como en `update_process()` cuando el paso cambia
  - el runtime bloquea saltos arbitrarios entre pasos no adyacentes del mismo flujo
  - entrar en un paso final sincroniza el proceso a `completed` sin crear un motor paralelo ni mover logica a controllers
- Riesgo residual:
  - el sistema todavia no modela transiciones condicionales, restricciones complejas por `metadata` ni enforcement funcional de `requires_approval` / `requires_note`

### Cierre final Fase 13
- la frontera transaccional valida inicio y confirmacion real de transaccion antes de permitir dispatch de eventos o reportar exito

### Reports 12E
- Estado: endurecimiento y consolidacion completados sin ampliar alcance funcional.
- Avance real confirmado:
  - `Report_Service` reutiliza los limites de `Report_Repository` como fuente unica para listados recientes y exportacion acotada
  - `Report_Admin_Controller` endurece la lectura de filtros admin antes de validarlos
  - las comparativas monetarias del bloque avanzado no fabrican una moneda por defecto cuando no hay datos comparables
  - la capa de reportes conserva consultas agregadas y listados acotados, sin mover SQL a `Dashboard_Service`
- Deuda tecnica documentada:
  - posibles indices futuros sobre fechas y estados del modulo
  - posible particion futura del render admin si `Report_Admin_Controller` sigue creciendo
  - cache selectivo futuro solo para agregados estables y nunca para datos operativos vivos

### Payments 15
- Estado: sistema de pagos consolidado sobre `sm_payments` y `sm_invoices`, sin cambios de schema.
- Avance real confirmado:
  - el flujo admin permite registrar y editar pagos manuales contra invoices existentes
  - `sm_payments` queda como unica fuente de verdad para validacion, saldo y resumen de cobranza
  - `Invoice_Service` expone `get_invoice_payment_summary()` y soporta exclusion de `payment_id` al editar pagos
  - `Invoice_Service` sigue recalculando `amount_paid`, `balance_due`, `paid_at` y el estado interno de invoice despues de cada mutacion solo por compatibilidad operativa
- `Report_Repository` agrega estado de cobro agregado de invoices e ingresos basicos por periodo sobre `payment_date`
- `Report_Admin_Controller` expone ese bloque financiero ampliado sin tocar dashboard ni bootstrap

### Settings 21 / Processes 21 / Financial 21
- Estado: configuracion avanzada base implementada sin cambios de schema.
- Avance real confirmado:
  - `Settings_Service` centraliza lectura y escritura de configuracion avanzada usando `sm_settings`, mientras la UI clasica mantiene `super_mechanic_settings` como compatibilidad legacy
  - `Process_Service` reutiliza configuracion para `allow_step_back` y `auto_complete_on_final_step`
  - `Invoice_Service` reutiliza configuracion para `allow_partial_payments`, moneda y nombre del negocio
  - `Quote_Service` reutiliza configuracion para moneda y nombre del negocio
- Riesgo residual:
  - cualquier futura UI de configuracion debe seguir usando la misma estructura central y no reintroducir lectura directa dispersa de options

### Documents 20 / Processes 20
- Estado: automatizacion documental minima y estados derivados seguros implementados sin cambios de schema.
- Avance real confirmado:
  - `Document_Service` agrega resolucion explicita para automatizacion documental de `quote_approved` e `invoice_issued`
  - la automatizacion no persiste attachments nuevos ni archivos fisicos redundantes; solo garantiza disponibilidad del documento logico desde la capa comun
  - `Event_Dispatcher` reutiliza el `Document_Service` compartido por `Plugin` y no crea un flujo paralelo
  - `Process_Derived_State_Service` centraliza derivados seguros de proceso a partir de `status`, quotes, invoices y `pre_delivery.delivery_ready`
  - `Invoice_Service` expone enriquecimiento reusable del estado visible de cobranza para portal y dashboard
- Riesgo residual:
  - cualquier evolucion futura hacia persistencia documental automatica debe deduplicar por objeto logico y seguir entrando por la capa documental comun

### Documents 20B / Payments 20B
- Estado: comprobante de pago documental implementado sin cambios de schema.
- Avance real confirmado:
  - `Document_Service` resuelve `payment_receipt` por `payment_id` como documento logico unico
  - `Invoice_Service` expone el contexto consolidado del comprobante usando pago, invoice, estado visible de cobranza, cliente y referencia de proceso ya existentes
  - `PDF_Service` renderiza el comprobante bajo demanda y `Download_Service` puede servirlo sin flujo paralelo
  - `Event_Dispatcher` solo garantiza disponibilidad logica del receipt en `payment_registered` e `invoice_paid`, sin persistencia de archivos
- Riesgo residual:
  - la fase no agrega botones ni entry points visuales nuevos; si en una subfase futura se exponen desde UI, deben reutilizar la misma ruta documental comun

### Reports 22
- Estado: ampliado con métricas avanzadas operativas y financieras sin cambios de schema.
- Avance real confirmado:
  - el modulo agrega lectura agregada de estado derivado de procesos, readiness operativa, aging de invoices, pagos por metodo y top clientes
  - los filtros se amplian con `derived_status`, `currency` y `payment_method`
  - `Report_Admin_Controller` expone los nuevos bloques sin romper la exportacion CSV admin existente

### Deudas técnicas transversales vigentes

- `Report_Service` sigue concentrando bastante logica de orquestacion; el modulo permanece funcional, pero debe vigilarse si crecen filtros o bloques analiticos.
- `includes/class-rest-api.php`, `includes/class-hooks.php` y `includes/class-post-types.php` deben tratarse como placeholders/no activos y no como parte del runtime real.
- la capa visual ya cubre dashboards, reportes, shortcodes cliente y paneles admin principales, pero si la UI sigue creciendo convendra extraer helpers o templates de presentacion
- la base de automatizacion de Fase 25 cubre solo validacion tecnica minima local; todavia no sustituye pruebas funcionales ni CI real
- la Fase 26 introduce metadata admin para documentar shortcodes activos; si se agregan nuevos shortcodes en el futuro, esa capa debe mantenerse alineada con el bootstrap real
- las rutas admin de PDF de quotes e invoices siguen como excepcion controlada por nonce/capability y aun no se unifican con `Download_Service`
## Actualizacion Fase 23. Portal cliente premium con acciones reales

### Client Portal
- Avance real confirmado:
  - el portal cliente gana una vista integrada de detalle de proceso desde su dashboard principal
  - el detalle del proceso reutiliza services existentes para timeline, comentarios, documentos, quotes e invoices relacionadas
  - el cliente puede crear comentarios reales del proceso sin introducir un sistema paralelo de mensajeria
  - invoices y pagos exponen descarga segura de `payment_receipt` mediante la capa documental comun
- Riesgo residual:
  - la experiencia premium sigue apoyandose en shortcodes y query args validados; cualquier futura navegacion mas rica debe conservar la misma politica de acceso y nonces



