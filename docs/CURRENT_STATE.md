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
