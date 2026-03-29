TEST SCENARIOS — SUPER MECHANIC

Este documento define escenarios funcionales clave del sistema.

Los escenarios sirven para:

- validar flujos de negocio
- detectar regresiones
- ayudar a agentes de IA a verificar implementaciones
- asegurar integridad entre módulos

Antes de implementar cambios importantes
se recomienda verificar que estos escenarios sigan funcionando.

==================================================
ESCENARIO 1 — REGISTRO DE CLIENTE
==================================================

Estado Fase 14: OK

Flujo:

Administrador
→ crea cliente

Resultado esperado:

- registro en sm_clients
- cliente visible en panel admin
- cliente disponible para asignación de vehículos

==================================================
ESCENARIO 2 — REGISTRO DE VEHÍCULO
==================================================

Estado Fase 14: OK

Flujo:

Administrador
→ crea vehículo

Resultado esperado:

- registro en sm_vehicles
- vehículo disponible para asignación a cliente

==================================================
ESCENARIO 3 — ASIGNAR VEHÍCULO A CLIENTE
==================================================

Estado Fase 14: OK

Flujo:

Administrador
→ selecciona cliente
→ asigna vehículo

Resultado esperado:

- registro en sm_client_vehicles
- relación cliente-vehículo visible en dashboard

==================================================
ESCENARIO 4 — CREAR PROCESO
==================================================

Estado Fase 14B: OK

Flujo:

Administrador
→ selecciona cliente
→ selecciona vehículo
→ define tipo de proceso
→ el sistema resuelve `flow_id` y `current_step_id`

Resultado esperado:

- registro en sm_processes
- creación de step inicial
- registro inicial en sm_process_step_logs
- persistencia atómica entre proceso y log inicial

==================================================
ESCENARIO 5 — AVANZAR PASO DE PROCESO
==================================================

Estado Fase 19: OK

Flujo:

Administrador o sistema
→ cambia step del proceso

Resultado esperado:

- actualización de sm_processes.current_step_id
- registro en sm_process_step_logs
- bloqueo de saltos a pasos no adyacentes del mismo flujo
- si el nuevo paso es final, sincronización mínima del proceso a estado `completed`

==================================================
ESCENARIO 6 — REGISTRAR MANTENIMIENTO
==================================================

Estado Subfases 10-13: OK

Flujo:

Administrador o mecánico
→ crea mantenimiento

Resultado esperado:

- registro en sm_maintenance
- registro de partes en sm_maintenance_parts
- registro de mano de obra en sm_maintenance_labor

==================================================
ESCENARIO 7 — CREAR COTIZACIÓN
==================================================

Estado Fase 14B: OK

Flujo:

Administrador
→ genera quote desde mantenimiento o gestiona quote del proceso

Resultado esperado:

- registro en sm_quotes
- registro de items en sm_quote_items

Observación Fase 14:

- la generación automática desde maintenance ahora persiste de forma atómica la quote y sus items

==================================================
ESCENARIO 8 — APROBAR COTIZACIÓN
==================================================

Estado Fase 14B: OK

Flujo:

Cliente autenticado con acceso a la cotización
→ aprueba una cotización en estado `sent`

Resultado esperado:

- estado de quote actualizado
- cotización lista para generar factura
- aprobación real disponible para cliente autenticado mediante shortcode y para staff desde admin como soporte operativo

==================================================
ESCENARIO 9 — CREAR FACTURA
==================================================

Estado Fase 14: OK

Flujo:

Sistema o administrador
→ genera invoice desde quote aprobada

Resultado esperado:

- registro en sm_invoices
- registro en sm_invoice_items
- cálculo correcto de totals
- creación transaccional desde quote aprobada

==================================================
ESCENARIO 10 — REGISTRAR PAGO
==================================================

Estado Fase 14: OK

Flujo:

Administrador
→ registra pago

Resultado esperado:

- registro en sm_payments
- actualización de invoice balance
- posible cambio de estado a paid

==================================================
ESCENARIO 11 — ADJUNTAR DOCUMENTO
==================================================

Estado Fase 14: OK

Flujo:

Administrador
→ sube documento

Resultado esperado:

- registro en sm_attachments
- documento visible en proceso

==================================================
ESCENARIO 12 — DESCARGA SEGURA DE DOCUMENTO
==================================================

Estado Fase 14: OK

Flujo:

Cliente
→ accede Client Portal
→ descarga documento

Resultado esperado:

- validación de ownership
- descarga mediante Download_Service
- no exposición directa de file_url

