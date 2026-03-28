# Fase 30. Tenancy base

- Estado: completado
- Fecha: 2026-03

## Objetivo

Preparar la base arquitectonica de tenancy para evolucion futura SaaS sin activar multi-tenant real ni romper compatibilidad single-business.

## Cambios implementados

- se crea `Business_Context_Service` como capa unica de contexto de negocio
- la capa reutiliza `Settings_Service` para resolver `business.business_context_key` desde `sm_settings`
- el contrato runtime queda explicitado como preparatorio:
  - `mode = single_business`
  - `business_context_key` estable
  - `business_id = null` (reservado para fases futuras)
  - `is_tenancy_active = false`
- se agrega wiring minimo en `class-plugin.php` para inicializar la capa sin alterar flujos actuales
- se actualiza documentacion tecnica base para dejar explicito el alcance y las exclusiones de Fase 30

## Archivos creados

- `includes/helpers/class-business-context-service.php`

## Archivos modificados

- `includes/class-plugin.php`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`

## Tablas afectadas

- sin cambios de schema
- sin columnas nuevas
- sin migraciones

## Exclusiones deliberadas de Fase 30

- no se implementa multi-tenant real
- no se agrega `business_id` a tablas
- no se agregan filtros por negocio en repositories
- no se modifica enforcement en `Access_Control_Service`
- no se cambia comportamiento funcional de controllers, services ni API actual

## Deuda tecnica que sigue abierta

- definir contrato de activacion tenancy para futuras fases (`business_id`, estrategia de migracion y boundaries por modulo)
- definir secuencia de adopcion tenant-aware en API/reportes/ownership antes de activar filtros de negocio
