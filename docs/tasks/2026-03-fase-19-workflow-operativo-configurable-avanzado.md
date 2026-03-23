# Fase 19. Workflow operativo configurable avanzado

- Estado: completado
- Fecha: 2026-03

## Objetivo real implementado

Endurecer el runtime actual de `flows + steps + processes` sin crear un motor nuevo ni cambiar schema.

## Archivos modificados

- `includes/flows/class-flow-step-service.php`
- `includes/processes/class-process-service.php`

## Archivos creados

- `docs/tasks/2026-03-fase-19-workflow-operativo-configurable-avanzado.md`

## Tablas afectadas

- `sm_flows`
- `sm_flow_steps`
- `sm_processes`
- `sm_process_step_logs`

## Integracion real

- `Flow_Step_Service` agrega validacion reusable de transiciones lineales entre pasos activos usando `step_order`
- `Process_Service::update_current_step()` reutiliza esa validacion y bloquea saltos arbitrarios
- `Process_Service::update_process()` aplica la misma regla cuando cambia `current_step_id`
- entrar en un paso final sincroniza el estado del proceso a `completed` y registra el log de cambio de estado
- mover un proceso ya finalizado a un paso no final queda bloqueado por la ruta operativa simple de cambio de paso

## Validacion tecnica

- `php -l includes/flows/class-flow-step-service.php` OK
- `php -l includes/processes/class-process-service.php` OK
- sin cambios de schema
- sin cambios en `includes/modules/*`

## Deuda tecnica abierta

- el workflow sigue siendo lineal por `step_order`
- no existe aun un grafo formal de transiciones condicionales
- `requires_approval`, `requires_note` y `metadata` siguen como base disponible, no como motor completo de restricciones
