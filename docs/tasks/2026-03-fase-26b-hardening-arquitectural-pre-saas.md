# Fase 26B. Hardening arquitectural pre-SaaS

- Estado: completado
- Fecha: 2026-03

## Objetivo

Reducir bloqueos arquitectonicos detectados en la auditoria SaaS sin cambiar el comportamiento funcional del plugin ni abrir una arquitectura paralela.

## Cambios implementados

- `Client_Dashboard_Controller` deja de concentrar parte de la agregacion pesada del detalle cliente y delega datasets reutilizables en `Client_Process_View_Service`
- `Client_Vehicle_Service::transfer_vehicle()` pasa a ejecutarse dentro de `Client_Vehicle_Transaction_Repository`
- `Flow_Service::delete_flow()` y `Flow_Step_Service::reorder_steps()` pasan a ejecutarse dentro de `Flow_Transaction_Repository`
- el admin de adjuntos deja de enlazar `file_url` directo y reutiliza `Download_Service`
- la documentacion base corrige contradicciones criticas sobre el modelo real de settings (`sm_settings` + fallback legacy `super_mechanic_settings`), `plate`, `flow_step_id` y estado real de fase

## Archivos creados

- `includes/dashboard/class-client-process-view-service.php`
- `includes/relations/class-client-vehicle-transaction-repository.php`
- `includes/flows/class-flow-transaction-repository.php`

## Archivos modificados

- `includes/dashboard/class-client-dashboard-controller.php`
- `includes/class-plugin.php`
- `includes/relations/class-client-vehicle-service.php`
- `includes/flows/class-flow-service.php`
- `includes/flows/class-flow-step-service.php`
- `includes/attachments/class-attachment-admin-controller.php`
- documentacion tecnica base de arquitectura, estado, mapa de sistema, registry y roadmap

## Tablas afectadas

- `sm_client_vehicles`
- `sm_vehicles`
- `sm_flows`
- `sm_flow_steps`
- `sm_processes`
- `sm_quotes`
- `sm_invoices`
- `sm_payments`
- `sm_attachments`
- `sm_comments`

## Deuda tecnica que sigue abierta

- `Process_Admin_Controller` sigue siendo el controller admin mas sensible por concentracion de orquestacion
- las rutas admin de PDF de quotes e invoices siguen como excepcion controlada por nonce/capability y no pasan aun por `Download_Service`
- la base local de scripts sigue siendo validacion tecnica minima; no reemplaza pruebas funcionales WordPress ni CI real

