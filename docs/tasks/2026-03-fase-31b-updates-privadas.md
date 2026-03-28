# Fase 31B. Updates privadas

## Objetivo

Implementar la base de updates privadas del plugin sobre arquitectura activa `includes/*`, preparada para provider externo futuro, sin abrir 31C.

## Alcance implementado

- Contrato desacoplado de provider de updates:
  - `includes/helpers/class-update-provider-interface.php`
- Provider local/stub:
  - `includes/helpers/class-local-update-provider.php`
- Servicio central de updates:
  - `includes/helpers/class-update-service.php`
- Integración con hooks nativos de WordPress:
  - `pre_set_site_transient_update_plugins`
  - `plugins_api`
- Descarga segura de paquete vía URL firmada y temporal:
  - `admin_post_sm_private_update_package`
  - `admin_post_nopriv_sm_private_update_package`
- Estado visible básico en Settings (solo lectura).
- Persistencia local en `sm_settings.updates` sin cambios de schema.

## Archivos modificados

- `includes/class-plugin.php`
- `includes/helpers/class-settings-service.php`
- `includes/class-settings.php`

## Shape persistido en `sm_settings.updates`

- `provider`
- `last_check_at`
- `latest_version`
- `package_available`
- `message`
- `last_result`

Metadata técnica adicional persistida:

- `requires`
- `tested`
- `changelog`
- `package_source_url`

## Reglas y seguridad aplicadas

- Sin cambios de schema.
- Sin apertura de 31C (sin flags/planes/premium gating).
- Lógica centralizada en `Update_Service` (UI solo lectura de estado).
- URL de paquete validada y controlada:
  - firma HMAC
  - expiración temporal
  - validación de licencia activa
  - validación de host/esquema permitido

## Validación técnica ejecutada

- `php -l includes/helpers/class-update-provider-interface.php`
- `php -l includes/helpers/class-local-update-provider.php`
- `php -l includes/helpers/class-update-service.php`
- `php -l includes/class-plugin.php`
- `php -l includes/helpers/class-settings-service.php`
- `php -l includes/class-settings.php`

Resultado:
- sin errores de sintaxis PHP.

## Exclusiones deliberadas de 31B

- Sin feature flags / planes (31C fuera de alcance).
- Sin cambios de licensing remoto ni validación externa real.
- Sin `upgrader_pre_download` adicional (no necesario para el baseline actual con endpoint firmado).
- Sin cambios de schema o migraciones.

## Estado final

- FASE 31B: `COMPLETO`.
- Base lista para evaluar FASE 31C bajo alcance explícito.

