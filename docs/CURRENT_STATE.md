# CURRENT STATE — SUPER MECHANIC

Version: 0.1.0
Schema: 1.14.0

Estado:
Arquitectura estable con riesgos controlados

Módulos activos:
clients
vehicles
processes
maintenance
predelivery
paperwork
quotes
invoices
attachments
communication
dashboard
appointments

Sistema:
Funcional (pre-SaaS)

PRE-FASE 27 STATUS:
- COMPLETO

SUBFASE 5-6 STATUS:
- COMPLETO

SUBFASES 7-9 STATUS:
- COMPLETO

SUBFASES 10-13 STATUS:
- COMPLETO

SUBFASES 14-16 STATUS:
- COMPLETO

Problemas actuales:
- Smoke test real ejecutado en runtime WordPress local el 2026-03-27
- No se detectaron bloqueadores críticos confirmados del plugin en dashboard, clientes, vehículos, procesos, comunicación, invoices ni portal mecánico
- Adjuntos cerrados como `OK en browser-admin / fallo solo CLI`: el mensaje `Specified file failed upload test.` proviene de la validación `is_uploaded_file()` de WordPress core cuando la prueba se ejecuta fuera de una subida HTTP real
- Descarga PDF de invoices sigue sin validación por ausencia de motor PDF activo en el entorno
- Subfases 5-6 revalidadas en browser-admin real: relación cliente ↔ vehículo consistente, autoselección cliente/vehículo operativa en procesos, historial de vehículo con fechas clave visible, comunicación/timeline operativos y adjuntos funcionales en upload + descarga segura
- UX todavía incompleta en módulos secundarios, pero el bloque previo a Fase 27 corrigió navegación útil del dashboard admin, vistas `Ver` en clientes y vehículos, acciones clave de procesos e invoices y consistencia base del panel mecánico
- Campos de expiración de vehículos siguen fuera del runtime activo actual
- Revalidación runtime WordPress real ejecutada el 2026-03-27 sobre SUBFASES 7-9: invoice manual sin quote, impuestos/descuentos `percent/fixed`, pagos sobre `sm_payments`, transición real a `paid`, descarga segura de adjuntos desde `uploads`, bloqueo de rutas arbitrarias y ownership/visibilidad cliente sobre invoice + attachment
- `sm_payments` queda definitivamente confirmado como modelo único real para validación, saldo y estado visible de cobranza; `Invoice_Transaction_Repository` queda acotado al boundary transaccional de `create_invoice_from_quote()`, no como ledger financiero
- `invoice_pdf` y `payment_receipt` quedan `N/A` en esta validación por ausencia de motor PDF activo en el entorno, sin bloquear el cierre funcional del bloque
- Revalidación runtime WordPress real ejecutada el 2026-03-27 sobre SUBFASES 10-13: portal cliente OK, shortcodes cliente OK, shortcodes mecánicos frontend OK, acciones reales de mecánico OK y enforcement admin/mechanic/client OK
- Se consolidó una capa reutilizable `Permission_Service` para portal cliente, portal mecánico frontend y shortcodes activos, reutilizando ownership real en `Access_Control_Service`
- El portal cliente quedó operativo con navegación estructurada, ownership estricto y continuidad real sobre procesos, vehículos, documentos, quotes e invoices
- El runtime expone shortcodes mecánicos frontend operativos y validados: `sm_mechanic_dashboard` y `sm_mechanic_processes`
- Se corrigieron fallos reales detectados durante la revalidación final: warning CLI por `REQUEST_METHOD` no definido en shortcodes/controladores frontend, fatal frontend por uso de `submit_button()` fuera de admin y desalineación entre listado mecánico y enforcement (`maintenance.mechanic_id` vs `assigned_to`)
- `payment_receipt` queda `N/A` en esta revalidación por ausencia de motor PDF activo en el entorno, sin bloquear el cierre funcional del bloque
- `sm_public_tracking` no se activó en este bloque: no existe hoy un token público no predecible ni un mecanismo equivalente en la arquitectura activa que permita tracking sin exponer datos sensibles o depender de IDs internos; queda como restricción operativa explícita y no bloquea el cierre funcional actual

Siguiente fase:
FASE 27 API — habilitada para preparación e implementación controlada, con SUBFASES 14-16 ya cerradas

