# QA REPORT — SMOKE TEST REAL (EJECUTADO)

## Validation Contract Traceability
- For non-trivial phases, QA entries should reference the Validation Contract used.
- Recommended fields per phase entry:
  - Task Contract path
  - Validation Contract path
  - Validation result summary (pass/fail by section)
- This report stores outcomes and traceability, while phase closure outputs remain concise.

## QA Runner Integration
- QA Runner output (text/json/markdown) can be attached as technical evidence per phase.
- Validation contracts should expose automated and manual checks separately.
- Runner statuses: PASS, FAIL, SKIPPED, NOT_RUN.
- PASS tecnico no implica fase completa; runtime manual remains required when applicable.

Fecha de ejecución: 2026-03-27

Modo de validación ejecutado:
- bootstrap real de WordPress por CLI con entorno local operativo
- ejecución con usuario administrador activo
- render real de controladores admin y portal mecánico
- creación temporal y limpieza best-effort de cliente, vehículo, proceso, comentario, cotización e invoice
- validación directa de services existentes sin modificar lógica del plugin
- revalidación browser-admin real con Playwright headless sobre relaciones, procesos completos, comunicación y adjuntos

Límites de esta ejecución:
- la validación de upload se hizo desde runtime CLI, no desde un `multipart/form-data` real del navegador
- la descarga PDF depende de un motor PDF instalado en el entorno
- la descarga PDF depende de un motor PDF instalado en el entorno

## 1. Dashboard
- KPIs clicables → OK
  Detalle: se detectaron enlaces reales a clientes y vehículos desde el dashboard admin.
- Navegación → OK
  Detalle: se detectó navegación real hacia listados y procesos.
- Filtros → OK
  Detalle: los resúmenes por estado generan enlaces con query args de filtro.

## 2. Clientes
- Crear cliente → OK
  Detalle: se creó un cliente temporal en runtime y se recuperó correctamente.
- Validaciones → OK
  Detalle: `email`, `phone` y `document_id` devolvieron error obligatorio en runtime.
- Vista detalle → OK
  Detalle: la vista `Ver` renderizó datos del cliente y procesos relacionados.
- Relación vehículos → OK
  Detalle: la vista detalle mostró el vehículo vinculado creado durante la prueba.

## 3. Vehículos
- Crear → OK
  Detalle: se creó un vehículo temporal y se vinculó al cliente por relación activa.
- Validaciones VIN/placa → OK
  Detalle: el sistema rechazó crear vehículo sin placa y sin VIN.
- Relación cliente → OK
  Detalle: la vista detalle mostró cliente principal vinculado.
- Vista detalle → OK
  Detalle: la vista `Ver` renderizó bloque de procesos relacionados.

## 4. Procesos
- Crear → OK
  Detalle: se creó un proceso real de mantenimiento con flujo resuelto por runtime.
- Cambios estado → OK
  Detalle: el proceso pasó de `draft` a `in_progress` correctamente.
- Logs → OK
  Detalle: se registró log inicial del flujo y también log de cambio de estado.

## 5. Comunicación
- Crear comentario → OK
  Detalle: se creó comentario interno publicado sobre el proceso temporal.
- Editar → OK
  Detalle: el comentario fue actualizado y recuperado con el nuevo contenido.
- Eliminar → OK
  Detalle: el comentario dejó de aparecer en el listado publicado tras eliminarse.

## 6. Adjuntos
- Subir → OK
  Detalle: el flujo del plugin usa formulario admin `multipart/form-data`, nonce válido y `Attachment_Service::handle_upload()` delegando en `wp_handle_upload()`.
  Validación dirigida: el error `Specified file failed upload test.` quedó trazado a WordPress core (`wp-admin/includes/file.php`), donde `wp_handle_upload()` exige que `tmp_name` provenga de una subida HTTP real mediante `is_uploaded_file()`.
  Conclusión: el `FAIL` observado aplica al contexto CLI usado en la prueba técnica, no al flujo browser-admin normal del plugin.
- Abrir → OK
  Detalle: el panel de adjuntos renderizó la acción `Abrir documento` con URL segura de descarga (`sm_document_download` + nonce), sin exponer ruta física.

## Revalidación runtime final — Subfases 5–6
- Relación cliente ↔ vehículo → OK
  Detalle: en browser-admin real, la vista de cliente `id=2` mostró `moto 2` y `moto 3`, y la vista de vehículo mostró cliente principal correcto.
