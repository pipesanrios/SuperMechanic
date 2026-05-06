# Fase 56P10-A - API Auth Model Audit

Fecha: 2026-05-05  
Contrato: `docs/contracts/56P10-A.md`  
Validation contract: `docs/contracts/validation/56P10-A-validation.md`

## Scope ejecutado

- Auditoria read-only de rutas REST registradas en runtime activo.
- Inventario de namespaces, metodos, callbacks y `permission_callback`.
- Revision de comportamiento actual de autenticacion/autorizacion:
  - cookie / current WordPress user
  - nonce explicito cuando aplica
  - capabilities
  - API key / token
  - comportamiento sin autenticacion
- Documentacion de gaps para fase 56P10-B.

No se implemento ningun cambio de autenticacion, permisos, endpoints, schema, frontend ni logica de negocio.

## Inventario de rutas REST

### API formal `sm/v1`

Archivo: `includes/api/controllers/class-public-api-controller.php`

| Namespace | Metodo | Ruta | Controller/method | permission_callback |
|---|---:|---|---|---|
| `sm/v1` | GET | `/clients` | `Public_API_Controller::get_clients` | `__return_true` |
| `sm/v1` | GET | `/vehicles` | `Public_API_Controller::get_vehicles` | `__return_true` |
| `sm/v1` | GET | `/processes` | `Public_API_Controller::get_processes` | `__return_true` |
| `sm/v1` | GET | `/processes/(?P<id>\d+)` | `Public_API_Controller::get_process` | `__return_true` |
| `sm/v1` | GET | `/invoices` | `Public_API_Controller::get_invoices` | `__return_true` |
| `sm/v1` | GET | `/reporting/summary` | `Public_API_Controller::get_reporting_summary` | `__return_true` |
| `sm/v1` | POST | `/quotes/(?P<id>\d+)/approve` | `Public_API_Controller::approve_quote` | `__return_true` |

### Internal/admin REST `super-mechanic/v1`

Files:
- `includes/dashboard/class-admin-rest-controller.php`
- `includes/dashboard/class-client-rest-controller.php`
- `includes/appointments/class-appointment-admin-controller.php`
- `includes/integrations/google-calendar/class-google-calendar-webhook-controller.php`

