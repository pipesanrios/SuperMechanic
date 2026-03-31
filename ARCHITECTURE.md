# ARCHITECTURE - SUPER MECHANIC

Documento tecnico de arquitectura real para continuidad multi-IA.
Fuente de verdad: codigo activo en `includes/*`.

==================================================
1) BOOTSTRAP Y FLUJO DE CARGA
==================================================

Entrada principal:
- `super-mechanic.php`

Flujo base:
1. carga de constantes/autoload
2. registro de hooks de activacion/desactivacion
3. inicializacion del plugin (`includes/class-plugin.php`)
4. carga de menu admin, assets, settings y modulos
5. carga de textdomain en `init` (hotfix i18n aplicado)

Regla:
- no romper bootstrap en `super-mechanic.php` y `includes/class-plugin.php`

==================================================
2) PATRON ARQUITECTONICO OBLIGATORIO
==================================================

`Controller -> Service -> Repository -> Database`

- Controller: integra con WordPress y UI
- Service: reglas de negocio
- Repository: SQL y persistencia
- Database: schema/migraciones

Reglas duras:
- SQL solo en Repository/Database
- `$wpdb` prohibido fuera de esas capas
- no meter logica de negocio pesada en Controller

==================================================
3) ARQUITECTURA ACTIVA VS LEGACY
==================================================

Activa:
- `includes/*`

Legacy/no tocar salvo fase explicita:
- `includes/modules/*`
- `includes/class-rest-api.php`
- `includes/class-hooks.php`
- `includes/class-post-types.php`

==================================================
4) MODULOS ACTIVOS REALES
==================================================

- `appointments`
- `attachments`
- `automation`
- `businesses`
- `clients`
- `communication`
- `crm`
- `dashboard`
- `database`
- `flows`
- `helpers`
- `integrations`
- `invoices`
- `maintenance`
- `paperwork`
- `predelivery`
- `processes`
- `quotes`
- `relations`
- `reports`
- `vehicles`

==================================================
5) TABLAS PRINCIPALES (MAPA RAPIDO)
==================================================

Core operativo:
- `sm_clients`
- `sm_vehicles`
- `sm_client_vehicles`
- `sm_processes`
- `sm_process_step_logs`
- `sm_maintenance`, `sm_maintenance_parts`, `sm_maintenance_labor`
- `sm_predelivery_items`
- `sm_paperwork`
- `sm_quotes`, `sm_quote_items`
- `sm_invoices`, `sm_invoice_items`
- `sm_payments`
- `sm_attachments`
- `sm_notifications`

CRM / automatizacion comercial:
- `sm_client_crm_meta`
- `sm_crm_pipeline`
- `sm_crm_tasks`
- `sm_crm_alerts`

Citas / integraciones:
- `sm_appointments`
- `sm_webhooks`
- `sm_webhook_deliveries`
- `sm_businesses`

Ver detalle canonico en `docs/DATABASE_MAP.md`.

==================================================
6) TENANCY
==================================================

Tenancy obligatoria por `business_id`.

Contexto de negocio por prioridad:
1. `sm_active_business_id` (user meta)
2. `sm_settings.business.business_id`
3. fallback default business (`id=1`)

Regla:
- toda consulta/edicion tenant-aware debe filtrar por `business_id`

==================================================
7) CRM, SCHEDULER Y ALERTAS
==================================================

Estado consolidado:
- `39A` CRM base: completo
- `39B` pipeline CRM: completo
- `39C` tareas y seguimiento: completo
- `39D` refinamiento operativo: completo
- `39E` automatizacion comercial avanzada: completo

Cobertura 39E:
- scheduler interno WP-Cron: `sm_crm_scheduler_tick`
- persistencia de alertas: `sm_crm_alerts`
- tipos base:
  - `overdue_task`
  - `inactive_opportunity`
  - `follow_up_needed`
  - `conversion_pending`
- consumo UI persistido en list/kanban/view
- fallback runtime controlado cuando no hay alerta persistida

==================================================
8) INTEGRACIONES ACTIVAS
==================================================

- Google Calendar (OAuth, sync, inbound, webhook/watch)
- API publica `super-mechanic-public/v1` (read + writes minimas de appointments)
- Webhooks outbound publicos (firma HMAC, retries basicos, idempotencia)
- WooCommerce (catalogo comercial snapshot en quotes/invoices)

==================================================
9) SEGURIDAD Y DESCARGAS
==================================================

- no exponer `file_url`
- descargas via `Document_Service` y `Download_Service`
- ownership/capabilities/nonces obligatorios

==================================================
10) RELACIONES ENTRE MODULOS (RESUMEN)
==================================================

- `clients` <-> `vehicles` via `relations`
- `processes` coordina `maintenance`, `predelivery`, `paperwork`, `quotes`, `invoices`, `payments`, `attachments`, `communication`
- `crm` conecta comercialmente con `clients`, opcionalmente `vehicles` y `processes`
- `appointments` y `crm_tasks` convergen en calendario unificado por tipo de evento
- `reports` agrega datos operativos/financieros tenant-aware

==================================================
11) FUENTE DE VERDAD
==================================================

Prioridad:
1. codigo real
2. `docs/CURRENT_STATE.md`
3. `docs/PLUGIN_ROADMAP.md`
4. `docs/DATABASE_MAP.md`
5. `docs/MODULE_REGISTRY.md`
6. `docs/SYSTEM_MAP.md`

Si hay conflicto, manda codigo y corrige docs.