Decisiones:
- API scope limitado (no full CRUD)
- No multi-tenant aún (solo preparación)
- Antes de abrir la API se consolidó un bloque de estabilización previa sobre dashboard, clientes, vehículos, procesos, invoices y panel de shortcodes sin tocar schema
- El bloque SUBFASES 10-13 queda cerrado como `COMPLETO` a nivel funcional sin abrir schema
- `sm_public_tracking` sigue explícitamente fuera del cierre funcional actual hasta que exista un mecanismo público seguro
- SUBFASES 14-16 quedan cerradas como `COMPLETO`: la pantalla de ajustes ya sincroniza la opción legacy con `sm_settings`, se cargó un dataset QA reproducible por script y se consolidó una base documental mínima pre-API
- La preparación de negocio sigue en modo single-business: se añadió `business_context_key` como clave estable de contexto futuro, pero no existe multi-tenant real ni `business_id` operativo en runtime
- El dataset QA reproducible quedó validado el 2026-03-27 en runtime WordPress real vía `scripts/seed-qa-dataset.php` con creación consistente de clientes, vehículos, procesos, quote, invoice, pagos, adjunto, comentarios y notificaciones
- La base de internacionalización queda operativa a nivel de `Text Domain` y carga de traducciones; el runtime mantiene todavía cadenas históricas mixtas, por lo que la convergencia completa de idioma sigue como deuda controlada y no bloquea Fase 27
- Ya se puede pasar a FASE 27 siempre que se mantengan explícitas las restricciones vigentes: `sm_public_tracking` sigue fuera por seguridad y la validación PDF continúa condicionada a disponer de un motor PDF activo en el entorno

Actualización FASE 27A (API base segura):
- Fecha de implementación: 2026-03-28
- Estado FASE 27A: COMPLETO
- Se habilitó API REST activa en arquitectura real `includes/*` con un único controller:
  - `includes/dashboard/class-client-rest-controller.php`
- Endpoints read-only implementados para cliente autenticado:
  - procesos propios + detalle
  - vehículos propios + detalle
  - quotes propias + detalle
  - invoices propias + detalle
- Seguridad aplicada:
  - autenticación WordPress obligatoria
  - `Permission_Service` para acceso de portal cliente
  - ownership estricto con `Access_Control_Service`
  - sin exposición de `file_url` ni rutas de descarga
- Restricciones mantenidas:
  - sin cambios de schema
  - sin apertura de 27B/27C
  - `sm_public_tracking` sigue fuera por seguridad
- Comentarios en API:
  - excluidos deliberadamente de 27A para mantener alcance mínimo y payload estable

Siguiente fase:
- 27B puede prepararse, sujeto a definición explícita de acciones write y contratos de validación.

Actualización FASE 27B (API interna admin):
- Fecha de implementación: 2026-03-28
- Estado FASE 27B: COMPLETO
- Se habilitó API REST interna admin en arquitectura real `includes/*` con un único controller:
  - `includes/dashboard/class-admin-rest-controller.php`
- Endpoints read-only implementados para admin autenticado:
  - listados filtrados + detalle de procesos
  - listados filtrados + detalle de vehículos
  - listados filtrados + detalle de clientes
  - listados filtrados + detalle de quotes
  - listados filtrados + detalle de invoices
- Seguridad aplicada:
  - autenticación WordPress obligatoria
  - capability admin estricta `sm_manage_plugin`
  - filtros sanitizados y acotados (`per_page`, `page`, `search`, `orderby`, `order` + filtros por recurso)
  - payload read-only consistente sin exposición de `file_url` ni rutas documentales
- Restricciones mantenidas:
  - sin writes (sin create/update/delete por API)
  - sin cambios de estado por API
  - sin acciones de pagos ni documentos por API
  - sin cambios de schema
  - sin apertura de 27C ni API pública

Siguiente fase:
- 27C puede prepararse, manteniendo separación estricta entre API interna admin y superficie pública.

Actualización FASE 27C-A (expansión controlada read-only):
- Fecha de implementación: 2026-03-28
- Estado FASE 27C-A: COMPLETO
- Se amplió cobertura de filtros en API cliente y API admin interna (sin writes) para listados de:
  - procesos
  - vehículos
  - clientes
  - quotes
  - invoices
- Filtros añadidos y estandarizados según recurso:
  - `search`, `status`, `type`, `per_page`, `page`, `orderby`, `order`
  - `date_from` y `date_to` con soporte real en repositories activos
- Respuesta de listados consolidada de forma compatible:
  - `items`
  - `count` (compatibilidad hacia atrás)
  - `page`
  - `per_page`
  - `total`
  - `total_pages`
- Payloads normalizados de forma no destructiva en:
  - processes
  - vehicles
  - clients
  - quotes
  - invoices
- Seguridad y restricciones mantenidas:
  - sin create/update/delete por API
  - sin approve/reject/send/cancel por API
  - sin create invoice from quote por API
  - sin cambios de estado por API
  - sin pagos por API
  - sin documentos/descargas por API
  - sin API pública
  - sin `sm_public_tracking`
  - sin cambios de schema
- Arquitectura respetada:
  - Controller → Service → Repository
  - SQL exclusivamente en repositories (`includes/*`)

Siguiente fase:
- Base lista para evaluar 27C-B (acciones mínimas internas) o pasar a FASE 28 según prioridad de roadmap.

Actualización FASE 27C-B (acciones internas mínimas y seguras):
- Fecha de implementación: 2026-03-28
- Estado FASE 27C-B: COMPLETO
- Se habilitaron únicamente 2 acciones write internas admin en `includes/*`:
  - `POST /super-mechanic/v1/admin/processes/{id}/status`
  - `POST /super-mechanic/v1/admin/processes/{id}/internal-comment`
- Reutilización de servicios reales:
  - cambio de estado por `Process_Service::update_process()`
  - comentario interno por `Comment_Service::create_comment()`
