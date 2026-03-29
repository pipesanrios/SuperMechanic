# PROJECT TRANSFER CONTEXT — SUPER MECHANIC

Documento compacto para transferencia a otra IA / NotebookLM.
Fecha: 2026-03-29

## Que es

Plugin WordPress modular para operacion de talleres/concesionarios:
clientes, vehiculos, procesos, mantenimiento, cotizaciones, facturacion, pagos, documentos, citas e integraciones.

## Arquitectura real

- Activa: `includes/*`
- Legacy: `includes/modules/*` (no usar)
- Patron: `Controller -> Service -> Repository -> Database`
- SQL: solo repositories (excepto infraestructura en `includes/database/*`)

## Versiones reales

- Plugin: `0.1.0`
- Schema: `1.15.0`

## Estado/fase real

- Fase actual cerrada: `36C-2`
- Ultimas fases cerradas: `35A/35B/35C`, `36A/36B/36C-1/36C-2`

## Modulos activos

`appointments`, `attachments`, `automation`, `businesses`, `clients`, `communication`, `dashboard`, `database`, `flows`, `helpers`, `integrations`, `invoices`, `maintenance`, `paperwork`, `predelivery`, `processes`, `quotes`, `relations`, `reports`, `vehicles`

## Integraciones activas

- Google Calendar: OAuth + sync 1-way + inbound controlado + webhook/watch
- API publica: `super-mechanic-public/v1`
  - read: `business`, `processes`, `appointments`
  - write minima: `appointments/{id}/cancel`, `appointments/{id}/confirm`
- Webhooks outbound publicos:
  - tablas `sm_webhooks`, `sm_webhook_deliveries`
  - firma `HMAC-SHA256`, retries basicos, idempotencia

## Tenancy real

- `business_id` activo en tablas tenant-aware
- tabla `sm_businesses` activa
- resolucion de contexto:
  - `sm_active_business_id` (usuario)
  - fallback `sm_settings.business.business_id`
  - fallback final negocio default `id=1`

## Reglas que no se deben romper

- no usar `includes/modules/*`
- no SQL fuera de repository/database infra
- no exponer `file_url` directo
- descargas via `Document_Service` + `Download_Service`
- mantener boundaries por servicio (evitar acoplar repositories cruzados)

## Deuda tecnica abierta

- placeholders no activos: `class-rest-api`, `class-hooks`, `class-post-types`
- hotspots: `Process_Admin_Controller`, `Report_Service`
- sin CI/CD externo ni E2E runtime automatizado
- falta UX/admin para API keys y webhooks publicos

## Documentos de entrada recomendados

1. `AGENTS_BOOTSTRAP.md`
2. `ARCHITECTURE.md`
3. `docs/CURRENT_STATE.md`
4. `docs/PLUGIN_ROADMAP.md`
5. `docs/DATABASE_MAP.md`
6. `.vscode/AI_CONTEXT.md`
