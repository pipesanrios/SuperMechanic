# Fase 20. Automatizacion documental y estados derivados

## Estado

- completada de forma acotada y segura

## Alcance real aplicado

- automatizacion documental no persistente sobre eventos ya existentes
- sin attachments automaticos nuevos
- sin archivos fisicos redundantes
- sin cambios de schema
- sin cron
- sin colas

## Integracion real

- `Document_Service` agrega resolucion explicita de documento logico para `quote_approved` e `invoice_issued`
- `Event_Dispatcher` prepara disponibilidad documental logica al aprobar quotes y emitir invoices sin crear un flujo paralelo a la capa documental comun
- `invoice_paid` no genera comprobante automatico porque no existe aun una ruta documental reusable y deduplicada para ese artefacto
- `Process_Derived_State_Service` centraliza estados derivados seguros de procesos sin persistencia nueva
- `Invoice_Service` expone enriquecimiento reusable del estado visible de cobranza
- dashboard cliente y portal mecanico muestran estados derivados seguros sin mover logica a controllers

## Estados derivados implementados

- procesos:
  - `waiting_approval`
  - `waiting_payment`
  - `ready_for_delivery` solo cuando `pre_delivery.delivery_ready = 1`
  - `completed`
- invoices:
  - `pending`
  - `partial`
  - `paid`

## Restricciones respetadas

- no se persisten estados derivados
- no se generan attachments automaticos
- no se toca `includes/modules/*`
- no se agrega SQL fuera de repositories
- `Notification_Service` no absorbe logica documental

## Deuda tecnica abierta

- no existe aun una ruta documental reusable para comprobantes de pago automaticos por `payment_id`
- los estados derivados de proceso hoy se exponen en dashboard/portal, pero todavia no alimentan bloques analiticos o reportes dedicados

## Cierre final

- sintaxis PHP validada en los archivos modificados
- bootstrap compartiendo `Document_Service` con `Event_Dispatcher` sin romper el wiring real
- sin cambios de schema
- sin cambios en `includes/modules/*`