- Seguridad aplicada:
  - autenticación WordPress obligatoria
  - capability admin estricta `sm_manage_plugin`
  - enforcement operativo adicional `sm_manage_processes` para writes de procesos
  - request sanitizado y validado con args REST explícitos
  - cambio de estado con payload mínimo (`status` únicamente)
  - comentario interno forzado a:
    - `is_internal = 1`
    - `is_client_visible = 0`
  - sin posibilidad de override de visibilidad por request
- Restricciones mantenidas:
  - sin cambio de paso por API
  - sin pagos por API
  - sin documentos/descargas por API
  - sin `create_invoice_from_quote` por API
  - sin `approve_quote` / `reject_quote` por API
  - sin CRUD amplio por recurso
  - sin API pública
  - sin `sm_public_tracking`
  - sin cambios de schema
- Arquitectura respetada:
  - Controller → Service → Repository
  - SQL exclusivamente en repositories (`includes/*`)

Siguiente fase:
- Base lista para pasar a FASE 28, manteniendo las restricciones de seguridad y alcance ya consolidadas.

Actualización FASE 28 (Centro financiero admin):
- Fecha de implementación: 2026-03-28
- Estado FASE 28: COMPLETO
- Se consolidó un centro financiero admin dedicado en arquitectura activa `includes/*` con dos paneles:
  - `Super Mechanic -> Finanzas: Invoices`
  - `Super Mechanic -> Finanzas: Payments`
- Implementación activa:
  - `includes/invoices/class-invoice-finance-admin-controller.php`
  - `includes/invoices/class-payment-finance-admin-controller.php`
  - `includes/invoices/class-invoice-finance-list-table.php`
  - `includes/invoices/class-payment-finance-list-table.php`
- Relación financiera consolidada:
  - invoice ↔ payments visible en ambos paneles
  - estado de cobro visible por invoice desde `Invoice_Service`:
    - `pending`
    - `partial`
    - `paid`
- UI financiera consolidada por invoice:
  - `subtotal`
  - `tax_total`
  - `discount_total`
  - `grand_total`
- Acciones admin disponibles en centro financiero:
  - abrir invoice (contexto proceso/tab invoice existente)
  - registrar pago (sobre flujo actual existente)
  - descargar invoice PDF cuando hay motor disponible
  - ver `payment_receipt` por flujo seguro
- Seguridad y alcance mantenidos:
  - sin exposición de `file_url`
  - descargas por `Download_Service` + `Document_Service`
  - sin API pública financiera
  - sin reportes avanzados nuevos
  - sin cambios de schema
  - sin romper `Process_Admin_Controller`/tab `invoice`
- Arquitectura respetada:
  - Controller -> Service -> Repository
  - SQL solo en repositories (`Payment_Repository` extendido para listados paginados/filtrados)

Siguiente fase:
- Se puede pasar a FASE 29 con base financiera admin operativa y estable.

Actualización FASE 29 (Reportes expansión):
- Fecha de implementación: 2026-03-28
- Estado FASE 29: COMPLETO
- Se amplió el módulo `includes/reports/*` en dos bloques controlados:
  - FASE 29-A (operativa): procesos por estado, tipo, rango de fecha, mecánico asignado, cliente y vehículo
  - FASE 29-B (financiera): invoices por estado de cobro, payments por rango de fecha, total cobrado, total pendiente y agregados de `subtotal`, `tax_total`, `discount_total`, `grand_total`
- Criterio operativo de mecánico definido y fijo:
  - se usa únicamente `sm_processes.assigned_to`
  - no se mezcla con `sm_maintenance.mechanic_id` en esta fase
- Separación financiera explícita mantenida:
  - `invoice_status` (estado documental de invoice) separado de `invoice_collection_status` (estado de cobranza derivado de pagos)
- Seguridad y alcance mantenidos:
  - sin API pública de reportes
  - sin exposición de `file_url` ni rutas documentales
  - capacidad admin estricta `sm_manage_plugin` en pantalla de reportes
- Arquitectura respetada:
  - Controller -> Service -> Repository
  - SQL solo en `Report_Repository`
  - sin cambios de schema
- Validación técnica:
  - lint PHP completo ejecutado con `scripts/php-lint.php --all` (0 errores)
  - sin validación runtime WordPress browser-admin en este cierre

Siguiente fase:
- Se puede pasar a FASE 30 con la expansión base de reportes ya operativa y sin cambios de schema.

Actualización FASE 30 (Tenancy base preparada, no activada):
- Fecha de implementación: 2026-03-28
- Estado FASE 30: COMPLETO
- Se incorporó una capa central única de contexto de negocio en arquitectura activa `includes/*`:
  - `includes/helpers/class-business-context-service.php`
- Contrato expuesto por la capa:
  - modo fijo `single_business`
  - lectura de `business_context_key` desde `Settings_Service` (`sm_settings`)
  - `business_id` reservado para evolución futura y no operativo en runtime actual
  - `is_tenancy_active = false` de forma explícita
