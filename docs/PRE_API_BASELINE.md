# PRE API BASELINE — SUPER MECHANIC

Fecha de consolidacion: 2026-03-27

Este documento resume la base tecnica minima previa a Fase 27.
No describe una API implementada.
Solo fija contrato operativo minimo, flujos criticos y restricciones vivas.

## 1. Flujos criticos activos

Cliente
-> Vehiculo
-> Relacion cliente-vehiculo
-> Proceso
-> Quote
-> Invoice
-> Payment

Capas transversales activas:
- attachments
- comments
- notifications
- timeline
- secure downloads
- ownership / permissions

## 2. Base configurable consolidada

La configuracion admin sigue guardando la opcion legacy `super_mechanic_settings`,
pero desde SUBFASES 14-16 sincroniza tambien `sm_settings`, que es la opcion real
consumida por services activos.

Claves activas consolidadas:
- `business.business_name`
- `business.business_context_key`
- `business.currency`
- `business.timezone`
- `business.locale`
- `business.date_format`
- `process.enabled_process_types`
- `process.allow_step_back`
- `process.auto_complete_on_final_step`
- `financial.default_tax_rate`
- `financial.allow_partial_payments`
- `notifications.enable_client_notifications`
- `portal.client_panel_enabled`

Importante:
- `business_context_key` prepara evolucion futura a `business_id`
- no existe multi-tenant real en runtime actual
- no se abrio schema para este bloque

## 3. Dataset QA reproducible

Script oficial del bloque:
- `scripts/seed-qa-dataset.php`

Cobertura minima asegurada por script:
- clients
- vehicles
- processes
- quotes
- invoices
- payments
- attachments
- comments
- notifications

Propiedades del dataset:
- idempotente a nivel funcional
- reutiliza registros deterministas en reruns
- respeta services activos y repositories reales
- deja ownership consistente para pruebas de roles y futura API

## 4. Payload minimo esperado para API cliente

Este bloque no implementa endpoints.
El objetivo es fijar el shape minimo que una API cliente deberia respetar.

### Process
Campos minimos:
- `id`
- `client_id`
- `vehicle_id`
- `process_type`
- `status`
- `title`
- `opened_at`
- `due_date`
- `completed_at`
- `current_step_id`
- `flow_id`

### Quote
Campos minimos:
- `id`
- `process_id`
- `client_id`
- `quote_number`
- `status`
- `currency`
- `subtotal`
- `tax_total`
- `discount_total`
- `grand_total`
- `approved_at`
- `notes`
- `items[]`

### Invoice
Campos minimos:
- `id`
- `process_id`
- `quote_id`
- `client_id`
- `invoice_number`
- `status`
- `currency`
- `subtotal`
- `tax_total`
- `discount_total`
- `grand_total`
- `amount_paid`
- `balance_due`
- `issued_at`
- `due_date`
- `paid_at`
- `items[]`
- `payments[]`

### Attachment
Campos minimos:
- `id`
- `process_id`
- `client_id`
- `attachment_type`
- `title`
- `description`
- `mime_type`
- `is_internal`
- `is_client_visible`
- `created_at`

Nota:
- nunca exponer `file_url` directo al cliente
- las descargas deben seguir pasando por `Document_Service` + `Download_Service`

### Comment
Campos minimos:
- `id`
- `object_type`
- `object_id`
- `process_id`
- `client_id`
- `comment_type`
- `content`
- `is_internal`
- `is_client_visible`
- `status`
- `created_at`

### Notification
Campos minimos:
- `id`
- `recipient_type`
- `recipient_id`
- `object_type`
- `object_id`
- `process_id`
- `notification_type`
- `title`
- `message`
- `data_json`
- `is_read`
- `read_at`
- `created_at`

## 5. Restricciones vigentes

Restricciones vivas pero controladas antes de Fase 27:
- `sm_public_tracking` sigue fuera por seguridad
- `invoice_pdf` y `payment_receipt` dependen de motor PDF activo en entorno
- el runtime mantiene cadenas historicas mixtas; la base i18n ya esta cableada, pero no existe convergencia total de idioma todavia
- la preparacion `business_id` existe solo como contexto/configuracion, no como tenancy operativa

## 6. Regla final

Si este documento diverge del runtime:
manda el codigo real en `includes/*` y luego las validaciones registradas en `docs/QA_REPORT.md`.