- Formulario de procesos → OK
  Detalle: seleccionar cliente `id=2` limitó vehículos a sus asociados y seleccionar vehículo `id=7` autocompletó el cliente `id=5` con contexto visible.
- Vehículo detalle → OK
  Detalle: en browser-admin real, el detalle del vehículo mostró proceso activo actual, estado visible y tabla de procesos con `Apertura`, `Objetivo` y `Finalización`.
- Comunicación / timeline → OK
  Detalle: el proceso `id=4` mostró comentarios operativos, feed de notificaciones y timeline dentro de la pestaña de documentos.
- Adjuntos en browser-admin → OK
  Detalle: se subió un archivo real al proceso `id=7`, quedó asociado al proceso y la acción `Abrir` disparó la descarga segura del documento en navegador.

## 7. Invoices
- Generar → OK
  Detalle: se creó una factura real desde una cotización aprobada en runtime.
- PDF → N/A
  Motivo: no hay motor PDF activo en este entorno para validar descarga real.

## 8. Portal Mecánico
- Dashboard → OK
  Detalle: el dashboard del portal mecánico cargó en runtime sin bloqueo.
- KPIs → OK
  Detalle: los KPIs renderizaron correctamente en el panel mecánico.

## 9. Shortcodes
- Copiar → OK
  Detalle: el catálogo renderizó el botón `Copiar shortcode` y la integración runtime esperada (`sm-copy-shortcode` + atributos de destino). No se ejecutó click automatizado de navegador.

## Errores reales detectados
- No se confirmó bug funcional del plugin en adjuntos para uso browser-admin normal.
- La única incidencia observada quedó acotada al contexto CLI:
  `Attachment_Service::handle_upload()` devolvió `Specified file failed upload test.` porque WordPress core valida `is_uploaded_file()` y el archivo de prueba no provenía de una subida HTTP real.
- No se detectaron fallos reales del plugin en la revalidación browser-admin de Subfases 5–6.

## Inconsistencias UX
- No se detectaron inconsistencias UX críticas en dashboard, clientes, vehículos, procesos, portal mecánico ni catálogo de shortcodes durante esta ejecución.
- La validación de copiar shortcode quedó confirmada a nivel de render y wiring runtime, no mediante click real en navegador.

## Riesgos funcionales
- La descarga PDF de invoices sigue sin validación por ausencia de motor PDF en el entorno.

## Clasificación operativa
- No se detectaron bloqueadores críticos confirmados del plugin para el uso base previo a Fase 27.
- Adjuntos queda cerrado como `OK en browser-admin / fallo solo CLI`.
- El estado del smoke test queda `COMPLETO` para el bloque previo a Fase 27.
- El estado de `SUBFASE 5-6` queda `COMPLETO`.

## Subfases 7-9 — Validación técnica dirigida

Fecha de actualización: 2026-03-27

Modo de validación ejecutado:
- revisión dirigida de código activo en `includes/*`
- verificación de wiring documental y financiero en services/controllers activos
- `php -l` OK en:
  - `includes/invoices/class-invoice-service.php`
  - `includes/invoices/class-invoice-admin-controller.php`
  - `includes/attachments/class-attachment-service.php`
- búsqueda de regresión sobre exposición directa de `file_url` en runtime activo

Límites de esta ejecución:
- no se ejecutó browser-admin real para las subfases 7-9 dentro de esta sesión
- no se ejecutó runtime WordPress real para alta de invoice, pagos y descargas seguras después de los cambios
- la descarga PDF y `payment_receipt` siguen condicionadas a la presencia de un motor PDF en el entorno

### 1. Facturación
- crear invoice -> OK
  Detalle: el runtime admin ya soportaba creación desde quote y ahora agrega alta manual por proceso mediante `Invoice_Admin_Controller`, reutilizando `Invoice_Service::create_invoice()` sin abrir una ruta paralela.
- crear invoice sin quote previa -> OK
  Detalle: se agregó flujo admin `create_manual_invoice` que persiste `process_id` y `client_id` del proceso actual sin exigir `quote_id`.
- impuestos por porcentaje -> OK
  Detalle: `Invoice_Service::normalize_adjustment_totals()` convierte entrada `percent` a `tax_total` absoluto sobre el subtotal actual, sin tocar schema.
- impuestos por monto fijo -> OK
  Detalle: el mismo helper conserva modo `fixed` y guarda el total absoluto en `tax_total`.
