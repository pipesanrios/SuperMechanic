PLUGIN ROADMAP — SUPER MECHANIC

Fecha de consolidacion: 2026-03-30

Este roadmap mantiene continuidad desde Fase 0 y refleja el estado real implementado.
Si hay conflicto con otro documento, manda el codigo real.

==================================================
FASES FUNDACIONALES
==================================================

Fase 0 — Planificacion y arquitectura base
- Estado: COMPLETA

Fase 1 — Base plugin WordPress (bootstrap, activacion, schema inicial)
- Estado: COMPLETA

Fases 2-10 — Core operativo (clientes, vehiculos, relaciones, procesos, flujos, mantenimiento, predelivery, paperwork)
- Estado: COMPLETAS

Fase 11 — Client Portal base
- Estado: COMPLETA (base funcional consolidada)

==================================================
FASES DE CONSOLIDACION OPERATIVA
==================================================

Fase 12A-12E — Reports (operativo + financiero + consolidacion + hardening)
- Estado: COMPLETAS

Fase 13 — Integridad transaccional del nucleo
- Estado: COMPLETA

Fase 14 / 14B — Validacion funcional y estabilizacion
- Estado: COMPLETAS

Fase 15 — Sistema de pagos (modelo real sobre `sm_payments`)
- Estado: COMPLETA

Fase 16 — Automatizaciones y eventos operativos
- Estado: COMPLETA

Fase 17 — Ownership, visibilidad y control de acceso
- Estado: COMPLETA

Fase 18 — Portal mecanico real
- Estado: COMPLETA

Fase 19 — Workflow operativo configurable (transiciones endurecidas)
- Estado: COMPLETA

Fase 20 / 20B — Automatizacion documental y `payment_receipt` logico
- Estado: COMPLETAS

Fase 21 — Configuracion avanzada por negocio/taller
- Estado: COMPLETA

Fase 22 — Reportes avanzados operativos y financieros
- Estado: COMPLETA

Fase 23 — Client Portal premium con acciones reales
- Estado: COMPLETA

Fase 24 / 24B — Modernizacion visual admin/frontend
- Estado: COMPLETAS

Fase 25 — Scripts/checklist tecnico
- Estado: COMPLETA

Fase 26 / 26B — Panel de shortcodes + hardening pre-SaaS
- Estado: COMPLETAS

==================================================
FASES API INTERNA Y HARDENING PRE-SAAS
==================================================

Fase 27A — API cliente interna read-only
- Estado: COMPLETA

Fase 27B — API admin interna read-only
- Estado: COMPLETA

Fase 27C-A — Expansion read-only y normalizacion de payloads
- Estado: COMPLETA

Fase 27C-B — Writes internos minimos admin (status/comment de proceso)
- Estado: COMPLETA

Fase 28 — Centro financiero admin (Invoices/Payments)
- Estado: COMPLETA

Fase 29 — Expansion de reportes admin
- Estado: COMPLETA

Fase 30 — Tenancy base preparada
- Estado: COMPLETA

Fase 31A — Base local de licencias
- Estado: COMPLETA

Fase 31B — Base local de updates privadas
- Estado: COMPLETA

Fase 31C — Plan efectivo + feature flags centralizados
- Estado: COMPLETA

==================================================
FASES CITAS, INTEGRACIONES Y TENANCY ACTIVA
==================================================

Fase 32A — Modulo de citas
- Estado: COMPLETA

Fase 32B-1 — Feed ICS/iCal firmado
- Estado: COMPLETA

Fase 32B-2 — Google Calendar 1-way
- Estado: COMPLETA

Fase 32B-3A — Reconciliacion inbound controlada
- Estado: COMPLETA

Fase 32B-3B — Webhook Google + watch channels + renovacion
- Estado: COMPLETA

Fase 33 — Notificaciones multicanal base (in-app + email desacoplado)
- Estado: COMPLETA

