# SYSTEM MAP

## Bootstrap del plugin

1. `super-mechanic.php`
2. Definicion de constantes:
   - `SM_PLUGIN_VERSION = 0.1.0`
   - `SM_PLUGIN_FILE`
   - `SM_PLUGIN_PATH`
   - `SM_PLUGIN_URL`
3. Carga de `includes/autoloader.php`
4. Activacion:
   - `Super_Mechanic\Activator::activate()`
   - registra roles
   - asigna capabilities
   - ejecuta `Installer::install()`
5. Desactivacion:
   - `Super_Mechanic\Deactivator::deactivate()`
6. `plugins_loaded` -> `sm_run_plugin()`
7. `sm_run_plugin()` -> instancia `Super_Mechanic\Plugin`
8. `Plugin::init()`:
   - `maybe_upgrade_schema()`
   - `register_hooks()`

## Arquitectura activa real

- La arquitectura activa real vive en `includes/*`.
- `includes/modules/*` existe como arbol legacy y no esta conectado al bootstrap real.

## Modulos activos reales

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
- Invoices
- Payments
- Reports
- Attachments / Timeline
- Communication / Notifications
- Documents / PDF / Secure Downloads
- Client Portal por shortcodes

## Modulos presentes pero no activos en el bootstrap real

- `includes/class-rest-api.php`: placeholder vacio.
- `includes/modules/*`: arbol paralelo no conectado.
- `includes/integrations/woocommerce/*`: scaffold tecnico no integrado.

## Dependencias entre modulos

- Core -> inicializa todos los modulos activos.
- Security -> condiciona accesos admin y frontend.
- Clients -> base para Vehicles, Relations, Processes, Quotes e Invoices.
- Vehicles -> base para Relations y Processes.
- Client-Vehicle Relations -> ownership para dashboard cliente y acceso a datos.
- Flows -> definen pasos para Processes.
- Processes -> orquesta Maintenance, Pre-Delivery, Paperwork, Quotes, Invoices, Attachments y Communication.
- Maintenance -> alimenta Quotes.
- Quotes -> alimenta Invoices y Documents.
- Invoices -> alimenta Payments, Documents y portal cliente.
- Attachments -> alimenta timeline y visibilidad documental.
- Documents / Secure Downloads -> `Document_Service` resuelve tipo, acceso y payload; `PDF_Service` genera PDFs; `Download_Service` sirve la respuesta protegida.
- Communication -> alimenta timeline, notificaciones y feed cliente/admin.
- Dashboards -> consumen Clients, Vehicles, Processes, Quotes, Invoices, Payments, Attachments y Communication.
- Dashboards -> consultan agregados de procesos via `Process_Service` y `Process_Repository`, sin SQL directo en `Dashboard_Service`.
- Access Control -> centraliza ownership y visibilidad para dashboard, portal cliente, communication y capa documental.

## Tablas por modulo

- Settings:
  - `wp_options` con `super_mechanic_settings`
  - `wp_options` con `sm_db_version`
- Clients:
  - `sm_clients`
- Vehicles:
  - `sm_vehicles`
- Client-Vehicle Relations:
  - `sm_client_vehicles`
- Flows:
  - `sm_flows`
  - `sm_flow_steps`
- Processes:
  - `sm_processes`
  - `sm_process_step_logs`
  - `sm_process_parts`
  - `sm_process_meta`
- Maintenance:
  - `sm_maintenance`
  - `sm_maintenance_parts`
  - `sm_maintenance_labor`
- Pre-Delivery:
  - `sm_pre_delivery`
- Paperwork:
  - `sm_paperwork`
  - `sm_paperwork_items`
- Quotes:
  - `sm_quotes`
  - `sm_quote_items`
- Invoices:
  - `sm_invoices`
  - `sm_invoice_items`
- Payments:
  - `sm_payments`
- Attachments:
  - `sm_attachments`
- Communication:
  - `sm_comments`
  - `sm_notifications`