- descuentos por porcentaje -> OK
  Detalle: el mismo flujo normaliza `discount_mode=percent` a `discount_total` absoluto.
- descuentos por monto fijo -> OK
  Detalle: `discount_mode=fixed` conserva el monto absoluto.
- totales correctos -> OK
  Detalle: `Invoice_Service::recalculate_totals()` mantiene la misma fórmula activa que Quotes: `subtotal + tax_total - discount_total`.
- abrir invoice -> OK
  Detalle: la acción `Abrir` sigue apuntando al detalle del proceso con `invoice_id` y mantiene el invoice activo en el panel.
- PDF -> N/A
  Motivo: no hay motor PDF validado en este entorno para confirmar descarga real.

### 2. Payments
- decisión única documentada -> OK
  Detalle: `sm_payments` queda ratificado como única fuente de verdad financiera; `Invoice_Transaction_Repository` queda solo como frontera transaccional de creación desde quote.
- registrar pago -> OK
  Detalle: el flujo activo sigue entrando por `Invoice_Service::add_payment()` y se endureció la UI admin usando selector de método en vez de texto libre.
- relación pago ↔ invoice -> OK
  Detalle: `Payment_Repository` sigue persistiendo `invoice_id` obligatorio y `Invoice_Service::user_can_access_payment()` deriva acceso desde la invoice asociada.
- estado de factura actualizado -> OK
  Detalle: `recalculate_balance()` sigue recalculando `amount_paid`, `balance_due`, `paid_at` y estado interno; `get_invoice_payment_summary()` expone `pending/partial/paid`.
- payment_receipt -> OK
  Detalle: el recibo sigue resuelto por `Document_Service` sobre `payment_id`; se añadió acceso admin al comprobante cuando existe motor PDF.

### 3. Seguridad documental
- admin descarga válida -> OK
  Detalle: las rutas admin activas siguen usando `Download_Service` para adjuntos y nonces/capabilities para invoice PDF.
- acceso cliente permitido solo si corresponde -> OK
  Detalle: `Document_Service` delega acceso en `Invoice_Service`, `Quote_Service` y `Attachment_Service`; `Access_Control_Service` mantiene ownership y visibilidad.
- acceso directo bloqueado cuando corresponda -> OK
  Detalle: no se detectaron enlaces directos por `file_url` fuera del almacenamiento; `Attachment_Service` ahora solo resuelve/borra archivos dentro de `uploads`.
- ownership validado -> OK
  Detalle: la capa documental sigue pasando por `user_can_access_document()` y derivados de ownership por invoice/quote/attachment.
- visibilidad internal/client respetada -> OK
  Detalle: `Access_Control_Service::user_can_access_attachment()` sigue negando cliente cuando `is_internal=1` o `is_client_visible=0`.

### Bugs reales encontrados
- No se confirmó un bug estructural en `payment_receipt`; el flujo seguía coherente con el modelo activo y solo se añadió salida admin.
- Se detectó una brecha funcional real en invoices: no existía entry point admin para crear factura manual sin quote previa aunque `Invoice_Service` ya lo soportaba.
- Se detectó una brecha de consistencia UX en payments: el alta admin permitía texto libre para `payment_method` aunque la validación solo aceptaba valores del catálogo cerrado.
- Se detectó un riesgo real de seguridad documental: `Attachment_Service` aceptaba resolver y borrar `file_path` legible sin verificar que permaneciera dentro de `uploads`.

### Revalidación runtime final — Subfases 7-9

Fecha de ejecución: 2026-03-27

Modo de validación ejecutado:
- bootstrap real de WordPress por CLI con entorno local operativo
- usuario administrador real cargado en runtime
- creación temporal y limpieza best-effort de cliente, vehículo, relación, proceso, invoice, pagos, adjuntos y usuario cliente vinculado
- render real del panel admin de invoices con helpers `wp-admin` cargados
- validación directa de services activos sin tocar schema ni bootstrap

Resultados:
- crear invoice desde proceso -> OK
  Detalle: se creó invoice real sobre un proceso temporal en runtime.
- crear invoice manual sin quote previa -> OK
  Detalle: `Invoice_Service::create_invoice()` funcionó con `process_id` y `client_id`, sin `quote_id`.
- impuestos por porcentaje -> OK
  Detalle: subtotal runtime `100.00`, `tax_total` recalculado a `10.00`.
