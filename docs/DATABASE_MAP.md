# DATABASE MAP

## Alcance real del schema actual

Este documento refleja el schema real del plugin en la version `1.11.0` definida en `includes/database/class-schema.php`.

Aclaraciones importantes:

- la tabla real es `sm_client_vehicles`, no `sm_client_vehicle`
- la tabla real es `sm_process_step_logs`, no `sm_process_logs`
- las tablas reales de flujo son `sm_flows` y `sm_flow_steps`, no `sm_process_flows` ni `sm_process_steps`
- las tablas `sm_attachments`, `sm_comments` y `sm_notifications` ya existen en el schema real
- este archivo documenta solo tablas reales del plugin activas en el esquema actual
- las subfases 11A, 11B, 11C y 11D no modificaron schema ni agregaron tablas; reutilizan tablas existentes para PDF, resolucion documental y descargas seguras
- el Refactor A de dashboards tampoco modifica schema; reutiliza `sm_processes`, `sm_process_step_logs`, `sm_maintenance`, `sm_clients` y `sm_vehicles`
- el Refactor B-R de invoices y el Fix D-R de descargas seguras tampoco modifican schema; reutilizan `sm_invoices`, `sm_invoice_items`, `sm_payments`, `sm_attachments`, `sm_processes` y ownership existente
- la Fase 12C de consolidacion de `Reports` tampoco modifica schema; reutiliza `sm_processes`, `sm_maintenance`, `sm_clients`, `sm_vehicles`, `sm_quotes`, `sm_invoices` y `sm_payments`
- la Fase 12D de reportes avanzados base tampoco modifica schema; reutiliza `sm_processes`, `sm_quotes`, `sm_invoices` y `sm_payments`
- la Fase 17 de control de acceso, visibilidad y ownership tampoco modifica schema; reutiliza relaciones y ownership ya existentes sobre `sm_client_vehicles`, `sm_processes`, `sm_quotes`, `sm_invoices`, `sm_attachments`, `sm_comments` y `sm_notifications`
- la Fase 20 de automatizacion documental y estados derivados tampoco modifica schema; reutiliza `sm_processes`, `sm_quotes`, `sm_invoices`, `sm_payments` y `sm_pre_delivery`
- la Fase 20B de comprobante de pago documental tampoco modifica schema; reutiliza `sm_payments`, `sm_invoices`, `sm_processes` y `sm_clients`
- la Fase 21 de configuracion avanzada por taller / negocio tampoco modifica schema; reutiliza `wp_options` con `sm_settings` y conserva `super_mechanic_settings` como option legacy de la UI clasica
- la Fase 32A de citas agrega la tabla `sm_appointments` como cambio minimo indispensable para agenda operativa
- la Fase 32B-2 agrega la tabla `sm_appointment_calendar_sync` para persistencia desacoplada de sincronizacion 1-way con Google Calendar (sin contaminar `sm_appointments`)

--------------------------------------------------

Tabla: sm_clients

Proposito:
Almacenar clientes del sistema.

Columnas principales:
- `id`
- `first_name`
- `last_name`
- `email`
- `phone`
- `document_id`
- `notes`
- `status`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `sm_vehicles.client_id`
- `sm_client_vehicles.client_id`
- `sm_processes.client_id`
- `sm_quotes.client_id`
- `sm_invoices.client_id`

Indices importantes:
- `email`
- `phone`
- `status`

--------------------------------------------------

Tabla: sm_vehicles

Proposito:
Almacenar vehiculos del sistema.

Columnas principales:
- `id`
- `client_id`
- `type`
- `make`
- `model`
- `year`
- `vin`
- `plate`
- `color`
- `mileage`
- `notes`
- `status`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `client_id` -> `sm_clients.id`
- `sm_client_vehicles.vehicle_id`
- `sm_processes.vehicle_id`

Indices importantes:
- `client_id`
- `vin`
- `plate`
- `status`

--------------------------------------------------

Tabla: sm_client_vehicles

Proposito:
Registrar la relacion cliente-vehiculo y ownership historico o actual.

