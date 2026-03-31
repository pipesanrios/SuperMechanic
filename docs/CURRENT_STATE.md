# CURRENT STATE — SUPER MECHANIC

Fecha de consolidacion: 2026-03-31

## Versiones reales

- Plugin: `0.1.0` (`super-mechanic.php`)
- Schema: `1.19.0` (`includes/database/class-schema.php`)

## Estado general

- Estado: ESTABLE CON RIESGOS CONTROLADOS
- Bloque actual real: `Fase 39 (CRM y automatizacion comercial)` en continuidad activa
- Estado de bloque 38D: COMPLETO
- Estado de bloque 38A: COMPLETA CON OBSERVACIONES
- Subfases 38A:
  - `38A-1`: COMPLETA (i18n/idioma base)
  - `38A-2`: PARCIAL (validacion UI manual final pendiente o confirmacion explicita)
  - `38A-3`: PARCIAL (email admin no validado + inestabilidad externa registrada)
  - `38A-3B`: COMPLETA (export/import operativo)
- Subfases 38B:
  - `38B-1`: COMPLETA (vinculacion comercial base WooCommerce con snapshot en quotes/invoices y autofill manual en maintenance)
  - `38B-2`: COMPLETA (totales comerciales consistentes para items manuales y Woo con snapshot-only y saneamiento legacy controlado)
  - `38B-3`: COMPLETA (hardening comercial Woo en quotes/invoices con validaciones de integridad equivalentes, saneamiento legacy y mensajes claros de Woo no disponible)
- Subfases 38C:
  - `38C-1`: COMPLETA (optimizacion operativa UX en dashboard/clientes/vehiculos/procesos/finanzas con atajos, labels y feedback visual sin cambios de schema)
  - `38C-2`: COMPLETA (estabilidad operativa y pulido fino con labels/acciones unificadas, retorno de contexto corregido y consistencia en procesos/finanzas)
- Subfases 38D:
  - `38D-1`: COMPLETA (reportes base financieros y operativos con resumen por cliente/vehiculo, filtros globales por negocio/fecha y agregaciones controladas sin doble conteo)
  - `38D-2`: COMPLETA (exportacion/presentacion operativa de reportes con CSV por vista, coherencia vista/export y filtros activos compartidos)
  - `38D-3`: COMPLETA (KPIs y bloques accionables en reports con lectura rapida, tablas cortas y carga operativa/financiera filtrada por negocio/fechas)