- Documents / Secure Downloads:
  - reutiliza `sm_quotes`
  - `sm_quote_items`
  - `sm_invoices`
  - `sm_invoice_items`
  - `sm_attachments`
  - `sm_processes`
  - `sm_client_vehicles`

## Clases clave por modulo

- Core:
  - `Plugin`
  - `Activator`
  - `Deactivator`
  - `Installer`
  - `Admin_Menu`
- Security:
  - `Roles`
  - `Capabilities`
  - `Sanitizer`
  - `Validator`
  - `Access_Control_Service`
- Settings:
  - `Settings`
- Clients:
  - `Client_Repository`
  - `Client_Service`
  - `Client_Admin_Controller`
  - `Client_List_Table`
- Vehicles:
  - `Vehicle_Repository`
  - `Vehicle_Service`
  - `Vehicle_Admin_Controller`
  - `Vehicle_List_Table`
- Client-Vehicle Relations:
  - `Client_Vehicle_Repository`
  - `Client_Vehicle_Service`
- Flows:
  - `Flow_Repository`
  - `Flow_Service`
  - `Flow_Step_Repository`
  - `Flow_Step_Service`
  - `Flow_Admin_Controller`
  - `Flow_List_Table`
- Processes:
  - `Process_Repository`
  - `Process_Service`
  - `Process_Admin_Controller`
  - `Process_List_Table`
- Maintenance:
  - `Maintenance_Repository`
  - `Maintenance_Service`
  - `Maintenance_Part_Repository`
  - `Maintenance_Labor_Repository`
  - `Maintenance_Admin_Controller`
- Pre-Delivery:
  - `Pre_Delivery_Repository`
  - `Pre_Delivery_Service`
  - `Pre_Delivery_Admin_Controller`
- Paperwork:
  - `Paperwork_Repository`
  - `Paperwork_Item_Repository`
  - `Paperwork_Service`
  - `Paperwork_Admin_Controller`
- Dashboards:
  - `Dashboard_Service`
  - `Admin_Dashboard_Controller`
  - `Mechanic_Dashboard_Controller`
  - `Client_Dashboard_Controller`
  - `Client_Dashboard_Shortcodes`
- Quotes:
  - `Quote_Repository`
  - `Quote_Item_Repository`
  - `Quote_Service`
  - `Quote_Admin_Controller`
  - `Client_Quote_Shortcodes`
- Invoices / Payments:
  - `Invoice_Repository`
  - `Invoice_Item_Repository`
  - `Invoice_Transaction_Repository`
  - `Payment_Repository`
  - `Invoice_Service`
  - `Invoice_Admin_Controller`
  - `Client_Invoice_Shortcodes`
- Attachments:
  - `Attachment_Repository`
  - `Attachment_Service`
  - `Attachment_Admin_Controller`
  - `Process_Timeline_Service`
  - `Client_Attachment_Shortcodes`
- Communication:
  - `Comment_Repository`
  - `Comment_Service`
  - `Notification_Repository`
  - `Notification_Service`
  - `Event_Dispatcher`
  - `Client_Comment_Shortcodes`
  - integracion admin en `Process_Admin_Controller`
- Documents / Secure Downloads:
  - `Document_Service`
  - `PDF_Service`
  - `Download_Service`
  - `Settings_Service`

## Shortcodes actuales

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

## Menus admin actuales

- `Super Mechanic`
- `Dashboard`
- `Panel mecanico`
- `Clientes`
- `Vehiculos`
- `Procesos`
- `Flujos`
- `Ajustes`

## Dashboards reales

- Admin dashboard:
  - KPIs generales
  - procesos por estado
  - procesos por tipo
  - ultimos procesos
  - ultimos vehiculos
  - ultimos clientes
- Mechanic dashboard:
  - activos asignados
  - procesos activos
  - procesos esperando aprobacion
  - portal operativo con listado, detalle, cambio de estado, cambio de paso y nota tecnica interna sobre procesos accesibles
- Client dashboard:
  - perfil y vehiculos
  - procesos recientes
  - quotes
  - invoices
  - comentarios recientes
  - notificaciones recientes
  - actividad reciente