- Wiring mínimo aplicado:
  - `includes/class-plugin.php` inicializa `Business_Context_Service` sin alterar flujos de negocio existentes
- Restricciones mantenidas (sin activación real):
  - sin multi-tenant real
  - sin `business_id` en tablas
  - sin filtros por negocio en repositories
  - sin cambios de enforcement en `Access_Control_Service`
  - sin cambios de schema
- Impacto documental consolidado:
  - se documenta esta fase como preparación arquitectónica pre-SaaS, no como activación tenant-aware

Siguiente fase:
- Se puede pasar a FASE 31 manteniendo `single_business` como modo activo hasta definición explícita de activación tenancy.

Actualización FASE 31A (Base local de licencias):
- Fecha de implementación: 2026-03-28
- Estado FASE 31A: COMPLETO
- Se incorporó la base local de licencias en arquitectura activa `includes/*`:
  - `includes/helpers/class-license-provider-interface.php`
  - `includes/helpers/class-local-license-provider.php`
  - `includes/helpers/class-license-service.php`
- Persistencia consolidada:
  - `wp_options` option `sm_settings`
  - grupo `license` con estado local
- Flujo local implementado:
  - activate
  - validate
  - deactivate
- UI admin implementada en Settings existente:
  - estado visible
  - key enmascarada
  - acciones protegidas por capability + nonce
- Restricciones mantenidas:
  - sin updates privadas
  - sin premium flags
  - sin bloqueo de features
  - sin llamadas remotas reales
  - sin cambios de schema

Siguiente fase:
- Se puede pasar a FASE 31B con contrato local ya preparado para provider externo futuro.

Actualización FASE 31B (Base de updates privadas):
- Fecha de implementación: 2026-03-28
- Estado FASE 31B: COMPLETO
- Se incorporó la base local de updates privadas en arquitectura activa `includes/*`:
  - `includes/helpers/class-update-provider-interface.php`
  - `includes/helpers/class-local-update-provider.php`
  - `includes/helpers/class-update-service.php`
- Integración WordPress nativa aplicada:
  - `pre_set_site_transient_update_plugins`
  - `plugins_api`
  - endpoint seguro para paquete privado:
    - `admin_post_sm_private_update_package`
    - `admin_post_nopriv_sm_private_update_package`
- Persistencia local consolidada:
  - `wp_options` option `sm_settings`
  - grupo `updates` con estado:
    - `provider`
    - `last_check_at`
    - `latest_version`
    - `package_available`
    - `message`
    - `last_result`
  - metadata técnica adicional (interna de runtime):
    - `requires`
    - `tested`
    - `changelog`
    - `package_source_url`
- Seguridad aplicada:
  - `package_url` final de WordPress se emite como URL firmada y temporal
  - validación de firma + expiración + licencia activa en descarga
  - bloqueo de source URL no válida/no permitida
  - sin exposición de `file_url` ni rutas documentales del dominio funcional
- UI admin mínima aplicada en Settings:
  - estado visible de updates privadas (solo lectura)
  - sin acciones premium ni feature gating
- Restricciones mantenidas:
  - sin cambios de schema
  - sin apertura de 31C
  - sin planes/flags premium
  - sin refactor amplio

Siguiente fase:
- Se puede pasar a FASE 31C solo si se define explícitamente el alcance de flags/planes sin romper el baseline seguro de updates privadas.

Actualización FASE 31C (Restricción de features, base centralizada):
- Fecha de implementación: 2026-03-28
- Estado FASE 31C: COMPLETO
- Se incorporó la base centralizada de plan efectivo y feature flags en arquitectura activa `includes/*`:
  - `includes/helpers/class-feature-flags.php`
  - `includes/helpers/class-plan-access-service.php`
- Persistencia local consolidada en `sm_settings` sin cambios de schema:
  - `plan.plan_key`
  - `plan.status`
  - `plan.source`
  - `plan.message`
  - `features.feature_flags`
- Resolución de plan efectivo:
  - centralizada en `Plan_Access_Service::get_effective_plan()`
  - señal local derivada de licencia por `License_Service::get_plan_signal()`
  - preparada para provider futuro vía filtros (`sm_plan_access_effective_plan` y `sm_plan_access_feature_overrides`)
- Resolución de features:
  - centralizada en `Plan_Access_Service::is_feature_enabled()`
  - catálogo único en `Feature_Flags`
  - defaults seguros y retrocompatibles (sin bloqueo de funciones core por defecto)
- Visibilidad admin básica en Settings:
  - nuevo bloque read-only de estado de plan + flags efectivas
- Gating mínimo y no destructivo aplicado en superficies admin no críticas:
  - Reportes admin (`admin_reports`)
  - Export CSV de reportes (`reports_csv_export`)
  - Catálogo admin de shortcodes (`admin_shortcode_catalog`)
- Restricciones mantenidas:
  - sin billing
  - sin suscripciones reales
  - sin cambios de schema
  - sin refactor amplio
  - sin tocar procesos/clientes/vehículos/invoices/payments base
  - sin checks dispersos (check central en `Plan_Access_Service`)

Siguiente fase:
- Se puede pasar a FASE 32, manteniendo la base 31C como capa de acceso central preparada para integración externa futura.