- Bloque tecnico post-cierre: `HOTFIX-MEM-1` COMPLETO (fatal `Allowed memory size exhausted` en cascadas de servicios)
- Runtime WordPress real para 37A-6: confirmado (validacion manual UI real + runtime backend, incluyendo timeline unificada de vehiculo con proceso+cita+mantenimiento)
- Runtime tecnico para 38A-1: validacion de cierre ejecutada (barrido dirigido final en list tables/flujo de facturacion + smoke backend `tmp-runtime-check.php` + `php-lint` global limpio)
- Runtime tecnico para 38A-2: validacion tecnica ejecutada (`php-lint` global + barrido dirigido de hardcode monetario). Validacion UI manual pendiente.
- Runtime tecnico para 38A-3: validacion base de seguridad DB ejecutada (master password + export JSON protegido + reset protegido). Pendiente validacion formal de envio email admin y se mantiene registro de inestabilidad externa.
- Runtime tecnico para 38A-3B: validacion tecnica y runtime manual dirigidas ejecutadas (export JSON/CSV ZIP/Excel XML + import JSON seguro con validacion previa y rollback transaccional).
- Runtime WordPress real para 38B-1: validacion ejecutada en modo Woo activo e inactivo (selector condicional, snapshot `reference_id/label/unit_price` en quote/invoice, autofill manual en maintenance, sin regresion de totales y sin dependencia forzada de Woo).
- Runtime WordPress real para 38B-2: validacion ejecutada en modo Woo activo e inactivo (normalizacion `line_total = quantity * unit_price`, `recalculate_totals()` por formula coherente por item, mapeo `manual -> custom`, sin recalculo de precios desde Woo y sin regresiones en totales).
- Runtime WordPress real para 38B-3: validacion ejecutada sobre hardening comercial Woo (manual/Woo valido/Woo invalido, saneamiento legacy a `custom`, mensaje especifico `WooCommerce not available` en indisponibilidad de catalogo, sin regresion de snapshot/totales).
- Runtime tecnico para 38C-1: validacion ejecutada con checklist operativo por codigo + lint global + structure-check + technical-checklist y hotfix puntual de quick action `Create quote`.
- Runtime WordPress real para 38C-2: confirmado por usuario (checklist runtime manual real completado en clientes/vehiculos/procesos/finanzas y no regresion operacional).
- Runtime WordPress real para 38D-1: confirmado por usuario (validacion manual de reportes base financieros/operativos, cliente/vehiculo, filtros por negocio/fechas y coherencia de datos).
- Runtime WordPress real para 38D-2: confirmado por usuario (validacion manual de export CSV por vista: `financial_base`, `operational_base`, `client_summary`, `vehicle_summary`, `recent_*`, con coherencia vista/export y filtros activos).
- Runtime WordPress real para 38D-3: confirmado por usuario (validacion manual de KPIs accionables: open/closed processes, overdue invoices, outstanding by currency, recent payments, average ticket, top clients/vehicles, operational load y filtros activos funcionando).
- Runtime WordPress real de bloque 38D: confirmado (subfases 38D-1, 38D-2 y 38D-3 validadas manualmente por usuario).
- Runtime WordPress real para 39A: confirmado por usuario (create/edit cliente con bloque CRM, persistencia en `sm_client_crm_meta`, sin regresiones reportadas).
- Subfases 39:
  - `39A`: COMPLETA (CRM base sobre clientes con `crm_status`, `assigned_user_id`, `last_contact_at`, `next_follow_up_at` y `commercial_notes` separadas de notas tecnicas).
  - `39B-1`: COMPLETA (pipeline CRM independiente en `sm_crm_pipeline`, CRUD usable, `View`, quick stage, quick create client, phone/email por relacion con cliente y tenancy por `business_id`).
  - `39B-2`: COMPLETA (vista kanban funcional por columnas, cards por stage, quick stage en kanban y consistencia visual operativa sin alterar CRUD).
  - `39B-3`: COMPLETA (conversion operativa explicita `create process` / `link existing process`, con reglas de validacion por tipo de proceso y sin sync automatica CRM-proceso).
  - `39C-1`: COMPLETA (tareas y recordatorios CRM base en `sm_crm_tasks`, create/edit/complete por oportunidad CRM, validaciones de `status` y `task_type`, tenancy por `business_id`).
  - `39C-2`: COMPLETA (vistas operativas de tareas `pending`/`overdue`/`upcoming` en CRM, con clasificacion por `due_at` + `status`, tenancy por `business_id` y tareas sin `due_at` solo en `pending`).
  - `39C-3`: COMPLETA (integracion CRM ↔ Calendar con feed unificado tipado `appointment|crm_task`, click por tipo funcional y `eventDrop` bloqueado/revertido para `crm_task`).
  - `39D-1`: COMPLETA (automatizacion comercial basica interna y controlada: auto-tarea inicial idempotente en alta, sugerencias en `contacted/quoted` sin auto-creacion extra, señal de conversion pendiente en `won` sin `process_id`, y señales de overdue/inactividad en UI sin cron/email).
  - `39D-2`: COMPLETA (refinamiento operativo CRM en filtros y control visual: filtro SQL real por `assigned_user_id`, filtros combinables `stage`/`search`/`requires_attention`/`overdue`, jerarquia visual con `Overdue` critico y `Attention` warning, y preservacion de contexto/filtros en quick stage).
  - `39E-1`: COMPLETA (scheduler interno CRM con `WP-Cron`: hook `sm_crm_scheduler_tick`, frecuencia 10 min, alta en activacion, limpieza en desactivacion, logging debug y hook extensible `sm_crm_scheduler_tick_executed`).
  - `39E-2`: COMPLETA (persistencia de alertas CRM en `sm_crm_alerts`, recálculo por lotes en scheduler, deduplicación funcional de alertas activas por tipo/pipeline y resolución a `resolved` con validación runtime WordPress real confirmada).
  - `39E-3`: COMPLETA (consumo UI de alertas persistidas como fuente principal en list/kanban/view, consulta por lote sin N+1 y fallback runtime controlado cuando no hay alerta persistida).
