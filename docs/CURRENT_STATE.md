# CURRENT STATE — SUPER MECHANIC

Version: 0.1.0
Schema: 1.9.0

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