Actualización FASE 32A (Calendario/Citas base operativa):
- Fecha de implementación: 2026-03-28
- Estado FASE 32A: COMPLETO
- Se incorpora módulo activo en arquitectura real `includes/*`:
  - `includes/appointments/class-appointment-repository.php`
  - `includes/appointments/class-appointment-service.php`
  - `includes/appointments/class-appointment-admin-controller.php`
  - `includes/appointments/class-appointment-list-table.php`
- Alcance implementado:
  - CRUD admin de citas
  - relación cita -> cliente
  - relación cita -> vehículo
  - `process_id` opcional
  - asignación de mecánico con criterio único `assigned_to`
  - estados básicos (`scheduled`, `confirmed`, `in_progress`, `completed`, `cancelled`)
  - filtros por fecha / mecánico / estado
  - listado admin usable
- Schema:
  - nueva tabla `sm_appointments`
  - versión schema actualizada a `1.10.0`
- Seguridad y restricciones mantenidas:
  - Controller -> Service -> Repository
  - SQL solo en repository
  - sin automatizaciones
  - sin notificaciones avanzadas
  - sin integraciones externas
  - sin calendario JS complejo
  - sin API de citas

Siguiente fase:
- Se puede pasar a FASE 33, manteniendo 32A como base operativa y sin abrir todavía automatizaciones complejas.

Actualización FASE 32B-1 (Feed ICS/iCal seguro de citas):
- Fecha de implementación: 2026-03-28
- Estado FASE 32B-1: COMPLETO
- Se incorporó feed ICS read-only de citas en arquitectura activa `includes/*`:
  - `includes/appointments/class-appointment-ical-feed-controller.php`
  - `includes/appointments/class-appointment-ical-feed-service.php`
  - `includes/helpers/class-feed-token-service.php`
- Endpoint de consumo:
  - `/?sm_appointments_ical=1&assigned_to={id}&status={status}&date_from={Y-m-d}&date_to={Y-m-d}&expires={unix}&sig={hmac}`
- Seguridad aplicada:
  - token firmado HMAC-SHA256
  - expiración obligatoria (`expires`)
  - firma incluye filtros permitidos (`assigned_to`, `status`, `date_from`, `date_to`)
  - bloqueo de acceso con token inválido/expirado
  - sin token global abierto
- Alcance funcional implementado:
  - exporta fecha/hora, estado, cliente, vehículo, mecánico asignado y notas básicas
  - `UID` estable por cita (`sm-appointment-{id}@{host}`)
  - `VCALENDAR/VEVENT` válido con `text/calendar; charset=utf-8`
  - escape ICS aplicado y líneas plegadas RFC 5545
- Rendimiento y alcance:
  - rango por defecto aplicado si no llegan fechas (`hoy` -> `+30 días`)
  - límite de resultados acotado en feed (máximo 250 por request)
- Restricciones mantenidas:
  - sin OAuth
  - sin Google API
  - sin sync bidireccional
  - sin webhooks
  - sin cambios de schema
  - sin refactor amplio

Siguiente fase:
- Se puede pasar a 32B-2 para capa de generación/admin UX de URLs firmadas y política operativa de rotación/revocación de feeds, sin abrir integraciones externas activas.

Actualización FASE 32B-2 (Google Calendar 1-way sync):
- Fecha de implementación: 2026-03-28
- Estado FASE 32B-2: COMPLETO
- Se incorporó integración Google Calendar en arquitectura activa `includes/*`:
  - `includes/integrations/google-calendar/class-google-calendar-auth-controller.php`
  - `includes/integrations/google-calendar/class-google-calendar-client.php`
  - `includes/integrations/google-calendar/class-google-calendar-service.php`
  - `includes/integrations/google-calendar/class-google-calendar-sync-service.php`
  - `includes/integrations/google-calendar/class-google-calendar-sync-repository.php`
- Persistencia de sync consolidada en tabla separada:
  - `sm_appointment_calendar_sync`
  - sin agregar campos de Google en `sm_appointments`
- Alcance implementado:
  - configuración admin de integración Google Calendar
  - OAuth básico connect/callback/disconnect
  - persistencia segura de configuración/tokens en `sm_settings.google_calendar`
  - creación de evento Google en alta de cita
  - actualización de evento Google al editar cita
  - referencia externa y estado de sync por cita en tabla dedicada
- Seguridad aplicada:
  - no exposición de `client_secret`/`refresh_token` en UI
  - validación de capability + nonce en acciones admin
  - estado OAuth temporal con expiración y usuario asociado
  - sin logging de secretos completos
- Comportamiento no destructivo:
  - si Google falla, la cita local se guarda igual
  - error remoto queda persistido en `sm_appointment_calendar_sync.last_error`
  - estado de sync persistido (`pending`/`synced`/`error`)
- Restricciones mantenidas:
  - sin 2-way sync
  - sin webhooks
  - sin watch channels
  - sin Outlook
  - sin booking público
  - sin SDK externo (HTTP con `wp_remote_*`)