## Eventos y notificaciones

- Dispatcher activo:
  - `Event_Dispatcher`
- Eventos internos activos:
  - `sm_event_process_created`
  - `sm_event_process_step_changed`
  - `sm_event_process_status_changed`
  - `sm_event_process_finalized`
  - `sm_event_process_updated`
  - `sm_event_quote_created_from_maintenance`
  - `sm_event_quote_sent`
  - `sm_event_quote_approved`
  - `sm_event_quote_rejected`
  - `sm_event_quote_cancelled`
  - `sm_event_invoice_created_from_quote`
  - `sm_event_invoice_issued`
  - `sm_event_payment_registered`
  - `sm_event_invoice_paid`
  - `sm_event_document_uploaded`
  - `sm_event_comment_added`
- Consumidor principal de eventos:
  - `Notification_Service`
- Entry points activos del modulo:
  - tab `communication` en detalle de proceso
  - `[sm_client_process_comments]`
  - `[sm_client_process_comment_form]`
  - `[sm_client_notifications]`

## Actualizacion Fase 16. Automatizaciones y eventos operativos

- Integracion real consolidada:
  - `Event_Dispatcher` registra el catalogo operativo ampliado y mantiene a `Notification_Service` como consumidor principal
  - `Process_Service` emite eventos especificos de creacion, cambio de paso y finalizacion para no sobrecargar `process_updated`
  - `Quote_Service` emite eventos especificos para generacion desde maintenance y cancelacion
  - `Invoice_Service` emite eventos especificos para creacion desde quote y transicion real a cobranza `paid`
  - `Process_Timeline_Service` sigue siendo una composicion de lectura, pero ahora humaniza mejor quotes e invoices segun su estado persistido y su estado de cobranza
- Riesgos arquitectonicos actualizados:
  - cualquier ampliacion futura debe evitar volver a mezclar eventos genericos con eventos especificos para el mismo cambio
  - la timeline no debe pasar a depender del bus de eventos si eso genera una segunda fuente de verdad

## Actualizacion Fase 17. Control de acceso, visibilidad y ownership

- Integracion real consolidada:
  - `Access_Control_Service` centraliza ownership y visibilidad de `vehicle`, `process`, `quote`, `invoice` y `attachment`
  - `Dashboard_Service` delega validaciones de cliente, vehiculo y proceso a la capa central
  - `Quote_Service` e `Invoice_Service` agregan listados filtrados por usuario sin confiar solo en `client_id`
  - `Comment_Service` y `Notification_Service` endurecen acceso a recursos visibles usando proceso u objeto real
  - `Document_Service` y `Download_Service` conservan coherencia con esta politica al delegar acceso en services ya endurecidos
- Clases activas nuevas:
  - `Access_Control_Service`
- Riesgos arquitectonicos actualizados:
  - cualquier nuevo entry point cliente o documental debe reutilizar `Access_Control_Service` y no volver a mover ownership a controllers o shortcodes
  - el acceso de mecanicos sigue siendo sensible y debe mantenerse alineado al proceso asignado cuando aplique

## Actualizacion Fase 18. Portal mecanico real

- Integracion real consolidada:
  - `Mechanic_Dashboard_Controller` deja de ser un panel pasivo y pasa a exponer un portal operativo admin-side para mecanicos
  - el portal lista solo procesos accesibles por la politica actual y reutiliza `Process_Service` para cambios de `status` y `current_step_id`
  - la captura de notas tecnicas internas reutiliza `Comment_Service` y no crea una ruta paralela de persistencia
  - la vista detalle reutiliza `Process_Timeline_Service`, `Attachment_Service` y `Maintenance_Service` sin arrastrar el flujo admin completo del proceso
  - `Plugin` registra hooks propios del portal mecanico sin tocar `includes/modules/*`
