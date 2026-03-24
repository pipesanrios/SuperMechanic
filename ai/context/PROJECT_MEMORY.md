PROJECT MEMORY — SUPER MECHANIC (OPTIMIZADO)

Memoria estructural del proyecto para reconstrucción rápida por IA.

No reemplaza documentación técnica.

==================================================
PROYECTO
==================================================

Super Mechanic

Plugin WordPress para gestión de:

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
STACK
==================================================

PHP
WordPress Plugin Architecture
MySQL
VSCode + Codex / ChatGPT

==================================================
ARQUITECTURA BASE
==================================================

Patrón:

Controller → Service → Repository

Reglas:

- SQL solo en Repository
- usar `includes/*`
- no usar `includes/modules/*`
- no duplicar lógica

==================================================
MÓDULOS CLAVE
==================================================

clients
vehicles
relations
flows
processes
maintenance
dashboard
reports
quotes
invoices
payments
attachments
communication

==================================================
FLUJO CENTRAL
==================================================

Cliente
→ Vehículo
→ Proceso
→ Maintenance / Quote / Invoice / Payment

Elementos asociados:

- attachments
- comments
- notifications
- timeline

==================================================
ESTADO ACTUAL
==================================================

plugin: 0.1.0  
schema: 1.9.0  

Fases consolidadas:
12A–26B

Ver detalle:
→ docs/CURRENT_STATE.md

==================================================
PUNTOS CLAVE DEL SISTEMA
==================================================

- portal cliente operativo
- portal mecánico operativo
- ownership centralizado
- documentos seguros
- workflow configurable
- UI moderna base
- scripts locales de validación
- hardening pre-SaaS aplicado

==================================================
DEUDA TÉCNICA ACTIVA
==================================================

- REST API no activa
- placeholders en core (rest-api, hooks, post-types)
- PDF admin fuera de Download_Service
- sin CI/CD real
- sin pruebas runtime automatizadas

==================================================
FUENTE DE VERDAD
==================================================

1. código (`includes/*`)
2. docs técnicos
3. contextos AI

==================================================
REGLA ANTI-ALUCINACIÓN
==================================================

Antes de implementar:

- verificar archivo
- verificar clase
- verificar tabla
- verificar módulo activo

Si no existe → indicarlo
No asumir implementaciones