==================================================
ESCENARIO 13 — Client Portal
==================================================

Estado Fase 14: PARCIAL

Flujo:

Cliente
→ inicia sesión
→ abre portal

Debe poder ver:

- procesos
- cotizaciones
- facturas
- pagos
- documentos

Sin acceso a datos de otros clientes.

Observación Fase 14:

- la visibilidad real se distribuye entre dashboard y shortcodes especializados
- la actividad reciente del Client Portal debe respetar `customer_visible` en logs de proceso
- en SUBFASES 10-13 el dashboard cliente consolidado agrega navegación interna y mantiene continuidad real sobre procesos, documentos, quotes e invoices sin abrir bypass de ownership

==================================================
ESCENARIO 14 — TIMELINE DEL PROCESO
==================================================

Estado Fase 14B: OK

Flujo:

Sistema
→ registra eventos del proceso

Resultado esperado:

- registros en sm_process_step_logs
- timeline visible en dashboard o portal según contexto

Observación Fase 14:

- la timeline consolidada no se limita a `sm_process_step_logs`; también integra adjuntos, comentarios, quotes, invoices y payments
- en contexto cliente solo se muestran logs de proceso con `customer_visible = 1`
- los eventos de quote e invoice usan tipado acorde al estado real para evitar etiquetas engañosas en la timeline consolidada
- Fase 16 agrega coherencia adicional: quotes canceladas, invoices creadas y facturas pagadas quedan tipadas de forma mas explicita en la timeline consolidada

==================================================
ESCENARIO 15 — NOTIFICACIONES
==================================================

Estado Fase 14: OK

Flujo:

Sistema
→ crea notificación

Resultado esperado:

- registro en sm_notifications
- notificación visible para usuario correspondiente

Observación Fase 16:

- las notificaciones internas ahora consumen un catalogo ampliado de eventos operativos sin duplicar `process_updated` para cambios que ya tienen evento especifico
- los eventos de creacion/cancelacion/finalizacion y de cobranza `paid` se despachan solo despues de persistencia exitosa

==================================================
ESCENARIO 16 — Mechanic Panel OPERATIVO
==================================================

Estado Subfases 10-13: OK

Flujo:

Mecánico autenticado con `sm_manage_processes`
→ abre `Panel mecánico`
→ ve solo procesos accesibles según política actual
→ entra al detalle de uno de sus procesos
→ actualiza estado o paso
→ registra nota técnica interna

Resultado esperado:

- listado restringido a procesos permitidos
- detalle operativo con timeline, comentarios, adjuntos y mantenimiento cuando aplica
- cambio de `status` vía `Process_Service`
- cambio de `current_step_id` vía `Process_Service`
- nota técnica interna persistida vía `Comment_Service`
- sin acceso operativo a procesos ajenos
- sin duplicar el flujo admin completo del proceso
- shortcodes frontend `sm_mechanic_dashboard` y `sm_mechanic_processes` operativos bajo el mismo enforcement real

==================================================
ESCENARIO 20 — SHORTCODES MECÁNICOS FRONTEND
==================================================

Estado Subfases 10-13: OK

Flujo:

Mecánico autenticado con `sm_manage_processes`
→ abre una página con `sm_mechanic_dashboard` o `sm_mechanic_processes`
→ ve solo procesos permitidos por rol y ownership operativo
→ ejecuta acciones válidas sobre un proceso asignado

Resultado esperado:

- render sin errores fuera de admin
- métricas coherentes con los procesos visibles
- acciones protegidas por nonce y permisos
- sin acceso a procesos ajenos
- sin dependencia de IDs públicos ni bypass de `Permission_Service`

==================================================
ESCENARIO 21 — TRACKING PÚBLICO SEGURO
==================================================

Estado Subfases 10-13: N/A

Flujo:

Usuario público
→ intenta consultar tracking sin autenticación

Resultado esperado:

- no existe shortcode público activo mientras no haya mecanismo seguro
- no se expone información por IDs internos o parámetros predecibles
- `sm_public_tracking` permanece como restricción operativa documentada, no como funcionalidad cerrada

==================================================
ESCENARIO 17 — REPORTES AVANZADOS
==================================================

Estado Fase 22: OK

Flujo:

Administrador con `sm_manage_plugin`
→ abre `Super Mechanic -> Reportes`
→ aplica filtros por fechas, tipo, estado, estado derivado, moneda o método de pago

Resultado esperado:

