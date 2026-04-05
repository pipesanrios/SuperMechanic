# Fase 49A — Cierre Técnico (Business Membership Model)

Fecha: 2026-04-05  
Estado: PARCIAL (runtime/manual pendiente)

## Objetivo ejecutado

Crear el modelo base de membresía por negocio para asociar usuarios a `business_id` con rol operativo y estado, dejando una capa de lectura centralizada para continuidad de 49B–49E.

## Archivos en alcance

- `includes/users/class-business-membership-installer.php`
- `includes/users/class-business-membership-repository.php`
- `includes/users/class-business-membership-service.php`
- `docs/CURRENT_STATE.md`
- `.vscode/AI_CONTEXT.md`
- `docs/tasks/2026-04-fase-49a-cierre.md`

## Implementación realizada

- Nuevo installer de membresías por negocio:
  - tabla: `sm_business_user_roles`
  - campos base: `id`, `business_id`, `user_id`, `operational_role`, `status`, `is_primary`, `created_at`, `updated_at`
  - índices para lecturas por usuario, negocio, rol/estado y primaria.

- Nuevo repository de membresías:
  - `get_user_memberships($user_id, $status = '')`
  - `get_user_primary_membership($user_id)`
  - `get_user_membership_in_business($user_id, $business_id)`
  - `get_business_members($business_id, $status = '')`
  - sanitización de filtros y SQL seguro con `prepare`.

- Nuevo service centralizado (solo lectura):
  - `get_user_memberships($user_id)`
  - `get_user_primary_membership($user_id)`
  - `get_business_members($business_id)`
  - `get_user_role_in_business($user_id, $business_id)`
  - `user_has_active_membership($user_id, $business_id)`
  - fallback para primaria cuando no existe `is_primary=1` (usa membresía activa más prioritaria).

## Reglas del contract respetadas

- sin cambios en lógica de negocio actual
- sin cambios en CRM Pipeline
- sin UI
- sin transferencias
- SQL solo en installer/repository
- service centralizado para lectura

## Validaciones previstas

- `php scripts/php-lint.php --all`
- `php scripts/qa-runner.php --contract=docs/contracts/validation/49A-validation.md --output=text`

## Deuda técnica

- sin UI de membresías todavía
- sin migración automática desde roles legacy a membresías
- sin transferencias de usuarios entre negocios
- sin sincronización bidireccional WP roles <-> memberships en esta fase

