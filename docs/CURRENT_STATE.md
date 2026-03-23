# CURRENT STATE

## Proyecto

- Proyecto: Super Mechanic
- Version interna actual:
  - plugin: `0.1.0`
  - schema: `1.9.0`
- Estado general del sistema: funcional a nivel base para operacion admin y portal cliente, estable con riesgos; la arquitectura activa sigue en `includes/*`, la capa documental segura sigue operativa y ahora tambien cubre comprobantes de pago logicos por `payment_id` sin persistencia fisica, mientras `Reports` ya cubre la base operativa 12A, la base financiera 12B, la consolidacion 12C y la capa de reportes avanzados base 12D sin cambios de schema.
- Actualizacion Fase 14: auditoria funcional basada en escenarios completada a nivel de codigo, con endurecimiento minimo de visibilidad en actividad reciente del portal cliente y clasificacion real de escenarios en `docs/TEST_SCENARIOS.md`.
- Ajuste 14B: escenarios criticos 7, 8 y 14 corregidos con cambios minimos sobre `quotes` y `attachments`; el escenario 4 queda alineado documentalmente con el flujo real de `processes`.
- Estado de cierre: Fase 17 base de control de acceso, visibilidad y ownership aplicada sobre el schema `1.9.0`, sin cambios de schema y sin impacto en bootstrap.

## Fases implementadas

- Fase 1. Esqueleto del plugin
- Fase 2. Datos maestros
- Fase 3. Motor de procesos
- Fase 4. Mantenimiento
- Fase 6. Paperwork
- Fase 8. Portal cliente
- Fase 10. Communication / Comments / Notifications

## Fases parciales

- Fase 5. Compra / pre-entrega
- Fase 7. Integracion WooCommerce
- Fase 9. PDFs / reportes / auditoria

## Subfases tecnicas recientes cerradas

- Fase 11A. PDF real de invoices
- Fase 11B. PDF real de quotes
- Fase 11C. Descarga segura de PDFs y documentos
- Fase 11D. Abstraccion final de document / PDF service
- Audit P12-R. Auditoria corta de integridad pre-Fase 12
- Refactor B-R. Encapsulacion transaccional real de invoices
- Fix D-R. Descarga segura de attachments en portal cliente

## Fase actual en desarrollo

- Fase operativa actual: cierre operativo posterior a Fase 20B.
- Bloques funcionales pendientes mas claros:
  - auditoria avanzada
  - firma digital
  - almacenamiento externo
  - consolidacion o descarte definitivo de capas legacy

## Modulos activos

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
- Reports
- Quotes
- Invoices
- Payments
- Attachments / Timeline
- Communication / Notifications
- Documents / PDF / Secure Downloads
- Client Portal

## Modulos parciales

- REST API: placeholder y clases legacy no conectadas al bootstrap real.
- WooCommerce integration: scaffold tecnico, sin flujo funcional integrado.
- Process Logs: trazabilidad base presente, auditoria avanzada pendiente.
- Documents / PDF: `Document_Service` ya orquesta resolucion, acceso y payload de descarga para invoices, quotes y attachments; `PDF_Service` queda especializado en PDF y `Download_Service` en streaming seguro.
- Placeholders raiz: `includes/class-rest-api.php`, `includes/class-assets.php`, `includes/class-hooks.php` y `includes/class-post-types.php` existen como piezas no activas / legacy de compatibilidad.

## Modulos legacy o no activos en bootstrap

- `includes/modules/*`
- `includes/class-rest-api.php`
- `includes/integrations/woocommerce/*`

## Tablas criticas

- `sm_clients`
- `sm_client_vehicles`
- `sm_processes`
- `sm_process_step_logs`
- `sm_quotes`
- `sm_quote_items`
- `sm_invoices`
- `sm_invoice_items`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

## Ultimos cambios tecnicos relevantes confirmados

- El bootstrap real usa `includes/class-plugin.php` como composition root.
- El schema actual confirmado es `1.9.0`.
- `includes/modules/*` no forma parte del flujo activo.
- `includes/class-rest-api.php` sigue siendo placeholder.
- Quotes e invoices siguen dependiendo del contexto del proceso en admin.
- Se implementaron y cablearon en bootstrap:
  - `PDF_Service` reusable para invoices y quotes
  - `Document_Service` reusable para documentos protegidos
  - `Download_Service` reusable para descargas seguras
  - descarga admin segura de quote PDF
  - descarga cliente segura de invoice PDF
  - descarga cliente segura de quote PDF
  - descarga cliente segura de attachments visibles