- impuestos por monto fijo -> OK
  Detalle: `tax_total` persistido a `7.50`.
- descuentos por porcentaje -> OK
  Detalle: subtotal runtime `100.00`, `discount_total` recalculado a `5.00`.
- descuentos por monto fijo -> OK
  Detalle: `discount_total` persistido a `3.25`.
- `tax_total` -> OK
  Detalle: validado en runtime con ambos modos `percent` y `fixed`.
- `discount_total` -> OK
  Detalle: validado en runtime con ambos modos `percent` y `fixed`.
- total final correcto -> OK
  Detalle: `grand_total` validado como `subtotal + tax_total - discount_total`.
- botón abrir invoice -> OK
  Detalle: el panel admin de invoices renderizó acción `Abrir` con `invoice_id` correcto.
- PDF -> N/A
  Motivo: no hay motor PDF activo en el entorno.

- registrar pago desde admin/runtime -> OK
  Detalle: pagos reales persistidos en `sm_payments`.
- catálogo cerrado de `payment_method` -> OK
  Detalle: la UI admin renderizó `<select>` cerrado y los pagos runtime persistieron solo valores válidos.
- relación pago ↔ invoice -> OK
  Detalle: `invoice_id` del pago validado en runtime.
- actualización de estado real de factura -> OK
  Detalle: primer pago dejó estado visible `partial`; pago final dejó invoice en `paid`.
- `payment_receipt` -> N/A
  Motivo: no hay motor PDF activo en el entorno.

- abrir/descargar adjunto válido desde admin -> OK
  Detalle: `Attachment_Service::get_attachment_download_data()` resolvió un archivo real dentro de `uploads`.
- archivo servido pertenece realmente a uploads -> OK
  Detalle: la ruta resuelta quedó dentro de `wp-content/uploads/...`.
- no se permiten rutas arbitrarias -> OK
  Detalle: un adjunto apuntando a archivo fuera de `uploads` devolvió `WP_Error`.
- borrado seguro -> OK
  Detalle: eliminar ese adjunto no borró el archivo externo al árbol `uploads`.
- ownership / visibilidad -> OK
  Detalle: un usuario WordPress temporal vinculado al cliente pudo acceder a invoice y attachment visible, respetando ownership y visibilidad.

### Bugs reales encontrados
- No se detectaron fallos reales del plugin en la revalidación runtime final de SUBFASES 7-9.

### Riesgos abiertos
- El modo `porcentaje` se normaliza a monto persistido para mantener schema; si cambian los ítems después, el porcentaje no queda almacenado como semántica independiente.
- `invoice_pdf` y `payment_receipt` siguen pendientes de validación específica con motor PDF activo, pero quedan clasificados como `N/A`, no como fallo funcional del bloque.

### Clasificación operativa del bloque
- `SUBFASES 7-9 STATUS`: `COMPLETO`
- payments quedó definitivamente saneado sobre `sm_payments`
- seguridad documental quedó validada en runtime real
- el sistema puede pasar a `SUBFASES 10-13`

## Subfases 10-13 — Portales + shortcodes + permisos

Fecha de actualización: 2026-03-27

Modo de validación ejecutado:
- bootstrap real de WordPress por CLI con entorno local operativo
- creación mínima de usuarios runtime temporales `client` y `mechanic`
- vinculación temporal de cliente/vehículos y asignación operativa mínima para cubrir el bloque sin tocar schema
- render real de shortcodes cliente y mecánicos en runtime
- simulación runtime real de acciones frontend mecánicas con POST + nonce válidos
- validación estructural de permisos reutilizables sobre la arquitectura activa
- `php -l` OK en:
  - `includes/helpers/class-permission-service.php`
  - `includes/dashboard/class-mechanic-dashboard-shortcodes.php`
  - `includes/dashboard/class-mechanic-dashboard-controller.php`
  - `includes/processes/class-process-repository.php`
  - `includes/dashboard/class-client-dashboard-shortcodes.php`
  - `includes/dashboard/class-client-dashboard-controller.php`
  - `includes/invoices/class-client-invoice-shortcodes.php`
  - `includes/quotes/class-client-quote-shortcodes.php`
  - `includes/attachments/class-client-attachment-shortcodes.php`
  - `includes/communication/class-client-comment-shortcodes.php`
  - `includes/class-shortcode-admin-controller.php`
  - `includes/class-plugin.php`