- métricas operativas avanzadas visibles
- métricas financieras avanzadas visibles
- comparativas solo cuando existe período anterior equivalente
- exportación CSV previa sigue limitada a las vistas soportadas

==================================================
RESUMEN HISTORICO FASE 14
==================================================

- Este bloque es historico de cierre de Fase 14.
- El estado actual consolidado del proyecto vive en `docs/CURRENT_STATE.md`.

==================================================
REGLA DE VALIDACIÓN
==================================================

Antes de aceptar una implementación nueva
verificar que:

- no rompa estos escenarios
- no introduzca dependencias incorrectas
- respete el flujo principal del sistema

==================================================
FLUJO PRINCIPAL DEL SISTEMA
==================================================

Cliente
→ Vehículo
→ Proceso
→ Mantenimiento
→ Cotización
→ Factura
→ Pago

Durante todo el proceso se pueden registrar:

documentos
comentarios
notificaciones
timeline de pasos

==================================================
FUENTE DE VERDAD
==================================================

Si existe diferencia entre estos escenarios
y el comportamiento real del código:

la fuente de verdad es el código real del plugin.
==================================================
ESCENARIO 18 — Client Portal PREMIUM OPERATIVO
==================================================

Estado Fase 23: OK

Flujo:

Cliente autenticado
→ abre su panel principal
→ entra al detalle de un proceso propio
→ consulta estado derivado y estado financiero
→ descarga documentos permitidos
→ revisa quotes, invoices y pagos relacionados
→ registra un comentario visible para staff

Resultado esperado:

- acceso restringido a procesos propios
- detalle integrado del proceso sin exponer datos ajenos
- descargas seguras de `quote_pdf`, `invoice_pdf` y `payment_receipt`
- comentarios creados mediante `Comment_Service`
- sin pagos online ni bypass de ownership

==================================================
ESCENARIO 19 — USABILIDAD ADMIN PREVIA FASE 27
==================================================

Estado Subfases 1-3 previa Fase 27: OK

Flujo:

Administrador
→ abre `Dashboard`
→ navega desde KPIs o resúmenes hacia clientes, vehículos o procesos filtrados
→ entra al detalle `Ver` de cliente o vehículo
→ revisa procesos relacionados
→ edita o elimina un comentario en un proceso
→ abre un adjunto desde el proceso
→ copia un shortcode desde el panel de shortcodes

Resultado esperado:

- KPIs y bloques relevantes con navegación real
- detalle reusable de cliente con vehículos y procesos relacionados
- detalle reusable de vehículo con cliente, procesos e historial relacionado
- comentarios de proceso editables y eliminables sin sacar lógica de `Comment_Service`
- adjuntos con acción útil `Abrir`
- botón copiar funcional en panel de shortcodes

==================================================
ESCENARIO 22 — API PÚBLICA READ-ONLY (36A)
==================================================

Estado Fase 36A: OK (validación técnica; sin runtime WordPress formal en este cierre)

Flujo:

Integración externa con API key válida
→ consulta `/business`, `/processes`, `/appointments`

Resultado esperado:

- namespace público separado de API interna
- `business_id` resuelto desde credencial
- payload público mínimo sin datos sensibles
- paginación y filtros sanitizados

==================================================
ESCENARIO 23 — WEBHOOKS OUTBOUND PÚBLICOS (36B)
==================================================

Estado Fase 36B: PARCIAL (validación sintáctica y documental; sin prueba E2E runtime en este cierre)

Flujo:

Evento interno permitido
→ encolado asíncrono de delivery
→ envío HTTP firmado al endpoint del negocio

Resultado esperado:

- evento filtrado por `business_id` del recurso interno
- firma `HMAC-SHA256` con headers `X-SM-*`
- idempotencia por `webhook_id + event_id`
- retry solo en red/timeout/429/5xx con backoff definido

==================================================
ESCENARIO 24 — CANCELACIÓN PÚBLICA CONTROLADA (36C-1)
==================================================

Estado Fase 36C-1: PARCIAL (validación técnica/documental; sin runtime WordPress formal en este cierre)

Flujo:

Integración externa con API key + scope `appointments:cancel`
→ `POST /appointments/{id}/cancel`

Resultado esperado:

- lookup/update tenant-safe por `appointment_id + business_id` de credencial
- cancelación permitida solo en estados definidos
- si ya está `cancelled`, éxito estable/idempotente
- idempotencia por `idempotency_key` (body/header) con transient 24h

==================================================
ESCENARIO 25 — CONFIRMACIÓN PÚBLICA CONTROLADA (36C-2)
==================================================

