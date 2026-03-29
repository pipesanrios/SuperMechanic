# FASE 36C-1 — Cancelación pública controlada de cita

Fecha: 2026-03-29
Estado: COMPLETO

## Alcance implementado

- Se habilitó una única acción write pública:
  - `POST /wp-json/super-mechanic-public/v1/appointments/{id}/cancel`
- Se agregó el scope público:
  - `appointments:cancel`
- No se habilitaron otras acciones write.

## Seguridad y validaciones

- API key activa obligatoria.
- Scope `appointments:cancel` obligatorio.
- Boundary tenant-aware explícito en write:
  - lookup por `appointment_id + business_id` de la credencial pública.
  - update por `appointment_id + business_id` de la credencial pública.
- Cancelación permitida solo desde estados:
  - `scheduled`
  - `confirmed`
  - `in_progress`
- Si la cita ya está `cancelled`, se devuelve éxito estable/idempotente.

## Idempotencia

- Se incorporó `Public_API_Idempotency_Service` con transient TTL de 24 horas.
- `idempotency_key` aceptado por:
  - body (`idempotency_key`)
  - header `X-Idempotency-Key`
- Clave de idempotencia:
  - `business_id + appointment_id + action(cancel) + idempotency_key`
- Sin idempotency key se mantiene idempotencia natural por estado.

## Archivos tocados (36C-1)

- `includes/integrations/public-api/class-public-rest-controller.php`
- `includes/integrations/public-api/class-public-api-service.php`
- `includes/integrations/public-api/class-public-api-idempotency-service.php` (nuevo)
- `includes/appointments/class-appointment-service.php`
- `includes/appointments/class-appointment-repository.php`
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `.vscode/AI_CONTEXT.md`

## Exclusiones deliberadas

- Sin creación pública de citas.
- Sin confirmación pública de citas.
- Sin CRUD público amplio.
- Sin writes sobre procesos, quotes, invoices, payments, documentos o comentarios internos.
- Sin cambios de schema.
