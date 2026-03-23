PROJECT MEMORY — SUPER MECHANIC

Este archivo resume el estado estructural del proyecto para reconstrucción rápida de contexto por IA.

No reemplaza la documentación técnica completa.

==================================================
INFORMACIÓN GENERAL
===================

Proyecto:
Super Mechanic

Tipo:
Plugin WordPress

Propósito:
Sistema de gestión para:

* talleres mecánicos
* concesionarios
* gestión de vehículos
* seguimiento de procesos
* mantenimiento
* trámites administrativos
* cotizaciones
* facturación
* portal cliente

Stack técnico:

PHP
WordPress Plugin Architecture
MySQL
VSCode
Codex / ChatGPT

==================================================
ARQUITECTURA PRINCIPAL
======================

Arquitectura modular obligatoria:

Repository
Service
Controller
REST Controller (cuando aplique)
Admin UI
Shortcodes frontend

Reglas:

Repository → acceso a base de datos
Service → lógica de negocio
Controller → UI admin
Shortcodes → frontend cliente

Nunca colocar SQL fuera de Repository.

==================================================
ESTRUCTURA REAL DEL PLUGIN
==========================

super-mechanic/
super-mechanic.php
uninstall.php

includes/

class-plugin.php
class-roles.php
class-capabilities.php
class-admin-menu.php
class-settings.php
class-rest-api.php

Módulos principales:

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
attachments
communication
helpers
integrations

Arquitectura activa:

includes/*

Legacy (NO USAR):

includes/modules/*

==================================================
BASE DE DATOS PRINCIPAL
=======================

Tablas principales:

sm_clients
sm_vehicles
sm_client_vehicles

sm_flows
sm_flow_steps

sm_processes
sm_process_step_logs
sm_process_parts
sm_process_meta

sm_maintenance
sm_maintenance_parts
sm_maintenance_labor

sm_pre_delivery

sm_paperwork
sm_paperwork_items

sm_quotes
sm_quote_items

sm_invoices
sm_invoice_items
sm_payments

sm_attachments
sm_comments
sm_notifications

Fuente de verdad del schema:

docs/DATABASE_MAP.md

==================================================
MÓDULOS PRINCIPALES
===================

Core / Bootstrap
Security
Settings
Clients
Vehicles
Client-Vehicle Relations
Flows
Processes
Maintenance
Pre-Delivery
Paperwork
Dashboard
Reports
Quotes
Invoices
Payments
Attachments
Communication
Helpers
Integrations

==================================================
FLUJOS PRINCIPALES DEL SISTEMA
==============================

Cliente
→ Vehículo
→ Relación cliente-vehículo
→ Proceso

Proceso puede incluir:

Process
→ Maintenance
→ Pre-Delivery
→ Paperwork

Maintenance
→ Quote

Quote approved
→ Invoice

Invoice
→ Payments

Process
→ Attachments
→ Communication
→ Timeline

Cliente puede acceder a:

Process
Quote
Invoice
Attachments
Comments
Notifications

==================================================
DOCUMENTACIÓN DEL PROYECTO
==========================

ARCHITECTURE.md
docs/FINAL_ARCHITECTURE_MAP.md
docs/SYSTEM_MAP.md
docs/CURRENT_STATE.md
docs/DEV_GUIDE.md
docs/MODULE_REGISTRY.md
docs/DATABASE_MAP.md
docs/tasks/

AGENTS.md
readme.txt

La documentación debe actualizarse al finalizar cada fase.

==================================================
FUENTE DE VERDAD
================

Si existe diferencia entre:

* documentación
* código

La fuente de verdad siempre es:

EL CÓDIGO REAL DEL PROYECTO.

El estado del sistema debe reconstruirse desde:

docs/CURRENT_STATE.md

==================================================
REGLA ANTI ALUCINACIÓN
======================

Antes de generar código:

* verificar que el archivo exista
* verificar que la clase exista
* verificar que la tabla exista
* verificar que el módulo esté activo en includes/*

Si algo no puede verificarse en el código real:

indicarlo antes de implementar.

Nunca asumir implementaciones inexistentes.

==================================================
ACTUALIZACIÓN DE MEMORIA OPERATIVA
==================================

Versión real del plugin:

0.1.0

Versión real del schema:

1.9.0

Estado funcional consolidado:

- `reports` ya forma parte del bootstrap real
- el portal cliente está operativo con descargas seguras, timeline, comentarios y notificaciones
- el portal mecánico ya es operativo dentro del admin actual
- `Access_Control_Service` centraliza ownership y visibilidad
- `Process_Derived_State_Service` ya forma parte del runtime real
- Fases 12A, 12B, 12C, 12D, 12E, 13, 14, 14B, 15, 16, 17, 18, 19 y 20 ya tienen huella real en código y documentación

Deudas técnicas activas que no deben ocultarse:

- `Client_Vehicle_Service::transfer_vehicle()` sigue sin repositorio transaccional dedicado
- `Flow_Service::delete_flow()` y `Flow_Step_Service::reorder_steps()` siguen sin atomicidad dedicada
- `Report_Service` mantiene concentración de lógica y debe vigilarse si el módulo crece
- varios placeholders raíz siguen presentes pero no cableados al bootstrap real
- sigue faltando una ruta documental reusable para comprobantes automáticos de pago
