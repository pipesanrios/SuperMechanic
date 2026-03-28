# FASE 32A - Calendario / Citas (base operativa)

Fecha: 2026-03-28  
Estado: COMPLETO

## Objetivo aplicado

Implementar la base operativa de citas con alcance minimo y seguro:

- CRUD admin de citas
- relacion cita -> cliente
- relacion cita -> vehiculo
- process_id opcional
- asignacion de mecanico con criterio unico `assigned_to`
- estados basicos de cita
- filtros por fecha, mecanico y estado
- listado admin usable

## Cambios de arquitectura

Modulo nuevo en arquitectura activa:

- `includes/appointments/class-appointment-repository.php`
- `includes/appointments/class-appointment-service.php`
- `includes/appointments/class-appointment-admin-controller.php`
- `includes/appointments/class-appointment-list-table.php`

Wiring runtime:

- `includes/class-plugin.php`
- `includes/class-admin-menu.php`

## Schema

Cambio minimo indispensable:

- nueva tabla `sm_appointments`
- version de schema: `1.10.0`

Campos base:

- `id`
- `client_id`
- `vehicle_id`
- `process_id` (nullable)
- `assigned_to`
- `appointment_status`
- `appointment_date`
- `start_at`
- `notes`
- `created_at`
- `updated_at`

## Reglas y seguridad aplicadas

- solo `includes/*`
- patron `Controller -> Service -> Repository`
- SQL solo en `Appointment_Repository`
- nonces y permisos admin (`sm_manage_processes`)
- validacion de integridad cliente/vehiculo/proceso en service
- sin exponer rutas/documentos ni `file_url`

## Estados operativos de cita

- `scheduled`
- `confirmed`
- `in_progress`
- `completed`
- `cancelled`

## Exclusiones deliberadas de FASE 32A

- sin automatizaciones
- sin notificaciones avanzadas
- sin integraciones externas
- sin kanban
- sin calendario JS complejo
- sin API de citas

## Deuda tecnica controlada

- no existe vista calendario visual avanzada aun; la fase queda en listado admin filtrable
- no se agrega motor de conflictos/solapamientos en esta fase base