Columnas principales:
- `id`
- `client_id`
- `vehicle_id`
- `ownership_type`
- `start_date`
- `end_date`
- `is_primary`
- `created_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `client_id` -> `sm_clients.id`
- `vehicle_id` -> `sm_vehicles.id`

Indices importantes:
- `client_id`
- `vehicle_id`
- `ownership_type`
- `is_primary`
- `current_owner (vehicle_id,end_date,is_primary)`

--------------------------------------------------

Tabla: sm_flows

Proposito:
Definir flujos configurables por tipo de proceso.

Columnas principales:
- `id`
- `name`
- `slug`
- `flow_type`
- `process_type`
- `description`
- `is_default`
- `is_active`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `sm_flow_steps.flow_id`
- `sm_processes.flow_id`

Indices importantes:
- `slug` unico
- `flow_type`
- `process_type`
- `is_active`

--------------------------------------------------

Tabla: sm_flow_steps

Proposito:
Definir pasos configurables dentro de un flujo.

Columnas principales:
- `id`
- `flow_id`
- `step_key`
- `step_label`
- `step_order`
- `step_type`
- `is_required`
- `is_initial`
- `is_final`
- `requires_approval`
- `requires_note`
- `is_active`
- `metadata`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `flow_id` -> `sm_flows.id`
- `sm_processes.current_step_id`
- `sm_process_step_logs.flow_step_id`

Indices importantes:
- `flow_id`
- `step_order`
- `is_initial`
- `is_active`
- `flow_step_key (flow_id,step_key)` unico

--------------------------------------------------

Tabla: sm_processes

Proposito:
Almacenar procesos operativos del negocio por vehiculo y cliente.

Columnas principales:
- `id`
- `vehicle_id`
- `client_id`
- `flow_id`
- `process_type`
- `title`
- `description`
- `internal_notes`
- `current_step_id`
- `status`
- `priority`
- `opened_at`
- `due_date`
- `completed_at`
- `closed_at`
- `created_by`
- `assigned_to`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `vehicle_id` -> `sm_vehicles.id`
- `client_id` -> `sm_clients.id`
- `flow_id` -> `sm_flows.id`
- `current_step_id` -> `sm_flow_steps.id`
- `sm_process_step_logs.process_id`
- `sm_process_parts.process_id`
- `sm_process_meta.process_id`
- `sm_maintenance.process_id`
- `sm_pre_delivery.process_id`
- `sm_paperwork.process_id`
- `sm_quotes.process_id`
- `sm_invoices.process_id`
- `sm_attachments.process_id`
- `sm_comments.process_id`
- `sm_notifications.process_id`

Indices importantes:
- `vehicle_id`
- `client_id`
- `flow_id`
- `current_step_id`
- `status`
- `process_type`
- `opened_at`
- `due_date`

Notas operativas actuales:
- `Process_Service` resuelve `flow_id` y `current_step_id` antes de persistir cuando aplica flujo
- la auditoria pre-Fase 12 no detecta persistencia nueva con `flow_id = 0` y `current_step_id = 0` por defecto en el flujo normal validado
- la Fase 20 agrega estados derivados de proceso a nivel de service y UI, pero no introduce columnas nuevas ni persistencia adicional

--------------------------------------------------

Tabla: sm_appointments

Proposito:
Registrar citas operativas del taller con relacion a cliente, vehiculo y mecanico asignado.

Columnas principales:
- `id`
- `client_id`
- `vehicle_id`
- `process_id`
- `assigned_to`
- `appointment_status`
- `appointment_date`
- `start_at`
- `notes`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `client_id` -> `sm_clients.id`
- `vehicle_id` -> `sm_vehicles.id`
- `process_id` -> `sm_processes.id` (opcional)
- `assigned_to` -> `wp_users.ID`

Indices importantes:
- `client_id`
- `vehicle_id`
- `process_id`
- `assigned_to`
- `appointment_status`
- `appointment_date`
- `start_at`

Notas operativas actuales:
- la fase 32A mantiene alcance admin interno (sin API publica ni automatizaciones)
- el criterio de mecanico en citas usa unicamente `assigned_to`
- la validacion cliente/vehiculo/proceso se resuelve en service, sin SQL fuera de repository

--------------------------------------------------

Tabla: sm_appointment_calendar_sync

Proposito:
Persistir estado de sincronizacion externa de citas con proveedores de calendario, manteniendo `sm_appointments` como fuente local de verdad.

Columnas principales:
- `id`
- `appointment_id`
- `provider`
- `external_calendar_id`
- `external_event_id`
- `sync_status`
- `last_synced_at`
- `last_sync_hash`
- `last_error`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `appointment_id` -> `sm_appointments.id`

Indices importantes:
- `appointment_id`
- `provider`
- `sync_status`
- `provider_appointment (provider,appointment_id)` unico
- `provider_external_event (provider,external_event_id)` unico

Notas operativas actuales:
- la Fase 32B-2 usa `provider = google_calendar`
- el guardado local de cita no depende del exito remoto; los errores se registran en `last_error`
- no hay sync bidireccional, webhooks ni watch channels en esta fase

--------------------------------------------------

Tabla: sm_process_step_logs

Proposito:
Registrar eventos y trazabilidad basica de pasos del proceso.

Columnas principales:
- `id`
- `process_id`
- `flow_step_id`
- `action_type`
- `message`
- `internal_note`
- `customer_visible`
- `created_by`
- `created_at`

Clave primaria:
- `id`

Claves foraneas logicas:
- `process_id` -> `sm_processes.id`
- `flow_step_id` -> `sm_flow_steps.id`

Indices importantes:
- `process_id`
- `flow_step_id`
- `action_type`
- `customer_visible`

Notas operativas actuales:
- `Process_Service` escribe logs operativos reales de `step_initialized`, `step_transition` y `status_changed`
- el `flow_step_id` se valida contra el flujo actual antes de registrar transiciones o cambios de estado
- la Fase 13 ya encapsula la escritura principal de proceso y logs operativos base dentro de una frontera transaccional del modulo
- Fase 19 endurece la alcanzabilidad del `flow_step_id` en runtime: el cambio de paso solo admite pasos activos adyacentes por `step_order`

--------------------------------------------------

Tabla: sm_process_parts

Proposito:
Registrar partes o piezas asociadas a un proceso.

Columnas principales:
- `id`
- `process_id`
- `part_name`
- `part_code`
- `quantity`
- `unit_price`
- `total_price`
- `status`
- `note`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `process_id`
- `status`
- `part_code`

--------------------------------------------------

Tabla: sm_process_meta

Proposito:
Guardar metadata extendida por proceso.

Columnas principales:
- `id`
- `process_id`
- `meta_key`
- `meta_value`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `process_id`
- `meta_key`
- `process_meta_key (process_id,meta_key)`

--------------------------------------------------

Tabla: sm_maintenance

Proposito:
Almacenar la ficha principal de mantenimiento por proceso.

Columnas principales:
- `id`
- `process_id`
- `diagnosis`
- `client_approved`
- `approved_at`
- `mechanic_id`
- `estimated_hours`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `process_id` unico
- `mechanic_id`
- `client_approved`

--------------------------------------------------

Tabla: sm_maintenance_parts

Proposito:
Registrar repuestos asociados al mantenimiento.

Columnas principales:
- `id`
- `maintenance_id`
- `part_name`
- `quantity`
- `unit_price`
- `total_price`
- `notes`
- `created_at`

Clave primaria:
- `id`

Indices importantes:
- `maintenance_id`

--------------------------------------------------

Tabla: sm_maintenance_labor

Proposito:
Registrar mano de obra asociada al mantenimiento.

Columnas principales:
- `id`
- `maintenance_id`
- `description`
- `hours`
- `hour_rate`
- `total_price`
- `created_at`

Clave primaria:
- `id`

Indices importantes:
- `maintenance_id`

--------------------------------------------------

Tabla: sm_pre_delivery

Proposito:
Registrar checklist y estado de pre-entrega por proceso.

Columnas principales:
- `id`
- `process_id`
- `insurance_required`
- `insurance_completed`
- `insurance_completed_at`
- `plate_required`
- `plate_completed`
- `plate_completed_at`
- `final_review_required`
- `final_review_completed`
- `final_review_completed_at`
- `delivery_ready`
- `delivery_ready_at`
- `assigned_user_id`
- `notes`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `process_id` unico
- `assigned_user_id`
- `delivery_ready`

--------------------------------------------------

Tabla: sm_paperwork

Proposito:
Registrar tramite principal administrativo por proceso.

Columnas principales:
- `id`
- `process_id`
- `paperwork_type`
- `target_date`
- `completed_date`
- `assigned_user_id`
- `status`
- `notes`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `process_id` unico
- `assigned_user_id`
- `status`
- `target_date`

--------------------------------------------------

Tabla: sm_paperwork_items

Proposito:
Registrar items o checklist interno del paperwork.

Columnas principales:
- `id`
- `paperwork_id`
- `item_key`
- `item_label`
- `is_required`
- `is_completed`
- `completed_at`
- `notes`
- `sort_order`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `paperwork_id`
- `sort_order`
- `is_completed`

--------------------------------------------------

Tabla: sm_quotes

Proposito:
Almacenar cotizaciones asociadas a procesos y clientes.

Columnas principales:
- `id`
- `process_id`
- `client_id`
- `quote_number`
- `status`
- `currency`
- `subtotal`
- `tax_total`
- `discount_total`
- `grand_total`
- `notes`
- `approved_by_client`
- `approved_at`
- `rejected_at`
- `rejection_reason`
- `created_by`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `quote_number` unico
- `process_id`
- `client_id`
- `status`
- `approved_by_client`

Notas operativas actuales:
- el PDF de quote reutiliza esta tabla y `sm_quote_items` sin cambios de schema
- el acceso cliente al PDF depende de ownership validado contra cliente/proceso
- la resolucion documental consolidada de 11D sigue reutilizando esta tabla sin columnas nuevas

--------------------------------------------------

Tabla: sm_quote_items

Proposito:
Registrar lineas de detalle de una cotizacion.

Columnas principales:
- `id`
- `quote_id`
- `item_type`
- `reference_id`
- `label`
- `description`
- `quantity`
- `unit_price`
- `line_total`
- `sort_order`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `quote_id`
- `item_type`
- `sort_order`

--------------------------------------------------

Tabla: sm_invoices

Proposito:
Almacenar facturas asociadas a procesos, quotes y clientes.

Columnas principales:
- `id`
- `process_id`
- `quote_id`
- `client_id`
- `invoice_number`
- `status`
- `currency`
- `subtotal`
- `tax_total`
- `discount_total`
- `grand_total`
- `amount_paid`
- `balance_due`
- `issued_at`
- `due_date`
- `paid_at`
- `notes`
- `created_by`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `invoice_number` unico
- `process_id`
- `quote_id`
- `client_id`
- `status`
- `due_date`

Notas operativas actuales:
- el PDF de invoice reutiliza esta tabla y `sm_invoice_items` sin cambios de schema
- el acceso cliente al PDF depende de ownership validado contra cliente/proceso
- la resolucion documental consolidada de 11D sigue reutilizando esta tabla sin columnas nuevas
- el flujo transaccional de creacion desde quote sigue reutilizando esta tabla sin cambios de schema, pero la frontera `START TRANSACTION` / `COMMIT` / `ROLLBACK` ya no vive en `Invoice_Service`
- `amount_paid` y `balance_due` se mantienen por compatibilidad operativa, pero el hardening final de Fase 15 los trata como cache legado y no como fuente primaria de decision financiera

--------------------------------------------------

Tabla: sm_invoice_items

Proposito:
Registrar lineas de detalle de una factura.

Columnas principales:
- `id`
- `invoice_id`
- `item_type`
- `reference_id`
- `label`
- `description`
- `quantity`
- `unit_price`
- `line_total`
- `sort_order`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `invoice_id`
- `item_type`
- `sort_order`
- `invoice_sort_order (invoice_id,sort_order)`

--------------------------------------------------

Tabla: sm_payments

Proposito:
Registrar pagos asociados a facturas.

Columnas principales:
- `id`
- `invoice_id`
- `payment_date`
- `amount`
- `payment_method`
- `reference`
- `notes`
- `received_by`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `invoice_id`
- `payment_date`
- `payment_method`
- `invoice_payment_date (invoice_id,payment_date)`

Notas operativas actuales:
- la Fase 15 no modifica schema ni agrega columnas nuevas
- `Invoice_Service` valida que el monto de un pago nuevo o editado no exceda el saldo pendiente disponible de la invoice asociada
- la validacion y el resumen de cobranza usan `sm_payments` como fuente de verdad y soportan exclusion del pago actual al editar
- `Reports` reutiliza `payment_date` como criterio temporal para ingresos basicos por periodo
- la Fase 20 sigue reutilizando `sm_payments` como unica fuente de verdad para el estado derivado visible de cobranza y no agrega tablas de comprobantes
- la Fase 20B agrega comprobante documental logico por `payment_id` sin columnas nuevas, sin tabla nueva y sin persistencia fisica del receipt

--------------------------------------------------

Tabla: sm_attachments

Proposito:
Registrar documentos adjuntos por proceso u objeto funcional.

Columnas principales:
- `id`
- `object_type`
- `object_id`
- `process_id`
- `client_id`
- `vehicle_id`
- `attachment_type`
- `title`
- `description`
- `file_url`
- `file_path`
- `mime_type`
- `file_size`
- `is_internal`
- `is_client_visible`
- `uploaded_by`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `object_type`
- `object_id`
- `process_id`
- `client_id`
- `vehicle_id`
- `attachment_type`
- `is_internal`
- `is_client_visible`

Notas operativas actuales:
- las descargas cliente validan `is_internal` e `is_client_visible` antes de servir el archivo
- la capa de descarga intenta resolver primero `file_path`; si no existe archivo local valido, devuelve error controlado
- no hubo cambios de schema para soportar la descarga segura
- la consolidacion documental de 11D sigue dependiendo de `file_path`, `mime_type`, `is_internal` e `is_client_visible` sin cambios estructurales
- dashboard cliente y shortcode de documentos del proceso ya no deben usar `file_url` directo para adjuntos protegidos; ambos dependen de la ruta segura servida por `Download_Service`

--------------------------------------------------

Tabla: sm_comments

Proposito:
Registrar comentarios y mensajes por proceso u objeto funcional.

Columnas principales:
- `id`
- `object_type`
- `object_id`
- `process_id`
- `client_id`
- `vehicle_id`
- `parent_id`
- `author_user_id`
- `author_client_id`
- `comment_type`
- `content`
- `is_internal`
- `is_client_visible`
- `status`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `object_type`
- `object_id`
- `process_id`
- `client_id`
- `vehicle_id`
- `parent_id`
- `author_user_id`
- `author_client_id`
- `comment_type`
- `is_internal`
- `is_client_visible`
- `status`

--------------------------------------------------

Tabla: sm_notifications

Proposito:
Registrar notificaciones internas por usuario o cliente.

Columnas principales:
- `id`
- `recipient_type`
- `recipient_id`
- `object_type`
- `object_id`
- `process_id`
- `notification_type`
- `title`
- `message`
- `data_json`
- `is_read`
- `read_at`
- `is_system`
- `created_at`
- `updated_at`

Clave primaria:
- `id`

Indices importantes:
- `recipient_type`
- `recipient_id`
- `object_type`
- `object_id`
- `process_id`
- `notification_type`
- `is_read`
- `is_system`

--------------------------------------------------

## Relaciones entre tablas

Relaciones principales del schema real:

- `sm_clients` -> `sm_vehicles` por `sm_vehicles.client_id`
- `sm_clients` -> `sm_client_vehicles` por `sm_client_vehicles.client_id`
- `sm_clients` -> `sm_processes` por `sm_processes.client_id`
- `sm_clients` -> `sm_quotes` por `sm_quotes.client_id`
- `sm_clients` -> `sm_invoices` por `sm_invoices.client_id`
- `sm_clients` -> `sm_appointments` por `sm_appointments.client_id`
- `sm_appointments` -> `sm_appointment_calendar_sync` por `sm_appointment_calendar_sync.appointment_id`
- `sm_vehicles` -> `sm_processes` por `sm_processes.vehicle_id`
- `sm_vehicles` -> `sm_appointments` por `sm_appointments.vehicle_id`
- `sm_flows` -> `sm_flow_steps` por `sm_flow_steps.flow_id`
- `sm_flows` -> `sm_processes` por `sm_processes.flow_id`
- `sm_flow_steps` -> `sm_processes` por `sm_processes.current_step_id`
- `sm_processes` -> `sm_process_step_logs` por `sm_process_step_logs.process_id`
- `sm_processes` -> `sm_appointments` por `sm_appointments.process_id`
- `sm_processes` -> `sm_process_parts` por `sm_process_parts.process_id`
- `sm_processes` -> `sm_process_meta` por `sm_process_meta.process_id`
- `sm_processes` -> `sm_maintenance` por `sm_maintenance.process_id`
- `sm_processes` -> `sm_pre_delivery` por `sm_pre_delivery.process_id`
- `sm_processes` -> `sm_paperwork` por `sm_paperwork.process_id`
- `sm_processes` -> `sm_quotes` por `sm_quotes.process_id`
- `sm_quotes` -> `sm_quote_items` por `sm_quote_items.quote_id`
- `sm_quotes` -> `sm_invoices` por `sm_invoices.quote_id`
- `sm_invoices` -> `sm_invoice_items` por `sm_invoice_items.invoice_id`
- `sm_invoices` -> `sm_payments` por `sm_payments.invoice_id`
- `sm_processes` -> `sm_attachments` por `sm_attachments.process_id`
- `sm_processes` -> `sm_comments` por `sm_comments.process_id`
- `sm_processes` -> `sm_notifications` por `sm_notifications.process_id`
- `sm_clients` -> `sm_comments` por `sm_comments.client_id`
- `sm_vehicles` -> `sm_comments` por `sm_comments.vehicle_id`

## Reglas de integridad y acceso relevantes

- El Client Portal no debe confiar solo en `invoice_id`, `quote_id` o `attachment_id`; el acceso se valida contra ownership real.
- `sm_client_vehicles` y la asociacion cliente-proceso siguen siendo base de seguridad para descargas protegidas.
- `sm_process_step_logs` ya forma parte operativa de dashboards, timeline y trazabilidad base; cualquier cambio debe preservar consistencia con `sm_processes` y `sm_flow_steps`.
- `sm_attachments.is_internal = 1` bloquea exposicion cliente.
- `sm_attachments.is_client_visible = 0` bloquea exposicion cliente.
- Las subfases 11A, 11B, 11C y 11D reutilizan estas reglas sin agregar columnas nuevas.
- El Refactor A reutiliza tablas existentes y traslada consultas de dashboards a `Process_Repository` sin cambiar reglas de integridad.
- El Fix D-R refuerza estas mismas reglas en dashboard cliente y shortcode de documentos del proceso, evitando exposicion por `file_url` directo.
- La Fase 17 centraliza estas mismas reglas de ownership y visibilidad en `Access_Control_Service` sin agregar columnas nuevas ni duplicar fuentes de verdad de acceso.
- La Fase 20B reutiliza esas mismas reglas para `payment_receipt`, resolviendo acceso por la invoice asociada al pago sin crear una tabla documental nueva.

## Tablas criticas del sistema

Estas tablas no deben modificarse sin revisar dependencias funcionales y documentacion tecnica completa:

- `sm_processes`
- `sm_quotes`
- `sm_invoices`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

Tablas tambien muy sensibles por impacto transversal:

- `sm_clients`
- `sm_client_vehicles`
- `sm_flow_steps`
- `sm_invoice_items`
- `sm_quote_items`

## Nota de mantenimiento

Si el schema cambia en futuras fases, actualizar tambien:

- `ARCHITECTURE.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## Nota Fase 12A. Reportes base operativos