Siguiente fase:
- Se puede pasar a 32B-3 para endurecimiento operativo (reintentos/control de colas/telemetría y reconciliación), manteniendo 1-way sync sin abrir sincronización bidireccional.

Actualización FASE 32B-3A (Reconciliación inbound controlada):
- Fecha de implementación: 2026-03-28
- Estado FASE 32B-3A: COMPLETO
- Se incorporó base inbound controlada sobre integración Google Calendar en arquitectura activa `includes/*`:
  - `includes/integrations/google-calendar/class-google-calendar-inbound-reconcile-service.php`
  - `includes/integrations/google-calendar/class-google-calendar-sync-service.php`
  - `includes/integrations/google-calendar/class-google-calendar-sync-repository.php`
  - `includes/integrations/google-calendar/class-google-calendar-client.php`
  - `includes/integrations/google-calendar/class-google-calendar-service.php`
- Alcance implementado:
  - lectura remota puntual de evento por `external_event_id`
  - remapeo por `external_event_id` en repositorio de sync
  - reconciliación inbound con política explícita de conflictos
  - acción manual admin `Reconcile inbound now` en Ajustes (sin webhooks)
  - resumen de ejecución inbound por estado (`processed`, `synced`, `conflict`, `rejected`, `error`)
- Política de campos inbound aplicada:
  - permitidos: `start_at`, `appointment_date` derivada, `notes` sanitizada/acotada, `appointment_status` solo para `cancelled`
  - prohibidos: `client_id`, `vehicle_id`, `process_id`, `assigned_to`, IDs estructurales, relaciones, `created_at` y datos financieros/documentales
- Política de conflicto aplicada:
  - solo remoto en campos permitidos: aplica inbound
  - local + remoto desde base previa: `conflict`
  - remoto toca campos no permitidos: `rejected`
  - solo local: conserva local y no fuerza merge remoto
- Persistencia y estado:
  - `sm_appointment_calendar_sync.sync_status` ahora contempla flujo operativo de `synced`, `error`, `conflict`, `rejected`
  - sin cambios de schema en 32B-3A
- Restricciones mantenidas:
  - plugin sigue como fuente de verdad
  - sin webhooks
  - sin watch channels
  - sin Outlook
  - sin sync 2-way libre
  - sin cambios de schema
  - sin refactor amplio

Siguiente fase:
- Se puede pasar a 32B-3B para endurecimiento operativo adicional (reintentos/cola/telemetría), manteniendo la reconciliación inbound como capa controlada y sin abrir sincronización 2-way libre.

Actualización FASE 32B-3B (Watch channels / webhooks Google Calendar):
- Fecha de implementación: 2026-03-28
- Estado FASE 32B-3B: COMPLETO
- Se incorporó endurecimiento operativo en arquitectura activa `includes/*`:
  - `includes/integrations/google-calendar/class-google-calendar-webhook-controller.php`
  - `includes/integrations/google-calendar/class-google-calendar-service.php`
  - `includes/integrations/google-calendar/class-google-calendar-client.php`
  - `includes/integrations/google-calendar/class-google-calendar-sync-service.php`
- Endpoint REST dedicado de webhook:
  - `POST /wp-json/super-mechanic/v1/google-calendar/webhook`
- Validaciones aplicadas sobre notificación:
  - `X-Goog-Channel-ID`
  - `X-Goog-Resource-ID`
  - `X-Goog-Channel-Token`
  - `X-Goog-Resource-State` permitido (`sync`, `exists`, `not_exists`)
- Política de seguridad y ejecución:
  - webhook no actualiza `sm_appointments` directamente
  - webhook valida, registra y encola procesamiento
  - reconciliación se dispara usando la lógica controlada de 32B-3A
  - respuesta rápida `2xx` cuando la notificación es válida
- Estado persistido en `sm_settings.google_calendar` (sin schema changes):
  - `watch_channel_id`
  - `watch_resource_id`
  - `watch_resource_uri`
  - `watch_expiration`
  - `watch_token_hash`
  - `watch_last_message_number`
  - `watch_last_webhook_at`
- Idempotencia aplicada:
  - descarte si `message_number <= watch_last_message_number`
  - lock corto por fingerprint `channel_id + message_number`
  - prevención básica de doble ejecución concurrente
- Renovación aplicada:
  - cron preventivo horario (`sm_google_calendar_watch_renew`)
  - renovación automática si expira en < 24h
  - acción manual admin “Renew watch channel now”
  - renovación segura: crear nuevo channel -> persistir estado -> intentar stop del anterior (best effort)
- Restricciones mantenidas:
  - sin Outlook
  - sin sync 2-way libre
  - sin booking público
  - sin cambios de schema
  - sin refactor amplio

Siguiente fase:
- Se puede pasar a FASE 33 manteniendo la política de plugin como fuente de verdad y la reconciliación inbound controlada.

