# CURRENT STATE — SUPER MECHANIC (OPTIMIZADO)

==================================================
PROYECTO
==================================================

Super Mechanic

Plugin WordPress modular para gestión de:

- talleres
- concesionarios
- vehículos
- procesos
- mantenimiento
- trámites
- cotizaciones
- facturación
- portal cliente

==================================================
VERSIONES
==================================================

plugin: 0.1.0  
schema: 1.9.0  

==================================================
ESTADO GENERAL
==================================================

Sistema funcional a nivel:

- admin
- portal cliente
- portal mecánico

Arquitectura estable con riesgos controlados.

Base técnica consolidada sobre:

- `includes/*` como arquitectura activa
- capa documental segura (`Document_Service`, `Download_Service`)
- ownership centralizado (`Access_Control_Service`)
- flujo transaccional base en procesos, relaciones y flows

==================================================
FASE ACTUAL
==================================================

Fase activa: 26B — hardening arquitectural pre-SaaS

Estado: COMPLETA

==================================================
FASES CONSOLIDADAS
==================================================

12A–12E → Reports  
13 → integridad transaccional  
14–14B → validación funcional  
15 → pagos  
16 → automatizaciones  
17 → ownership  
18 → portal mecánico  
19 → workflow avanzado  
20–20B → capa documental  
21 → settings  
22 → reports avanzados  
23 → portal cliente premium  
24–24B → UI/UX  
25 → scripts técnicos  
26 → panel shortcodes  
26B → hardening pre-SaaS  

==================================================
MÓDULOS ACTIVOS
==================================================

clients  
vehicles  
relations  
flows  
processes  
maintenance  
predelivery  
paperwork  
dashboard  
reports  
quotes  
invoices  
payments  
attachments  
communication  

==================================================
MÓDULOS PARCIALES / NO ACTIVOS
==================================================

- REST API → placeholder
- WooCommerce → scaffold
- includes/modules/* → legacy

==================================================
CAPACIDADES CLAVE DEL SISTEMA
==================================================

- portal cliente operativo (quotes, invoices, timeline, comments)
- portal mecánico operativo
- workflow configurable
- reportes operativos y financieros
- sistema de pagos consolidado
- documentos seguros + PDF bajo demanda
- `payment_receipt` lógico por payment
- UI moderna reusable (`sm-*`)
- scripts locales de validación técnica

==================================================
DEUDA TÉCNICA ACTIVA
==================================================

- REST API no implementada
- placeholders en core (rest-api, hooks, post-types)
- rutas admin de PDF aún fuera de `Download_Service`
- falta CI/CD real
- falta testing funcional en WordPress runtime
- `Report_Service` y `Process_Admin_Controller` pueden crecer demasiado

==================================================
RIESGOS ACTUALES
==================================================

- crecimiento de services tipo “god class”
- desviaciones en descargas seguras si no se respeta `Download_Service`
- dependencia fuerte del ownership cliente-vehículo
- posible desalineación documental si no se mantiene cierre disciplinado

==================================================
PENDIENTES INMEDIATOS
==================================================

- consolidación API base (Fase 27A)
- definición capa SaaS
- posible unificación de rutas PDF admin
- evaluación CI/CD
- control de crecimiento de reports

==================================================
FUENTE DE VERDAD
==================================================

1. código (`includes/*`)
2. docs técnicos
3. contextos AI

==================================================
NOTA
==================================================

El historial detallado de fases y cambios técnicos debe consultarse en:

- ARCHITECTURE.md
- docs/tasks/
- commits del proyecto