Fase 34 — Automatizacion operativa avanzada (recordatorios citas)
- Estado: COMPLETA

Fase 35A — Activacion `business_id` en nucleo
- Estado: COMPLETA

Fase 35B — Enforcement tenant-aware transversal
- Estado: COMPLETA

Fase 35C — Operacion multi-store visible (`sm_businesses`)
- Estado: COMPLETA

==================================================
FASE API PUBLICA
==================================================

Fase 36A — API publica read-only (`super-mechanic-public/v1`)
- Estado: COMPLETA

Fase 36B — Webhooks outbound publicos (`sm_webhooks`, `sm_webhook_deliveries`)
- Estado: COMPLETA

Fase 36C-1 — Write publica minima: cancel appointment
- Estado: COMPLETA

Fase 36C-2 — Write publica minima: confirm appointment
- Estado: COMPLETA

==================================================
FASES OPERATIVAS POST API PUBLICA
==================================================

Fase 37A — Calendario operativo de citas (admin)
- Estado: COMPLETA

Subfases de cierre operativo:
- Fase 37A-1 — UX operativa de calendario (drag/drop, estado rapido, rollback): COMPLETA
- Fase 37A-2 — Hardening y refinamiento UX de calendario: COMPLETA
- Fase 37A-3 — Bloqueadores de consistencia operativa (cliente-vehiculos, procesos, tenancy): COMPLETA
- Fase 37A-4 — Consolidacion operativa pre-CRM: COMPLETA
- Fase 37A-5 — Ajustes operativos de estabilidad UX: COMPLETA
- Fase 37A-6 — UX operativa general + validacion runtime de timeline unificada por vehiculo: COMPLETA
- Bloque tecnico post 37A-3 — `HOTFIX-MEM-1` (fatal memory exhausted en cascadas de inicializacion): COMPLETO

Fase 38A-1 — Idioma / internacionalizacion base (ingles por defecto, limpieza visible ES/EN en pantallas clave)
- Estado: COMPLETA

Fase 38A-2 — Monedas / configuracion monetaria (listas configurables + consistencia multi-store)
- Estado: PARCIAL

Fase 38A-3 — Seguridad DB base (master password + export JSON protegido + reset protegido)
- Estado: PARCIAL

Fase 38A-3B — Export / Import operativo (CSV ZIP + Excel XML + import seguro JSON canónico)
- Estado: COMPLETA
- Decisiones consolidadas:
  - JSON canónico para backup/restauración
  - CSV/Excel solo para export operativo/humano
  - import soportado solo para JSON canónico
  - validación previa completa antes de transacción
  - rollback transaccional y preservación de baseline default business

Consolidado del bloque 38A (Plataforma):
- Cobertura funcional del bloque:
  - i18n/idioma base
  - moneda configurable por settings/filtro
  - seguridad DB administrativa con master password
  - backup/restauracion operativa con JSON canónico
- Estado global del bloque: COMPLETA CON OBSERVACIONES
- Observaciones abiertas:
  - 38A-2 sigue PARCIAL por validacion UI/manual pendiente o confirmacion explicita
  - 38A-3 sigue PARCIAL por validacion de email admin pendiente e inestabilidad externa registrada
  - 38A-3B se mantiene COMPLETA con limite explicito: import solo JSON canónico

Fase 38B-1 — Vinculacion comercial base (WooCommerce como fuente de productos)
- Estado: COMPLETA
- Alcance consolidado:
  - seleccion de productos Woo en quotes e invoices
  - persistencia por snapshot en `label` y `unit_price`
  - persistencia de `woo_product_id` en `reference_id`
  - maintenance sin cambio de schema: Woo solo para autofill manual de nombre/precio
  - comportamiento seguro con Woo activo e inactivo sin romper flujo manual
- Validacion de cierre:
  - runtime WordPress real con Woo activo: OK
  - runtime WordPress real con Woo inactivo: OK
  - no regresion de totales: OK
  - sin dependencia forzada de Woo: OK

