# Fase 56P10-B - Endpoint Protection Hardening

Fecha: 2026-05-05  
Contrato: `docs/contracts/56P10-B.md`  
Validation contract: `docs/contracts/validation/56P10-B-validation.md`

## Scope ejecutado

- Hardening de rutas REST `sm/v1` solamente.
- Se reemplazaron todos los `permission_callback => __return_true` del controlador formal `sm/v1`.
- Se agregaron callbacks de permiso nombrados en `Public_API_Controller`.
- Se mantuvo el modelo decidido:
  - `sm/v1` usa autenticacion de usuario WordPress.
  - no se agregaron API keys a `sm/v1`.
  - integraciones externas permanecen bajo `super-mechanic-public/v1`.
- No se cambiaron rutas, namespace, payloads de exito, servicios ni comportamiento interno de callbacks.

## Analisis aplicado desde 56P10-A

- Rutas read-only:
  - `GET /clients`
  - `GET /vehicles`
  - `GET /processes`
  - `GET /processes/{id}`
  - `GET /invoices`
  - `GET /reporting/summary`
- Ruta write/mutation:
  - `POST /quotes/{id}/approve`
- Necesidades de acceso:
  - bloqueo temprano de usuarios no autenticados en la capa REST
  - validacion temprana del `business_id` solicitado cuando se envia
  - preservacion de filtros/ownership internos existentes en callbacks y servicios
  - proteccion especifica de aprobacion de cotizacion antes de ejecutar la mutacion

## Cambios implementados

Archivo modificado:
- `includes/api/controllers/class-public-api-controller.php`

Callbacks agregados:
- `permission_can_read( WP_REST_Request $request )`
- `permission_can_write( WP_REST_Request $request )`
- `permission_can_approve_quote( WP_REST_Request $request )`
- helper protegido `permission_user_can_access_business_scope( WP_REST_Request $request )`

Comportamiento:
- usuarios no autenticados quedan bloqueados por `permission_callback` con `WP_Error` status `401`.
- solicitudes con `business_id` invalido para el usuario quedan bloqueadas por `permission_callback` con status `403`.
- `POST /quotes/{id}/approve` valida acceso a la cotizacion en su permission callback antes de llegar al callback de mutacion.
- las validaciones internas existentes permanecen intactas como defensa en profundidad.

## Mapa de rutas protegido

| Metodo | Ruta | Nuevo permission_callback |
|---:|---|---|
| GET | `/clients` | `permission_can_read` |
| GET | `/vehicles` | `permission_can_read` |
| GET | `/processes` | `permission_can_read` |
| GET | `/processes/(?P<id>\d+)` | `permission_can_read` |
| GET | `/invoices` | `permission_can_read` |
| GET | `/reporting/summary` | `permission_can_read` |
| POST | `/quotes/(?P<id>\d+)/approve` | `permission_can_approve_quote` |

## Validaciones

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P10-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual/static:
- `sm/v1` no contiene `permission_callback => __return_true` -> PASS
- las 6 rutas read-only usan `permission_can_read` -> PASS
- la ruta de aprobacion de cotizacion usa `permission_can_approve_quote` -> PASS
- `permission_can_write` existe como politica base para mutaciones -> PASS
- callbacks internos existentes permanecen activos -> PASS

Runtime:
- llamadas REST reales en WordPress -> NOT_RUN

## Riesgos / diferidos

- Queda pendiente smoke test runtime con:
  - usuario no autenticado
  - usuario autenticado con lectura permitida
  - usuario autenticado sin ownership de cotizacion
  - usuario con `business_id` invalido
- `sm/v1` sigue sin API keys por decision de fase; integraciones externas deben continuar usando `super-mechanic-public/v1`.
- No se agrego rate limiting ni auditoria de uso por endpoint.
- No se cambio el formato de errores REST generados por `permission_callback`; los payloads internos de callbacks se preservan para solicitudes que llegan al callback.

## Documentacion actualizada

- `docs/tasks/2026-04-56p10-b-endpoint-protection-hardening.md`
- `docs/CURRENT_STATE.md`
- `docs/QA_REPORT.md`

## Estado

- Estado tecnico: PASS
- Estado de fase: COMPLETA
