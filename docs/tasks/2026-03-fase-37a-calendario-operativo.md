# FASE 37A — Calendario Operativo de Citas

Fecha: 2026-03-29  
Estado: COMPLETA

## Objetivo

Agregar una vista de calendario operativa en admin para gestionar citas de forma visual sin cambiar schema ni mezclar con la API publica.

## Implementacion

- Nueva pantalla admin: `Super Mechanic -> Calendar` (`page=super-mechanic-calendar`).
- Integracion FullCalendar local (vendorizado en `assets/vendor/fullcalendar/*`, sin CDN).
- Endpoint interno REST autenticado:
  - `GET /super-mechanic/v1/admin/appointments/calendar`
  - `POST /super-mechanic/v1/admin/appointments/{id}/status`
- Tenancy activa por `business_id` mediante `Appointment_Service -> Appointment_Repository` (contexto resuelto por `Business_Context_Service`).
- Cambio de estado desde calendario mediante metodo especifico:
  - `Appointment_Service::update_appointment_status_from_calendar()`
  - conserva sync Google Calendar y dispatch de eventos internos.
- Payload estable de eventos para FullCalendar:
  - `id`
  - `title`
  - `start`
  - `end`
  - `url`
  - `extendedProps.appointment_status`
  - `extendedProps.client_name`
  - `extendedProps.vehicle_label`
  - `extendedProps.mechanic_name`
  - `extendedProps.process_id`
- Filtros por rango visible `start/end` en endpoint de calendario.
- Carga condicional de assets solo en `page=super-mechanic-calendar`.

## Mapeo estado -> color

- `scheduled` -> palette `primary`
- `confirmed` -> palette `success`
- `in_progress` -> palette `warning`
- `completed` -> palette `neutral`
- `cancelled` -> palette `danger`

Colores alineados con los badges existentes de `assets/css/admin.css`.

## Archivos modificados

- `includes/class-admin-menu.php`
- `includes/class-assets.php`
- `includes/appointments/class-appointment-admin-controller.php`
- `includes/appointments/class-appointment-service.php`
- `assets/css/admin.css`

## Archivos nuevos

- `assets/js/admin-calendar.js`
- `assets/vendor/fullcalendar/fullcalendar-6.1.19.index.global.min.js`
- `assets/vendor/fullcalendar/LICENSE.md`

## Validaciones ejecutadas

- `php -l includes/class-admin-menu.php`
- `php -l includes/class-assets.php`
- `php -l includes/appointments/class-appointment-admin-controller.php`
- `php -l includes/appointments/class-appointment-service.php`

Sin errores de sintaxis.

## Exclusiones deliberadas (fuera de 37A)

- Sin drag & drop de eventos.
- Sin modal inline complejo.
- Sin creacion/edicion inline completa dentro del calendario.
- Sin cambios de schema.
- Sin cambios en API publica.