- La Fase 12A no modifica schema ni agrega tablas.
- El modulo `Reports` reutiliza:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`

## Nota Fase 12B. Reportes financieros base

- La Fase 12B no modifica schema ni agrega tablas.
- El modulo `Reports` amplía su lectura reutilizando:
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Los totales financieros de 12B se agrupan por `currency` para no mezclar importes incompatibles.
- En 12B, el criterio temporal actual para `total facturado` usa `sm_invoices.created_at`.
- En 12B, el criterio temporal actual para `total cobrado` usa `sm_payments.payment_date`.

## Nota Fase 12C. Consolidacion del modulo Reports

- La Fase 12C no modifica schema ni agrega tablas.
- El modulo `Reports` consolida filtros compartidos y exportacion CSV admin reutilizando:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`

## Nota Fase 12D. Reportes avanzados base

- La Fase 12D no modifica schema ni agrega tablas.
- El modulo `Reports` amplía su lectura reutilizando:
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Las comparativas de 12D siguen usando consultas agregadas por rango y agrupaciones simples, sin cambios estructurales del schema.

## Nota Fase 12E. Endurecimiento / Performance / Task Files de Reports

- La Fase 12E no modifica schema ni agrega tablas.
- El modulo `Reports` consolida limites y robustez de filtros/exportacion reutilizando:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- La revision de 12E confirma que el modulo sigue operando con consultas agregadas y listados recientes acotados, sin introducir tablas nuevas ni SQL fuera del repository.

