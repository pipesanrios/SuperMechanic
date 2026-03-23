AI CONTEXT — SUPER MECHANIC

Este archivo proporciona contexto técnico rápido para agentes de IA
que operan dentro del entorno de desarrollo (VSCode).

El objetivo es permitir que el agente entienda la arquitectura
del proyecto sin necesidad de leer toda la documentación completa.

==================================================
PROYECTO
==================================================

Nombre:
Super Mechanic

Tipo:
Plugin WordPress

Propósito:

Sistema modular para gestión de:

- talleres mecánicos
- concesionarios
- procesos de vehículos
- mantenimiento
- trámites administrativos
- cotizaciones
- facturación
- portal cliente

==================================================
ARQUITECTURA
==================================================

El plugin sigue arquitectura modular basada en:

Repository
Service
Controller
Shortcodes
REST Controller (cuando aplique)

Responsabilidades:

Repository
→ acceso a base de datos

Service
→ lógica de negocio

Controller
→ UI admin

Shortcodes
→ frontend cliente

Regla crítica:

SQL solo permitido en Repository.

==================================================
ESTRUCTURA DEL PLUGIN
==================================================

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

La arquitectura activa está en:

includes/*

La carpeta:

includes/modules/*

es legacy y no debe modificarse.

==================================================
BASE DE DATOS
==================================================

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

==================================================
FLUJO PRINCIPAL
==================================================

Cliente
→ Vehículo
→ Relación cliente-vehículo
→ Proceso

Proceso
→ Maintenance
→ Quote
→ Invoice
→ Payment

Durante el proceso pueden existir:

attachments
comments
notifications
timeline de pasos

==================================================
REGLAS CRÍTICAS
==================================================

Nunca colocar SQL fuera de Repository.

No modificar:

includes/modules/*

Validar siempre:

- permisos
- ownership
- sanitización
- escaping

==================================================
DESCARGAS SEGURAS
==================================================

Nunca exponer file_url directo.

Documentos deben descargarse mediante:

Download_Service
Document_Service

Esto aplica a:

quotes
invoices
attachments
PDF

==================================================
ESTADO OPERATIVO ACTUAL
==================================================

Versión real del plugin:

- plugin: 0.1.0
- schema: 1.9.0

Fases ya consolidadas en código:

- 12A, 12B, 12C, 12D y 12E: reports
- 13: integridad transaccional base de processes
- 14: validación funcional y escenarios
- 14B: endurecimiento mínimo de quotes/timeline y cierre de escenarios críticos
- 15: sistema de pagos consolidado sobre `sm_payments`
- 16: automatizaciones y eventos operativos
- 17: control de acceso, visibilidad y ownership
- 18: portal mecánico real
- 19: workflow operativo configurable avanzado
- 20: automatización documental y estados derivados seguros

Deuda técnica viva:

- `includes/modules/*` sigue como legacy y no debe entrar en nueva implementación
- `includes/class-rest-api.php`, `includes/class-assets.php`, `includes/class-hooks.php` y `includes/class-post-types.php` siguen como placeholders/no activos
- `Client_Vehicle_Service::transfer_vehicle()` y `Flow_Service::delete_flow()` / `Flow_Step_Service::reorder_steps()` todavía no tienen una frontera transaccional dedicada
- `Report_Service` sigue siendo grande y debe vigilarse si el módulo crece
- falta una ruta documental reusable y deduplicada para comprobantes automáticos de pago por `payment_id`

==================================================
DOCUMENTACIÓN COMPLETA
==================================================

Ver documentación completa en:

docs/

ARCHITECTURE.md
SYSTEM_MAP.md
CURRENT_STATE.md
MODULE_REGISTRY.md
DATABASE_MAP.md
DOMAIN_MODEL.md
PLUGIN_ROADMAP.md
PLUGIN_LIFECYCLE.md
TEST_SCENARIOS.md

==================================================
REGLA FINAL
==================================================

Si existe diferencia entre:

documentación
y
código

la fuente de verdad es siempre el código real.