| Namespace | Metodo | Ruta | Controller/method | permission_callback |
|---|---:|---|---|---|
| `super-mechanic/v1` | GET | `/admin/processes` | `Admin_REST_Controller::get_admin_processes` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/processes/(?P<id>\d+)` | `Admin_REST_Controller::get_admin_process` | `check_admin_permission` |
| `super-mechanic/v1` | POST | `/admin/processes/(?P<id>\d+)/status` | `Admin_REST_Controller::update_admin_process_status` | `check_admin_process_write_permission` |
| `super-mechanic/v1` | POST | `/admin/processes/(?P<id>\d+)/internal-comment` | `Admin_REST_Controller::create_admin_process_internal_comment` | `check_admin_process_write_permission` |
| `super-mechanic/v1` | GET | `/admin/vehicles` | `Admin_REST_Controller::get_admin_vehicles` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/vehicles/(?P<id>\d+)` | `Admin_REST_Controller::get_admin_vehicle` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/clients` | `Admin_REST_Controller::get_admin_clients` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/clients/(?P<id>\d+)` | `Admin_REST_Controller::get_admin_client` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/quotes` | `Admin_REST_Controller::get_admin_quotes` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/quotes/(?P<id>\d+)` | `Admin_REST_Controller::get_admin_quote` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/invoices` | `Admin_REST_Controller::get_admin_invoices` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/admin/invoices/(?P<id>\d+)` | `Admin_REST_Controller::get_admin_invoice` | `check_admin_permission` |
| `super-mechanic/v1` | GET | `/client/processes` | `Client_REST_Controller::get_client_processes` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/processes/(?P<id>\d+)` | `Client_REST_Controller::get_client_process` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/vehicles` | `Client_REST_Controller::get_client_vehicles` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/vehicles/(?P<id>\d+)` | `Client_REST_Controller::get_client_vehicle` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/quotes` | `Client_REST_Controller::get_client_quotes` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/quotes/(?P<id>\d+)` | `Client_REST_Controller::get_client_quote` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/invoices` | `Client_REST_Controller::get_client_invoices` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/client/invoices/(?P<id>\d+)` | `Client_REST_Controller::get_client_invoice` | `check_client_portal_permission` |
| `super-mechanic/v1` | GET | `/admin/appointments/calendar` | `Appointment_Admin_Controller::get_calendar_events` | `check_calendar_permission` |
| `super-mechanic/v1` | POST | `/admin/appointments/(?P<id>\d+)/status` | `Appointment_Admin_Controller::update_calendar_appointment_status` | `check_calendar_permission` |
| `super-mechanic/v1` | POST | `/admin/appointments/(?P<id>\d+)/reschedule` | `Appointment_Admin_Controller::update_calendar_appointment_schedule` | `check_calendar_permission` |
| `super-mechanic/v1` | POST | `/google-calendar/webhook` | `Google_Calendar_Webhook_Controller::handle_webhook` | `__return_true` |

### Public integration API `super-mechanic-public/v1`

Archivo: `includes/integrations/public-api/class-public-rest-controller.php`

| Namespace | Metodo | Ruta | Controller/method | permission_callback |
|---|---:|---|---|---|
| `super-mechanic-public/v1` | GET | `/business` | `Public_REST_Controller::get_business_summary` | `check_business_permission` |
| `super-mechanic-public/v1` | GET | `/processes` | `Public_REST_Controller::get_processes` | `check_processes_permission` |
| `super-mechanic-public/v1` | GET | `/appointments` | `Public_REST_Controller::get_appointments` | `check_appointments_permission` |
| `super-mechanic-public/v1` | POST | `/appointments/(?P<id>\d+)/cancel` | `Public_REST_Controller::cancel_appointment` | `check_appointments_cancel_permission` |
| `super-mechanic-public/v1` | POST | `/appointments/(?P<id>\d+)/confirm` | `Public_REST_Controller::confirm_appointment` | `check_appointments_confirm_permission` |

## Auth model findings

### `sm/v1` formal API

- Route registration uses `permission_callback => __return_true` for all 7 routes.
- Authentication is enforced inside each callback through `get_current_user_id()`.
- Unauthenticated requests reach the callback and receive an application-level `401 Authentication required`.
- Business scoping is centralized per request through `Business_Context_Service::resolve_business_id_for_user(...)`.
- Read endpoints filter or constrain results with existing services:
  - full-access users can list tenant-scoped collections
  - non-full-access users are filtered through client/vehicle/process/quote/invoice access checks where implemented
- `POST /quotes/{id}/approve` validates authenticated user, business scope and quote ownership before calling `Quote_Service::approve_quote(...)`.
- No explicit route-level capability callbacks are used in `sm/v1`.
- No explicit nonce validation is implemented in `sm/v1`; browser/cookie usage relies on WordPress REST cookie authentication behavior.
- No plugin-managed API key, Bearer token, OAuth/JWT or application-specific external auth exists for `sm/v1`.

### Internal/admin REST

- Admin dashboard REST uses strict permission callbacks:
  - `is_user_logged_in()`
  - `current_user_can( 'sm_manage_plugin' )`
  - write routes additionally require `current_user_can( 'sm_manage_processes' )`
- Appointment calendar REST uses:
  - `is_user_logged_in()`
  - `current_user_can( 'sm_manage_processes' )`
- Client portal REST uses `Permission_Service::user_can_access_client_portal(...)`, then detail/list callbacks use user-linked client, vehicle, process, quote and invoice access services.
- These routes are session/current-user oriented, not external API-key oriented.
- No explicit per-route nonce validation appears in these REST controllers; browser calls depend on normal WP REST cookie/nonce handling.

### Public integration API

- `super-mechanic-public/v1` has a plugin-managed API-key model.
- Credentials are accepted via:
  - `Authorization: Bearer <key>`
  - `X-SM-API-Key: <key>`
- Keys are hashed with plugin salt, must be active, are scoped, and resolve to a valid `business_id`.
- Public integration API returns 401 for missing/invalid key and 403 for disabled API, invalid business or insufficient scope.
- Scope checks are endpoint-specific (`business:read`, `processes:read`, `appointments:read`, `appointments:cancel`, `appointments:confirm`).

### Google Calendar webhook

- Webhook route uses `permission_callback => __return_true` because Google push notifications cannot use a WP session.
- Actual validation happens inside `Google_Calendar_Client_Service::queue_webhook_notification(...)`.
- Header validation requires Google channel/resource/token headers and compares them with stored watch channel metadata.
- Invalid webhook headers return 403 from callback; duplicate messages return 200.

## Identified risks and gaps

1. `sm/v1` has permissive route-level permissions.
   - All formal API routes are registered as public at the WordPress REST permission layer.
   - Auth failures are handled inside callbacks, so route-level permission semantics are weaker than internal/admin routes.

2. `sm/v1` has no external auth mechanism.
   - The formal API cannot currently be consumed safely as a true external API unless the caller authenticates as a WordPress user.
   - Existing plugin API-key auth is isolated under `super-mechanic-public/v1` and is not reused by `sm/v1`.

3. `sm/v1` lacks explicit per-route capability policy.
   - Internal/admin routes use named permission callbacks and capabilities.
   - `sm/v1` uses authenticated user + service ownership checks but no route-level capability map for read/write semantics.

4. `POST /sm/v1/quotes/{id}/approve` should be reviewed first in hardening.
   - It mutates quote state.
   - It currently relies on callback-level current user and quote ownership checks, with no route-level nonce/capability/token policy.

5. Business ownership is partially callback/service based, not route-policy based.
   - `resolve_business_scope(...)` blocks invalid requested `business_id`.
   - Detail endpoints also check ownership, but collection endpoint behavior depends on downstream service filters.
   - 56P10-B should define a repeatable route auth/ownership contract rather than relying on ad hoc callback logic.

6. Multiple REST auth models coexist.
   - `sm/v1`: WordPress current user only, permissive route callback.
   - `super-mechanic/v1`: internal cookie/current-user + capability/portal permissions.
   - `super-mechanic-public/v1`: plugin-managed API keys + scopes.
   - Google webhook: unauthenticated route + signed/channel-header validation.
   - This is workable but should be documented as explicit product architecture before hardening.

## Recommended 56P10-B scope

- Define canonical auth policy for `sm/v1`:
  - cookie/session + WP nonce for browser/admin usage
  - optional Application Password compatibility for machine-to-machine WordPress users
  - plugin API-key/Bearer model reuse or bridge if `sm/v1` is intended as external API
- Replace `__return_true` on `sm/v1` with named permission callbacks.
- Add route-level policy methods by operation type:
  - authenticated read
  - authenticated write
  - business scoped read
  - business scoped mutation
- Start hardening with `POST /sm/v1/quotes/{id}/approve`.
- Preserve existing response contracts and backward compatibility unless a dedicated migration is approved.
- Keep Google webhook auth separate; do not force WP auth on provider webhooks.
- Decide whether `super-mechanic-public/v1` remains the external API or whether `sm/v1` becomes the canonical external namespace.

## Validation

Automated ejecutada:
- `php scripts/php-lint.php --all`
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P10-A-validation.md --output=text`

Manual audit checks:
- `routes_inventoried` -> PASS
- `permission_callbacks_reviewed` -> PASS
- `auth_gaps_documented` -> PASS

Runtime/browser:
- NOT_RUN. Fase 56P10-A es auditoria documental/read-only; no se ejecutaron llamadas REST reales en WordPress.

## Documentacion actualizada

- `docs/tasks/2026-04-56p10-a-api-auth-audit.md`
- `docs/CURRENT_STATE.md`
- `docs/QA_REPORT.md`

## Estado

- Estado tecnico: PASS
- Estado de fase: COMPLETA