## Nota Fase 13. Integridad transaccional y endurecimiento del nucleo

- La Fase 13 no modifica schema ni agrega tablas.
- El modulo `Processes` endurece la persistencia atomica reutilizando:
  - `sm_processes`
  - `sm_process_step_logs`
- La revision de 13 confirma que las escrituras coordinadas de proceso y timeline quedan dentro de una frontera transaccional del modulo, sin cambios estructurales del schema.
- El cierre final de 13 confirma que esa frontera transaccional valida inicio y confirmacion real de transaccion antes de reportar exito.

## Nota Fase 14. Validacion funcional, escenarios y estabilizacion

- La Fase 14 no modifica schema ni agrega tablas.
- El cierre final reutiliza sin cambios estructurales:
  - `sm_quotes`
  - `sm_quote_items`
  - `sm_process_step_logs`
  - `sm_attachments`
  - `sm_comments`
  - `sm_invoices`
  - `sm_payments`
- La subfase 14B endurece la atomicidad del flujo maintenance -> quote en la capa transaccional del modulo `Quotes`, sin alterar tablas ni relaciones.
- La timeline consolidada sigue siendo una composicion de lectura sobre tablas existentes; no introduce nuevas columnas ni reglas estructurales.

## Nota Fase 15. Sistema de pagos

