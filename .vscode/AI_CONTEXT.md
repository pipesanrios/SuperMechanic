AI CONTEXT — SUPER MECHANIC

Contexto operativo rapido para agentes IA.
Fuente de verdad: codigo real (`includes/*`).

## Estado real

- Plugin: `0.1.0`
- Schema: `1.15.0`
- Fase actual: `37A-3` (consistencia operativa y tenancy endurecida)
- Bloque tecnico post-cierre: `HOTFIX-MEM-1` COMPLETO (fatal memory exhausted por cascadas de servicios)
- Arquitectura activa: `includes/*`
- Legacy no activa: `includes/modules/*`

## Patrón obligatorio

`Controller -> Service -> Repository -> Database`

- SQL solo en repositories (y capa `includes/database/*` para schema/migraciones)
- `$wpdb` prohibido fuera de repository/database infra
- Descargas seguras via `Document_Service` + `Download_Service`
- No exponer `file_url` directo

## Modulos activos reales

`clients`, `vehicles`, `relations`, `flows`, `processes`, `maintenance`, `predelivery`, `paperwork`, `quotes`, `invoices`, `attachments`, `communication`, `dashboard`, `reports`, `appointments`, `automation`, `businesses`, `helpers`, `integrations`, `database`

## Integraciones activas

- Google Calendar (OAuth, sync 1-way, inbound controlado, webhook/watch channel)
- API publica `super-mechanic-public/v1`
  - read-only: `business`, `processes`, `appointments`
  - write minima: `appointments/{id}/cancel`, `appointments/{id}/confirm`
- Webhooks outbound publicos (`sm_webhooks`, `sm_webhook_deliveries`)
- Calendario operativo admin de citas
  - FullCalendar local (sin CDN)
  - REST interno: `GET /super-mechanic/v1/admin/appointments/calendar`
  - REST interno: `POST /super-mechanic/v1/admin/appointments/{id}/status`
  - update de estado via `Appointment_Service` (preserva sync Google)

## Tenancy real

- `business_id` activo en tablas tenant-aware
- `sm_businesses` activo
- contexto por prioridad:
  - `sm_active_business_id` (user meta)
  - `sm_settings.business.business_id`
  - fallback negocio default `id=1`

## Deuda tecnica viva

- placeholders no activos: `includes/class-rest-api.php`, `includes/class-hooks.php`, `includes/class-post-types.php`
- no hay CI/CD externo ni E2E runtime automatizado
- faltan UX/admin dedicadas para API keys/webhooks publicos
- faltan tests automatizados de regresion para prevenir bucles de inicializacion entre services

## Docs clave

- `ARCHITECTURE.md`
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## Regla final

Si docs/prompts difieren del codigo: manda el codigo y corrige la documentacion.
