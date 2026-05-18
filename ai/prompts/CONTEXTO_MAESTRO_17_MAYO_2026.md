# MEKVORT — CONTEXTO MAESTRO DE CONTINUIDAD

## Estado consolidado del proyecto + reglas operativas + roadmap + arquitectura + comportamiento esperado IA

---

# IDENTIDAD DEL PROYECTO

## Nombre actual

```txt
Mekvort
```

## Nombre histórico

```txt
Super Mechanic
```

El proyecto migró visualmente a Mekvort, pero muchas clases, slugs, namespaces y estructuras internas aún conservan referencias `Super_Mechanic` por compatibilidad.

---

# OBJETIVO DEL PROYECTO

Plugin WordPress tipo ERP/CRM para:

* talleres mecánicos
* concesionarios
* mantenimiento automotriz
* pre-entrega de vehículos
* gestión de clientes
* seguimiento de procesos
* inventario
* cotizaciones
* facturación
* pagos
* dashboards
* portal cliente
* portal mecánico
* reporting
* APIs
* integraciones futuras SaaS

Arquitectura preparada para evolución futura:

```txt
Plugin WP -> Plataforma SaaS
```

---

# REGLAS CRÍTICAS DE DESARROLLO

## Arquitectura obligatoria

```txt
Controller → Service → Repository → Database
```

## Reglas

* SQL SOLO en Repository/Database
* Services contienen lógica de negocio
* Controllers solo orquestan/renderizan
* No duplicar lógica
* No usar `includes/modules/*`
* Mantener business scope/tenant scope
* Seguridad WP:

  * sanitize
  * escape
  * nonces
  * capability checks

---

# DOCUMENTOS PRINCIPALES

Orden de prioridad:

```txt
1. Código existente
2. docs/CURRENT_STATE.md
3. .vscode/AI_CONTEXT.md
4. AGENTS.md
5. ARCHITECTURE.md
```

---

# BOOTSTRAP OBLIGATORIO

Toda IA/Codex debe leer:

```txt
AGENTS_BOOTSTRAP.md
AGENTS.md
ARCHITECTURE.md
CURRENT_STATE.md
AI_CONTEXT.md
PLUGIN_ROADMAP.md
```

---

# ESTILO OPERATIVO IA

## Siempre responder:

* técnico
* directo
* sin relleno
* pensando como arquitecto
* continuidad total del proyecto
* sin reinventar lógica

## Para prompts Codex:

* completos
* copy/paste ready
* sin explicación innecesaria
* con:

  * scope
  * restricciones
  * validación
  * runtime real

---

# ESTADO GENERAL DEL ROADMAP

---

# FASES COMPLETADAS IMPORTANTES

---

# 56P1 — Rename + I18N base

COMPLETA

Incluye:

* Mekvort visible
* language selector
* i18n helper base

---

# 56P2 — Superadmin + roles foundation

COMPLETA

---

# 56P3 — Reset + data integrity

COMPLETA

Incluye:

* integrity validation repository/service
* orphan checks
* CRM integrity
* payment integrity

---

# 56P4 — Admin UX consistency

COMPLETA

Incluye:

* dashboard cards/grid
* reporting cards/grid
* branding UX
* settings/license UX

Problema importante resuelto:

```txt
cache busting CSS usando filemtime()
```

---

# 56P5 — CRM hardening

COMPLETA

Incluye:

* bulk actions
* cascade delete
* alert cleanup
* state consistency

---

# 56P6 — Roles & Access

COMPLETA

Muy importante.

## Estado final correcto:

* memberships multi-role por negocio
* una tarjeta consolidada por negocio
* primary membership real
* assign/remove sincronizado
* actions sincronizadas con estado persistido
* add membership compacto
* business scoped
* backend hardened

Problemas resueltos:

* roles no removían realmente
* UI desincronizada
* memberships duplicadas
* primary handoff
* assign/remove inconsistentes

---

# 56P7 — Client & Mechanic Panel

COMPLETA

## Cliente

Shortcodes:

```txt
[mekvort_client_panel]
[sm_client_dashboard]
[sm_client_vehicles]
[sm_client_processes]
etc
```

Problema crítico resuelto:

```txt
fallback automático por email
si no existía sm_client_id
```

## Mecánico

Shortcodes:

```txt
[mekvort_mechanic_panel]
[sm_mechanic_dashboard]
[sm_mechanic_processes]
```

Se corrigieron:

* labels corruptos
* navegación
* catálogo shortcodes

---

# 56P8 — Email triggers/templates

COMPLETA

## Servicios creados

```txt
Email_Trigger_Service
Email_Template_Service
```

## Eventos

* process status
* quote approved/rejected
* invoice paid
* invoice partial

## Importante

NO envía emails reales todavía.
Solo:

```txt
trigger intents + templates + logs
```

---

# 56P9 — Google Calendar

COMPLETA

## 56P9-A

Config service:

```txt
Google_Calendar_Config_Service
```

## 56P9-B

Payload builder:

```txt
Google_Calendar_Sync_Service
```

## 56P9-C

Consolidación definitiva:

* payload ownership centralizado
* integration service convertido en:

```txt
Google_Calendar_Client_Service
```

Muy importante:

```txt
NO dejar wrappers temporales
Se consolidó correctamente
```

---

# 56P10 — API auth certification

COMPLETA

## Resultado final

`sm/v1` YA NO usa:

```txt
__return_true
```

Ahora usa:

```txt
permission_can_read()
permission_can_write()
permission_can_approve_quote()
```

## Runtime validado

* no autenticado bloqueado
* quote approve protegido
* respuestas compatibles
* namespace preservado

---

# 56P11 — PDF reporting final

COMPLETA

MUY IMPORTANTE.

## Problema crítico resuelto

TCPDF embebido no cargaba en invoice/quote.

Resultado final:

```txt
includes/helpers/class-pdf-service.php
```

centraliza carga TCPDF embebido.

## Estado final:

* invoice PDFs OK
* quote PDFs OK
* reporting PDFs OK
* export stability OK
* invalid IDs controlados
* MIME correcto
* `%PDF-`
* no HTML crudo

## Path TCPDF embebido

```txt
includes/libs/pdf/tcpdf/tcpdf.php
```

---

# 56P12 — Vehicle catalog + inventory base

---

## 56P12-A

COMPLETA

### Tabla

```txt
wp_sm_vehicle_catalog
```

### Repository

```txt
Vehicle_Catalog_Repository
```

### Service

```txt
Vehicle_Catalog_Service
```

### Campos

* business_id
* make
* model
* year
* trim_version
* body_type
* fuel_type
* transmission
* engine
* notes
* status

---

## 56P12-B

COMPLETA

### Admin UI

```txt
Super Mechanic -> Vehicle Catalog
```

Incluye:

* create
* edit
* deactivate
* business scope
* nonces
* capability
* lista admin

---

## 56P12-C

PENDIENTE / EN PROCESO

Objetivo:
crear vehículos desde catálogo.

---

# SHORTCODES IMPORTANTES

## Cliente

```txt
[mekvort_client_panel]
[sm_client_dashboard]
[sm_client_vehicles]
[sm_client_processes]
[sm_client_quotes]
[sm_client_invoices]
etc
```

## Mecánico

```txt
[mekvort_mechanic_panel]
[sm_mechanic_dashboard]
[sm_mechanic_processes]
```

---

# PDF SYSTEM

## Servicio compartido

```txt
includes/helpers/class-pdf-service.php
```

## Reporting

```txt
includes/reporting/class-report-pdf-service.php
```

## TCPDF

```txt
includes/libs/pdf/tcpdf/tcpdf.php
```

---

# API

## Namespace protegido

```txt
sm/v1
```

## Namespace integraciones públicas

```txt
super-mechanic-public/v1
```

NO mezclarlos.

---

# ESTRUCTURA MULTI-BUSINESS

Todo debe respetar:

```txt
business_id
```

Especialmente:

* catálogos
* memberships
* CRM
* reporting
* APIs
* dashboards

---

# ESTADO TÉCNICO ACTUAL

## Muy estable

Las áreas más críticas ya fueron endurecidas:

* memberships
* APIs
* PDFs
* reporting
* dashboards
* CRM consistency
* data integrity

---

# ÁREAS PENDIENTES GRANDES

## 56P12

Vehicle catalog integration + inventory

## 56P13

External inventory connector strategy

---

# COMPORTAMIENTO ESPERADO DEL NUEVO CHAT

Debe:

* continuar exactamente igual
* mantener tono técnico/arquitecto
* asumir continuidad
* NO reiniciar lógica
* NO reestructurar arquitectura
* NO proponer cambios radicales
* respetar:

```txt
Controller → Service → Repository → Database
```

---

# REGLAS CRÍTICAS PARA NUEVO CHAT

## Nunca:

* usar includes/modules/*
* meter SQL fuera repository
* romper business scope
* duplicar lógica
* inventar arquitectura nueva
* usar wrappers temporales si existe consolidación correcta

---

# FORMA CORRECTA DE TRABAJAR

Cada subfase:

```txt
1. Contract
2. Validation contract
3. Implementación
4. php-lint
5. qa-runner
6. Runtime real
7. Cierre
```

---

# ESTADO ACTUAL REAL

## Última fase cerrada

```txt
56P12-B → COMPLETA
```

## Fase siguiente

```txt
56P12-C — Vehicle creation from catalog
```

Objetivo:
seleccionar catálogo y autocompletar datos del vehículo real.

---

# ENTORNO

## Local

```txt
XAMPP
WordPress local
VSCode
Codex
Windows
```

## Path típico

```txt
c:/xampp/htdocs/Mekvort/wp-content/plugins/Mekvort/
```

---

# NOTAS IMPORTANTES

## PDF

NO pedir instalar plugins externos.
TCPDF ya está embebido.

## APIs

`sm/v1` usa auth WP.
NO API keys ahí.

## Reporting

Ya estable.

## Memberships

Ya estabilizadas.
No romperlas otra vez.

## CSS cache

Usar:

```txt
filemtime()
```

para evitar caché roto.

---

# FIN CONTEXTO MAESTRO

Este bloque debe servir como continuidad completa para abrir un nuevo chat sin perder:

* arquitectura
* roadmap
* reglas
* estado técnico
* decisiones críticas
* fixes importantes
* comportamiento esperado IA.