- Riesgos arquitectonicos actualizados:
  - el portal mecanico debe mantenerse acotado a acciones operativas minimas y no evolucionar hacia una copia del `Process_Admin_Controller`
  - cualquier futura ampliacion de acciones para mecanicos debe seguir usando services del dominio y no controllers admin como backend indirecto

## Actualizacion Fase 19. Workflow operativo configurable avanzado

- Integracion real consolidada:
  - `Flow_Step_Service` pasa a resolver alcanzabilidad lineal minima entre pasos activos del flujo
  - `Process_Service` valida transiciones de `current_step_id` usando esa capa y deja de aceptar saltos arbitrarios dentro del mismo `flow_id`
  - el sistema toma `step_order` como fuente de verdad del orden operativo mientras no exista un grafo formal de transiciones
  - al entrar en un paso final, `Process_Service` sincroniza el proceso a `completed` y registra el log de `status_changed` sin mover SQL fuera de repositories
- Riesgos arquitectonicos actualizados:
  - el workflow sigue siendo lineal; si en una fase futura se requieren bifurcaciones o reglas condicionales, deberan modelarse de forma explicita y no con ifs dispersos
  - `requires_approval`, `requires_note` y `metadata` no deben documentarse como motor operativo completo mientras no exista enforcement real mas alla de la transicion lineal

## Actualizacion Fase 20. Automatizacion documental y estados derivados

- Integracion real consolidada:
  - `Document_Service` agrega resolucion explicita para automatizacion documental logica de `quote_approved` e `invoice_issued`
  - `Event_Dispatcher` reutiliza esa capa para preparar disponibilidad automatica sin persistir nuevos artefactos documentales
  - `Process_Derived_State_Service` centraliza derivados de proceso a partir de `status`, quotes, invoices y `pre_delivery.delivery_ready`
  - `Invoice_Service` expone enriquecimiento reusable del estado visible de cobranza sobre `sm_payments`
  - dashboard cliente y portal mecanico consumen esos derivados sin trasladar la logica a controllers
- Riesgos arquitectonicos actualizados:
  - cualquier futura persistencia documental automatica debe seguir entrando por la capa documental comun y deduplicar por objeto logico

## Actualizacion Fase 20B. Comprobante de pago documental

- Integracion real consolidada:
  - `Document_Service` resuelve `payment_receipt` como documento logico unico por `payment_id`
  - `PDF_Service` genera el comprobante de pago bajo demanda reutilizando `Invoice_Service`
  - `Invoice_Service` expone acceso a pago, contexto consolidado del receipt, HTML imprimible y filename estable
  - `Event_Dispatcher` garantiza disponibilidad logica del receipt para `payment_registered` e `invoice_paid` sin crear archivos ni attachments
- Riesgos arquitectonicos actualizados:
  - cualquier futura exposicion UI del comprobante debe seguir usando `Download_Service` y no enlaces o renderers paralelos
  - la deduplicacion debe seguir anclada al `payment_id` y no a eventos de negocio, para evitar receipts duplicados por el mismo pago

## Actualizacion Fase 21. Configuracion avanzada por taller / negocio

- Integracion real consolidada:
  - `Settings_Service` centraliza lectura y escritura de configuracion avanzada usando la option `sm_settings`
  - la configuracion queda agrupada en `business`, `process`, `financial` y `notifications`
  - `Settings_Service` aplica defaults que preservan el comportamiento actual y mantiene fallback minimo hacia settings legacy de negocio
  - `Process_Service` reutiliza la capa central para `allow_step_back` y `auto_complete_on_final_step`
  - `Invoice_Service` reutiliza la capa central para `currency`, `business_name` y `allow_partial_payments`
  - `Quote_Service` reutiliza la capa central para `currency` y `business_name`
- Riesgos arquitectonicos actualizados:
  - la configuracion avanzada no debe fragmentarse entre lectura directa de `wp_options` y `Settings_Service`
  - cualquier futura UI de configuracion debe seguir usando la misma estructura `sm_settings` y no crear una segunda fuente de verdad

## Flujos principales del negocio

### Flujo maestro