- La Fase 15 no modifica schema ni agrega tablas.
- El cierre funcional reutiliza:
  - `sm_invoices`
  - `sm_payments`
  - `sm_clients`
  - `sm_processes`
- El estado visible de cobranza (`pending`, `partial`, `paid`) se calcula a nivel de service/reporting y no agrega columnas nuevas al schema.
- El hardening final de Fase 15 confirma que `sm_payments` es la unica fuente de verdad financiera; `sm_invoices.amount_paid` y `sm_invoices.balance_due` quedan como cache legado de compatibilidad.

## Nota Fase 17. Control de acceso, visibilidad y ownership

- La Fase 17 no modifica schema ni agrega tablas.
- El endurecimiento reutiliza:
  - `sm_client_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- La validacion de acceso deja de depender solo de filtros de listado y pasa a resolverse de forma central en la capa de services sobre ownership ya existente.

## Nota Fase 18. Portal mecanico real

- La Fase 18 no modifica schema ni agrega tablas.
- El portal mecanico reutiliza:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_maintenance`
  - `sm_maintenance_parts`
  - `sm_maintenance_labor`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- La actualizacion operativa de `status` y `current_step_id` sigue pasando por la capa transaccional y de eventos existente del modulo `Processes`.
- Las notas tecnicas internas del portal mecanico reutilizan `sm_comments` y no introducen una ruta paralela de persistencia.
- La visibilidad del portal mecanico debe seguir resolviendose contra acceso real al proceso; no debe confiar solo en capability amplia del rol.

