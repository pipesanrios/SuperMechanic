# Fase 49C — Cierre Técnico (Roles & Access UI por Negocio)

Fecha: 2026-04-05  
Estado: PARCIAL (runtime/manual pendiente)

## Objetivo ejecutado

Extender Roles & Access para gestionar memberships por negocio desde UI, sin romper roles WP actuales ni lógica de negocio.

## Archivos en alcance

- `includes/users/class-business-membership-service.php`
- `includes/users/class-business-membership-repository.php`
- `includes/users/class-role-access-service.php`
- `includes/users/class-admin-roles-controller.php`
- `includes/admin/class-roles-access-controller.php`
- `assets/js/admin-roles-access.js`
- `assets/css/admin.css`
- `docs/CURRENT_STATE.md`
- `.vscode/AI_CONTEXT.md`
- `docs/tasks/2026-04-fase-49c-cierre.md`
- `docs/contracts/validation/49C-validation.md`

## Implementación realizada

- Backend memberships:
  - create/update/status/primary/remove en service:
    - `create_membership`
    - `update_membership_role`
    - `set_membership_status`
    - `set_primary_membership`
    - `remove_membership`
  - repository con métodos write y lecturas por ID.

- Reglas garantizadas:
  - una primaria por usuario
  - primaria siempre activa
  - prevención de duplicado operativo por `user + business` en flujo de creación
  - validación de `business_id` existente y payload sanitizado

- UI Roles & Access extendida:
  - columna memberships por usuario
  - acciones:
    - Add membership
    - Change role
    - Activate / Deactivate
    - Set as primary
    - Remove membership
  - copy UX:
    - `No membership assigned`
    - `Global scope`

- Seguridad:
  - capability `sm_manage_plugin`
  - nonce obligatorio en AJAX
  - endpoint seguro `wp_ajax_sm_roles_membership_action`

## Deuda técnica

- sin filtros avanzados en memberships UI
- sin bulk actions de memberships
- sin transferencia completa entre negocios (49D)

