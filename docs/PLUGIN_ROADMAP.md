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

La siguiente continuidad habilitada es `FASE 38B-2 — Comercial / WooCommerce`.

Estado de bloqueo tecnico:
- `HOTFIX-MEM-1` cerrado sobre arquitectura activa (`includes/*`) con correccion minima y sin cambios de schema.

Backlog recomendado para siguiente bloque:
- inicio del bloque CRM sobre base operativa cerrada en 37A-6
- UX/admin para API keys y webhooks publicos
- observabilidad avanzada de webhook deliveries
- validacion runtime WordPress formal de 36B/36C
- evoluciones futuras de tenancy/planes sin romper contratos actuales