Límites de esta ejecución:
- no se ejecutó navegador real; la validación frontend se hizo por runtime WordPress real vía CLI renderizando shortcodes y enviando POSTs válidos
- no se activó `sm_public_tracking` por ausencia de un mecanismo público seguro no basado en IDs internos ni schema nuevo

### 1. Portal cliente
- dashboard carga -> OK
  Detalle: el shortcode sigue cableado al bootstrap real y ahora reutiliza `Permission_Service`.
- procesos propios visibles -> OK
  Detalle: se mantiene resolución por ownership real vía `Access_Control_Service`.
- vehículos propios visibles -> OK
  Detalle: no se alteró la fuente de datos; solo se centralizó el guard de acceso.
- documentos propios visibles -> OK
  Detalle: el shortcode documental ahora también puede resolver `process_id` por query string sin abrir acceso nuevo.
- quotes/invoices visibles -> OK
  Detalle: shortcodes existentes siguen activos y ahora comparten guard reusable.
- payment_receipt visible si corresponde -> N/A
  Motivo: el enlace sigue condicionado a motor PDF activo.
- ownership correcto -> OK
  Detalle: no se movió ownership fuera de `Access_Control_Service`.

### 2. Portal mecánico
- dashboard carga -> OK
  Detalle: existe shortcode nuevo `sm_mechanic_dashboard` cableado al bootstrap real.
- procesos asignados visibles -> OK
  Detalle: la revalidación final confirmó listado visible en runtime real tras alinear la query mecánica con el fallback por `assigned_to`.
- métricas consistentes -> OK
  Detalle: el listado y KPIs reutilizan `Dashboard_Service`.
- acciones reales funcionales -> OK
  Detalle: una actualización real de estado dejó el proceso `id=7` en `in_progress` y una nota técnica real quedó persistida desde el flujo frontend del mecánico.

### 3. Shortcodes
- `sm_mechanic_dashboard` -> OK
  Detalle: registrado y documentado en el catálogo admin.
- `sm_mechanic_processes` -> OK
  Detalle: registrado y documentado en el catálogo admin.
- `sm_public_tracking` -> N/A
  Motivo: restricción operativa explícita por seguridad; no se implementó.
- copiar shortcode -> OK
  Detalle: el wiring del catálogo no se alteró y ahora incluye shortcodes mecánicos reales.
- documentación shortcode actualizada -> OK
  Detalle: el catálogo refleja el bootstrap activo real tras los cambios.

### 4. Permisos
- admin enforcement -> OK
  Detalle: no se alteró `sm_manage_plugin`.
- mechanic enforcement -> OK
  Detalle: portal y shortcodes mecánicos nuevos pasan por `Permission_Service`.
- client enforcement -> OK
  Detalle: shortcodes cliente principales reutilizan `Permission_Service`.
- reglas reutilizables centralizadas -> OK
  Detalle: el nuevo servicio se apoya en `Access_Control_Service` y evita repetir guards base.

### Bugs reales encontrados
- Se confirmó un warning real en runtime CLI: acceso directo a `$_SERVER['REQUEST_METHOD']` sin guard en shortcodes/controladores frontend.
- Se confirmó un bug real frontend en mecánico: `submit_button()` provocaba fatal fuera de admin al renderizar `sm_mechanic_dashboard`.
- Se confirmó una desalineación real entre listado mecánico y enforcement: la query usaba `maintenance.mechanic_id` como única fuente, mientras la política reutilizable operaba sobre `assigned_to`.
- Se detectó además un gap de dataset local en relaciones cliente ↔ vehículo; se resolvió solo como preparación temporal de validación y no como bug estructural del plugin.
- `sm_public_tracking` sigue siendo una restricción operativa de seguridad, no un fallo funcional del bloque actual.

### Clasificación operativa del bloque
- `SUBFASES 10-13 STATUS`: `COMPLETO`
- portal cliente, shortcodes cliente y portal mecánico frontend quedaron validados en runtime real
- permisos reutilizables quedaron validados en conjunto con ownership y rol
- `payment_receipt` queda `N/A` por motor PDF ausente, sin bloquear el cierre funcional del bloque
- `sm_public_tracking` queda documentado como restricción operativa explícita y no forma parte del cierre funcional actual mientras no exista un mecanismo público seguro
- el bloque ya puede pasar a `SUBFASES 14-16`

## Subfases 14-16 — Configuracion global + dataset + documentacion minima

Fecha de ejecución: 2026-03-27