Estado Fase 36C-2: PARCIAL (validación sintáctica y documental; sin prueba runtime WordPress formal en este cierre)

Flujo:

Integración externa con API key + scope `appointments:confirm`
→ `POST /appointments/{id}/confirm`

Resultado esperado:

- lookup/update tenant-safe por `appointment_id + business_id` de credencial
- transición permitida solo `scheduled -> confirmed`
- si ya está `confirmed`, éxito estable/idempotente
- si está `cancelled`, `completed` o `in_progress`, respuesta `409`
- idempotencia por `idempotency_key` (body/header) con transient 24h

==================================================
ESCENARIO 26 — CALENDARIO OPERATIVO ADMIN (37A)
==================================================

Estado Fase 37A: PARCIAL (wiring REST corregido; sin validación E2E visual completa en este cierre)

Flujo:

Administrador con `sm_manage_processes`
→ abre `Super Mechanic -> Calendar`
→ FullCalendar solicita eventos por rango visible
→ selecciona cita y cambia estado desde control rápido

Resultado esperado:

- feed interno responde en `GET /wp-json/super-mechanic/v1/admin/appointments/calendar`
- payload estable por evento (`id`, `title`, `start`, `end`, `url`, `extendedProps`)
- update de estado en `POST /wp-json/super-mechanic/v1/admin/appointments/{id}/status`
- cambio de estado vía `Appointment_Service` (sin update directo en repository)
- tenancy por `business_id` respetada en lectura y cambio de estado
- click en evento abre el detalle existente de la cita

==================================================
ESCENARIO 27 — REGRESION DE MEMORIA EN SERVICIOS CORE (HOTFIX-MEM-1)
==================================================

Estado HOTFIX-MEM-1: PARCIAL (validacion tecnica OK; runtime formal pendiente en este cierre)

Flujo:

Administrador
-> abre paneles con carga transversal (dashboard, clientes, procesos)
-> navega entre listados y detalles con tenancy activa

Resultado esperado:

- no aparece `Allowed memory size exhausted`
- no hay cascada de inicializacion entre services que degrade memoria progresivamente
- resolucion de `client_id` y `business_id` se mantiene estable por request

==================================================
ESCENARIO 28 — TIMELINE UNIFICADA DE VEHICULO (37A-6)
==================================================

Estado 37A-6: OK (runtime WordPress real validado en 2026-03-29)

Flujo:

Administrador con permisos de vehículos
→ abre `Super Mechanic -> Vehículos`
→ entra a `Ver` de un vehículo con dataset operativo mínimo

Resultado esperado:

- la sección `Timeline operativa del vehículo` muestra eventos de Proceso, Cita y Mantenimiento en un mismo vehículo
- el orden de eventos es cronológico descendente por fecha (`event_at`)
- los links operativos existen y abren detalle real:
  - `Abrir proceso` (`super-mechanic-processes`)
  - `Abrir cita` (`super-mechanic-appointments`)
  - `Abrir mantenimiento` (detalle de proceso maintenance)
- sin mezcla cross-tenant en la carga del vehículo validado

Evidencia runtime 2026-03-29 (dataset QA):

- `vehicle_id=12`
- `process_id=12`
- `appointment_id=5`
- `maintenance_id=5`
- flags de validación: `TIMELINE_HAS_PROCESS=1`, `TIMELINE_HAS_APPOINTMENT=1`, `TIMELINE_HAS_MAINTENANCE=1`, `TIMELINE_HAS_PROCESS_LINK=1`, `TIMELINE_HAS_APPOINTMENT_LINK=1`, `TIMELINE_CHRONO_DESC=1`

==================================================
ESCENARIO 29 - LIMPIEZA VISIBLE DE IDIOMA (38A-1)
==================================================

Estado 38A-1: COMPLETA

Flujo:

Administrador
-> revisa pantallas clave: dashboard admin, mechanic dashboard, procesos, clientes, vehiculos, citas, reportes, negocios, finanzas/pagos
-> verifica labels, botones, filtros y mensajes visibles sin mezcla ES/EN importante

Resultado esperado:

- textos visibles principales en ingles base
- textdomain `super-mechanic` mantenido en funciones i18n estandar
- sin cambios de schema ni logica funcional
- `php-lint` global limpio y smoke backend sin fatal

==================================================
ESCENARIO 30 — CONFIGURACION MONETARIA DINAMICA (38A-2)
==================================================