Fase 38B-2 — Totales automaticos y consistencia comercial
- Estado: COMPLETA
- Alcance consolidado:
  - `line_total` normalizado en quotes/invoices con formula unica: `quantity * unit_price`
  - `recalculate_totals()` usa calculo coherente por item como fuente de verdad
  - saneamiento controlado de registros legacy con `line_total` inconsistente
  - normalizacion de tipo de item: `manual` se mapea a `custom`
  - Woo se mantiene snapshot-only (sin recalculo de precio en tiempo real)
- Validacion de cierre:
  - runtime WordPress real con Woo activo: OK
  - runtime WordPress real con Woo inactivo: OK
  - sin regresion de totales en quotes/invoices: OK
  - sin cambios de schema ni dependencia forzada de Woo: OK

Fase 38B-3 — Hardening comercial Woo
- Estado: COMPLETA
- Alcance consolidado:
  - reglas de integridad equivalentes en quotes e invoices para `item_type`, `reference_id`, `label`, `unit_price`, `quantity`
  - bloqueo de persistencia Woo incompleta en altas/ediciones con intención Woo explícita
  - saneamiento controlado de legacy inconsistente `woo_product -> custom` sin error duro al usuario actual
  - mensajes mínimos y claros para indisponibilidad Woo o snapshot inválido
  - preservación de snapshot-only (`reference_id`, `label`, `unit_price`) sin recalculo dinámico desde Woo
- Validacion de cierre:
  - runtime WordPress real con Woo activo: OK
  - runtime WordPress real con Woo inactivo: OK
  - saneamiento legacy controlado validado: OK
  - sin regresion de cálculo de totales/snapshots: OK

Consolidado del bloque 38B (Comercial / WooCommerce):
- Cobertura funcional del bloque:
  - WooCommerce como catálogo operativo de productos
  - quote/invoice con snapshot comercial (`reference_id=woo_product_id`, `label`, `unit_price`)
  - maintenance con autofill manual (sin vínculo estructural persistente)
  - normalización de `line_total = quantity * unit_price` y recálculo coherente por item
  - hardening de integridad y saneamiento legacy controlado
- Estado global del bloque: COMPLETA
- Límites vigentes:
  - sin integración de orders/checkout/pagos Woo en este bloque
  - sin sync de precios Woo en tiempo real
  - sin cambios de schema

Fase 38C-1 — Optimizacion operativa real (UX + flujos)
- Estado: COMPLETA
- Alcance consolidado:
  - quick actions operativas en dashboard (`Create process`, `Open maintenance`, `Create quote`, `Create invoice`)
  - atajos `Create process` en filas de clientes y vehiculos con contexto (`client_id`, `vehicle_id`) preservado
  - atajos de proceso a `maintenance`, `quote`, `invoice` y acciones rapidas de estado con feedback visual
  - refinamiento de finanzas con busqueda/labels/notices mas claros en invoices/payments
  - hotfix de cierre: `Create quote` en dashboard deja de compartir destino con `Open maintenance`
- Validacion de cierre:
  - php lint global: OK
  - structure-check: OK
  - technical-checklist: OK
  - sin cambios de schema ni arquitectura base

Fase 38C-2 — Estabilidad operativa y pulido fino
- Estado: COMPLETA
- Alcance consolidado:
  - unificacion de labels y acciones operativas en clientes/vehiculos/procesos para reducir ambiguedad
  - correccion de retorno de contexto en flujos de alta contextual:
    - `return_vehicle_id` en clientes
    - `return_client_id` en vehiculos
  - consistencia de columnas/acciones en procesos para navegacion mas clara
  - estandarizacion de labels en finanzas/pagos (`Amount`, `Reference`, `Notes`) sin tocar logica financiera