## Nota Fase 19. Workflow operativo configurable avanzado

- La Fase 19 no modifica schema ni agrega tablas.
- El endurecimiento reutiliza:
  - `sm_flows`
  - `sm_flow_steps`
  - `sm_processes`
  - `sm_process_step_logs`
- La validacion de transiciones sigue siendo lineal sobre `step_order`; no introduce tablas nuevas de transicion ni columnas adicionales.
- `requires_approval`, `requires_note` y `metadata` siguen presentes en schema como base estructural, pero la Fase 19 no los convierte aun en un motor completo de restricciones funcionales.

## Nota Fase 20. Automatizacion documental y estados derivados

- La Fase 20 no modifica schema ni agrega tablas.
- El cierre funcional reutiliza:
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_pre_delivery`
- La automatizacion documental de `quote_approved` e `invoice_issued` se resuelve como disponibilidad logica desde `Document_Service` y `PDF_Service`, sin persistir attachments nuevos.
- `ready_for_delivery` se deriva desde `sm_pre_delivery.delivery_ready` y no agrega columnas nuevas a `sm_processes`.
- `waiting_payment` se deriva desde invoices + pagos agregados y no agrega columnas nuevas ni cache adicional.

## Nota Fase 20B. Comprobante de pago documental

- La Fase 20B no modifica schema ni agrega tablas.
- El cierre funcional reutiliza:
  - `sm_payments`
  - `sm_invoices`
  - `sm_processes`
  - `sm_clients`
- El comprobante de pago se resuelve como documento logico reusable por `payment_id`; no agrega columnas, no crea attachments y no persiste PDFs.

## Nota Fase 21. Configuracion avanzada por taller / negocio

- La Fase 21 no modifica schema ni agrega tablas.
- La configuracion avanzada reutiliza:
  - `wp_options` mediante la option `sm_settings` y la option legacy `super_mechanic_settings`
- Los grupos base de configuracion quedan organizados en:
  - `business`
  - `process`
  - `financial`
  - `notifications`
- Los defaults preservan el comportamiento actual y mantienen fallback minimo hacia settings legacy del negocio.

## Nota Fase 22. Reportes operativos y financieros avanzados

- La Fase 22 no modifica schema ni agrega tablas.
- El modulo `Reports` amplía su lectura reutilizando:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_pre_delivery`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_clients`
- Los estados derivados operativos del modulo se resuelven como lectura agregada sobre datos ya persistidos; no agregan columnas nuevas.
## Nota Fase 23. Client Portal premium con acciones reales

- La Fase 23 no modifica schema ni agrega tablas.
- El cierre funcional reutiliza:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_attachments`
  - `sm_comments`
