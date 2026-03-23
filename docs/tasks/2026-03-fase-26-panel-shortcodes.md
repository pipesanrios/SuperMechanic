# Fase 26. Panel / catálogo de shortcodes

## Estado

Completado

## Objetivo

Agregar una pantalla admin dentro del plugin para visualizar, entender y copiar los shortcodes activos del runtime real sin alterar su lógica existente.

## Alcance ejecutado

- nueva página admin `Super Mechanic -> Shortcodes`
- catálogo agrupado por contexto `cliente`, `mecánico` y `general`
- inventario real basado en shortcodes activos del bootstrap actual
- visualización de descripción, parámetros, ejemplo y contexto recomendado
- acción de copia al portapapeles con feedback visual simple
- reutilización de `assets/css/admin.css` y `assets/js/admin.js`

## Archivos creados

- `includes/class-shortcode-admin-controller.php`

## Archivos modificados

- `includes/class-plugin.php`
- `includes/class-admin-menu.php`
- `assets/js/admin.js`
- `assets/css/admin.css`
- `docs/CURRENT_STATE.md`
- `docs/SYSTEM_MAP.md`
- `docs/MODULE_REGISTRY.md`

## Validación funcional esperada

- panel visible para usuarios con `sm_manage_plugin`
- shortcodes activos listados correctamente
- botón de copia operativo
- sin cambios en lógica ni registro de shortcodes existentes

## Notas técnicas

- no se modificó schema
- no se tocaron services ni repositories
- no se agregaron shortcodes nuevos
- hoy el runtime real solo expone shortcodes de contexto cliente; los grupos `mecánico` y `general` quedan preparados para crecimiento futuro sin inventar entries no activas