1. Se crea cliente.
2. Se crea vehiculo.
3. Se vincula cliente con vehiculo.
4. Se crea proceso con `flow_id` resuelto y `current_step_id` valido cuando aplica flujo.
5. El proceso se trabaja por tipo:
   - maintenance
   - pre_delivery
   - paperwork

### Flujo maintenance -> quote -> invoice -> payment

1. El proceso de maintenance registra diagnostico, partes y labor.
2. `Quote_Service` puede generar o gestionar la cotizacion.
3. El cliente aprueba o rechaza la quote desde shortcode.
4. Solo una quote aprobada y no convertida puede generar invoice en el estado actual.
5. `Invoice_Service` registra pagos y recalcula balance.
6. El registro y la edicion de pagos validan que el monto no exceda el saldo pendiente disponible de la invoice.
7. Quotes e invoices pueden descargarse como PDF desde admin y portal cliente cuando hay motor PDF disponible.
8. La generacion automatica maintenance -> quote usa persistencia atomica para quote e items.

### Flujo de portal cliente

1. Se resuelve cliente por `sm_client_id` y ownership.
2. El cliente consulta dashboard.
3. Ve quotes e invoices relacionadas.
4. Puede aprobar o rechazar la quote cuando el estado lo permite.
5. Puede descargar quote PDF, invoice PDF y attachments visibles mediante enlaces seguros.
6. Puede ver timeline, comentarios y notificaciones visibles.
7. La actividad reciente del cliente debe excluir logs internos de proceso y usar solo eventos visibles al cliente.
8. La timeline consolidada refleja quotes, invoices y payments con tipos de evento acordes a su estado real.

## Riesgos arquitectonicos actuales

- `Process_Admin_Controller` concentra demasiada orquestacion y es el punto mas sensible del admin.
- Quotes e invoices no tienen menu admin propio; dependen del contexto del proceso.
- Existen dos arboles arquitectonicos (`includes/*` y `includes/modules/*`) y solo uno esta activo.
- `class-rest-api.php` puede inducir a error si se asume una API REST funcional.
- WooCommerce existe solo como scaffolding tecnico.
- La capa documental depende de mantener ownership estricto y de no reintroducir `file_url` publico en renders cliente fuera de `Download_Service`.
- La deuda transaccional principal ya no esta en `processes`, pero sigue abierta en `relations` y `flows`, donde no existe aun un repository transaccional dedicado.
- aunque las transacciones de invoices y quotes desde maintenance ya se encapsulan fuera de sus services principales, el flujo quote -> invoice -> payment sigue siendo sensible por compatibilidad y consistencia documental
- La documentacion puede desincronizarse si no se actualiza al cierre de cada fase.
- reintroducir checks de ownership dispersos fuera de `Access_Control_Service` volveria a fragmentar la politica de visibilidad del sistema.

## Que revisar antes de modificar el sistema

- `super-mechanic.php`
- `includes/class-plugin.php`
- `includes/database/class-schema.php`
- `includes/class-admin-menu.php`
- `includes/processes/class-process-admin-controller.php`
- `includes/maintenance/class-maintenance-service.php`
- `includes/quotes/class-quote-service.php`
- `includes/quotes/class-quote-admin-controller.php`
- `includes/invoices/class-invoice-service.php`
- `includes/invoices/class-invoice-admin-controller.php`
- `includes/helpers/class-pdf-service.php`
- `includes/helpers/class-download-service.php`
- `includes/attachments/class-attachment-service.php`
- `includes/communication/class-notification-service.php`
- `includes/dashboard/class-dashboard-service.php`
- `includes/relations/class-client-vehicle-service.php`

## Regla de mantenimiento documental

Al cerrar cualquier fase o subfase real, actualizar:

