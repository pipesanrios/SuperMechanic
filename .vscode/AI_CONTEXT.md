AI CONTEXT — SUPER MECHANIC

Contexto operativo rápido para agentes IA.
Fuente de verdad: código real (`includes/*`).

## Estado real

- Plugin: `0.1.0`
- Schema: `1.15.0`
- Bloque actual: `38B (Comercial / WooCommerce)` (continuidad activa)
- Estado de subfases 38A:
  - `38A-1` — COMPLETA (idioma / i18n base)
  - `38A-2` — PARCIAL (validacion manual final pendiente o confirmacion explicita)
  - `38A-3` — PARCIAL (email admin no validado + inestabilidad externa registrada)
  - `38A-3B` — COMPLETA (export/import operativo JSON/CSV ZIP/Excel XML)
- Estado de subfases 38B:
  - `38B-1` — COMPLETA (vinculacion comercial base Woo con snapshot en quote/invoice y autofill manual en maintenance)
  - `38B-2` — COMPLETA (totales consistentes por formula por item, saneamiento legacy controlado y snapshot-only en Woo)
- Siguiente fase habilitada: `38B-3 — Comercial / WooCommerce`
- Últimas subfases completas:
  - `38A-3B` — export/import operativo (JSON canónico + CSV ZIP + Excel XML)
  - `37A-3` — consistencia operativa y tenancy endurecida
  - `37A-4` — multi-store operativo completo
  - `37A-5` — núcleo operativo diario
  - `37A-6` — UX operativa general
  - `38A-1` — idioma / internacionalización base
- Bloque técnico post-cierre: `HOTFIX-MEM-1` — `COMPLETO`
  - fatal `memory exhausted` causado por cascadas/recursión de servicios
  - bootstrap estable restaurado
- Arquitectura activa: `includes/*`
- Legacy no activa: `includes/modules/*`

## Patrón obligatorio

`Controller -> Service -> Repository -> Database`

- SQL solo en repositories (y en `includes/database/*` para schema/migraciones)
- `$wpdb` prohibido fuera de repository / database infra
- Descargas seguras vía `Document_Service` + `Download_Service`
- No exponer `file_url` directo

## Módulos activos reales

`clients`, `vehicles`, `relations`, `flows`, `processes`, `maintenance`, `predelivery`, `paperwork`, `quotes`, `invoices`, `attachments`, `communication`, `dashboard`, `reports`, `appointments`, `automation`, `businesses`, `helpers`, `integrations`, `database`

## Integraciones activas

- Google Calendar
  - OAuth
  - sync 1-way
  - inbound controlado
  - webhook/watch channel
- API pública `super-mechanic-public/v1`
  - read-only:
    - `business`
    - `processes`
    - `appointments`
  - write mínima:
    - `appointments/{id}/cancel`
    - `appointments/{id}/confirm`
- Webhooks outbound públicos
  - `sm_webhooks`
  - `sm_webhook_deliveries`
- Calendario operativo admin de citas
  - FullCalendar local (sin CDN)
  - REST interno:
    - `GET /super-mechanic/v1/admin/appointments/calendar`
    - `POST /super-mechanic/v1/admin/appointments/{id}/status`
    - `POST /super-mechanic/v1/admin/appointments/{id}/reschedule`
  - update de estado vía `Appointment_Service` (preserva sync Google)

## Tenancy real

- `business_id` activo en tablas tenant-aware
- `sm_businesses` activo
- contexto por prioridad:
  - `sm_active_business_id` (user meta)
  - `sm_settings.business.business_id`
  - fallback negocio default `id=1`
- restricción por usuario:
  - `sm_allowed_business_ids`
- visibilidad operativa:
  - `administrator` → acceso global
  - `sm_admin` → solo negocios permitidos
  - `sm_mechanic` → solo negocios asignados
  - `sm_client` → contexto restringido

## Plataforma / i18n / moneda

- `38A-1`:
  - inglés como base operativa
  - soporte base preparado para:
    - `en_US`
    - `es_ES`
    - `it_IT`
  - i18n estándar WordPress
  - textdomain del plugin cargado con fallback limpio
- `38A-2`:
  - capa monetaria ya no rígida
  - monedas soportadas base:
    - `USD`
    - `EUR`
    - `COP`
    - `PAB`
  - ampliables vía configuración / filtro
  - estado: PARCIAL (pendiente validacion runtime/UI manual final o confirmacion explicita)
- `38A-3` y `38A-3B`:
  - seguridad DB administrativa con `master password` y nonces/capabilities
  - export operativo en `JSON` (canónico), `CSV ZIP` y `Excel XML`
  - import soportado solo para backup JSON canónico con validación previa estricta y rollback transaccional
  - estado 38A-3: PARCIAL (validacion email admin pendiente + inestabilidad externa registrada)
  - estado 38A-3B: COMPLETA
- `38B-1`:
  - Woo activo/inactivo validado en runtime WordPress real
  - quote/invoice: `reference_id` persiste `woo_product_id` + snapshot obligatorio (`label`, `unit_price`)
  - maintenance: Woo solo autofill manual de nombre/precio (sin cambio de schema)
  - sin regresion de totales y sin dependencia forzada de Woo
- `38B-2`:
  - `line_total` normalizado por formula: `quantity * unit_price`
  - `recalculate_totals()` usa calculo por item como fuente de verdad
  - mapeo de compatibilidad: `manual -> custom`
  - saneamiento controlado de `line_total` legacy inconsistente
  - Woo snapshot-only: no recalculo dinamico de precios desde catalogo Woo
  - validacion runtime WordPress real en Woo activo/inactivo: OK

## Deuda técnica viva

- placeholders no activos:
  - `includes/class-rest-api.php`
  - `includes/class-hooks.php`
  - `includes/class-post-types.php`
- no hay CI/CD externo ni E2E runtime automatizado
- faltan UX/admin dedicadas para API keys y webhooks públicos
- faltan tests automatizados de regresión para prevenir bucles de inicialización entre services
- cierre pendiente de validacion manual final para 38A-2
- cierre pendiente de validacion formal de email admin en 38A-3
- se mantiene registro de inestabilidad externa asociada a 38A-3
- conviene vigilar dependencias circulares y evitar eager initialization entre services

## Docs clave

- `ARCHITECTURE.md`
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`
- `docs/TEST_SCENARIOS.md`
- `docs/PROJECT_TRANSFER_CONTEXT.md`

## Regla final

Si docs/prompts difieren del código: manda el código y corrige la documentación.
