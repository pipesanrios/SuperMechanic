# FASE 36C-2 — Confirmación pública controlada de cita

Fecha: 2026-03-29
Estado: COMPLETO

## Alcance implementado

- Se habilitó una única acción write pública:
  - `POST /wp-json/super-mechanic-public/v1/appointments/{id}/confirm`
- Se agregó el scope público:
  - `appointments:confirm`
- No se habilitaron otras acciones write.

## Seguridad y validaciones

- API key activa obligatoria.
- Scope `appointments:confirm` obligatorio.
- Boundary tenant-aware explícito en write:
  - lookup por `appointment_id + business_id` de la credencial pública.
  - update por `appointment_id + business_id` de la credencial pública.
- Confirmación permitida solo desde:
  - `scheduled`
- Si la cita ya está `confirmed`, se devuelve éxito estable/idempotente.
- Si la cita está `cancelled`, `completed` o `in_progress`, se devuelve `409`.

## Idempotencia

- Se reutiliza `Public_API_Idempotency_Service` (transient TTL 24h).
- `idempotency_key` aceptado por:
  - body (`idempotency_key`)
  - header `X-Idempotency-Key`
- Clave de idempotencia:
  - `business_id + appointment_id + action(confirm) + idempotency_key`
- Sin idempotency key se mantiene idempotencia natural por estado.

## Archivos tocados (36C-2)

- `includes/integrations/public-api/class-public-rest-controller.php`
- `includes/integrations/public-api/class-public-api-service.php`
- `includes/appointments/class-appointment-service.php`
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `.vscode/AI_CONTEXT.md`

## Exclusiones deliberadas

- Sin creación pública de citas.
- Sin reprogramación pública de citas.
- Sin CRUD público amplio.
- Sin writes sobre procesos, quotes, invoices, payments, documentos o comentarios internos.
- Sin cambios de schema.