- Validacion de cierre:
  - php lint global: OK
  - validacion runtime manual WordPress real: CONFIRMADA POR USUARIO
  - sin cambios de schema ni arquitectura base

Fase 38D-1 — Reportes base (financieros y operativos)
- Estado: COMPLETA
- Alcance consolidado:
  - reportes financieros base (`total billed`, `total paid`, `pending`, `invoices`, `average ticket`)
  - reportes operativos base de procesos (`total`, por tipo, por estado, abiertos vs cerrados)
  - resumen por cliente (`total procesos`, `total facturado`, `total pagado`)
  - resumen por vehiculo (`total procesos`, `gasto acumulado`)
  - filtros globales simples por negocio y rango de fechas
- Validacion de cierre:
  - php lint global: OK
  - validacion runtime manual WordPress real: CONFIRMADA POR USUARIO
  - sin cambios de schema ni arquitectura base

Fase 38D-2 — Exportacion / presentacion de reportes
- Estado: COMPLETA
- Alcance consolidado:
  - export CSV por vista sobre el modulo `reports` existente
  - vistas de export operativas:
    - `financial_base`
    - `operational_base`
    - `client_summary`
    - `vehicle_summary`
    - `recent_*` existentes
  - consistencia entre UI y export desde la misma capa (`Report_Service`)
  - filtros activos compartidos entre vista y export (`business_id`, `date_from`, `date_to`)
  - pulido de labels/typos en reports dentro del alcance
- Validacion de cierre:
  - php lint global: OK
  - validacion runtime manual WordPress real: CONFIRMADA POR USUARIO
  - sin cambios de schema ni arquitectura base

Fase 38D-3 — KPIs y dashboards mas accionables
- Estado: COMPLETA
- Alcance consolidado:
  - KPIs accionables en `reports` para control rapido operativo/financiero:
    - open/closed processes
    - overdue invoices
    - outstanding by currency
    - recent payments
    - average ticket
    - top clients
    - top vehicles
    - operational load (por estado/tipo)
  - bloques de lectura rapida orientados a accion:
    - requiere atencion
    - pendiente de cobro
    - mas actividad / mas facturacion
    - estados criticos
  - filtros activos preservados y aplicados de forma consistente:
    - `business_id`
    - `date_from`
    - `date_to`
- Validacion de cierre:
  - php lint global: OK
  - validacion runtime manual WordPress real: CONFIRMADA POR USUARIO
  - sin cambios de schema ni arquitectura base

Consolidado del bloque 38D (Reportes y control):
- Cobertura funcional del bloque:
  - reportes financieros
  - reportes operativos
  - reportes por cliente
  - reportes por vehiculo
  - export CSV por vista
  - KPIs accionables
  - dashboards de control
- Estado global del bloque: COMPLETO
- Validacion global del bloque: runtime manual WordPress real confirmada por usuario en 38D-1/38D-2/38D-3
- Restricciones preservadas: sin cambios de schema ni arquitectura

==================================================
CONTINUIDAD POST 38 (RENumeracion OFICIAL)
==================================================

Justificacion de continuidad:
- el bloque `38` ya fue consumido por ejecucion real (`38A`, `38B`, `38C`, `38D`) y queda consolidado como historico cerrado
- CRM no debe quedar como `37B` porque rompe continuidad cronologica y funcional
- la evolucion natural posterior al bloque 38 consolidado es:
  - plataforma
  - comercial
  - operacion
  - reportes/control
  - luego CRM y automatizacion comercial
- hosting gestionado / WordPress dedicado corresponde a fase comercial/de despliegue posterior
- SaaS independiente queda como evolucion arquitectonica mayor final

Fase 39 — CRM y automatizacion comercial
- Estado: EN CURSO
- Objetivo:
  - evolucion funcional del producto hacia seguimiento comercial/cliente
- Incluye:
  - seguimiento de clientes
  - recordatorios
  - pipeline comercial
  - tareas/estados de seguimiento
  - automatizaciones simples comerciales