- Estado de bloque 39B: COMPLETO (pipeline CRM consolidado)
- Runtime WordPress real de bloque 39B: confirmado por usuario (39B-1/39B-2/39B-3).
- Runtime WordPress real para 39C-1: confirmado por usuario (create/edit/complete de tareas CRM, tenancy correcta y sin regresion del modulo CRM).
- Runtime WordPress real para 39C-2: confirmado por usuario (bloques operativos `pending`/`overdue`/`upcoming`, enlaces/contexto tenant-aware y no regresion de CRM tasks/pipeline/kanban).
- Runtime WordPress real para 39C-3: confirmado por usuario (calendario unificado con `appointments` + `crm_tasks`, click funcional por tipo y bloqueo de `eventDrop` para `crm_task`).
- Runtime WordPress real para 39D-1: confirmado por usuario (alta de oportunidad con auto-tarea inicial idempotente, sugerencias en `contacted/quoted`, señal de conversion pendiente en `won` sin `process_id`, señales overdue/inactividad y no regresion de CRM/pipeline/tasks/kanban/calendar/conversion).
- Runtime WordPress real para 39D-2: confirmado por usuario (filtros operativos por `assigned_user_id`/`stage`/`search`/`requires_attention`/`overdue`, priorizacion visual `Overdue` critico sobre `Attention`, preservacion de contexto en quick stage y no regresion general).
- Runtime WordPress real para 39E-2: confirmado por usuario (tick scheduler, creacion/actualizacion/resolucion de alertas persistidas por lotes, sin duplicacion activa por tipo/pipeline y sin regresion de pipeline/tasks/calendar/scheduler).
- Runtime WordPress real para 39E-3: confirmado por usuario (consumo UI persistido en list/kanban/view, prioridad visual correcta, fallback controlado coherente y no regresion de filtros/quick stage/tasks/calendar).
- Restricciones consolidadas 39B:
  - sin cambios de schema adicionales fuera de `sm_crm_pipeline`
  - sin automatizaciones ni sincronizacion automatica CRM/proceso
- Restricciones consolidadas 39C-1:
  - sin cron, sin email y sin recordatorios automaticos
  - sin dashboard de vencidas en esta subfase
  - sin borrado en cascada automatica (deuda controlada)
- Estado de bloque 39C: COMPLETO (tareas CRM y seguimiento consolidado)
- Runtime WordPress real de bloque 39C: confirmado por usuario (39C-1/39C-2/39C-3)
- Restricciones consolidadas 39C:
  - sin cron
  - sin email automatico
  - sin automatizacion compleja
- Restricciones consolidadas 39D-1:
  - sin cron
  - sin email automatico
  - sin automatizacion externa/agresiva (solo interna y controlada)
- Restricciones consolidadas 39D-2:
  - sin cron
  - sin email automatico
  - sin cambios de schema
  - sin regresion de CRUD pipeline/tasks/kanban/calendar/conversion
- Restricciones consolidadas 39E-2:
  - sin email automatico
  - sin notificaciones externas
  - sin UI nueva grande
  - recálculo controlado por lotes (sin procesamiento agresivo por tick)
- Restricciones consolidadas 39E-3:
  - sin cambios de schema
  - sin nuevas automatizaciones
  - sin cron/email/notificaciones externas adicionales
  - consumo UI basado en persistido con fallback runtime controlado
- Estado de bloque 39E: COMPLETO (scheduler + persistencia + consumo UI)
- Runtime WordPress real de bloque 39E: confirmado por usuario (39E-1/39E-2/39E-3)

- Hotfix i18n reciente:
  - carga de textdomain `super-mechanic` movida a `init` prioridad `0` (`sm_load_textdomain`)
  - bootstrap funcional en `plugins_loaded` preservado
  - objetivo: eliminar notice `_load_textdomain_just_in_time ... too early`

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
- `crm`
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
- Faltan tests automatizados de regresion para detectar bucles de inicializacion/cascadas de servicios antes de runtime WordPress
- 38A-2 pendiente de validacion runtime/UI manual final o confirmacion explicita de cierre
- 38A-3 pendiente de validacion formal del envio email al admin en flujo de master password
- 38A-3 mantiene inestabilidad externa registrada fuera del control de logica del plugin

## Siguiente fase real

- Siguiente fase habilitada: continuidad de `Fase 39 — CRM y automatizacion comercial`.
- Siguiente fase recomendada: `39F-1` (acciones comerciales asistidas sobre alertas persistidas: bandeja operativa y reglas de atencion por SLA sin email ni notificaciones externas).
- Continuidad oficial post-38:
  - `Fase 39` — CRM y automatizacion comercial
  - `Fase 40` — Hosting gestionado / WordPress dedicado
  - `Fase 41` — SaaS independiente
- Justificacion de renumeracion:
  - el bloque `38` ya fue consumido y consolidado (`38A` a `38D`)
  - CRM deja de referenciarse como `37B` para mantener continuidad historica y funcional
- Condicion de continuidad: el bloqueante `HOTFIX-MEM-1` queda cerrado y no bloquea retomar roadmap.
- Backlog inmediato recomendado:
  - cierre formal pendiente de 38A-2/38A-3 (validaciones manuales restantes)
  - continuidad del bloque `Fase 39` con subfases CRM posteriores a `39A`
  - UX/admin de API keys y webhooks publicos
  - observabilidad avanzada de entregas webhook
  - consolidacion final de checklist runtime 36B/36C
