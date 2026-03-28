AI CONTEXT — SUPER MECHANIC

Contexto técnico rápido para agentes de IA en VSCode.

Objetivo:
dar contexto operativo mínimo del proyecto sin reemplazar la documentación técnica completa.

==================================================
PROYECTO
==================================================

Nombre:
Super Mechanic

Tipo:
Plugin WordPress

Propósito:
sistema modular para gestión de talleres, concesionarios, procesos de vehículos, mantenimiento, trámites, cotizaciones, facturación y Client Portal.

==================================================
ARQUITECTURA ACTIVA
==================================================

Patrón principal:

Repository
Service
Controller
Shortcodes
REST Controller (cuando aplique)

Reglas base:

- SQL solo en Repository
- la arquitectura activa real está en `includes/*`
- `includes/modules/*` es legacy y no debe usarse
- Controller → Service → Repository
- Shortcodes → frontend cliente
- Controllers admin → UI admin
- Shortcodes mecánicos → frontend autenticado por rol cuando aplique

==================================================
MÓDULOS ACTIVOS
==================================================

clients
vehicles
relations
flows
processes
maintenance
predelivery
paperwork
dashboard
reports
quotes
invoices
payments
attachments
communication
helpers
integrations

==================================================
CAPAS TRANSVERSALES
==================================================

- core / bootstrap
- security / ownership
- settings
- documents / PDF / secure downloads
- UI / assets
- quality / scripts locales
- client portal

No activas todavía:

- API pública / integraciones externas
- SaaS real

==================================================
REGLAS CRÍTICAS
==================================================

- nunca colocar SQL fuera de Repository
- nunca modificar `includes/modules/*`
- validar ownership siempre
- aplicar sanitización y escaping
- nunca exponer `file_url` directo
- usar `Document_Service` + `Download_Service` para descargas seguras
- no romper formularios, nonces ni query args
- reutilizar sistema visual `sm-*`

==================================================
FLUJO PRINCIPAL
==================================================

Cliente
→ Vehículo
→ Relación cliente-vehículo
→ Proceso
→ Maintenance / Quote / Invoice / Payment

Durante el proceso pueden existir:

attachments
comments
notifications
timeline

==================================================
SHORTCODES ACTIVOS
==================================================

Actualmente existen shortcodes activos de cliente:

- sm_client_dashboard
- sm_client_vehicles
- sm_client_processes
- sm_client_process_documents
- sm_client_process_timeline
- sm_client_quotes
- sm_client_quote_detail
- sm_client_quote_action
- sm_client_invoices
- sm_client_invoice_detail
- sm_client_process_comments
- sm_client_process_comment_form
- sm_client_notifications

Shortcodes activos de mecánico:

- sm_mechanic_dashboard
- sm_mechanic_processes

No existen todavía shortcodes activos para:

- contexto público/general

Fuente de verdad:
- `includes/dashboard/class-client-dashboard-shortcodes.php`
- `includes/dashboard/class-mechanic-dashboard-shortcodes.php`
- `includes/attachments/class-client-attachment-shortcodes.php`
- `includes/quotes/class-client-quote-shortcodes.php`
- `includes/invoices/class-client-invoice-shortcodes.php`
- `includes/communication/class-client-comment-shortcodes.php`

==================================================
SCRIPTS DE VALIDACIÓN TÉCNICA
==================================================

Ubicación:
`scripts/`

Scripts activos:

- `php-lint.php`
- `structure-check.php`
- `technical-checklist.php`

Propósito:
- lint PHP
- chequeo estructural
- checklist técnico local previo al cierre

==================================================
ESTADO ACTUAL RESUMIDO
==================================================

Versión real:
- plugin: `0.1.0`
- schema: `1.9.0`

Fases consolidadas:
- 12A–12E
- 13
- 14
- 14B
- 15
- 16
- 17
- 18
- 19
- 20
- 20B
- 21
- 22
- 23
- 24
- 24B
- 25
- 26
- 26B

Resumen de hitos recientes:
- reports consolidado y avanzado
- Client Portal y mecánico operativos
- ownership centralizado
- workflow endurecido
- documentos seguros y `payment_receipt`
- settings avanzados con `sm_settings`
- UI moderna base + cobertura admin
- scripts locales de validación
- hardening pre-SaaS en controllers críticos, transacciones y descargas admin
- bloque previo a Fase 27 estabilizado con smoke test runtime real `COMPLETO`
- SUBFASES 7-9 validadas en runtime real: invoice manual sin quote, impuestos/descuentos `percent/fixed`, pagos consolidados sobre `sm_payments` y seguridad documental endurecida sobre `uploads`
- SUBFASES 10-13 validadas en runtime real: `Permission_Service` reutilizable, portal cliente consolidado, shortcodes mecánicos frontend activos y enforcement coherente por rol + ownership
- FASE 27A + 27B + 27C-A activas en runtime real: API interna read-only para cliente y admin en `includes/dashboard/class-client-rest-controller.php` y `includes/dashboard/class-admin-rest-controller.php`, con filtros/paginación consistentes y payloads normalizados para procesos, vehículos, clientes, quotes e invoices
- FASE 27C-B activa en runtime real con write mínimo interno admin: cambio de estado de proceso y comentario interno de proceso, protegidos por `sm_manage_plugin` + `sm_manage_processes`, payload acotado y sin apertura de writes adicionales

==================================================
DEUDA TÉCNICA VIVA
==================================================

- `includes/class-rest-api.php`, `includes/class-hooks.php` y `includes/class-post-types.php` siguen como placeholders / no activos
- rutas admin de PDF de quotes/invoices siguen como excepción controlada
- `Process_Admin_Controller` y `Report_Service` siguen siendo puntos a vigilar si crecen más
- no hay todavía API pública productiva (solo API interna autenticada; cliente/admin read-only más dos writes internos admin mínimos en procesos)
- no hay CI/CD externo real
- no hay validación runtime completa en WordPress automatizada
- invoices PDF sigue pendiente mientras no exista motor PDF activo en entorno
- `sm_public_tracking` sigue pendiente por seguridad hasta que exista un mecanismo público seguro no basado en IDs internos

==================================================
FUENTE DE VERDAD
==================================================

Si hay conflicto entre:

- código
- documentación
- prompts

manda siempre:

1. código real (`includes/*`)
2. docs técnicos base
3. contextos AI rápidos

==================================================
DOCUMENTACIÓN CLAVE
==================================================

Ver detalle en:

- `ARCHITECTURE.md`
- `docs/CURRENT_STATE.md`
- `docs/SYSTEM_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`
- `docs/SECURITY_MODEL.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/TEST_SCENARIOS.md`

==================================================
REGLA FINAL
==================================================

AI_CONTEXT.md debe mantenerse como contexto rápido.
No debe duplicar en detalle a `ARCHITECTURE.md`, `SYSTEM_MAP.md` o `CURRENT_STATE.md`.