Subfases de Fase 39:
- Fase 39A — CRM base (tracking de clientes): COMPLETA
  - Alcance consolidado:
    - bloque CRM en create/edit de cliente
    - campos CRM: `crm_status`, `assigned_user_id`, `last_contact_at`, `next_follow_up_at`
    - notas comerciales separadas de notas tecnicas
    - persistencia en tabla auxiliar `sm_client_crm_meta`
    - sin cambios estructurales en `sm_clients`
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - create/edit cliente con CRM: OK
    - persistencia CRM: OK
    - sin regresiones reportadas: OK

- Fase 39B-1 — Pipeline CRM independiente (base + CRUD): COMPLETA
  - Alcance consolidado:
    - entidad comercial independiente `sm_crm_pipeline`
    - vinculacion estructural:
      - `client_id` obligatorio
      - `vehicle_id` opcional
      - `process_id` opcional
    - CRUD usable completo con vista detalle `View`
    - `phone`/`email` obtenidos por relacion con cliente (sin duplicacion)
    - quick create client desde CRM
    - quick stage operativo con capability/nonce/tenancy
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - CRUD, view, quick create y quick stage: OK
    - sin regresiones reportadas: OK

- Fase 39B-2 — Vista kanban del pipeline CRM: COMPLETA
  - Alcance consolidado:
    - kanban funcional por columnas de stage
    - cards por oportunidad en columna correspondiente
    - cambio de stage desde card reutilizando flujo validado de quick stage
    - sin drag/drop ni automatizaciones en esta subfase
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - kanban en columnas + quick stage: OK
    - CRUD previo sin regresion: OK

- Fase 39B-3 — Conversion operativa CRM -> proceso: COMPLETA
  - Alcance consolidado:
    - conversion explicita por accion del usuario (sin auto-conversion)
    - `Create process` y `Link existing process`
    - validaciones de link:
      - mismo `business_id`
      - mismo `client_id`
      - mismo `vehicle_id` si la oportunidad tiene vehiculo
    - reglas por tipo de proceso:
      - `maintenance`: requiere vehiculo
      - `pre_delivery`: permite sin vehiculo
      - `paperwork`: permite sin vehiculo
    - sin sync automatica de estados CRM/proceso y sin cambio automatico de stage CRM
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - conversion y vinculo operativo: OK
    - sin regresiones reportadas: OK

- Fase 39C-1 — Tareas y recordatorios CRM (base): COMPLETA
  - Alcance consolidado:
    - entidad de tareas CRM en `sm_crm_tasks` vinculada a oportunidad (`crm_pipeline_id`)
    - CRUD base de tareas en detalle de oportunidad CRM:
      - create
      - edit
      - complete
    - validaciones estrictas:
      - `status`: `pending`, `completed`, `cancelled`
      - `task_type`: `call`, `follow_up`, `meeting`, `quote`, `reminder`
      - oportunidad CRM valida y tenant-aware por `business_id`
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - create/edit/complete de tareas CRM: OK
    - tenancy de tareas CRM: OK
    - sin regresion del modulo CRM: OK

- Fase 39C-2 — Tareas vencidas / proximas / pendientes: COMPLETA
  - Alcance consolidado:
    - vistas operativas en CRM para:
      - `pending`
      - `overdue`
      - `upcoming`
    - clasificacion operacional mantenida en capa de servicio con consultas por fecha/estado en repository
    - `overdue` y `upcoming` tratados como subconjuntos de `pending`
    - tareas sin `due_at` visibles solo en `pending`
    - tenancy por `business_id` preservada en buckets, enlaces y contexto operativo
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - buckets operativos y contexto tenant-aware: OK
    - sin regresion de CRUD tasks/pipeline/kanban: OK

