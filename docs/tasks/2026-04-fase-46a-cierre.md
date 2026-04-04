# Fase 46A — Query Optimization (Cierre Técnico)

Date: 2026-04-04

## Scope aplicado

- `includes/dashboard/class-admin-dashboard-controller.php`
- `includes/automation/class-execution-log-service.php`
- `docs/contracts/validation/46A-validation.md`
- `docs/CURRENT_STATE.md`
- `.vscode/AI_CONTEXT.md`

## Causa raíz detectada

1. En páginas de Automation Center y Logs se obtenía `business_id` cargando `get_user_workload()` completo, agregando lecturas innecesarias.
2. En Logs había resolución de actor por fila (`get_userdata` por cada row), generando patrón N+1.
3. Se creaba `Operational_Rules_Service` de forma repetida en controller en vez de reutilizar una instancia por request.

## Optimización aplicada

1. Resolver `business_id` vía `Business_Context_Service` en controller cuando no se requiere payload de workload.
2. Reutilizar una única instancia de `Operational_Rules_Service` en `Admin_Dashboard_Controller`.
3. Batch lookup de actores en `Execution_Log_Service::get_logs_list()` con cache en-request.
4. Normalización de `docs/contracts/validation/46A-validation.md` a formato JSON compatible con `qa-runner`.

## No cambios funcionales

- Sin cambios de lógica de negocio.
- Sin cambios de seguridad/nonce/capabilities.
- Sin cambios de CRM Pipeline.
- Sin cambios de estructura de tablas.

## Validación prevista por contrato

- `php scripts/php-lint.php --all`
- `php scripts/qa-runner.php --contract=docs/contracts/validation/46A-validation.md --output=text`
- Runtime/manual:
  - dashboard perceptiblemente más rápido
  - logs estables
  - mismos resultados funcionales
  - sin regresión dashboard / CRM Pipeline / 43A–45E

## Deuda técnica abierta

- Sin transients persistentes (postergado por scope 46A).
- Sin lazy loading de UI (postergado por scope 46A).
- Sin profiling avanzado por endpoint (postergado por scope 46A).
