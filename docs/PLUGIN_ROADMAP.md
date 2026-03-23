PLUGIN ROADMAP — SUPER MECHANIC

Este documento define la evolución planificada del plugin Super Mechanic.

El objetivo es guiar el desarrollo futuro del sistema
y evitar implementaciones fuera del alcance del proyecto.

Este roadmap es una guía técnica y funcional
para desarrolladores y agentes de IA.

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
- portal cliente

El plugin está diseñado para operar completamente
dentro del ecosistema WordPress.

==================================================
ESTADO ACTUAL DEL PROYECTO
==================================================

Arquitectura base:
implementada

Sistema de procesos:
implementado

Módulo mantenimiento:
implementado

Módulo trámites:
implementado

Sistema de cotizaciones:
implementado

Sistema de facturación:
implementado

Pagos:
implementado

Portal cliente:
implementado

Sistema documental:
parcial

REST API:
placeholder

Integración WooCommerce:
parcial

Automatización operativa:
implementada en su base actual

Control de acceso y ownership:
implementado

Portal mecánico:
implementado

Workflow configurable avanzado:
implementado en su base lineal actual

==================================================
FASE 11 — PORTAL CLIENTE
==================================================

Objetivo:

portal seguro para clientes donde puedan ver:

procesos
cotizaciones
facturas
pagos
documentos
timeline del proceso

Tareas principales:

- consolidar dashboard cliente
- asegurar ownership de datos
- integrar descargas seguras
- mostrar timeline de procesos
- mostrar historial de documentos

Estado:

implementado en su base actual

==================================================
FASE 12 — REPORTES Y ANALÍTICA
==================================================

Objetivo:

proveer información operativa para talleres.

Funciones previstas:

reportes de mantenimiento
reportes de ingresos
reportes de procesos activos
reportes de pagos
reportes de productividad

Posibles componentes:

Dashboard analytics
filtros por fechas
exportación CSV / PDF

Estado real actual:

- 12A reportes operativos base: completada
- 12B reportes financieros base: completada
- 12C consolidación y exportación CSV acotada: completada
- 12D reportes avanzados base: completada
- 12E endurecimiento, performance básica y cierre documental: completada

Pendiente fuera del alcance ya implementado:

- cache avanzada
- índices nuevos
- gráficos
- BI avanzado
- exportación PDF de reportes
- cron

==================================================
FASE 13 — SISTEMA DOCUMENTAL AVANZADO
==================================================

Objetivo:

gestión documental completa.

Funciones previstas:

PDF de cotizaciones
PDF de facturas
PDF de reportes
gestión de archivos adjuntos
versionado de documentos

Integración con:

Download_Service
Document_Service

==================================================
FASE 14 — REST API
==================================================

Objetivo:

permitir integración con sistemas externos.

Ejemplos:

apps móviles
ERP
integraciones externas

Endpoints previstos:

clients
vehicles
processes
quotes
invoices
payments

==================================================
FASE 15 — INTEGRACIÓN WOOCOMMERCE
==================================================

Objetivo:

permitir pagos online y productos relacionados
con servicios mecánicos.

Posibles usos:

pago de facturas
venta de repuestos
servicios programados

Estado actual:

scaffold inicial
no integrado completamente

==================================================
FASE 16 — AUTOMATIZACIÓN
==================================================

Objetivo:

automatizar flujos del sistema.

Ejemplos:

recordatorios automáticos
notificaciones de estado
automatización de pasos de proceso
alertas administrativas

==================================================
FASE 17 — ESCALABILIDAD
==================================================

Objetivo:

optimizar rendimiento para talleres grandes.

Posibles mejoras:

caching interno
mejoras en consultas SQL
optimización de índices
mejoras en dashboard

==================================================
PRIORIDADES ACTUALES
==================================================

Las prioridades del proyecto son:

1 consolidar coherencia entre código, docs y prompts
2 reforzar seguridad documental sin reintroducir `file_url` directo
3 evaluar endurecimiento transaccional futuro en `relations` y `flows`
4 preparar sistema documental avanzado

==================================================
ACTUALIZACIONES OPERATIVAS YA CONSOLIDADAS
==================================================

El roadmap debe leerse junto con la documentación técnica base.

Estado real ya confirmado en código y docs:

- Fase 15: pagos consolidados sobre `sm_payments` como fuente primaria de verdad financiera
- Fase 16: automatizaciones y eventos operativos implementados
- Fase 17: ownership, visibilidad y access control centralizados
- Fase 18: portal mecánico real implementado
- Fase 19: workflow operativo lineal endurecido por `step_order`
- Fase 20: automatización documental lógica y estados derivados implementados

Pendientes posteriores a ese estado:

- REST API real
- integración WooCommerce real
- auditoría avanzada dedicada
- firma digital
- almacenamiento externo
- CI/CD real sobre la base local de scripts introducida en Fase 25

==================================================
REGLAS DEL ROADMAP
==================================================

Los agentes de IA no deben implementar funcionalidades
que no estén alineadas con este roadmap.

Si se propone una funcionalidad nueva:

debe justificarse
debe evaluarse impacto en arquitectura
debe confirmarse antes de implementarse.
