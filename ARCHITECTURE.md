# ARCHITECTURE — SUPER MECHANIC

## Propósito

Super Mechanic es un plugin WordPress modular para gestión de:

- talleres mecánicos
- concesionarios
- vehículos
- procesos
- mantenimiento
- trámites
- cotizaciones
- facturación
- Client Portal

El sistema está diseñado para evolucionar hacia una base pre-SaaS sin romper compatibilidad WordPress.

---

## Principios arquitectónicos

### Patrón obligatorio

Controller  
↓  
Service  
↓  
Repository  
↓  
Database

### Reglas base

- SQL solo en Repository
- `$wpdb` solo en Repository
- Services contienen lógica de negocio
- Controllers integran con WordPress y renderizan UI
- Shortcodes exponen frontend cliente
- `includes/*` es la arquitectura activa
- `includes/modules/*` es legacy y no debe extenderse

---

## Estructura activa del proyecto

### Core activo

- `super-mechanic.php`
- `includes/class-plugin.php`
- `includes/class-admin-menu.php`
- `includes/class-assets.php`
- `includes/class-settings.php`

### Módulos activos

- `clients`
- `vehicles`
- `relations`
- `flows`
- `processes`
- `maintenance`
- `predelivery`
- `paperwork`
- `dashboard`
- `reports`
- `quotes`
- `invoices`
- `payments`
- `attachments`
- `communication`
- `appointments`
- `businesses`
- `automation`
- `integrations`
- `helpers`
- `database`

### Legacy o no activo

- `includes/modules/*`
- `includes/class-rest-api.php`
- `includes/class-hooks.php`
- `includes/class-post-types.php`

---

## Módulos y responsabilidades

### Clients
Gestión de clientes, datos de contacto, relación con usuario WordPress y ownership base.

### Vehicles
Gestión de vehículos, vínculo con cliente, historial asociado y documentos.

### Relations
Relación cliente ↔ vehículo y transferencias de propiedad.

### Flows
Definición de flujos, pasos y orden operativo de procesos.

### Processes
Entidad central del sistema. Orquesta el ciclo operativo y conecta maintenance, predelivery, paperwork, quotes, invoices y timeline.

### Maintenance
Diagnóstico, repuestos, mano de obra y operación técnica de taller.

### Predelivery
Flujo de pre-entrega para concesionarios.

### Paperwork
Trámites administrativos configurables.

### Quotes
Cotizaciones vinculadas al proceso.

### Invoices / Payments
Facturación y pagos. `sm_payments` es la fuente financiera de verdad.

### Attachments
Adjuntos, timeline documental y archivos visibles/internos.

### Communication
Comentarios, notificaciones y eventos operativos.

### Dashboard
Superficies admin, Client Portal y Mechanic Panel.

### Reports
Reportes operativos y financieros.

### Helpers
Servicios transversales:
- `Access_Control_Service`
- `Document_Service`
- `Download_Service`
- `PDF_Service`
- `Settings_Service`

---

## Flujo central del sistema

Cliente  
→ Vehículo  
→ Relación cliente-vehículo  
→ Proceso

Proceso  
→ Maintenance / Predelivery / Paperwork  
→ Quote  
→ Invoice  
→ Payment

Elementos asociados:
- attachments
- comments
- notifications
- timeline

---

## Seguridad

### Ownership
La política central de ownership y visibilidad vive en:

- `Access_Control_Service`

Debe aplicarse a:
- procesos
- quotes
- invoices
- attachments
- comments
- notifications

### Descargas seguras
Nunca exponer `file_url` directo.

Flujo obligatorio:
- `Document_Service`
- `Download_Service`

Esto aplica a:
- quotes
- invoices
- attachments
- `payment_receipt`

---

## UI / UX

La capa visual reusable del plugin usa:

- `assets/css/admin.css`
- `assets/css/client.css`
- `assets/css/mechanic.css`
- clases `sm-*`

Reglas:
- no romper formularios
- no romper nonces
- no romper query args
- no crear un segundo sistema visual paralelo

---

## Estado técnico consolidado

### Versiones
- plugin: `0.1.0`
- schema: `1.15.0`

### Fases consolidadas
- 12A–12E
- 13
- 14
- 14B
- 15
- 16
- 17
- 18
- 19
- 20
- 20B
- 21
- 22
- 23
- 24
- 24B
- 25
- 26
- 26B
- 27A
- 27B
- 27C-A
- 27C-B
- 28
- 29
- 30
- 31A
- 31B
- 31C
- 32A
- 32B-1
- 32B-2
- 32B-3A
- 32B-3B
- 33
- 34
- 35A
- 35B
- 35C
- 36A
- 36B
- 36C-1
- 36C-2

### Hitos recientes
- reports consolidado
- ownership centralizado
- Client Portal operativo
- Mechanic Panel operativo
- módulo de citas operativo
- operación multi-store visible con `sm_businesses` y selector de contexto por usuario
- integración Google Calendar 1-way + reconciliación inbound controlada
- watch channels / webhook REST dedicado con idempotencia y renovación preventiva
- notificaciones multicanal base (in-app + email desacoplado)
- recordatorios automáticos de citas con `wp_cron` y deduplicación
- API pública separada `super-mechanic-public/v1` con auth por API key tenant-aware
- webhooks outbound públicos por negocio con firma `HMAC-SHA256`, idempotencia y retries básicos
- write pública mínima de citas (`cancel` + `confirm`) con scopes dedicados e idempotencia por transient
- `payment_receipt` lógico por `payment_id`
- panel admin de shortcodes
- scripts locales de validación técnica
- hardening pre-SaaS en dashboard cliente, transacciones y descargas admin

---

## Deuda técnica activa

- REST API interna autenticada conectada al runtime real (`includes/dashboard/class-client-rest-controller.php` y `includes/dashboard/class-admin-rest-controller.php`)
- rutas admin de PDF de quotes/invoices siguen como excepción controlada
- `Process_Admin_Controller` y `Report_Service` siguen siendo puntos a vigilar
- no existe CI/CD real todavía
- no hay pruebas runtime automáticas en WordPress
- settings legacy conviven con `sm_settings`

---

## Reglas de escalabilidad

- no reintroducir lógica en controllers
- no omitir transacciones en operaciones críticas
- no mover SQL fuera de repository
- evitar services tipo “god class”
- no mezclar arquitectura activa con legacy
- mantener el sistema preparado para una futura API y evolución SaaS

---

## Fuente de verdad

Prioridad:

1. código real (`includes/*`)
2. este documento
3. `docs/SYSTEM_MAP.md`
4. `docs/FINAL_ARCHITECTURE_MAP.md`
5. `docs/CURRENT_STATE.md`
6. `docs/MODULE_REGISTRY.md`
7. `docs/DATABASE_MAP.md`

---

## Referencias

Ver detalle en:

- `docs/CURRENT_STATE.md`
- `docs/SYSTEM_MAP.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`
- `docs/SECURITY_MODEL.md`
- `docs/tasks/`
