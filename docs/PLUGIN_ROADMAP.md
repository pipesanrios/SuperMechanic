PLUGIN ROADMAP — SUPER MECHANIC

Este documento define la evolución real y planificada del plugin Super Mechanic.

Sirve como referencia oficial de continuidad del sistema desde Fase 0
y como control para evitar implementaciones fuera de alcance.

==================================================
OBJETIVO DEL PLUGIN
==================================================

Super Mechanic es un sistema modular para:

- talleres mecánicos
- concesionarios
- gestión de vehículos
- seguimiento de procesos
- mantenimiento
- trámites administrativos
- cotizaciones
- facturación
- pagos
- gestión documental
- agenda de citas
- integraciones externas
- Client Portal

El sistema opera dentro del ecosistema WordPress,
con arquitectura modular y orientada a futura evolución SaaS.

==================================================
FASE 0 — PLANIFICACIÓN Y ARQUITECTURA
==================================================

Definición del sistema:

- modelo de datos base
- arquitectura Controller → Service → Repository
- separación de responsabilidades
- decisión de WordPress como base
- definición de módulos principales

Estado:
completada

==================================================
FASE 1 — BASE DEL PLUGIN
==================================================

- estructura del plugin
- bootstrap
- activación/desactivación
- creación de tablas
- roles y capabilities
- admin base

Estado:
completada

==================================================
FASES 2–10 — CORE OPERATIVO
==================================================

Incluye:

- clientes
- vehículos
- relación cliente-vehículo
- procesos
- flujos configurables
- mantenimiento
- pre-entrega
- trámites
- notas técnicas
- asignación de mecánicos
- base de operación diaria

Estado:
completadas

==================================================
FASE 11 — CLIENT PORTAL
==================================================

Portal seguro para clientes:

- procesos
- cotizaciones
- facturas
- pagos
- documentos
- timeline

Estado:
implementado (base funcional)

==================================================
FASE 12 — REPORTES Y ANALÍTICA
==================================================

12A–12E completadas:

- reportes operativos
- reportes financieros
- exportación CSV
- consolidación
- optimización base

Pendiente:
- BI avanzado
- gráficos
- cache avanzada

==================================================
FASE 13 — SISTEMA DOCUMENTAL
==================================================

- Document_Service
- Download_Service
- gestión de adjuntos
- seguridad documental

Estado:
implementado parcialmente

==================================================
FASE 14 — REST API
==================================================

Estado:
placeholder

==================================================
FASE 15 — WOOCOMMERCE
==================================================

Estado:
parcial (scaffold)

==================================================
FASE 16 — AUTOMATIZACIÓN
==================================================

- eventos base
- automatización operativa inicial

Estado:
implementada en base

==================================================
FASE 17 — SEGURIDAD Y OWNERSHIP
==================================================

- Access_Control_Service
- visibilidad
- ownership estructural

Estado:
completada

==================================================
FASES 18–20 — OPERACIÓN REAL
==================================================

- Mechanic Panel
- workflow endurecido
- automatización documental
- estados derivados

Estado:
completadas

==================================================
FASES 21–26 — CONSOLIDACIÓN INTERNA
==================================================

- estabilidad
- mejoras internas
- scripts
- base CI/CD inicial
- optimizaciones

Estado:
completadas

==================================================
FASE 27 — PREPARACIÓN API / SAAS
==================================================

- auditoría del sistema
- normalización de arquitectura
- preparación para integraciones externas

Estado:
completada

==================================================
FASES 28–31 — HARDENING
==================================================

Incluye:

- seguridad
- control de acceso reforzado
- validaciones
- limpieza arquitectural
- estabilidad operativa

Estado:
completadas

==================================================
FASE 32 — SISTEMA DE CITAS
==================================================

32A:
- CRUD de citas
- relación con cliente/vehículo
- asignación de mecánico
- filtros admin

32B-1:
- exportación ICS (calendar feed seguro)

32B-2:
- integración Google Calendar (1-way)

32B-3A:
- reconciliación inbound controlada

32B-3B:
- webhook Google Calendar
- watch channels
- idempotencia
- renovación automática

Estado:
completada

==================================================
FASE 33 — SISTEMA DE COMUNICACIÓN
==================================================

Objetivo:

- consolidar motor de notificaciones existente
- unificar eventos
- soportar múltiples canales

Incluye:

- Event Dispatcher
- Notification Service
- canal interno (in-app)
- primer canal externo (email desacoplado)

Estado:
completada

==================================================
FASE 34 — AUTOMATIZACIÓN OPERATIVA AVANZADA
==================================================

Objetivo:

- automatización operativa controlada sobre eventos existentes
- recordatorios automáticos de citas sin motor complejo
- ejecución programada mínima con deduplicación

Incluye:

- `Automation_Service` + `Automation_Rule_Engine` (reglas simples)
- scheduler de recordatorios de citas con `wp_cron`
- trigger `appointment_reminder` integrado al dispatcher central
- toggles mínimos de automatización en settings

Estado:
completada

==================================================
FASE 35 — ACTIVACIÓN MULTI-STORE / MULTI-TENANT
==================================================

35A (base controlada) — COMPLETADA:

- activación real de `business_id` en núcleo mínimo transaccional
- tablas incluidas:
  - `sm_clients`
  - `sm_vehicles`
  - `sm_client_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- estrategia legacy:
  - `business_id = 1` por defecto
  - backfill idempotente explícito separado de services operativos
- filtros base tenant-aware aplicados solo en lecturas núcleo de 35A

35B (completada):

- expansión tenant-aware a entidades no incluidas en 35A:
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
  - `sm_appointments`
  - `sm_appointment_calendar_sync`
  - `sm_process_step_logs`
  - `sm_quote_items`
  - `sm_invoice_items`
- endurecimiento transversal de aislamiento en dashboard/reportes/API

35C (completada):

- incorporación de entidad real `sm_businesses` con negocio legacy default (`id=1`)
- activación de contexto de negocio visible con resolución por prioridad:
  - selector admin por usuario (`user meta`)
  - fallback `sm_settings.business.business_id`
  - fallback final negocio default (`id=1`)
- CRUD admin básico de negocios en módulo dedicado `includes/businesses/*`
- validación y reparación idempotente de huérfanos `business_id` sobre tablas tenant-aware
- base operativa visible de multi-store sin abrir billing SaaS ni multi-login complejo

Restricciones mantenidas en Fase 35:

- no crear tabla `businesses` en 35A
- no cerrar branding/billing/multi-login complejo en esta fase
- no alterar numeradores globales `quote_number`/`invoice_number` en 35A/35B/35C

==================================================
PRÓXIMAS FASES
==================================================

- REST API real
- WooCommerce completo
- sistema documental avanzado (PDF engine)
- auditoría avanzada
- firma digital
- almacenamiento externo
- integraciones adicionales (Outlook, etc.)
- CI/CD completo

==================================================
REGLAS DEL ROADMAP
==================================================

- este documento define la continuidad oficial del sistema
- debe mantenerse alineado con el código real
- no duplicar CURRENT_STATE
- no mezclar historia con estado actual

Los agentes de IA:

- no deben implementar fuera de este roadmap
- deben validar coherencia antes de ejecutar cambios
- deben reportar desalineaciones detectadas

Si una funcionalidad nueva aparece:

- debe justificarse
- debe evaluarse impacto
- debe confirmarse antes de implementarse