Estado 38A-2: PARCIAL (validacion tecnica OK; runtime UI manual pendiente)

Flujo:

Administrador
-> abre `Ajustes` y configura `Supported currencies`
-> valida coherencia de `Default currency` con la lista soportada
-> revisa selector `currency` en Negocios, Quotes e Invoices
-> valida filtros de moneda en Reportes

Resultado esperado:

- no existen listas de monedas rigidas en settings/reportes
- monedas base soportadas: `USD`, `EUR`, `COP`, `PAB`
- lista extensible por configuracion sin tocar codigo central
- `default_currency` siempre pertenece a `supported_currencies`
- sin cambios de schema ni conversion automatica de montos

==================================================
ESCENARIO 30B — SEGURIDAD DB BASE (38A-3)
==================================================

Estado 38A-3: PARCIAL (seguridad base validada; email admin no validado + inestabilidad externa registrada)

Flujo:

Administrador
-> genera/rota master password
-> ejecuta export JSON protegido
-> ejecuta reset DB protegido con confirmacion fuerte
-> verifica bloqueos por capability + nonce + master password en intentos invalidos

Resultado esperado:

- controles de seguridad DB base operativos en runtime admin
- export JSON y reset protegido funcionan sin tocar tablas core WordPress
- pendiente: validacion formal de envio email admin (si aplica en entorno)
- pendiente: cierre de inestabilidad externa registrada fuera de la logica del plugin

==================================================
ESCENARIO 31 — BACKUP/RESTAURACION OPERATIVA (38A-3B)
==================================================

Estado 38A-3B: COMPLETA (validacion tecnica + runtime manual dirigida)

Flujo:

Administrador
-> exporta DB en `JSON` (canonico), `CSV ZIP` y `Excel XML`
-> ejecuta import con JSON invalido y verifica rechazo por validacion previa
-> ejecuta import con JSON canonico valido y verifica restauracion exitosa

Resultado esperado:

- export JSON mantiene payload canonico (`schema_version`, `plugin_version`, `tables`)
- export CSV genera ZIP con `manifest.json` + 1 CSV por tabla del plugin
- export Excel genera XML compatible con Excel (sin librerias externas)
- import acepta solo JSON canonico
- import exige capability + nonce + master password
- import valida estructura completa antes de `START TRANSACTION`
- import aplica rollback completo en error
- import preserva baseline de negocio default (`sm_businesses` id=1) cuando aplica
- sin cambios de schema ni impacto en tablas core de WordPress

==================================================
ESCENARIO 32 — VINCULACION COMERCIAL BASE WOO (38B-1)
==================================================

Estado 38B-1: COMPLETA (validacion runtime WordPress real en Woo ON/OFF)

Flujo:

Administrador
-> con Woo activo abre tabs de quote/invoice/maintenance en un proceso
-> selecciona producto Woo para quote/invoice
-> registra parte en maintenance usando autofill Woo
-> repite validacion con Woo inactivo

Resultado esperado:

- con Woo activo:
  - selector Woo visible en quote/invoice/maintenance
  - quote/invoice persisten snapshot en `label` y `unit_price`
  - quote/invoice persisten `woo_product_id` en `reference_id`
  - maintenance usa Woo solo para autofill manual de nombre/precio
- con Woo inactivo:
  - selector Woo no visible
  - flujo manual de quote/invoice/maintenance sigue operativo
  - sin fatales ni bloqueos por dependencia de Woo
- no regresion:
  - totales de quote/invoice permanecen consistentes
  - sin cambios de schema

==================================================
ESCENARIO 33 — TOTALES AUTOMATICOS Y CONSISTENCIA COMERCIAL (38B-2)
==================================================

Estado 38B-2: COMPLETA (validacion runtime WordPress real en Woo ON/OFF)

Flujo:

Administrador
-> crea/edita quotes e invoices con items `custom` y `woo_product`
-> fuerza recálculo de totales
-> valida casos legacy con `line_total` inconsistente
-> repite validacion con Woo inactivo

Resultado esperado:

- `line_total` queda normalizado por formula: `quantity * unit_price`
- `recalculate_totals()` calcula por item y no depende de `line_total` legacy sin validacion
- entradas con `item_type=manual` se normalizan a `custom` sin romper compatibilidad
- precios Woo no se recalculan en tiempo real; se usa solo snapshot persistido
- si hay correccion de registros legacy, se registra como saneamiento controlado
- con Woo activo e inactivo, el calculo final mantiene consistencia y no hay regresion de totales