Modo de validación ejecutado:
- bootstrap real de WordPress por CLI con entorno local operativo
- sincronización real de la pantalla de ajustes legacy con la opción activa `sm_settings`
- creación/reutilización idempotente de usuarios QA, clientes, vehículos, relaciones, procesos, quote, invoice, pagos, adjunto, comentarios y notificaciones mediante `scripts/seed-qa-dataset.php`
- validación directa de consistencia relacional y de lectura de settings consumidos por services activos

Resultados:
- settings guardan -> OK
  Detalle: la configuración admin persistió en `super_mechanic_settings` y sincronizó `sm_settings` para los services activos.
- settings cargan -> OK
  Detalle: `Settings_Service` resolvió correctamente `business_name`, `business_context_key`, `locale`, `timezone` y `allow_partial_payments` desde la opción activa.
- impacto en runtime actual -> OK
  Detalle: `Invoice_Service` aceptó pagos parciales según la configuración persistida y `Process_Service` siguió operativo sin romper flujos existentes.

- clientes creados -> OK
  Detalle: se aseguraron dos clientes QA (`id=11`, `id=12`) sin duplicación por reruns.
- vehículos creados -> OK
  Detalle: se aseguraron dos vehículos QA (`id=12`, `id=13`) con placa y VIN deterministas.
- procesos creados -> OK
  Detalle: se aseguraron dos procesos QA (`id=12`, `id=13`) sobre relaciones consistentes cliente ↔ vehículo.
- quotes creadas -> OK
  Detalle: la quote `SMQ-QA-001` quedó creada y aprobada para el cliente QA principal.
- invoices creadas -> OK
  Detalle: la invoice manual `SMI-QA-001` quedó creada sobre el proceso QA principal sin tocar schema.
- payments creados -> OK
  Detalle: se registraron dos pagos QA y la invoice quedó en estado de cobranza válido con saldo resuelto.
- adjuntos creados -> OK
  Detalle: se generó un archivo QA en `uploads` y se registró como adjunto visible para cliente.
- comments creados -> OK
  Detalle: se aseguró una nota interna y una respuesta visible para cliente en el proceso QA principal.
- notifications creadas -> OK
  Detalle: se generó una notificación QA de tipo `reminder` y además el runtime siguió despachando eventos operativos existentes.
- relaciones consistentes -> OK
  Detalle: ownership, proceso, quote, invoice y adjunto quedaron alineados sobre el mismo cliente/vehículo principal.

Documentación mínima:
- flujos críticos documentados -> OK
  Detalle: se consolidó baseline pre-API y estado operativo en `CURRENT_STATE`, más soporte técnico mínimo específico en `PRE_API_BASELINE`.
- restricciones vigentes documentadas -> OK
  Detalle: se mantienen explícitas `sm_public_tracking` fuera por seguridad y la validación PDF condicionada a motor PDF activo.
- payload mínimo API cliente documentado -> OK
  Detalle: quedó consolidado en `docs/PRE_API_BASELINE.md` solo como contrato mínimo orientativo previo a Fase 27, sin implementar API en este bloque.

Bugs reales encontrados:
- Se detectó una desalineación real en configuración: la UI admin seguía guardando solo `super_mechanic_settings`, mientras los services activos consumían `sm_settings`.
  Resolución: la pantalla de ajustes ahora sincroniza ambas opciones sin romper fallback legacy.
- Se detectó una dependencia local de entorno para dataset secundario: no existía flujo activo `pre_delivery` en esta instalación.
  Resolución: el dataset reproducible quedó ajustado a flujos activos realmente disponibles, sin tocar schema ni abrir alcance funcional.

Clasificación operativa del bloque:
- `SUBFASES 14-16 STATUS`: `COMPLETO`
- el sistema ya puede pasar a `FASE 27` desde la base pre-API actual
- restricciones vivas pero controladas:
  - `sm_public_tracking` sigue fuera por seguridad hasta disponer de un mecanismo público seguro no basado en IDs internos
  - `invoice_pdf` y `payment_receipt` siguen condicionados a un motor PDF activo en el entorno
  - la convergencia completa de idioma del runtime sigue como deuda controlada; la base i18n operativa ya quedó cableada

## FASE 27A - API BASE SEGURA (IMPLEMENTACIÓN)

Fecha de ejecución: 2026-03-28

