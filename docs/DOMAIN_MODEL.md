DOMAIN MODEL — SUPER MECHANIC

Este documento define el modelo de dominio del plugin Super Mechanic.

El objetivo es clarificar:

- entidades principales
- relaciones entre entidades
- responsabilidades de cada módulo

Este documento sirve como referencia conceptual para:

desarrolladores
agentes de IA
documentación del sistema

==================================================
ENTIDADES PRINCIPALES
==================================================

CLIENT

Representa una persona o empresa que posee vehículos
y puede solicitar servicios.

Tabla:

sm_clients

Responsabilidades:

- almacenar información del cliente
- vincular clientes con vehículos
- servir como punto de ownership del sistema

==================================================

VEHICLE

Representa un vehículo registrado en el sistema.

Tabla:

sm_vehicles

Un vehículo puede existir en el sistema
sin estar asignado a un cliente.

La relación cliente-vehículo se maneja mediante:

sm_client_vehicles

==================================================

CLIENT VEHICLE RELATION

Representa la relación entre un cliente y un vehículo.

Tabla:

sm_client_vehicles

Permite:

- múltiples vehículos por cliente
- historial de propietarios
- transferencias de propiedad

==================================================

FLOW

Define un flujo de trabajo configurable.

Tabla:

sm_flows

Un flujo define:

- pasos del proceso
- orden de ejecución
- estados posibles

==================================================

FLOW STEP

Representa un paso dentro de un flujo.

Tabla:

sm_flow_steps

Ejemplos:

- revisión inicial
- cambio de aceite
- trámite de placa
- revisión final

==================================================

PROCESS

Representa un proceso activo en el sistema.

Tabla:

sm_processes

Un proceso conecta:

cliente
vehículo
flujo

Puede representar:

- mantenimiento
- entrega de vehículo
- trámite administrativo

==================================================

PROCESS STEP LOG

Registra la evolución de un proceso.

Tabla:

sm_process_step_logs

Registra eventos como:

step_initialized
step_transition
status_changed

Esto permite construir la timeline del proceso.

==================================================

MAINTENANCE

Representa una intervención técnica en un vehículo.

Tabla:

sm_maintenance

Puede incluir:

- partes
- mano de obra
- diagnóstico

Tablas relacionadas:

sm_maintenance_parts
sm_maintenance_labor

==================================================

PRE DELIVERY

Representa tareas previas a la entrega de un vehículo.

Tabla:

sm_pre_delivery

Ejemplos:

- seguro
- placa
- revisión final

==================================================

PAPERWORK

Representa trámites administrativos.

Tabla:

sm_paperwork

Puede incluir múltiples elementos.

Tabla relacionada:

sm_paperwork_items

==================================================

QUOTE

Representa una cotización.

Tabla:

sm_quotes

Generalmente se origina desde:

Maintenance

Tabla de items:

sm_quote_items

==================================================

INVOICE

Representa una factura generada a partir de una cotización.

Tabla:

sm_invoices

Tabla de items:

sm_invoice_items

==================================================

PAYMENT

Representa un pago realizado por el cliente.

Tabla:

sm_payments

Los pagos se aplican a:

Invoices

==================================================

ATTACHMENT

Representa documentos asociados a procesos,
cotizaciones o facturas.

Tabla:

sm_attachments

Ejemplos:

- documentos
- fotos del vehículo
- archivos PDF

==================================================

COMMENT

Representa comunicación asociada a un proceso.

Tabla:

sm_comments

Permite interacción entre:

administradores
mecánicos
clientes

==================================================

NOTIFICATION

Representa notificaciones del sistema.

Tabla:

sm_notifications

Se utilizan para:

- avisos de estado
- mensajes internos
- notificaciones al cliente

==================================================
RELACIONES PRINCIPALES
==================================================

Cliente
→ ClientVehicle
→ Vehicle

Vehicle
→ Process

Process
→ Flow
→ FlowSteps

Process
→ Maintenance
→ Quote
→ Invoice
→ Payment

Process
→ Paperwork

Process
→ Attachments

Process
→ Comments

Process
→ Timeline (step logs)

==================================================
FLUJO PRINCIPAL DEL NEGOCIO
==================================================

Cliente
→ Vehículo
→ Proceso

Proceso
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
REGLA DE MODELO DE DOMINIO
==================================================

Las entidades del dominio no deben mezclarse.

Ejemplos incorrectos:

Invoice accediendo directamente a tablas de Maintenance

Payments accediendo a Quotes sin pasar por Invoices

Los módulos deben respetar las dependencias
definidas en MODULE_BOUNDARIES.md.

==================================================
FUENTE DE VERDAD
==================================================

Si existe diferencia entre este documento
y el código real del sistema:

la fuente de verdad es el código real del plugin.