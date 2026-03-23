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
SHORTCODES ACTIVOS
==================================================

Actualmente solo existen shortcodes de cliente:

- sm_client_dashboard
- sm_client_vehicles
- sm_client_processes
- sm_client_process_documents
- sm_client_process_timeline
- sm_client_quotes
- sm_client_quote_detail
- sm_client_quote_action
- sm_client_invoices
- sm_client_invoice_detail
- sm_client_process_comments
- sm_client_process_comment_form
- sm_client_notifications

No existen aún shortcodes para:

- portal mecánico
- contexto público/general

La fuente de verdad son las clases:

- includes/dashboard/class-client-dashboard-shortcodes.php
- includes/attachments/class-client-attachment-shortcodes.php
- includes/quotes/class-client-quote-shortcodes.php
- includes/invoices/class-client-invoice-shortcodes.php
- includes/communication/class-client-comment-shortcodes.php

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
SCRIPTS DE VALIDACIÓN TÉCNICA
==================================================

Ubicación:

scripts/

Incluye:

- php-lint.php
- structure-check.php
- technical-checklist.php

Propósito:

- validar sintaxis PHP
- validar estructura del plugin
- ejecutar checklist técnico antes del cierre de fase

Estos scripts son base local para futura integración CI/CD.

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
- 21: configuración avanzada por taller / negocio
- 22: reportes operativos y financieros avanzados
- 23: portal cliente premium con acciones reales
- 24: modernización visual integral UI/UX
- 24B: cobertura visual restante de paneles admin
- 25: automatización del checklist en scripts / CI como base local reusable para validación técnica
- 26: panel / catálogo de shortcodes (admin UI informativa sobre shortcodes activos)

Deuda técnica viva:

- `includes/modules/*` sigue como legacy y no debe entrar en nueva implementación
- `includes/class-rest-api.php`, `includes/class-hooks.php` y `includes/class-post-types.php` siguen como placeholders/no activos
- `Client_Vehicle_Service::transfer_vehicle()` y `Flow_Service::delete_flow()` / `Flow_Step_Service::reorder_steps()` todavía no tienen una frontera transaccional dedicada
- `Report_Service` sigue siendo grande y debe vigilarse si el módulo crece
- la exposición UI avanzada del `payment_receipt` (botones dedicados y visibilidad extendida) puede ampliarse en futuras fases
- Fase 23 amplía el portal cliente con detalle integrado de proceso, comentarios reales del cliente y exposición segura de `payment_receipt`
- Fase 24 agrega una capa real de assets y moderniza dashboards, reportes y shortcodes cliente sin tocar lógica de negocio ni schema
- Fase 24B extiende esa misma capa visual a clientes, vehículos, procesos, flujos y ajustes dentro del admin
- Fase 25 agrega `scripts/php-lint.php`, `scripts/structure-check.php` y `scripts/technical-checklist.php` como base portable para validación local previa al cierre

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