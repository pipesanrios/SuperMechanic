MODULE BOUNDARIES — SUPER MECHANIC

Este archivo define los límites entre módulos del plugin.

Su objetivo es evitar que la lógica de negocio se mezcle entre dominios funcionales
y preservar la arquitectura modular del sistema.

==================================================
REGLA PRINCIPAL
==================================================

Cada módulo es responsable únicamente de su propio dominio.

Los módulos no deben acceder directamente a:

- tablas de otros módulos
- repositories de otros módulos
- lógica interna de otros módulos

La comunicación entre módulos debe hacerse a través de:

Services públicos.

==================================================
EJEMPLO CORRECTO
==================================================

Invoice_Service necesita datos de Quote.

Correcto:

Invoice_Service
→ usa Quote_Service

Incorrecto:

Invoice_Service
→ usa Quote_Repository directamente

==================================================
MÓDULOS DEL SISTEMA
==================================================

Core / Bootstrap

Security

Settings

Clients

Vehicles

Client-Vehicle Relations

Flows

Processes

Maintenance

Pre-Delivery

Paperwork

Dashboard

Quotes

Invoices

Payments

Attachments

Communication

Helpers

==================================================
DEPENDENCIAS PERMITIDAS
==================================================

Clients
→ Vehicles
→ Client-Vehicle Relations

Processes
→ Flows

Maintenance
→ Processes

Pre-Delivery
→ Processes

Paperwork
→ Processes

Quotes
→ Maintenance
→ Processes

Invoices
→ Quotes

Payments
→ Invoices

Attachments
→ Processes
→ Quotes
→ Invoices

Communication
→ Processes

Dashboard
→ agregador de múltiples módulos

Helpers
→ utilidades compartidas

==================================================
DEPENDENCIAS PROHIBIDAS
==================================================

Vehicles
→ Quotes

Invoices
→ Maintenance

Payments
→ Quotes

Clients
→ Invoices

Attachments
→ Payments

Communication
→ Payments

==================================================
REGLAS DE ACCESO A BASE DE DATOS
==================================================

Cada módulo tiene su propio Repository.

Ejemplos:

Quote_Repository
Invoice_Repository
Process_Repository

Nunca acceder a tablas de otros módulos desde un Repository.

Si un módulo necesita datos de otro:

usar su Service.

==================================================
REGLA DE EXPOSICIÓN DE SERVICIOS
==================================================

Cada módulo puede exponer:

1 Service principal.

Ejemplo:

Quote_Service
Invoice_Service
Process_Service
Attachment_Service

Los Controllers y Shortcodes deben consumir únicamente estos Services.

==================================================
MÓDULOS ESPECIALES
==================================================

Helpers

Puede ser usado por todos los módulos.

No debe contener lógica de negocio.

Solo utilidades.

Dashboard

Puede consumir múltiples Services.

Nunca debe escribir directamente en la base de datos.

Solo lectura agregada.

==================================================
REGLA DE EVOLUCIÓN
==================================================

Si un cambio requiere que un módulo acceda directamente
a la lógica interna de otro módulo:

detener implementación
explicar el motivo
evaluar rediseño

==================================================
VERIFICACIÓN ANTES DE IMPLEMENTAR
==================================================

Antes de escribir código confirmar:

1 módulo afectado
2 módulo que provee datos
3 Service que se utilizará
4 que no se está accediendo a Repository externo

==================================================
EJEMPLO REAL DE ERROR CRÍTICO
==================================================

Incorrecto:

Quote_Repository
→ consulta directamente sm_invoices

Correcto:

Quote_Service
→ usa Invoice_Service
→ Invoice_Service usa Invoice_Repository