- `Attachment_Service` ahora resuelve archivo local descargable y nombre de descarga consistente sin cambiar schema.
- Las subfases 11A, 11B y 11C no modificaron tablas; reutilizan ownership y visibilidad existentes.
- La subfase 11D tampoco modifica tablas; consolida la capa documental sobre el schema `1.9.0`.
- Refactor A extrae el SQL de `Dashboard_Service` hacia `Process_Repository` mediante `Process_Service`, sin cambios de schema ni de controllers.
- Refactor B-R crea `Invoice_Transaction_Repository` y mueve ahi la frontera transaccional de `create_invoice_from_quote()`, sin romper la API publica de `Invoice_Service`.
- Fix D-R elimina el uso directo de `file_url` en el dashboard cliente y endurece el shortcode de documentos del proceso para renderizar solo enlaces seguros via `Download_Service`.
- La auditoria corta pre-Fase 12 confirma:
  - `Process_Service` ya resuelve `flow_id` y `current_step_id` validos antes de persistir
  - `sm_process_step_logs` ya recibe escritura operativa real de inicializacion, transicion y cambio de estado
  - `Dashboard_Service` ya opera sin SQL directo
  - `Invoice_Service` mantiene compatibilidad funcional tras mover la frontera transaccional a `Invoice_Transaction_Repository`
  - portal cliente y shortcodes documentales siguen usando la arquitectura segura de descarga
- Fase 12C consolida `Reports` sin tocar bootstrap ni schema:
  - `Report_Service` centraliza filtros compartidos y separa bloques operativos y financieros
  - `Report_Admin_Controller` separa la UI admin de reportes por bloque
  - la exportacion CSV admin queda limitada a `recent_processes`, `recent_quotes`, `recent_invoices` y `recent_payments`
  - los listados recientes del modulo quedan acotados por limites explicitos
- Fase 12D amplía `Reports` sin tocar bootstrap ni schema:
  - `Report_Repository` agrega comparativas base de procesos, quotes, invoices y payments por rango
  - `Report_Service` calcula periodo actual vs periodo anterior equivalente solo cuando existe rango completo y expone un bloque `advanced`
  - cuando el rango es parcial o el baseline no es comparable, la variacion porcentual se renderiza como `N/A`
  - `Report_Service` agrega resumen ejecutivo simple con metricas de alto nivel
  - `Report_Admin_Controller` agrega la seccion `Reportes avanzados base` separada de operativo y financiero
  - el modulo sigue admin-only, sin charts y sin evolucionar a dashboard paralelo

## Actualizacion Fase 15. Sistema de pagos

- Estado: implementada como cierre funcional del bloque de pagos sobre invoices existentes.
- Componentes reales ampliados:
  - `includes/invoices/class-invoice-service.php`
  - `includes/invoices/class-invoice-admin-controller.php`
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - `sm_payments` queda como unica fuente de verdad para validacion, saldo y resumen de cobranza
  - validacion de pago contra saldo pendiente disponible al crear o actualizar pagos
  - exclusion del `payment_id` actual al editar pagos para evitar doble conteo
  - rechazo de montos mayores al saldo y de montos menores o iguales a cero
  - resumen reutilizable de cobranza por invoice con estados visibles `pending`, `partial` y `paid`
  - UI admin de invoices ampliada con estado de pago visible y metodos de pago acotados
  - reportes financieros ampliados con estado de cobro de invoices e ingresos basicos por periodo sobre `payment_date`
  - `amount_paid` y `balance_due` quedan como cache legado de compatibilidad y no como fuente primaria de decision
- Cambios de schema:
  - ninguno

## Actualizacion Fase 16. Automatizaciones y eventos operativos

- Estado: implementada como endurecimiento minimo del bus interno de eventos y de las automatizaciones operativas del sistema.
- Componentes reales ampliados:
  - `includes/communication/class-event-dispatcher.php`
  - `includes/communication/class-notification-service.php`
  - `includes/processes/class-process-service.php`
  - `includes/quotes/class-quote-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/attachments/class-process-timeline-service.php`
