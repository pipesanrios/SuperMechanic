AGENTS QUICK CONTEXT — SUPER MECHANIC

Este archivo proporciona un contexto rápido del proyecto para agentes de IA.

Debe leerse antes de realizar análisis o generar código.

No reemplaza la documentación completa.

==================================================
PROYECTO
========

Nombre:
Super Mechanic

Tipo:
Plugin WordPress

Propósito:
Sistema modular para gestión de:

* talleres mecánicos
* concesionarios
* procesos de vehículos
* mantenimiento
* trámites administrativos
* cotizaciones
* facturación
* portal cliente

==================================================
ARQUITECTURA PRINCIPAL
======================

Arquitectura modular obligatoria:

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
ESTRUCTURA ACTIVA DEL PLUGIN
============================

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
FLUJO PRINCIPAL DEL SISTEMA
===========================

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
FUENTE DE VERDAD
================

Si existe diferencia entre:

* código
* documentación

La fuente de verdad es siempre:

EL CÓDIGO REAL DEL PROYECTO.

El estado actual del sistema debe consultarse en:

docs/CURRENT_STATE.md

==================================================
REGLAS CRÍTICAS
===============

* Nunca colocar SQL fuera de Repository
* No modificar includes/modules/*
* Validar ownership siempre
* Aplicar sanitización y escaping
* No exponer file_url directo
* Usar Download_Service y Document_Service para descargas seguras

==================================================
DOCUMENTACIÓN COMPLETA
======================

ARCHITECTURE.md
docs/SYSTEM_MAP.md
docs/CURRENT_STATE.md
docs/MODULE_REGISTRY.md
docs/DATABASE_MAP.md
docs/DOMAIN_MODEL.md
docs/PLUGIN_ROADMAP.md
docs/TEST_SCENARIOS.md
docs/PLUGIN_LIFECYCLE.md

Reglas IA:

ai/rules/*

==================================================
ESTADO CONSOLIDADO ACTUAL
==================================================

Versiones reales:

plugin
0.1.0

schema
1.9.0

Fases cerradas relevantes:

12A
12B
12C
12D
12E
13
14
14B
15
16
17
18
19
20

Notas operativas:

- `reports` ya es módulo activo real en `includes/reports/`
- el portal cliente está activo con dashboard, quotes, invoices, attachments, comments, notifications y timeline
- `Download_Service` y `Document_Service` siguen siendo el flujo obligatorio para descargas protegidas
- `Access_Control_Service` es la política central de ownership y visibilidad
- el portal mecánico ya es operativo dentro del admin actual
- `Process_Derived_State_Service` ya existe como capa reusable para estados derivados
- `payment_receipt` ya existe como documento lógico reusable por `payment_id`
- `reports` ya expone métricas avanzadas operativas y financieras reutilizables
- `includes/class-rest-api.php`, `includes/class-assets.php`, `includes/class-hooks.php` y `includes/class-post-types.php` deben tratarse como placeholders/no activos
- `includes/modules/*` sigue siendo legacy de referencia, no arquitectura activa
