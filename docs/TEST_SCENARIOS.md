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

Estado Fase 14: PARCIAL

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
→ accede portal cliente
→ descarga documento

Resultado esperado:

- validación de ownership
- descarga mediante Download_Service
- no exposición directa de file_url

==================================================
ESCENARIO 13 — PORTAL CLIENTE
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
- la actividad reciente del portal cliente debe respetar `customer_visible` en logs de proceso

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
ESCENARIO 16 — PORTAL MECÁNICO OPERATIVO
==================================================

Estado Fase 18: OK

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

==================================================
RESUMEN FASE 14
==================================================

- OK: 1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 14, 15
- PARCIAL: 6, 13
- DESALINEADO: ninguno critico
- FRÁGIL: ninguno critico

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
