# Fase 49D — Cierre Técnico (Membership Transfers)

Fecha: 2026-04-05  
Estado: PARCIAL (runtime/manual pendiente)

## Objetivo ejecutado

Habilitar transferencias de usuarios entre negocios usando memberships, sin pérdida de histórico y manteniendo consistencia de primaria.

## Archivos en alcance

- `includes/users/class-business-membership-service.php`
- `includes/users/class-business-membership-repository.php`
- `includes/users/class-admin-roles-controller.php`
- `assets/js/admin-roles-access.js`
- `assets/css/admin.css`
- `docs/CURRENT_STATE.md`
- `docs/tasks/2026-04-fase-49d-cierre.md`
- `docs/contracts/validation/49D-validation.md`

## Implementación realizada

- Service:
  - `transfer_user_to_business($user_id, $target_business_id, $role, $mode)`
  - modo `replace`:
    - desactiva memberships activas actuales (no borra)
    - crea/reactiva membership destino
    - marca membership destino como primaria
  - modo `add`:
    - crea/reactiva membership destino
    - mantiene memberships previas activas

- Repository:
  - nuevo helper para desactivar memberships activas por usuario sin borrar registros
  - reutilización/reactivación de membership existente para evitar duplicados activos

- UI:
  - nueva acción `Transfer / Move user` por usuario
  - inputs:
    - business destino
    - role
    - mode (`replace` / `add`)
  - AJAX seguro con capability `sm_manage_plugin` y nonce

## Reglas garantizadas

- sin borrado de registros por transferencia
- primaria única mantenida por `set_primary_membership`
- validación de `business_id` destino
- sanitización estricta en backend y JS

## Deuda técnica

- sin historial visual de transferencias
- sin automatización de reasignación operativa post-transfer
- sin flujo avanzado multi-step