- `ARCHITECTURE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

Registrar solo cambios reales detectables en el codigo y evitar duplicar historial.

## Actualizacion Fase 12A. Reportes base operativos

- Modulo activo nuevo:
  - Reports
- Dependencias maestras nuevas:
  - Reports -> consume Processes, Maintenance, Clients y Vehicles para reporting admin interno
  - Reports -> concentra consultas reutilizables en `Report_Repository` sin mover SQL nuevo a `Dashboard_Service`
- Tablas reutilizadas por Reports:
  - `sm_processes`
  - `sm_maintenance`
  - `sm_clients`
  - `sm_vehicles`
- Clases activas nuevas:
  - `Report_Repository`
  - `Report_Service`
  - `Report_Admin_Controller`
- Menu admin actualizado:
  - `Reportes`

## Actualizacion Fase 12B. Reportes financieros base

- Dependencias maestras ampliadas:
  - Reports -> consume Quotes, Invoices y Payments para reporting financiero admin interno
  - Reports -> mantiene consultas SQL financieras en `Report_Repository` sin moverlas a `Dashboard_Service`
- Tablas reutilizadas por Reports en 12B:
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Capacidades reales agregadas:
  - quotes por estado
  - quotes recientes
  - invoices por estado
  - invoices recientes
  - payments recientes
  - total facturado por rango agrupado por moneda
  - total cobrado por rango agrupado por moneda
  - balance pendiente total por rango agrupado por moneda
- Filtros reales agregados:
  - `date_from`
  - `date_to`
  - `quote_status`
  - `invoice_status`
- Criterio temporal real:
  - en 12B, el total facturado usa `sm_invoices.created_at`

## Actualizacion Fase 12C. Consolidacion del modulo Reports

- Integracion real consolidada:
  - `Report_Service` centraliza validacion y normalizacion de filtros compartidos
  - `Report_Service` separa filtros operativos y financieros y expone datasets reutilizables por bloque
  - `Report_Admin_Controller` separa la UI admin de reportes en bloques operativos y financieros
  - `Report_Admin_Controller` registra el hook `admin_post_sm_export_report_csv` para exportacion CSV admin segura
- Capacidades reales agregadas en 12C:
  - exportacion CSV de `recent_processes`
  - exportacion CSV de `recent_quotes`
  - exportacion CSV de `recent_invoices`
  - exportacion CSV de `recent_payments`
  - limite configurable y acotado para listados recientes
- Riesgos arquitectonicos actualizados:
  - el modulo `Reports` sigue siendo admin-only y no debe evolucionar hacia dashboard paralelo
  - la exportacion CSV debe mantenerse acotada a vistas definidas y protegida por capability + nonce

## Actualizacion Fase 12D. Reportes avanzados base

- Integracion real consolidada:
  - `Report_Repository` agrega consultas agregadas base para comparativas de procesos, quotes, invoices y payments por rango
  - `Report_Service` calcula un periodo anterior equivalente a partir del rango actual solo cuando el filtro incluye `date_from` y `date_to`
  - cuando no existe baseline comparable, `Report_Service` mantiene el bloque avanzado sin comparacion valida y la UI renderiza `N/A`
  - `Report_Service` agrega un resumen ejecutivo simple reutilizable para UI admin
  - `Report_Admin_Controller` agrega un tercer bloque visual separado: `Reportes avanzados base`
- Capacidades reales agregadas en 12D:
  - comparativas simples por rango para procesos, quotes, invoices y payments
  - agrupaciones reutilizables de procesos por estado y tipo
  - agrupaciones reutilizables de quotes por estado
  - agrupaciones reutilizables de invoices por estado
  - resumen ejecutivo simple sin charts ni BI pesado
- Riesgos arquitectonicos actualizados:
- el modulo `Reports` no debe duplicar responsabilidades de `Dashboard_Service`
- las comparativas deben seguir usando consultas agregadas y no datasets completos
- el bloque avanzado debe seguir siendo admin-only y no evolucionar a dashboard paralelo

## Actualizacion Fase 12E. Endurecimiento / Performance / Task Files de Reports

- Integracion real consolidada:
  - `Report_Service` reutiliza los limites de `Report_Repository` para evitar desalineacion entre service, UI y exportacion
  - `Report_Admin_Controller` valida filtros admin sobre datos desescapados con `wp_unslash()` antes de delegar al service
  - el bloque avanzado muestra estado vacio controlado cuando no existen datos monetarios comparables, sin inventar moneda por defecto
  - la exportacion CSV mantiene las mismas vistas permitidas y no amplía su alcance funcional
- Riesgos arquitectonicos actualizados:
- `Report_Admin_Controller` sigue siendo el punto a vigilar si el modulo crece en nuevas subfases
- cualquier optimizacion futura de reportes debe mantenerse en `Report_Repository` y no migrar a `Dashboard_Service`
- si el volumen real crece, la necesidad de indices debe resolverse en una fase futura y no como ajuste ad hoc

## Actualizacion Fase 13. Integridad transaccional y endurecimiento del nucleo

- Integracion real consolidada:
  - `Process_Service` delega la frontera transaccional del modulo a `Process_Transaction_Repository`
  - la creacion de procesos persiste `sm_processes` y `sm_process_step_logs` de `step_initialized` en una sola operacion atomica
  - la actualizacion de procesos persiste la mutacion principal y los logs `step_transition` / `status_changed` en una sola operacion atomica cuando corresponde
  - `update_current_step()` persiste el nuevo `current_step_id` junto con su log `step_transition` en una sola operacion atomica
- Riesgos arquitectonicos actualizados:
  - el riesgo principal de estado parcial entre proceso y timeline queda reducido en los entry points principales del modulo
  - la logica de negocio sigue residiendo en `Process_Service`; no se debe migrar a repositories fuera de la frontera transaccional

- Ajuste final de cierre:
  - `Process_Transaction_Repository` valida `START TRANSACTION` y `COMMIT`, y ejecuta `ROLLBACK` seguro en fallos

## Actualizacion Fase 15. Sistema de pagos

- Integracion real consolidada:
  - `sm_payments` queda como unica fuente de verdad financiera para validacion, saldo y resumen de cobranza
  - `Invoice_Service` endurece el registro y la edicion de pagos para no exceder el saldo disponible de la invoice
  - `Invoice_Service` expone `get_invoice_payment_summary()` con exclusion opcional de `payment_id` para validar ediciones sin doble conteo
  - el modulo `Invoices` mantiene sus estados internos (`draft`, `issued`, `partially_paid`, `paid`, `overdue`, etc.) y expone adicionalmente un estado visible de cobranza (`pending`, `partial`, `paid`)
  - `Invoice_Admin_Controller` muestra estado de factura y estado de pago por separado en el detalle del proceso
  - `Report_Repository` agrega datasets reutilizables para estado de cobro de invoices e ingresos basicos por periodo
  - el estado de cobro agregado y el saldo pendiente de reportes se calculan dinamicamente desde pagos agregados por invoice
  - `Report_Service` incorpora esos datasets al bloque financiero sin crear un modulo paralelo
- Riesgos arquitectonicos actualizados:
  - el estado de cobro visible no debe confundirse con el estado operativo interno de la invoice
  - `amount_paid` y `balance_due` en `sm_invoices` siguen existiendo por compatibilidad, pero no deben volver a usarse como fuente primaria de decision
  - cualquier futura pasarela de pago debe seguir delegando reglas de saldo al service y no introducir SQL fuera de repositories

## Actualizacion Fase 22. Reportes operativos y financieros avanzados

- Integracion real consolidada:
  - `Report_Repository` agrega agregados avanzados para estados derivados, aging de invoices, pagos por metodo, top clientes y actividad operativa agregada
  - `Report_Service` mantiene la orquestacion del modulo por bloques y amplia filtros con `derived_status`, `currency` y `payment_method`
  - `Report_Admin_Controller` expone tablas admin nuevas para analitica operativa y financiera reutilizable
- Riesgos arquitectonicos actualizados:
  - el modulo `Reports` sigue siendo admin-only y no debe absorber logica de dashboard operativo vivo
  - los derivados operativos del modulo deben seguir siendo lectura agregada y no una segunda fuente de verdad
