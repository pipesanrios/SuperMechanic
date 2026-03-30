# CURRENT STATE — SUPER MECHANIC

Fecha de consolidacion: 2026-03-30

## Versiones reales

- Plugin: `0.1.0` (`super-mechanic.php`)
- Schema: `1.16.0` (`includes/database/class-schema.php`)

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
- Faltan tests automatizados de regresion para detectar bucles de inicializacion/cascadas de servicios antes de runtime WordPress
- 38A-2 pendiente de validacion runtime/UI manual final o confirmacion explicita de cierre
- 38A-3 pendiente de validacion formal del envio email al admin en flujo de master password
- 38A-3 mantiene inestabilidad externa registrada fuera del control de logica del plugin

## Siguiente fase real

- Siguiente fase habilitada: `Fase 39 — CRM y automatizacion comercial`.
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
