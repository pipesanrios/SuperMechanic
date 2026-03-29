# HOTFIX-MEM-1 — Fatal memory exhausted (post 37A-3)

Fecha: 2026-03-29
Estado: COMPLETO

## Contexto

Se detecto un bloqueante de runtime WordPress:

- `Allowed memory size exhausted` en `includes/clients/class-client-service.php`
- luego desplazado a `includes/helpers/class-access-control-service.php`

## Causa raiz consolidada

- Cascadas de inicializacion y dependencias indirectas entre services/repositorios en rutas de acceso/tenancy.
- Resoluciones repetitivas de contexto/cliente en el mismo request sin memoizacion local.

## Correccion aplicada (sin ampliar alcance)

- Inicializacion lazy de dependencias sensibles en services afectados.
- Cache por request para resoluciones repetidas de `client_id` y `business_id`.
- Ajustes minimos para cortar cascadas sin mover SQL fuera de repositories y sin cambiar schema.

## Archivos de codigo involucrados

- `includes/clients/class-client-service.php`
- `includes/clients/class-client-repository.php`
- `includes/helpers/class-access-control-service.php`
- `includes/quotes/class-quote-service.php`
- `includes/maintenance/class-maintenance-service.php`

## Validacion tecnica

- `php scripts\\php-lint.php --all` -> OK (0 errores)
- `php -l super-mechanic.php` -> OK

## Validacion runtime

- No ejecutada en este cierre documental.
- Se requiere smoke test manual en WordPress para confirmar no reaparicion del fatal bajo carga operativa.