- La vista integrada del Client Portal sigue resolviendo ownership y descargas sobre datos ya persistidos; no introduce columnas nuevas ni rutas paralelas de persistencia.

## Nota Fase 26B. Hardening arquitectural pre-SaaS

- La Fase 26B no modifica schema ni agrega tablas.
- El endurecimiento reutiliza:
  - `sm_client_vehicles`
  - `sm_vehicles`
  - `sm_flows`
  - `sm_flow_steps`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_attachments`
  - `sm_comments`
- La fase agrega fronteras transaccionales para escrituras ya existentes en `relations` y `flows`, pero no introduce columnas nuevas.
- La correccion documental de 26B consolida el modelo real de settings (`sm_settings` + fallback legacy `super_mechanic_settings`), `plate` y `flow_step_id` sin alterar el schema.

## Nota Fase 28. Centro financiero admin

- La Fase 28 no modifica schema ni agrega tablas.
- El cierre funcional reutiliza:
  - `sm_invoices`
  - `sm_invoice_items`
  - `sm_payments`
  - `sm_processes`
  - `sm_clients`
- `Payment_Repository` amplía consultas de lectura para panel admin dedicado de payments, manteniendo SQL en repository.
- El estado de cobro visible de invoices en el centro financiero sigue derivándose desde `sm_payments` vía `Invoice_Service` (`pending` / `partial` / `paid`), sin columnas nuevas.

## Nota Fase 29. Reportes expansión

- La Fase 29 no modifica schema ni agrega tablas.
- La expansión del módulo `Reports` reutiliza:
  - `sm_processes`
  - `sm_clients`
  - `sm_vehicles`
  - `sm_invoices`
  - `sm_payments`
- El criterio de mecánico de reportes operativos se fija sobre `sm_processes.assigned_to` para evitar mezclar fuentes de asignación.
- La separación `invoice_status` (documental) vs estado de cobro (derivado de pagos) se mantiene en lectura, sin persistencia adicional.

## Nota Fase 30. Tenancy base preparada (no activada)

- La Fase 30 no modifica schema ni agrega tablas.
- La fase introduce únicamente una capa de contexto de negocio en services:
  - `includes/helpers/class-business-context-service.php`
- El runtime permanece en modo `single_business`:
  - sin `business_id` persistente
  - sin filtros tenant-aware en repositories
  - sin cambios de ownership/enforcement existentes
- Tablas candidatas para futura evolucion a `business_id` (fase posterior, fuera de Fase 30):
  - `sm_clients`
  - `sm_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`

## Nota Fase 31A. Base local de licencias

- La Fase 31A no modifica schema ni agrega tablas.
- La persistencia de licencia reutiliza:
  - `wp_options` option `sm_settings`
  - grupo `license`
- Shape local de `sm_settings.license`:
  - `license_key`
  - `status`
  - `activated_at`
  - `last_validated_at`
  - `provider`
  - `message`
- Estados locales soportados en 31A:
  - `inactive`
  - `active`
  - `invalid`
  - `unknown`

## Nota Fase 31B. Base de updates privadas

- La Fase 31B no modifica schema ni agrega tablas.
- La persistencia de updates privadas reutiliza:
  - `wp_options` option `sm_settings`
  - grupo `updates`
- Shape operativo base de `sm_settings.updates`:
  - `provider`
  - `last_check_at`
  - `latest_version`
  - `package_available`
  - `message`
  - `last_result`
- Metadata técnica adicional (sin impacto de schema):
  - `requires`
  - `tested`
  - `changelog`
  - `package_source_url`

## Nota Fase 32A. Calendario / Citas base operativa

- La Fase 32A agrega la tabla `sm_appointments`.
- El modulo `appointments` reutiliza:
  - `sm_clients`
  - `sm_vehicles`
  - `sm_processes` (vinculo opcional)
  - `wp_users` para `assigned_to`
- El criterio de mecanico queda unificado en `assigned_to`.
- La fase mantiene alcance interno admin sin automatizaciones, sin notificaciones avanzadas y sin integraciones externas.

## Nota Fase 32B-2. Google Calendar 1-way sync

- La Fase 32B-2 agrega la tabla `sm_appointment_calendar_sync`.
- La integracion Google Calendar reutiliza:
  - `sm_appointments` (fuente local de verdad)
  - `sm_clients`
  - `sm_vehicles`
  - `wp_options` con grupo `sm_settings.google_calendar` para credenciales y tokens OAuth
- La persistencia de referencia externa y estado de sync se desacopla de `sm_appointments` para permitir evolucion de proveedores sin alterar la entidad core de citas.
- La fase mantiene alcance 1-way (sin sync bidireccional, webhooks ni watch channels).
