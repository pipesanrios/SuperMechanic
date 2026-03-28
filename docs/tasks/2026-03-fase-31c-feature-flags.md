# Fase 31C. Restriccion de features (base centralizada)

## Objetivo

Implementar una base centralizada de plan efectivo y feature flags, preparada para provider externo futuro, con gating minimo y no destructivo.

## Alcance implementado

- Catalogo central de features y planes:
  - `includes/helpers/class-feature-flags.php`
- Servicio central de acceso a plan/features:
  - `includes/helpers/class-plan-access-service.php`
- Resolucion de plan efectivo local:
  - `Plan_Access_Service::get_effective_plan()`
  - `License_Service::get_plan_signal()`
- Check reutilizable central:
  - `Plan_Access_Service::is_feature_enabled()`
- Persistencia local sin schema changes en `sm_settings`:
  - `plan.plan_key`
  - `plan.status`
  - `plan.source`
  - `plan.message`
  - `features.feature_flags`
- Estado visible basico en Ajustes:
  - bloque read-only "Plan and feature access"
- Gating minimo en superficies admin no criticas:
  - reportes admin
  - export CSV de reportes
  - catalogo admin de shortcodes

## Archivos nuevos

- `includes/helpers/class-feature-flags.php`
- `includes/helpers/class-plan-access-service.php`

## Archivos modificados

- `includes/helpers/class-settings-service.php`
- `includes/helpers/class-license-service.php`
- `includes/class-settings.php`
- `includes/reports/class-report-admin-controller.php`
- `includes/class-shortcode-admin-controller.php`
- `docs/CURRENT_STATE.md`
- `docs/MODULE_REGISTRY.md`
- `.vscode/AI_CONTEXT.md`

## Features definidas en 31C

- `admin_reports`
- `admin_shortcode_catalog`
- `reports_csv_export`

## Seguridad y compatibilidad

- Sin billing.
- Sin suscripciones reales.
- Sin cambios de schema.
- Sin refactor amplio.
- Sin bloquear funciones core por default.
- Sin checks dispersos: gating central por `Plan_Access_Service`.
- Preparado para proveedor futuro con filtros:
  - `sm_plan_access_effective_plan`
  - `sm_plan_access_feature_overrides`

## Validacion tecnica ejecutada

- `php -l includes/helpers/class-feature-flags.php`
- `php -l includes/helpers/class-plan-access-service.php`
- `php -l includes/helpers/class-settings-service.php`
- `php -l includes/helpers/class-license-service.php`
- `php -l includes/class-settings.php`
- `php -l includes/reports/class-report-admin-controller.php`
- `php -l includes/class-shortcode-admin-controller.php`

Resultado:
- sin errores de sintaxis PHP.

## Exclusiones deliberadas de 31C

- Sin provider externo real de planes/licencias.
- Sin UI de edicion de feature flags (estado solo lectura en ajustes).
- Sin gating sobre procesos/clientes/vehiculos/invoices/payments base.
- Sin cambios de modelo de datos ni migraciones.

## Estado final

- FASE 31C: `COMPLETO`.
- Base lista para evolucion controlada en fase siguiente sin romper compatibilidad.

## Siguiente fase

- Se puede pasar a FASE 32.