- Fase 39C-3 — Integracion CRM ↔ Calendar: COMPLETA
  - Alcance consolidado:
    - feed de calendario unificado sin endpoint nuevo, combinando:
      - `appointment`
      - `crm_task`
    - eventos CRM tipados con `event_type=crm_task`, `url`, `className` diferenciada y `extendedProps` utiles
    - click funcional por tipo en calendario:
      - cita -> detalle de cita
      - tarea CRM -> detalle de oportunidad CRM
    - `eventDrop` permitido solo para `appointment`; bloqueado/revertido para `crm_task`
    - tenancy por `business_id` y rango visible del calendario preservados
  - Validacion de cierre:
    - runtime WordPress manual real: CONFIRMADA POR USUARIO
    - calendario unificado + click por tipo: OK
    - bloqueo de drag/drop para `crm_task`: OK
    - sin regresion de calendario operativo de citas: OK

Consolidado del bloque 39B (Pipeline CRM):
- Cobertura funcional del bloque:
  - entidad independiente `sm_crm_pipeline`
  - CRUD usable + `View`
  - quick create client
  - quick stage
  - kanban por columnas
  - conversion operativa (`create process`, `link existing process`)
  - reglas por tipo de proceso (`maintenance`, `pre_delivery`, `paperwork`)
- Estado global del bloque: COMPLETO
- Validacion global del bloque: runtime manual WordPress real confirmada por usuario en 39B-1/39B-2/39B-3
- Restricciones preservadas:
  - sin cambios de schema adicionales fuera de `sm_crm_pipeline`
  - sin automatizaciones ni sync automatica CRM/proceso

Consolidado del bloque 39C (Tareas y seguimiento CRM):
- Cobertura funcional del bloque:
  - tareas CRM (`sm_crm_tasks`)
  - CRUD base de tareas
  - estados/tipos base de tarea
  - vistas operativas (`pending`, `overdue`, `upcoming`)
  - integracion con detalle de oportunidad CRM
  - integracion CRM ↔ Calendar en feed unificado (`appointment`, `crm_task`)
  - click funcional por tipo y `eventDrop` bloqueado para `crm_task`
- Estado global del bloque: COMPLETO
- Validacion global del bloque: runtime manual WordPress real confirmada por usuario en 39C-1/39C-2/39C-3
- Restricciones preservadas:
  - sin cron
  - sin email automatico
  - sin automatizacion compleja

Fase 40 — Hosting gestionado / WordPress dedicado
- Estado: PLANIFICADA
- Objetivo:
  - modelo comercial completo para clientes sin infraestructura propia
- Incluye:
  - WordPress dedicado por cliente
  - despliegue estandar
  - gestion de hosting
  - soporte centralizado

Fase 41 — SaaS independiente
- Estado: PLANIFICADA
- Objetivo:
  - version desacoplada de WordPress
- Incluye:
  - backend central
  - frontend propio
  - WordPress opcional o accesorio
  - migracion progresiva desde plugin

Alcance consolidado:
- menu admin `Super Mechanic -> Calendar`
- FullCalendar local (sin CDN)
- endpoint REST interno para eventos por rango visible
- cambio basico de estado desde calendario via `Appointment_Service`
- tenancy por `business_id` preservada
- sin cambios de schema

==================================================
SIGUIENTE CONTINUIDAD (NO CERRADA)
==================================================

La siguiente continuidad habilitada es la continuidad de `Fase 39` despues de `39C` (subfases CRM siguientes).

Estado de bloqueo tecnico:
- `HOTFIX-MEM-1` cerrado sobre arquitectura activa (`includes/*`) con correccion minima y sin cambios de schema.

Backlog recomendado para siguiente bloque:
- inicio del bloque CRM sobre base operativa cerrada en 37A-6
- UX/admin para API keys y webhooks publicos
- observabilidad avanzada de webhook deliveries
- validacion runtime WordPress formal de 36B/36C
- evoluciones futuras de tenancy/planes sin romper contratos actuales
