# CURRENT STATE — SUPER MECHANIC

## Proyecto

- Proyecto: Super Mechanic
- Version interna actual:
  - plugin: `0.1.0`
  - schema: `1.9.0`

## Estado general

- Estado del sistema: funcional a nivel admin, portal cliente y portal mecánico.
- Arquitectura activa: `includes/*`
- Nivel actual: pre-SaaS
- Estabilidad: estable con riesgos controlados
- Seguridad documental: operativa mediante `Document_Service` + `Download_Service`
- Ownership y visibilidad: centralizados en `Access_Control_Service`

## Fase actual

- Fase operativa actual: **26B — hardening arquitectural pre-SaaS**
- Estado: **completa**

## Fases consolidadas

- 12A–12E → Reports
- 13 → Integridad transaccional
- 14–14B → Validación funcional
- 15 → Pagos
- 16 → Automatizaciones y eventos
- 17 → Ownership y visibilidad
- 18 → Portal mecánico
- 19 → Workflow operativo avanzado
- 20–20B → Capa documental y `payment_receipt`
- 21 → Settings avanzados
- 22 → Reportes avanzados
- 23 → Portal cliente premium
- 24–24B → Modernización UI/UX
- 25 → Scripts técnicos de validación
- 26 → Panel de shortcodes
- 26B → Hardening pre-SaaS

## Módulos activos

- Core / Bootstrap
- Security
- Settings
- Clients
- Vehicles
- Client-Vehicle Relations
- Flows
- Processes
- Maintenance
- Pre-Delivery
- Paperwork
- Dashboards
- Reports
- Quotes
- Invoices
- Payments
- Attachments / Timeline
- Communication / Notifications
- Documents / PDF / Secure Downloads
- Client Portal

## Módulos parciales o no activos

- REST API: placeholder / no conectada al bootstrap real
- WooCommerce integration: scaffold técnico, no integrado funcionalmente
- `includes/modules/*`: legacy
- Placeholders raíz:
  - `includes/class-rest-api.php`
  - `includes/class-hooks.php`
  - `includes/class-post-types.php`

## Capacidades clave ya operativas

- Portal cliente con quotes, invoices, timeline, comments, notifications y documentos seguros
- Portal mecánico operativo dentro del admin
- Workflow configurable con validación de transiciones
- Reportes operativos y financieros avanzados
- Sistema de pagos consolidado sobre `sm_payments`
- `payment_receipt` lógico por `payment_id`
- UI moderna reusable (`sm-*`)
- Scripts locales de validación técnica en `scripts/`

## Deuda técnica activa

- REST API todavía no implementada
- Las rutas admin de PDF de quotes e invoices siguen como excepción controlada y no pasan todavía por `Download_Service`
- `Process_Admin_Controller` y `Report_Service` siguen siendo puntos a vigilar si el sistema crece
- No existe CI/CD real todavía
- No hay pruebas funcionales WordPress runtime automatizadas
- La UI legacy de settings sigue coexistiendo con `sm_settings` mediante compatibilidad legacy

## Riesgos actuales

- Reintroducción de lógica en controllers
- Crecimiento excesivo de services tipo “god class”
- Desalineación documental si no se ejecuta cierre disciplinado de fase
- Reaparición de `file_url` directo en nuevos entry points
- Confusión por capas legacy si se vuelven a usar

## Pendientes inmediatos

- Fase 24C — UX fixes críticos post smoke test
- Fase 27A — API base / REST pre-SaaS
- Evaluar consolidación de rutas admin PDF sobre la misma capa documental segura
- Evaluar CI/CD real en fase futura
- Mantener sincronizados docs técnicos base y contextos AI

## Fuente de verdad

Prioridad:

1. código real (`includes/*`)
2. `ARCHITECTURE.md`
3. `docs/SYSTEM_MAP.md`
4. `docs/FINAL_ARCHITECTURE_MAP.md`
5. `docs/MODULE_REGISTRY.md`
6. `docs/DATABASE_MAP.md`

## Nota

El detalle histórico de implementación por fase debe consultarse en:

- `ARCHITECTURE.md`
- `docs/tasks/`
- historial del repositorio