- Capacidades reales implementadas:
  - catalogo operativo ampliado con `process_created`, `process_step_changed`, `process_finalized`, `quote_created_from_maintenance`, `quote_cancelled`, `invoice_created_from_quote` e `invoice_paid`
  - dispatch consistente despues de persistencia exitosa y despues de commit en flujos transaccionales ya existentes
  - `Notification_Service` amplía cobertura sin mover reglas de negocio desde los services de dominio
  - la timeline consolidada tipa mejor quotes e invoices segun estado real y estado de cobranza agregado
  - `process_updated` deja de usarse para creacion o cambios de paso/estado, reduciendo duplicidad de eventos
- Cambios de schema:
  - ninguno

## Actualizacion Fase 17. Control de acceso, visibilidad y ownership

- Estado: completada como endurecimiento transversal del ownership y visibilidad sin cambios de schema.
- Componentes reales ampliados:
  - `includes/helpers/class-access-control-service.php`
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
- Capacidades reales implementadas:
  - centralización base del ownership en `Access_Control_Service`
  - resolución reusable de `client_id` por usuario WordPress
  - distinción explícita entre acceso total staff/admin y acceso restringido para cliente o mecánico
  - validación central de acceso para `vehicle`, `process`, `quote`, `invoice` y `attachment`
  - `Dashboard_Service` deja de ser la fuente primaria del ownership y delega en la capa central
  - quotes e invoices del portal cliente añaden filtrado por usuario además del filtro por `client_id`
  - comments y notifications validan acceso sobre proceso/objeto además del destinatario
  - `Document_Service` y `Download_Service` conservan compatibilidad con la política central porque delegan acceso documental en los services endurecidos de `invoice`, `quote` y `attachment`
- Cambios de schema:
  - ninguno

## Actualizacion Fase 18. Portal mecanico real

- Estado: implementada como ampliacion operativa del panel mecanico sobre la arquitectura activa.
- Componentes reales ampliados:
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
  - `includes/class-plugin.php`
  - `includes/attachments/class-process-timeline-service.php`
- Capacidades reales implementadas:
  - listado operativo de procesos accesibles para mecanico sobre la politica actual del sistema
  - filtros basicos por tipo y estado dentro del portal mecanico
  - detalle operativo del proceso con resumen, timeline, comentarios, adjuntos y ficha de mantenimiento cuando aplica
  - cambio controlado de `status` mediante `Process_Service::update_process()`
  - cambio controlado de `current_step_id` mediante `Process_Service::update_current_step()`
  - registro de nota tecnica interna mediante `Comment_Service::create_comment()`
  - descarga de adjuntos del proceso desde la ruta segura comun
  - wiring admin del portal mecanico con hooks propios sin romper dashboard admin, portal cliente ni bootstrap
- Ajustes tecnicos adicionales:
  - correccion minima de tipado/event label en `Process_Timeline_Service` para sostener la timeline operativa del portal
- Cambios de schema:
  - ninguno

## Actualizacion Fase 19. Workflow operativo configurable avanzado

- Estado: implementada como endurecimiento minimo del motor `flows + steps + processes` sin crear arquitectura paralela ni cambiar schema.
- Componentes reales ampliados:
  - `includes/flows/class-flow-step-service.php`
  - `includes/processes/class-process-service.php`
- Capacidades reales implementadas:
  - validacion lineal de transiciones entre pasos activos por `step_order`
  - bloqueo de saltos arbitrarios a pasos no adyacentes dentro del mismo flujo
  - reutilizacion de `Flow_Step_Service` como fuente de verdad de alcanzabilidad minima entre pasos
  - validacion de la misma regla tanto en `update_current_step()` como en `update_process()` cuando cambia `current_step_id`
  - sincronizacion minima de `current_step_id` y `status` al entrar en un paso final, forzando `completed` cuando el proceso aun no estaba en estado terminal
  - bloqueo del cambio simple de paso desde un proceso finalizado hacia un paso no final
- Cambios de schema:
  - ninguno

## Actualizacion Fase 20. Automatizacion documental y estados derivados

- Estado: implementada como automatizacion minima y segura sobre la arquitectura existente.
- Componentes reales ampliados:
  - `includes/helpers/class-document-service.php`
  - `includes/communication/class-event-dispatcher.php`
  - `includes/processes/class-process-derived-state-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/dashboard/class-dashboard-service.php`
  - `includes/dashboard/class-client-dashboard-controller.php`
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
  - `includes/class-plugin.php`
- Capacidades reales implementadas:
  - disponibilidad documental automatica no persistente para `quote_approved` e `invoice_issued`
  - sin generacion automatica de attachments o archivos fisicos redundantes
  - `invoice_paid` conserva notificacion y coherencia financiera sobre la misma capa documental comun
  - estados derivados de proceso seguros para `waiting_approval`, `waiting_payment`, `ready_for_delivery` y `completed`
  - `ready_for_delivery` solo se deriva desde `pre_delivery.delivery_ready = 1`
  - el estado visible de cobranza de invoices se expone de forma reusable para dashboard y portal
