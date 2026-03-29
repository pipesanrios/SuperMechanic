# CURRENT STATE — SUPER MECHANIC

Fecha de consolidacion: 2026-03-29

## Versiones reales

- Plugin: `0.1.0` (`super-mechanic.php`)
- Schema: `1.15.0` (`includes/database/class-schema.php`)

## Estado general

- Estado: ESTABLE CON RIESGOS CONTROLADOS
- Fase actual real: `37A-3` (bloqueadores de consistencia operativa y hardening tenancy)
- Ultima fase completa real: `37A-3`
- Runtime WordPress real para 37A-3: confirmado (validacion manual UI real + runtime backend)

## Arquitectura activa real

- Activa: `includes/*`
- Legacy no activa: `includes/modules/*`
- Patron operativo: `Controller -> Service -> Repository -> Database`
- SQL fuera de repositories: no detectado en modulos de dominio activos
- Excepciones permitidas por arquitectura: `includes/database/*` (schema/migracion/backfill)

## Modulos activos reales (runtime)

- `appointments`
- `attachments`
- `automation`
- `businesses`
- `clients`
- `communication`
- `dashboard`
- `flows`
- `helpers`
- `integrations` (`public-api`, `google-calendar`)
- `invoices`
- `maintenance`
- `paperwork`
- `predelivery`
- `processes`
- `quotes`
- `relations`
- `reports`
- `vehicles`
- `database` (infraestructura de schema/migraciones)

## Integraciones activas reales

- Google Calendar:
  - OAuth admin
  - sync 1-way
  - reconciliacion inbound controlada
  - webhook REST + watch channel + renovacion
- API publica `super-mechanic-public/v1`:
  - read-only: `business`, `processes`, `appointments`
  - writes minimas: `appointments/{id}/cancel`, `appointments/{id}/confirm`
- Webhooks outbound publicos:
  - tablas `sm_webhooks` y `sm_webhook_deliveries`
  - firma `HMAC-SHA256`
  - retries basicos e idempotencia
- Calendario operativo admin:
  - menu `Super Mechanic -> Calendar`
  - FullCalendar local (sin CDN)
  - REST interno `GET /admin/appointments/calendar` + `POST /admin/appointments/{id}/status`
  - cambio de estado via `Appointment_Service` con sync Google preservado
- Updates/licenciamiento:
  - base local de licencias, updates privadas y feature flags (sin billing SaaS)

## Seguridad y tenancy (estado real)

- Descargas protegidas por `Document_Service` + `Download_Service`
- Sin exposicion directa de `file_url` en flujos cliente confirmados por codigo
- Tenancy activa por `business_id` + `sm_businesses` + `Business_Context_Service`
- Contexto operativo por prioridad:
  - `user meta` (`sm_active_business_id`)
  - fallback `sm_settings.business.business_id`
  - fallback final negocio default (`id=1`)

## Deuda tecnica abierta

- `includes/class-rest-api.php`, `includes/class-hooks.php`, `includes/class-post-types.php` siguen como placeholders legacy/no activos
- `Process_Admin_Controller` y `Report_Service` siguen siendo puntos de alta complejidad
- No hay CI/CD externo consolidado
- No hay bateria automatizada de runtime WordPress E2E
- Motor PDF depende del entorno; validacion funcional completa de PDFs no siempre disponible en cierres recientes
- Falta UX/admin dedicada para gestionar API keys y webhooks publicos (alta/rotacion/revocacion/observabilidad)

## Siguiente fase real

- Siguiente subfase recomendada: `37A-4` (consolidacion operativa previa a CRM).
- Backlog inmediato recomendado:
  - UX/admin de API keys y webhooks publicos
  - observabilidad avanzada de entregas webhook
  - consolidacion final de checklist runtime 36B/36C