Actualización FASE 33 (Notificaciones avanzadas base centralizada):
- Fecha de implementación: 2026-03-28
- Estado FASE 33: COMPLETO
- Se consolida la expansión del motor existente de notificaciones en arquitectura activa `includes/*` sin rehacer sistema ni cambiar schema:
  - `includes/communication/class-notification-service.php`
  - `includes/communication/class-event-dispatcher.php`
  - `includes/communication/class-notification-event-catalog.php`
  - `includes/communication/class-notification-channel-interface.php`
  - `includes/communication/class-email-notification-channel.php`
- Catálogo centralizado de eventos/triggers y plantillas base:
  - eventos operativos existentes de procesos/quotes/invoices/payments/documentos/comentarios consolidados en catálogo
  - nuevos eventos de citas incorporados al catálogo:
    - `appointment_created`
    - `appointment_updated`
    - `appointment_status_changed`
    - `appointment_cancelled`
    - `appointment_reminder` (preparado para uso futuro)
- Canal externo desacoplado implementado:
  - canal `email` por adaptador `Email_Notification_Channel` usando `wp_mail`
  - envío externo no bloqueante y controlado por metadata de catálogo (`email_enabled`)
  - sin acoplar proveedor SMS ni SDK externo
- Integración de citas con dispatcher central:
  - `Appointment_Service` ahora emite eventos internos `sm_event_appointment_*` en create/update/cambio de estado/cancelación
  - `Event_Dispatcher` registra y enruta handlers de citas al `Notification_Service`
  - se mantiene la política no destructiva de sync Google 1-way + inbound controlado
- Configuración mínima de toggles consolidada:
  - se mantiene `notifications.enable_client_notifications`
  - se agrega `notifications.enable_email_notifications` en settings legacy + `sm_settings`
- Seguridad y restricciones mantenidas:
  - canal in-app en `sm_notifications` permanece activo
  - ownership y visibilidad siguen en `Access_Control_Service`
  - sin cambios de schema
  - sin campañas/marketing masivo
  - sin proveedor SMS real
  - sin automatizaciones destructivas

Siguiente fase:
- Se puede pasar a FASE 34, manteniendo FASE 33 como base de notificaciones multicanal desacoplada y preparando SMS real para una fase posterior controlada.

Actualización FASE 34 (Automatización operativa avanzada controlada):
- Fecha de implementación: 2026-03-28
- Estado FASE 34: COMPLETO
- Se incorpora base de automatización operativa controlada en arquitectura activa `includes/*`:
  - `includes/automation/class-automation-service.php`
  - `includes/automation/class-automation-rule-engine.php`
  - `includes/appointments/class-appointment-reminder-scheduler.php`
- Scheduler base implementado con `wp_cron`:
  - cron recurrente `sm_appointment_reminder_cron` (cada 15 minutos)
  - ejecución on-demand `sm_appointment_reminder_run_once` para refresco rápido por eventos
  - lock corto por transient para prevenir doble ejecución concurrente
- Recordatorios automáticos de citas:
  - trigger operativo `appointment_reminder` integrado al dispatcher central
  - `Notification_Service` agrega `notify_appointment_reminder()` para flujo in-app + canal externo existente
  - ventana configurable y lead-time configurable desde settings
- Automation_Service escuchando dispatcher:
  - listeners sobre `appointment_created`, `appointment_updated`, `appointment_status_changed`, `appointment_cancelled`
  - ejecución de reglas simples activables/desactivables sin builder complejo
  - acción actual: refresco controlado del scheduler de recordatorios
- Configuración mínima agregada (sin UI compleja):
  - `automation.enable_automation_runtime`
  - `automation.enable_appointment_reminders`
  - `automation.appointment_reminder_minutes_before`
  - `automation.appointment_reminder_window_minutes` (base fija segura)
- Seguridad y alcance mantenidos:
  - sin cambios de schema
  - sin campañas
  - sin integraciones externas nuevas
  - sin motor complejo de reglas
  - sin SQL fuera de repository
- Control de duplicación de envíos:
  - deduplicación por transient por cita+ventana objetivo (`sm_appointment_reminder_sent_*`)
  - lock runtime (`sm_appointment_reminder_lock`)

Siguiente fase:
- Se puede pasar a FASE 35 manteniendo FASE 34 como capa de automatización operativa simple y controlada.

