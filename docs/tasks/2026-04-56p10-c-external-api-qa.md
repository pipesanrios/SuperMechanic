# Fase 56P10-C - External API QA

Fecha: 2026-05-06  
Contrato: `docs/contracts/56P10-C.md`  
Validation contract: `docs/contracts/validation/56P10-C-validation.md`

## Scope ejecutado

- QA runtime de endpoints `sm/v1` endurecidos en 56P10-B.
- No se modifico codigo.
- No se redisenaron endpoints.
- No se cambio modelo de autenticacion.
- No se tocaron integraciones publicas `super-mechanic-public/v1`.

## Entorno de QA

- WordPress local cargado desde:
  - `C:/xampp/htdocs/Mekvort/wp-load.php`
- URL detectada:
  - `http://localhost/Mekvort`
- Usuarios runtime disponibles:
  - `admin@mardisom.com` (`administrator`, user ID `1`)
  - `client@mekvort.local` (`sm_client`, user ID `8`)
- WP-CLI no disponible en shell.
- Se uso:
  - HTTP local real para pruebas no autenticadas contra `/wp-json/sm/v1/...`
  - dispatch runtime de WordPress REST (`rest_do_request`) para pruebas autenticadas con `wp_set_current_user(...)`

## Runtime QA matrix

| Caso | Metodo | Ruta | Usuario | Resultado | Estado |
|---|---:|---|---|---:|---|
| unauthenticated read | GET | `/wp-json/sm/v1/clients` | none | `401 sm_api_authentication_required` | PASS |
| unauthenticated read | GET | `/wp-json/sm/v1/vehicles` | none | `401 sm_api_authentication_required` | PASS |
| unauthenticated read | GET | `/wp-json/sm/v1/processes` | none | `401 sm_api_authentication_required` | PASS |
| unauthenticated mutation | POST | `/wp-json/sm/v1/quotes/1/approve` | none | `401 sm_api_authentication_required` | PASS |
| authenticated read | GET | `/sm/v1/clients` | admin user `1` | `200`, payload keys `success,data,meta` | PASS |
| authenticated read | GET | `/sm/v1/vehicles` | admin user `1` | `200`, payload keys `success,data,meta` | PASS |
| authenticated read | GET | `/sm/v1/processes` | admin user `1` | `200`, payload keys `success,data,meta` | PASS |
| authenticated read | GET | `/sm/v1/reporting/summary` | admin user `1` | `200`, payload keys `success,data,meta` | PASS |
| unauthorized mutation | POST | `/sm/v1/quotes/999999/approve` | client user `8` | `403 sm_api_quote_approve_forbidden` | PASS |

## Blocked vs allowed route results

Blocked:
- unauthenticated read requests blocked at REST permission layer with `401`.
- unauthenticated quote approval blocked at REST permission layer with `401`.
- authenticated client mutation against inaccessible/nonexistent quote blocked with `403`.

Allowed:
- authenticated administrator read requests succeeded for:
  - clients
  - vehicles
  - processes
  - reporting summary

## Compatibility verification

- Namespace remains `sm/v1`.
- HTTP path remains `/wp-json/sm/v1/...`.
- Route registration for `/sm/v1/clients` remains active.
- Authenticated successful responses preserve the existing payload shape:
  - `success`
  - `data`
  - `meta`
- Error responses from REST permission layer use WordPress REST error shape:
  - `code`
  - `message`
  - `data.status`
- No route path changes observed.
- No `super-mechanic-public/v1` changes were made or required.

## Validation

Automated baseline:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P10-C-validation.md --output=text` -> PASS execution, manual-only contract:
  - PASS: 0
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5

Manual/runtime:
- `unauthenticated_reads_blocked` -> PASS
- `unauthenticated_quote_approve_blocked` -> PASS
- `authenticated_reads_work` -> PASS
- `unauthorized_quote_approve_blocked` -> PASS
- `response_compatibility_preserved` -> PASS

## Discovered issues

- No endpoint protection regression found in the tested matrix.
- No critical bug discovered.
- No code changes required.

## Notes / limitations

- Authenticated checks used WordPress runtime dispatch with `wp_set_current_user(...)`, not browser cookie or Application Password HTTP auth, because no external HTTP credentials were available in the session.
- The unauthorized mutation test used quote ID `999999` intentionally to avoid mutating real quote data.
- A future transport-level smoke can add cookie nonce or Application Password tests if credentials are provided.

## Documentacion actualizada

- `docs/tasks/2026-04-56p10-c-external-api-qa.md`
- `docs/CURRENT_STATE.md`
- `docs/QA_REPORT.md`

## Estado

- Estado runtime QA: PASS
- Estado de fase: COMPLETA