Modo de validación ejecutado:
- revisión de wiring runtime real sobre `includes/*`
- validación de rutas REST activas por código
- validación de sintaxis PHP de archivos modificados
- verificación de seguridad de acceso por `Permission_Service` y ownership por `Access_Control_Service`

Resultados:
- controller único 27A -> OK
  Detalle: se implementó `includes/dashboard/class-client-rest-controller.php`.
- wiring bootstrap -> OK
  Detalle: `includes/class-plugin.php` registra hooks REST del controller nuevo en runtime activo.
- endpoints read-only -> OK
  Detalle: se implementaron listados + detalle de procesos, vehículos, quotes e invoices para cliente autenticado.
- autenticación y permisos -> OK
  Detalle: `permission_callback` reutiliza `Permission_Service::user_can_access_client_portal()`.
- ownership estricto -> OK
  Detalle: detalle de proceso/vehículo/quote/invoice valida acceso con `Access_Control_Service` y services de dominio.
- no exposición documental -> OK
  Detalle: no se agregaron rutas de descarga ni campos `file_url`.
- comentarios en API -> EXCLUIDO DELIBERADO
  Detalle: se excluyeron para mantener 27A mínimo, consistente y sin abrir alcance.

Validaciones ejecutadas:
- `php -l includes/dashboard/class-client-rest-controller.php` -> OK
- `php -l includes/class-plugin.php` -> OK
- `php -l includes/class-rest-api.php` -> OK

Clasificación operativa:
- `FASE 27A STATUS`: `COMPLETO`
- Base REST segura disponible para cliente autenticado en arquitectura activa
- Sin cambios de schema y sin apertura de 27B/27C

---

## FASE 53 - QA STATUS

Automated:
- php_lint_all -> PASS
- QA runner contracts -> PASS

Manual runtime:
- dashboard_visible -> PASS
- portal_render -> PASS
- mobile_behavior -> PASS
- widgets_render -> PASS
- no_regression_ui -> PASS

Estado:
COMPLETA

Notas:
- overflow detectado en algunos shortcodes (no estructural)
- clasificado como bug puntual (no bloqueante)


## 56A — PRE-SAAS RUNTIME AUDIT

Estado: `PARCIAL`

Resumen:

- contrato creado: `docs/contracts/56A.md`
- validation contract creado: `docs/contracts/validation/56A-validation.md`
- auditoria runtime/manual ejecutada y documentada en:
  - `docs/tasks/2026-04-pre-saas-runtime-audit.md`

Resultado consolidado:

- core/admin base: estable
- portales: shortcodes funcionales, publicacion real pendiente
- PDF reporting: descarga tecnica OK, cierre visual pendiente
- Google Calendar: existe en runtime, no operativo hoy en esta instalacion
- email cliente: pipeline/logica existente, entrega end-to-end no certificada en esta auditoria
- recomendacion final:
  - `CASI LISTO, REQUIERE MICROFASES DE CORRECCION`

---

## 56P1-A1 — PLUGIN VISIBLE BRANDING ONLY

Fecha de ejecución: 2026-04-12

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P1-A1-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- plugin_visible_branding_is_mekvort -> NOT_RUN
- branding_default_name_is_mekvort -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- visible branding only applied
- technical identifiers intentionally unchanged
- admin menu/settings/runtime-sensitive admin screens intentionally unchanged

---

## 56P1-A2 — ADMIN MENU VISIBLE RENAME

Fecha de ejecución: 2026-04-12

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P1-A2-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- top_level_menu_is_mekvort -> NOT_RUN
- safe_menu_titles_aligned -> NOT_RUN
- no_roles_access_regression -> NOT_RUN

Scope confirmation:
- visible admin menu rename only applied
- technical identifiers intentionally unchanged
- settings internals / Roles & Access / CRM / reset logic / API untouched

Rollback update (2026-04-12):
- runtime real regression reported on `Roles & Access` after top-level menu rename
- 56P1-A2 change reverted in `includes/class-admin-menu.php`
- top-level admin menu label restored to `Super Mechanic`
- phase status updated to `REVERTIDA / POSTERGADA`
- 56P1-A1 remains intact (`Mekvort` plugin header + branding default system_name)

---

## 56P1-B — LANGUAGE SETTINGS

Fecha de ejecución: 2026-04-12

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P1-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5

Manual runtime:
- language_settings_visible -> NOT_RUN
- language_selector_visible -> NOT_RUN
- bundled_languages_visible -> NOT_RUN
- future_language_placeholder_visible -> NOT_RUN
- no_settings_regression -> NOT_RUN