- Cambios de schema:
  - ninguno

## Actualizacion Fase 20B. Comprobante de pago documental

- Estado: implementada como cierre de la deuda documental pendiente sobre pagos.
- Componentes reales ampliados:
  - `includes/helpers/class-document-service.php`
  - `includes/helpers/class-pdf-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/communication/class-event-dispatcher.php`
- Capacidades reales implementadas:
  - `payment_receipt` como tipo documental logico unico por `payment_id`
  - generacion PDF bajo demanda sin persistir archivos fisicos
  - sin creacion automatica de attachments
- acceso documental del comprobante resuelto por acceso a la invoice asociada
- disponibilidad logica del receipt preparada desde `payment_registered` e `invoice_paid` sin duplicacion de artefactos
- Cambios de schema:
  - ninguno

## Actualizacion Fase 21. Configuracion avanzada por taller / negocio

- Estado: completada como capa transversal minima de configuracion por `wp_options`, sin cambios de schema ni UI pesada.
- Componentes reales ampliados:
  - `includes/helpers/class-settings-service.php`
  - `includes/class-plugin.php`
  - `includes/processes/class-process-service.php`
  - `includes/invoices/class-invoice-service.php`
  - `includes/quotes/class-quote-service.php`
- Capacidades reales implementadas:
  - storage central en option `sm_settings`
  - grupos normalizados `business`, `process`, `financial` y `notifications`
  - defaults seguros con preservacion del comportamiento actual
  - fallback minimo a settings legacy existentes para `business_name` y `currency`
  - `Process_Service` respeta `allow_step_back` y `auto_complete_on_final_step`
  - `Invoice_Service` respeta `allow_partial_payments` y reutiliza moneda/nombre del negocio desde la capa central
  - `Quote_Service` reutiliza moneda/nombre del negocio desde la capa central
  - validacion PHP confirmada para `includes/helpers/class-settings-service.php` y `includes/quotes/class-quote-service.php`
- Cambios de schema:
  - ninguno

## Riesgos actuales del sistema

- Alta concentracion de logica de integracion en `Process_Admin_Controller`.
- Riesgo de confusion por arquitectura duplicada entre `includes/*` y `includes/modules/*`.
- Ausencia de REST funcional a pesar de existir scaffolding.
- La deuda transaccional principal ya no está en el flujo base de `processes`, pero sí quedan operaciones sin frontera transaccional dedicada en `relations` y `flows`.
- persisten riesgos residuales del modulo `processes`, pero la creacion, actualizacion y cambio directo de paso ya no dejan estados parciales entre `sm_processes` y `sm_process_step_logs` en el flujo principal implementado en 13
- Dependencia fuerte del ownership cliente-vehiculo para seguridad del portal cliente.
- La capa documental depende de un motor PDF compatible instalado en el entorno.
- Cualquier renderer frontend nuevo que vuelva a usar `file_url` directo puede romper el modelo de descarga segura ya consolidado.
- WooCommerce existe solo como scaffolding tecnico.
- La documentacion puede desincronizarse si no se actualiza al cierre de cada fase o subfase.
- `Report_Service` y `Report_Admin_Controller` siguen siendo puntos a vigilar si el modulo `Reports` crece.
- los placeholders raiz pueden inducir a error si se documentan como arquitectura operativa.
- `Document_Service`, `Download_Service` y algunos entry points cliente siguen requiriendo vigilancia continua para asegurar que toda descarga protegida termine usando la politica central sin checks divergentes.
- la exposicion visual del nuevo `payment_receipt` todavia no tiene entry points UI dedicados en admin ni shortcodes cliente

## Pendientes inmediatos

- Mantener sincronizados los seis documentos tecnicos base.
- mantener alineados los limites, vistas exportables y bloques comparativos del modulo `Reports` si se amplian subfases futuras
- evaluar si conviene introducir repositories transaccionales dedicados para `relations` y `flows`
- Revisar si conviene consolidar la salida documental segura en mas entry points cliente/admin sin duplicar links publicos.
- Evitar introducir nuevos modulos en `includes/modules/*` sin una decision de consolidacion.
- Validar cualquier avance futuro contra bootstrap y schema reales antes de marcarlo como implementado.

