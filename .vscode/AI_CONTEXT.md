AI CONTEXT — SUPER MECHANIC

Contexto operativo rápido para agentes IA.
Fuente de verdad: código real (`includes/*`).

## Entrypoint oficial

- START HERE oficial: `AGENTS_BOOTSTRAP.md`
- Regla: no tocar código sin completar lectura obligatoria de bootstrap.

## Orden oficial de lectura (resumen)

1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`
4. `docs/CURRENT_STATE.md`
5. `docs/PROJECT_TRANSFER_CONTEXT.md`
6. `docs/PLUGIN_ROADMAP.md`
7. `ARCHITECTURE.md`
8. `docs/DATABASE_MAP.md`
9. `docs/MODULE_REGISTRY.md`
10. `docs/SYSTEM_MAP.md`
11. `docs/TEST_SCENARIOS.md`

## Estado real

- Plugin: `0.1.0`
- Schema: `1.19.0`
- Bloque actual: `Fase 39 (CRM y automatizacion comercial)` (continuidad activa)
- Estado de subfases 38A:
  - `38A-1` — COMPLETA (idioma / i18n base)
  - `38A-2` — PARCIAL (validacion manual final pendiente o confirmacion explicita)
  - `38A-3` — PARCIAL (email admin no validado + inestabilidad externa registrada)
  - `38A-3B` — COMPLETA (export/import operativo JSON/CSV ZIP/Excel XML)
- Estado de subfases 38B:
  - `38B-1` — COMPLETA (vinculacion comercial base Woo con snapshot en quote/invoice y autofill manual en maintenance)
  - `38B-2` — COMPLETA (totales consistentes por formula por item, saneamiento legacy controlado y snapshot-only en Woo)
  - `38B-3` — COMPLETA (hardening comercial Woo en quotes/invoices con saneamiento legacy y mensajes de validacion claros)
- Estado de subfases 38C:
  - `38C-1` — COMPLETA (optimizacion operativa UX en dashboard/clientes/vehiculos/procesos/finanzas + hotfix de quick action `Create quote`)
  - `38C-2` — COMPLETA (estabilidad operativa y pulido fino: labels/acciones unificadas, retorno de contexto corregido y consistencia en procesos/finanzas/pagos)
- Estado de subfases 38D:
  - `38D-1` — COMPLETA (reportes base financieros y operativos con agregaciones controladas por entidad y filtros simples por negocio/fechas)
  - `38D-2` — COMPLETA (export CSV por vista en reports, consistencia vista/export y filtros activos compartidos)
  - `38D-3` — COMPLETA (KPIs accionables y bloques de control en reports con filtros activos consistentes)
- Estado de bloque 38D: COMPLETO (reportes y control consolidados)
- Estado de subfases 39:
  - `39A` — COMPLETA (CRM base sobre clientes con bloque CRM, tabla auxiliar `sm_client_crm_meta` y persistencia validada en runtime manual)
  - `39E-1` — COMPLETA (scheduler interno CRM por `WP-Cron` con hook `sm_crm_scheduler_tick`)
  - `39E-2` — COMPLETA (persistencia de alertas CRM en `sm_crm_alerts` con recálculo por lotes y resolución `active -> resolved`)
  - `39E-3` — COMPLETA (consumo UI de alertas persistidas en list/kanban/view con fallback runtime controlado)
- Siguiente fase habilitada: `Fase 39 — CRM y automatizacion comercial`
- Continuidad oficial post-38:
  - `Fase 39` — CRM y automatizacion comercial
  - `Fase 40` — Hosting gestionado / WordPress dedicado
  - `Fase 41` — SaaS independiente
- Nota de continuidad:
  - el bloque `38` ya fue consumido en ejecucion real (`38A` a `38D`)
  - CRM no se mantiene como `37B`; se renumera oficialmente a `Fase 39`
- Últimas subfases completas:
  - `39E-1` — scheduler interno CRM (WP-Cron controlado)
  - `39A` — CRM base (tracking de clientes)
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
- `38B-3`:
  - integridad equivalente quotes/invoices para payload Woo
  - no persistencia de Woo incompleta con intención explícita Woo
  - saneamiento controlado `woo_product` legacy inconsistente a `custom`
  - mensaje priorizado de indisponibilidad: `WooCommerce not available`
  - validacion runtime WordPress real de cierre/hotfix: OK
- `38C-1`:
  - dashboard con quick actions operativas diferenciadas y ruta corregida para `Create quote`
  - listados de clientes/vehiculos con atajo `Create process` contextual
  - procesos con atajos directos a tabs operativas y feedback de estado
  - finanzas con busqueda y notices visuales mas claros
  - sin cambios de schema ni logica financiera core
- `38C-2`:
  - unificacion de labels/acciones en clientes, vehiculos y procesos
  - fix de retorno de contexto:
    - `return_vehicle_id` en flujo de clientes
    - `return_client_id` en flujo de vehiculos
  - procesos con columnas/acciones consistentes para reducir ambiguedad
  - finanzas/pagos con labels coherentes en ingles (`Amount`, `Reference`, `Notes`)
  - validacion runtime manual WordPress real confirmada por usuario
  - sin cambios de schema ni arquitectura
- `38D-1`:
  - reportes financieros base con metricas: facturado, cobrado, pendiente, invoices y ticket promedio
  - reportes operativos de procesos por tipo/estado y abiertos vs cerrados
  - reportes por cliente y por vehiculo sin doble conteo (agregacion controlada)
  - filtros globales simples por negocio y fechas
  - validacion runtime manual WordPress real confirmada por usuario
  - sin cambios de schema ni arquitectura
- `38D-2`:
  - export CSV por vista completado en reports:
    - `financial_base`
    - `operational_base`
    - `client_summary`
    - `vehicle_summary`
    - `recent_*`
  - coherencia vista/export confirmada en runtime manual real
  - filtros activos compartidos entre UI/export: `business_id`, `date_from`, `date_to`
  - sin cambios de schema ni arquitectura
- `38D-3`:
  - KPIs accionables completados en reports:
    - open/closed processes
    - overdue invoices
    - outstanding by currency
    - recent payments
    - average ticket
    - top clients
    - top vehicles
    - operational load
  - bloques accionables compactos para control rapido operativo/financiero
  - filtros activos funcionando correctamente (`business_id`, `date_from`, `date_to`)
  - validacion runtime manual WordPress real confirmada por usuario
  - sin cambios de schema ni arquitectura
- Consolidado 38D:
  - cobertura completa de reportes financieros/operativos + cliente/vehiculo
  - export CSV por vista consolidado
  - KPIs accionables y dashboards de control consolidados
  - bloque validado en runtime manual WordPress real (38D-1/38D-2/38D-3)

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

## ACTUALIZACION 39B (CONSOLIDADO)

- Estado de subfases 39:
  - `39A` - COMPLETA
  - `39B-1` - COMPLETA
  - `39B-2` - COMPLETA
  - `39B-3` - COMPLETA
- Estado de bloque 39B: COMPLETO
- Cobertura consolidada 39B:
  - `sm_crm_pipeline` independiente
  - `client_id` obligatorio, `vehicle_id` opcional, `process_id` opcional
  - CRUD usable, `View`, quick create client, quick stage
  - kanban funcional por columnas
  - conversion operativa: `create process` y `link existing process`
  - reglas por tipo:
    - `maintenance` requiere vehiculo
    - `pre_delivery` permite sin vehiculo
    - `paperwork` permite sin vehiculo
- Runtime WordPress manual real de 39B: CONFIRMADO POR USUARIO
- Restricciones preservadas:
  - sin cambios de schema adicionales fuera de `sm_crm_pipeline`
  - sin automatizaciones ni sync automatica CRM/proceso
- Siguiente fase recomendada:
  - continuidad de `Fase 39` (subfases CRM posteriores a 39B)

## ACTUALIZACION 39C-1 (CIERRE DOCUMENTAL)

- `39C-1` - COMPLETA
- Alcance validado:
  - tareas CRM base en `sm_crm_tasks`
  - create/edit/complete desde detalle de oportunidad CRM
  - tenancy por `business_id` en operaciones de tareas
- Validacion runtime WordPress manual real: CONFIRMADA POR USUARIO
- Restricciones vigentes en 39C-1:
  - sin cron
  - sin email
  - sin recordatorios automaticos
  - sin dashboard de vencidas
  - sin borrado en cascada automatica
- Siguiente continuidad recomendada:
  - `39C-2` (recordatorios CRM simples y operativos, aun sin automatizacion por cron/email)

## ACTUALIZACION 39C (CIERRE CONSOLIDADO)

- Estado de subfases 39C:
  - `39C-1` - COMPLETA
  - `39C-2` - COMPLETA
  - `39C-3` - COMPLETA
- Estado de bloque 39C: COMPLETO
- Cobertura consolidada 39C:
  - tareas CRM en `sm_crm_tasks`
  - CRUD base de tareas (`create`, `edit`, `complete`)
  - estados/tipos base de tareas validados
  - vistas operativas:
    - `pending`
    - `overdue`
    - `upcoming`
  - integracion con detalle de oportunidad CRM
  - integracion CRM ↔ Calendar con feed unificado:
    - `event_type=appointment`
    - `event_type=crm_task`
  - click funcional por tipo en calendario
  - `eventDrop` bloqueado/revertido para `crm_task`
- Runtime WordPress manual real de 39C: CONFIRMADO POR USUARIO
- Restricciones preservadas:
  - sin cron
  - sin email automatico
  - sin automatizacion compleja
- Siguiente continuidad recomendada:
  - continuidad de `Fase 39` posterior a `39C` (automatizaciones CRM controladas y/o agenda comercial progresiva, sin romper tenancy ni arquitectura actual)

## ACTUALIZACION 39D-1 (CIERRE DOCUMENTAL)

- `39D-1` - COMPLETA
- Alcance validado:
  - auto-tarea inicial idempotente en alta de oportunidad (solo si no existen tareas)
  - sugerencias en `contacted`/`quoted` cuando no hay `pending` (sin auto-creacion extra)
  - señal de conversion pendiente en `won` sin `process_id`
  - señales de overdue/inactividad en UI
  - agregacion por multiples oportunidades para evitar N+1 en list/kanban
- Validacion runtime WordPress manual real: CONFIRMADA POR USUARIO
- No regresion confirmada por usuario:
  - CRUD pipeline
  - tareas CRM
  - kanban
  - calendar
  - conversion operativa
- Restricciones preservadas:
  - sin cron
  - sin email automatico
  - sin automatizacion externa/agresiva
- Siguiente continuidad recomendada:
  - continuidad de `Fase 39` posterior a `39D-1` (siguiente subfase CRM comercial)

## ACTUALIZACION 39D-2 (CIERRE DOCUMENTAL)

- `39D-2` - COMPLETA
- Alcance validado:
  - filtros operativos combinables en CRM:
    - `assigned_user_id`
    - `stage`
    - `search`
    - `requires_attention`
    - `overdue`
  - priorizacion visual:
    - `Overdue` critico
    - `Attention` warning
  - quick stage preserva contexto/filtros:
    - `view_mode`
    - `search`
    - `stage`
    - `assigned_user_id`
    - `requires_attention`
    - `overdue`
- Validacion runtime WordPress manual real: CONFIRMADA POR USUARIO
- No regresion confirmada por usuario:
  - CRUD pipeline
  - tasks CRM
  - kanban
  - calendar
  - conversion operativa
- Restricciones preservadas:
  - sin cron
  - sin email automatico
  - sin cambios de schema

## ACTUALIZACION 39E (SCHEDULER + ALERTAS PERSISTIDAS + CONSUMO UI)

- `39E-1` - COMPLETA
  - hook cron propio `sm_crm_scheduler_tick`
  - schedule `sm_crm_every_ten_minutes`
  - alta en activacion y limpieza en desactivacion
  - logging debug + hook `sm_crm_scheduler_tick_executed`
- `39E-2` - COMPLETA
  - tabla `sm_crm_alerts` persistida
  - alertas por tipo:
    - `overdue_task`
    - `inactive_opportunity`
    - `follow_up_needed`
    - `conversion_pending`
  - recálculo por lotes con limites por tick
  - deduplicación funcional de alertas `active` por tipo/pipeline/negocio
  - resolución `active -> resolved` cuando deja de aplicar
  - validación runtime WordPress manual real confirmada por usuario
- `39E-3` - COMPLETA
  - alertas persistidas como fuente principal de UI en:
    - list
    - kanban
    - view
  - consulta por lote sin N+1 por `crm_pipeline_id`
  - fallback runtime controlado cuando no existen alertas persistidas
  - prioridad visual preservada (`overdue` crítico sobre `attention`)
  - validación runtime WordPress manual real confirmada por usuario
- Estado bloque 39E: COMPLETO
- Restricciones preservadas:
  - sin email automático
  - sin notificaciones externas
  - sin automatización masiva adicional

## HOTFIX I18N RECIENTE

- `load_plugin_textdomain('super-mechanic', ...)` movido a `init` prioridad `0`
- bootstrap en `plugins_loaded` preservado
- objetivo: eliminar notice `_load_textdomain_just_in_time ... too early`
- Siguiente continuidad recomendada:
  - continuidad de `Fase 39` posterior a `39E` (siguiente subfase CRM comercial sobre alertas persistidas)