Actualización FASE 35A (Activación multi-business base controlada):
- Fecha de implementación: 2026-03-28
- Estado FASE 35A: COMPLETO
- Activación real de `business_id` en núcleo transaccional mínimo (sin tabla `businesses`):
  - `sm_clients`
  - `sm_vehicles`
  - `sm_client_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- Schema actualizado a `1.12.0` con `business_id` + índice por tabla en alcance 35A.
- Backfill explícito e idempotente incorporado vía:
  - `includes/database/class-tenancy-backfill-migrator.php`
  - ejecución desde `class-migrator.php` tras migración de schema
- Estrategia legacy aplicada:
  - `business_id` por defecto `1` para instalaciones existentes
  - herencia estructural desde entidad padre cuando existe (sin cálculo ambiguo por contexto)
- Runtime tenancy:
  - `Business_Context_Service` pasa a resolver `business_id` real con fallback legacy
  - `Settings_Service` normaliza `business.business_id` con default `1`
- Restricciones mantenidas de alcance:
  - sin tabla `businesses`
  - sin aislamiento completo tenant-aware de dashboard/reportes/API
  - sin cambios a numeradores globales `quote_number`/`invoice_number`
  - sin activar 35B dentro de 35A

Exclusiones deliberadas 35A (diferidas a 35B):
- `sm_attachments`
- `sm_comments`
- `sm_notifications`
- `sm_appointments`
- `sm_appointment_calendar_sync`
- `sm_process_step_logs`
- `sm_quote_items`
- `sm_invoice_items`

Deuda técnica abierta para 35B:
- tenant-awareness completo en capas no núcleo (adjuntos/comunicación/citas/logs/items)
- revisión de aislamiento transversal de lecturas agregadas (dashboard/reportes/API)
- estrategia futura de numeradores por negocio (fuera de 35A)

Siguiente fase:
- Se puede pasar a FASE 35B con base multi-business mínima activa y backfill legacy estable.

Actualización FASE 35B (Enforcement multi-tenant transversal):
- Fecha de implementación: 2026-03-28
- Estado FASE 35B: COMPLETO
- Schema actualizado a `1.13.0`.
- Tablas activadas en 35B con `business_id` + índice:
  - `sm_quote_items`
  - `sm_invoice_items`
  - `sm_process_step_logs`
  - `sm_appointments`
  - `sm_appointment_calendar_sync`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- Backfill idempotente extendido en `Tenancy_Backfill_Migrator`:
  - herencia estructural desde padre principal por bloque
  - fallback legacy explícito a `business_id = 1` cuando no existe padre inequívoco
  - validación de consistencia en entidades polimórficas para evitar desalineación silenciosa
- Enforcement aplicado:
  - filtros base tenant-aware en repositorios diferidos de 35B
  - endurecimiento de acceso en `Access_Control_Service` con regla `ownership + business_id`
  - aislamiento tenant-aware en reportes mediante filtros obligatorios por `business_id` en procesos/quotes/invoices/payments y actividad agregada
  - propagación de `business_id` en servicios de citas, adjuntos, comentarios, notificaciones y sync Google Calendar
- Compatibilidad mantenida:
  - sin tabla `businesses`
  - sin branding tenant
  - sin billing SaaS
  - sin selector complejo multi-negocio
  - sin cambio de numeradores globales `quote_number`/`invoice_number`

Exclusiones deliberadas de 35B:
- no se implementa tabla `businesses`
- no se implementa branding/billing SaaS por tenant
- no se implementa selector multi-login/multi-business complejo
- no se cambia la estrategia de numeradores globales

Deuda técnica abierta para 35C:
- introducir entidad `businesses` y modelo de membresía/selección de negocio
- migrar numeradores a ámbito por negocio cuando se apruebe impacto funcional
- completar aislamiento tenant-aware en superficies legacy no críticas y utilidades históricas
- ampliar observabilidad/auditoría de consistencia cross-tenant

Siguiente fase:
- Se puede pasar a FASE 35C con 35A+35B cerradas y enforcement transversal tenant-aware operativo.

Actualización FASE 35C (Operación multi-store visible):
- Fecha de implementación: 2026-03-28
- Estado FASE 35C: COMPLETO
- Schema actualizado a `1.14.0`.
- Entidad visible de negocio implementada:
  - nueva tabla `sm_businesses`
  - CRUD admin básico en `includes/businesses/*`
- Shape de `sm_businesses`:
  - `id`, `slug`, `name`, `status`, `is_default`
  - `timezone`, `currency`
  - `branding_logo_attachment_id`, `primary_color`
  - `created_at`, `updated_at`
- Estrategia legacy/default aplicada:
  - negocio default idempotente `id=1`, `slug=default`, `is_default=1`
  - nombre desde settings (`business.business_name`) con fallback `Super Mechanic`
  - `business_id=1` se mantiene como base de compatibilidad legacy
- Selección de contexto operativo implementada:
  - prioridad 1: selector admin por usuario (`user meta`)
  - prioridad 2: fallback `sm_settings.business.business_id`
  - prioridad 3: fallback final a negocio default (`id=1`)
- Integración runtime:
  - `Business_Context_Service` resuelve y valida contexto contra entidad real `sm_businesses`
  - `Settings` conserva y expone fallback de `business_id` sin romper formularios legacy
  - reparación idempotente de huérfanos `business_id` a `1` en tablas tenant-aware

Exclusiones deliberadas 35C:
- sin billing SaaS
- sin multi-login complejo
- sin permisos ultrafinos por tenant
- sin cambio de numeradores globales por negocio
- sin branding avanzado (solo base simple por `attachment_id`)

Deuda técnica abierta para FASE 36:
- modelo avanzado de membresía/planes por negocio
- numeradores por negocio (`quote_number`/`invoice_number`) con migración controlada
- políticas avanzadas de administración multi-negocio (roles/capabilities por tenant)
- expansión de branding y preferencias por negocio en superficies cliente/mecánico

Siguiente fase:
- Se puede pasar a FASE 36 con 35A+35B+35C cerradas y operación multi-store visible estable.