## Actualizacion Fase 12A. Reportes base operativos

- Estado: implementada como base inicial de la Fase 12.
- Modulo activo nuevo:
  - Reports
- Componentes reales implementados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
  - pantalla admin `Super Mechanic -> Reportes`
  - filtros por fechas, estado y tipo para reportes base
  - reportes recientes de procesos, mantenimientos, clientes y vehiculos
- Cambios de schema:
  - ninguno

## Actualizacion Fase 12B. Reportes financieros base

- Estado: implementada como extension limpia del modulo `Reports`.
- Componentes reales ampliados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - quotes por estado
  - quotes recientes
  - invoices por estado
  - invoices recientes
  - payments recientes
  - total facturado por rango de fechas agrupado por moneda
  - total cobrado por rango de fechas agrupado por moneda
  - balance pendiente total por rango de fechas agrupado por moneda
- Filtros reales agregados:
  - `date_from`
  - `date_to`
  - `quote_status`
  - `invoice_status`
- Criterio temporal actual:
  - `total facturado` usa `sm_invoices.created_at` como referencia operativa en 12B
- Cambios de schema:
  - ninguno

## Actualizacion Fase 12C. Consolidacion del modulo Reports

- Estado: implementada como consolidacion limpia del modulo `Reports`.
- Componentes reales ampliados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - filtros compartidos consolidados para reportes operativos y financieros
  - separacion clara de bloques operativos y financieros en admin UI
  - exportacion CSV admin segura de `recent_processes`, `recent_quotes`, `recent_invoices` y `recent_payments`
  - limite configurable y acotado para listados recientes del modulo
- Cambios de schema:
  - ninguno

## Actualizacion Fase 12D. Reportes avanzados base

- Estado: implementada como extension limpia del modulo `Reports`.
- Componentes reales ampliados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - comparativa de procesos por rango de fechas
  - comparativa de quotes por rango de fechas
  - comparativa de invoices por rango de fechas
  - comparativa de payments por rango de fechas
  - comparacion entre periodo actual y periodo anterior equivalente cuando el rango principal esta completo
  - resumen ejecutivo simple con metricas de alto nivel
  - seccion admin separada para `Reportes avanzados base`
  - agrupaciones avanzadas reutilizables de procesos por estado y tipo, y de quotes e invoices por estado
- Cambios de schema:
  - ninguno

## Actualizacion Fase 12E. Endurecimiento / Performance / Task Files de Reports

- Estado: implementada como consolidacion tecnica y documental de la Fase 12.
- Componentes reales ampliados:
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - consolidacion de limites recientes alrededor de `Report_Repository`
  - lectura endurecida de filtros admin antes de validar
  - estado vacio controlado para comparativas monetarias sin datos comparables
  - cierre de trazabilidad documental con task files de `12B`, `12D` y `12E`
- Cambios de schema:
  - ninguno

## Actualizacion Fase 13. Integridad transaccional y endurecimiento del nucleo

- Estado: implementada como endurecimiento del modulo `Processes`.
- Componentes reales ampliados:
  - `includes/processes/class-process-service.php`
  - `includes/processes/class-process-transaction-repository.php`
- Capacidades reales implementadas:
  - atomicidad basica para `create_process()` y su log `step_initialized`
  - atomicidad basica para `update_process()` y sus logs asociados
  - atomicidad basica para `update_current_step()` y su `step_transition`
  - dispatch de eventos solo despues de persistencia exitosa
  - validacion real de `START TRANSACTION` y `COMMIT`, con `ROLLBACK` seguro en fallo
- Cambios de schema:
  - ninguno

## Actualizacion Fase 22. Reportes operativos y financieros avanzados

- Estado: implementada como ampliacion avanzada del modulo `Reports` sin crear arquitectura paralela.
- Componentes reales ampliados:
  - `includes/reports/class-report-repository.php`
  - `includes/reports/class-report-service.php`
  - `includes/reports/class-report-admin-controller.php`
- Capacidades reales implementadas:
  - procesos por estado derivado
  - matriz de procesos por tipo y estado
  - procesos finalizados por período
  - procesos listos para entrega por período
  - tiempos básicos de transición por tipo de proceso
  - actividad reciente relevante agregada
  - aging simple de invoices
  - pagos por método
  - top clientes por facturación
  - top clientes por cobro
  - filtros ampliados con `derived_status`, `currency` y `payment_method`
- Cambios de schema:
  - ninguno
