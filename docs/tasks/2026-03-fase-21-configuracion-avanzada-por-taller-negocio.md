# Fase 21. Configuracion avanzada por taller / negocio

- Estado: completada.
- Objetivo: introducir una capa central y reusable de configuracion por taller/negocio sin cambiar schema ni agregar UI pesada.

## Archivos creados

- `includes/helpers/class-settings-service.php`

## Archivos modificados

- `includes/class-plugin.php`
- `includes/processes/class-process-service.php`
- `includes/invoices/class-invoice-service.php`
- `includes/quotes/class-quote-service.php`

## Integracion real

- `Settings_Service` centraliza lectura y escritura de configuracion en `wp_options` usando `sm_settings`.
- la estructura base queda organizada en grupos `business`, `process`, `financial` y `notifications`.
- el servicio aplica defaults seguros y mantiene fallback minimo hacia la option legacy existente para `business_name` y `currency`.
- `Process_Service` deja de hardcodear totalmente dos reglas operativas y pasa a respetar `allow_step_back` y `auto_complete_on_final_step`.
- `Invoice_Service` pasa a respetar `allow_partial_payments` y reutiliza la configuracion central para moneda y nombre del negocio.
- `Quote_Service` reutiliza la configuracion central para moneda y nombre del negocio.

## Estructura base de settings

- `business.business_name`
- `business.currency`
- `business.timezone`
- `process.allow_step_back`
- `process.auto_complete_on_final_step`
- `financial.default_tax_rate`
- `financial.allow_partial_payments`
- `notifications.enable_client_notifications`

## Cambios de schema

- ninguno

## Deudas tecnicas abiertas

- falta una UI admin minima para editar `sm_settings` de forma oficial si se considera necesaria en una fase futura
- la capa legacy de settings sigue existiendo y conviene consolidarla cuando se defina el siguiente cierre documental/operativo

## Validacion final

- `php -l` OK en `includes/helpers/class-settings-service.php`
- `php -l` OK en `includes/quotes/class-quote-service.php`
- wiring confirmado en `includes/class-plugin.php` con una instancia compartida de `Settings_Service`
- `Quote_Service`, `Invoice_Service` y `Process_Service` reciben el nuevo service desde `Plugin`