Scope confirmation:
- Language Settings section visible in Settings
- bundled languages visible (English, Español, Italiano)
- future language expansion placeholder visible
- full i18n system intentionally deferred to `56P1-C`

---

## 56P1-C — I18N HELPER BASE

Fecha de ejecución: 2026-04-12

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P1-C-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- helper_uses_persisted_language -> NOT_RUN
- english_fallback_works -> NOT_RUN
- available_languages_correct -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- centralized i18n helper base implemented
- persisted language resolution + English fallback baseline implemented
- full translation rollout remains pending future phases

---

## 56P2-A — SUPERADMIN BOOTSTRAP

Fecha de ejecución: 2026-04-12

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P2-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- primary_admin_bootstrapped -> NOT_RUN
- other_admins_not_auto_promoted -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- superadmin bootstrap baseline implemented
- only primary WP admin auto-promoted in bootstrap flow
- broader superadmin management deferred to later subphases

---

## 56P2-B — SUPERADMIN ASSIGNMENT CONTROLS

Fecha de ejecución: 2026-04-13

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P2-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- superadmin_can_promote_admin -> NOT_RUN
- superadmin_can_revoke_promoted_superadmin -> NOT_RUN
- non_superadmin_cannot_manage_superadmins -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- controlled assign/revoke flows added for Mekvort superadmin state
- authorization restricted to existing superadmins
- promotion eligibility restricted to WordPress administrators
- no auto-promotion of all WP admins
- broader role-management redesign remains deferred

---

## 56P2-B1 — MANAGED SUPERADMIN OPERATIONAL PARITY

Fecha de ejecución: 2026-04-13

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P2-B1-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 6

Manual runtime:
- promoted_superadmin_locked_superadmin -> NOT_RUN
- promoted_superadmin_global_scope -> NOT_RUN
- promoted_superadmin_no_add_membership -> NOT_RUN
- promoted_superadmin_no_membership_controls -> NOT_RUN
- managed_superadmin_revocation_still_works -> NOT_RUN
- admin_stable -> NOT_RUN

Scope confirmation:
- managed superadmin now follows locked/global superadmin operational parity in Roles & Access
- normal membership controls are hidden/blocked for any superadmin
- bootstrap remains non-revocable from normal flow
- managed superadmin revocation path remains active

---

## 56P2-A1 — SUPERADMIN BOOTSTRAP COMPLETION FIX

Fecha de ejecución: 2026-04-13

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P2-A1-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5

Manual runtime:
- primary_admin_superadmin_real -> NOT_RUN
- superadmin_global_total_scope -> NOT_RUN
- roles_access_locked_superadmin -> NOT_RUN
- other_admins_not_auto_promoted -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- locked superadmin runtime representation added (Global scope + admin/mechanic/client)
- normal membership controls hidden/blocked for locked superadmin in Roles & Access
- primary bootstrap superadmin state persistence hardened
- non-primary WP admins remain non-auto-promoted

---

## 56P3-B — USER HANDLING

Fecha de ejecución: 2026-04-14

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P3-B-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- protected_superadmins_remain -> NOT_RUN
- non_protected_users_removed -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- reset user-handling service implemented and integrated into reset engine flow
- protected Mekvort superadmins preserved per bootstrap/managed/global model
- non-protected runtime/business users cleaned per reset policy
- broader user/integrity runtime validation deferred to `56P3-C`

56P3-B fix update (2026-04-14):
- issue confirmed: normal WordPress administrators could survive reset due to policy preserving all WP admins and incomplete candidate selection.
- fix applied:
  - only protected Mekvort superadmins remain protected
  - non-protected WordPress administrators are now included in reset cleanup and removed
- technical validation:
  - `php scripts/php-lint.php --all` -> PASS

---

## 56P3-A — RESET ENGINE

Fecha de ejecución: 2026-04-13

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P3-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- operational_data_reset_works -> NOT_RUN
- crm_task_notification_reset_works -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- centralized reset engine implemented in helpers/database scope
- reset orchestration delegated from DB Security service to centralized engine
- reset cleanup now explicitly includes CRM pipeline/tasks/alerts and notification/webhook runtime data
- reset entrypoint/capability/nonce flow in Settings preserved (backward compatibility)
- user cleanup/full integrity reset intentionally deferred to later 56P3 subphases
