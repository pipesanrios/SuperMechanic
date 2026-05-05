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

---

## 56P3-C — DATA INTEGRITY VALIDATION

Fecha de ejecución: 2026-04-14

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P3-C-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- orphan_detection_coherent -> NOT_RUN
- consistency_output_coherent -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- integrity validation service/repository implemented in allowed helpers/database scope
- structured report added with per-check PASS/FAIL + counts + sample IDs
- covered integrity checks:
  - clients/vehicles/ownership
  - processes/logs
  - CRM relations
  - payments/invoices links
- validation-only scope respected:
  - no schema redesign
  - no aggressive cleanup
  - auto-fix path remains non-destructive and skipped by design

---

## 56P4-A — DASHBOARD LAYOUT FIX

Fecha de ejecución: 2026-04-14

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P4-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- dashboard_renders -> NOT_RUN
- cards_layout_applied -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- dashboard metrics rendering updated to grouped card/grid layout
- responsive layout adjustments added in admin CSS
- data/query/business logic intentionally unchanged

---

## 56P4-B — REPORTING LAYOUT FIX

Fecha de ejecución: 2026-04-14

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P4-B-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- reporting_renders -> NOT_RUN
- reporting_cards_layout_applied -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- reporting metrics rendering updated to grouped card/grid layout
- responsive layout adjustments added in admin CSS
- reporting service/repository/query/PDF logic intentionally unchanged

56P4-B fix update (2026-04-14):
- issue confirmed in runtime: reporting metric cards remained stacked vertically.
- root cause: reporting card wrappers relied on `sm-grid-cards` / `sm-grid-cards-compact` (no `display:grid`), so column templates were not applied.
- fix applied:
  - `assets/css/admin.css`
  - `.sm-reporting-card-grid` now enforces `display:grid` + `gap`
  - reporting layout now applies columns in desktop and keeps single-column collapse in mobile.

56P4-B final fix update (2026-04-14):
- issue persisted in runtime due to CSS-effective layout still depending on generic shared grid behavior and insufficient autonomous reporting grid definition/sizing.
- final fix applied in `assets/css/admin.css`:
  - reporting grid wrappers hardened with explicit width/min-width constraints
  - reporting card-grid now has full standalone definition:
    - `display:grid`
    - `width:100%`
    - `grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))`
    - `gap`
  - reporting KPI cards constrained for grid flow (`width:auto`, `min-width:0`)
  - explicit mobile one-column collapse preserved for reporting card-grid

56P4-B runtime asset audit update (2026-04-14):
- reporting page style source confirmed:
  - handle: `sm-admin-ui`
  - file: `assets/css/admin.css`
  - enqueue path: `includes/class-assets.php`
- no later plugin stylesheet override detected for reporting page.
- root cause for non-reflecting CSS in runtime:
  - admin stylesheet version was fixed to `SM_PLUGIN_VERSION` (`0.1.0`), allowing stale browser/proxy cache to keep serving old CSS.
- fix applied:
  - `includes/class-assets.php`
  - `sm-admin-ui` version now uses `filemtime(assets/css/admin.css)` fallback to `SM_PLUGIN_VERSION`.

---

## 56P4-C — BRANDING UX CLEANUP

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P4-C-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- branding_page_renders -> NOT_RUN
- branding_layout_improved -> NOT_RUN
- branding_preview_works -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- branding page UI reorganized into clearer layout (form + preview) in allowed admin controller scope
- fields grouped for readability:
  - Brand identity
  - Color theme
  - Footer text
- helper descriptions and spacing hierarchy improved without changing save payload or business logic
- branding preview card added for current values (logo/name/colors/footer) with responsive behavior
- no branding service logic changes and no changes in rename/i18n/roles-access/CRM/API/schema scope

---

## 56P4-D — SETTINGS / LICENSE CONSISTENCY

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P4-D-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- settings_clearer -> NOT_RUN
- license_page_still_works -> NOT_RUN
- duplication_reduced -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- Settings page licensing area converted to read-only summary + guidance UX
- duplicated license action controls removed from Settings (activation/validation/deactivation actions no longer rendered there)
- clear navigation to canonical License page added in Settings
- plan-related copy in Settings clarified as diagnostic/read-only
- no changes in licensing business logic, trial/enforcement, roles/CRM/reset/API/schema

---

## 56P5-A — CRM BULK ACTIONS

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P5-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- multi_select_works -> NOT_RUN
- select_all_works -> NOT_RUN
- bulk_action_executes -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- CRM list view now includes row checkboxes + select-all control
- bulk action dropdown/control added in CRM list view with POST+nonce protection
- supported bulk action in this subphase: delete selected opportunities
- bulk delete reuses existing opportunity delete service path; no cascade task-delete layer introduced
- existing CRM list/kanban and single-item actions preserved without schema/API changes

---

## 56P5-B — CRM CASCADE DELETE

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P5-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- single_delete_cascades_tasks -> NOT_RUN
- bulk_delete_cascades_tasks -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- CRM opportunity delete path now performs task cleanup by `crm_pipeline_id` before deleting opportunity row
- bulk delete inherits cascade through existing service delete path used per selected opportunity
- task cleanup is business-scoped (active `business_id`) to preserve tenant safety
- no CRM/task model redesign, no API/schema/reset/roles/settings changes

---

## 56P5-C — CRM STATE CONSISTENCY

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P5-C-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- single_delete_state_consistent -> NOT_RUN
- bulk_delete_state_consistent -> NOT_RUN
- update_flows_consistent -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- CRM delete consistency hardened in service/repository layer:
  - tasks cleanup by `crm_pipeline_id`
  - active alerts resolution by `crm_pipeline_id`
  - opportunity delete proceeds only after related cleanup succeeds
- bulk delete remains aligned because it reuses the same service delete flow per opportunity
- tenant scope preserved (`business_id`) for task and alert cleanup
- no CRM redesign, no schema/API/reset/roles/settings changes

---

## 56P6-A — ROLES & ACCESS UI STABILIZATION

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5

Manual runtime:
- roles_access_page_renders -> NOT_RUN
- roles_access_readable -> NOT_RUN
- superadmin_clearly_differentiated -> NOT_RUN
- roles_actions_still_work -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- Roles & Access UI stabilized in allowed scope (`includes/users/*`, `assets/css/*`)
- readability improvements applied without changing role/membership backend logic:
  - column-toggle toolbar preserved/restored in page surface
  - improved table/action spacing and responsive stability
- superadmin clarity improvements applied:
  - protected superadmin badge in user identity area
  - dedicated visual row differentiation for locked superadmin users
  - guidance note clarifying protected/global behavior and disabled controls
- regular user rendering preserved with clearer labels and unchanged action flows
- no CRM/reset/i18n/API/schema changes

56P6-A fix update (regression hotfix, 2026-04-15):
- issue: `WP roles` column rendered vertically (stacked text).
- issue: column filter toolbar had been removed from Roles & Access.
- fix applied:
  - restored column filter toolbar rendering in `class-admin-roles-controller.php` (same behavior path with existing JS toggles)
  - applied minimal CSS for `data-col="wp_roles"` to prevent vertical stacking and keep readable horizontal rendering
- technical validation:
  - `php scripts/php-lint.php --all` -> PASS

---

## 56P6-B — EXTEND VISIBLE COLUMNS

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS

Manual runtime:
- visible_columns_has_id_name_email -> NOT_RUN
- toggle_id_works -> NOT_RUN
- toggle_name_works -> NOT_RUN
- toggle_email_works -> NOT_RUN
- existing_toggles_work -> NOT_RUN
- table_readable_no_console_errors -> NOT_RUN

Scope confirmation:
- `Visible columns` extended with `ID`, `Name`, `Email` in Roles & Access toolbar
- existing column toggles preserved, including:
  - `WP roles`
  - `Operational role`
  - `Business`
  - `Memberships`
  - `Dashboard access`
  - `Automation/Logs access`
  - `Status`
  - `Actions`
- checkbox value ↔ `th[data-col]` ↔ `td[data-col]` mapping kept aligned
- localStorage persistence added with compatibility fallback for legacy keys; existing saved preferences are reused when present
- no role logic, memberships logic, superadmin flows, CRM/reset/settings/admin-menu changes

56P6-B adjustment update (2026-04-15):
- default first-load visible columns adjusted to:
  - `Name`
  - `Operational role`
  - `Business`
  - `Memberships`
  - `Actions`
- default first-load hidden columns adjusted to:
  - `ID`
  - `Email`
  - `WP roles`
  - `Dashboard access`
  - `Automation/Logs access`
  - `Status`
- persistence behavior updated:
  - if no stored preference exists, defaults are applied
  - if stored preference exists, stored user selection is applied on reload
  - legacy localStorage states are read and migrated to primary key safely

---

## 56P6-C — BACKEND ENFORCEMENT

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-C-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- protected_superadmin_backend_blocked -> NOT_RUN
- invalid_membership_ops_blocked -> NOT_RUN
- authorized_ops_still_work -> NOT_RUN
- no_admin_regression -> NOT_RUN

Scope confirmation:
- server-side membership-action target resolution hardened for `membership_id`-based operations in Roles & Access AJAX flow
- protected superadmin restrictions are now enforced in backend even when payload attempts bypass direct `user_id` checks
- `repair_membership_consistency` is blocked for locked superadmins in backend service flow
- superadmin revocation hardened to managed-superadmin targets only in backend flow
- no UI redesign, no role-model redesign, no CRM/reset/i18n/API/schema changes

---

## 56P6-C1 — MEMBERSHIP ACTION CONSISTENCY

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-C1-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 7

Manual runtime:
- assign_admin_hidden_if_already_admin -> NOT_RUN
- assign_mechanic_hidden_if_already_mechanic -> NOT_RUN
- assign_client_hidden_if_already_client -> NOT_RUN
- add_membership_hidden_if_all_roles_present -> NOT_RUN
- primary_membership_invalid_deactivation_blocked_in_ui -> NOT_RUN
- role_removal_consistent_final_state -> NOT_RUN
- admin_stable -> NOT_RUN

Scope confirmation:
- actions card now hides role buttons that do not apply to current user role state
- assign-client flow added to role actions with backend support and consistent operational-role replacement behavior
- remove-role flow was later refined in 56P6-C2 to remove one resolved role at a time without replacing unrelated roles
- primary membership invalid deactivation path is blocked from UI before submit
- add-membership card is shown only when at least one business still has missing active role coverage
- no CRM/reset/i18n/API/schema changes

---

## 56P6-C2 — MULTI-ROLE MEMBERSHIP CONSISTENCY

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-C2-validation.md --output=text` -> PASS
  - PASS: 3
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 6

Manual runtime:
- add_mechanic_keeps_admin -> NOT_RUN
- add_client_keeps_mechanic -> NOT_RUN
- remove_admin_keeps_other_roles -> NOT_RUN
- current_memberships_matches_state -> NOT_RUN
- add_membership_only_missing_roles -> NOT_RUN
- admin_stable -> NOT_RUN

Scope confirmation:
- create-membership flow changed from business-level replacement to tuple-level add/reactivate (`user + business + role`)
- multi-role coexistence by business preserved; adding one role no longer removes another role in same business
- primary membership deactivate/remove now supports automatic valid primary handoff when another active membership exists
- consistency warning/repair logic now targets duplicates by `business + role` (not by business only), preserving valid multi-role rows
- add-membership UI now offers only missing role options per business using quick-add entries
- role-access service role assignment no longer removes other operational roles; remove-role flow removes one resolved role
- no CRM/reset/i18n/API/schema changes

---

## 56P6-C3 — PER-BUSINESS MEMBERSHIP UI CONSOLIDATION

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-C3-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 6

Manual runtime:
- one_business_one_card -> NOT_RUN
- roles_badges_not_dropdown -> NOT_RUN
- compact_add_membership_dropdown -> NOT_RUN
- only_missing_roles_offered -> NOT_RUN
- actions_visibility_consistent -> NOT_RUN
- admin_stable -> NOT_RUN

Scope confirmation:
- Current memberships render consolidated per business (single card per business)
- role state in Current memberships now uses badges/text, not role dropdown selectors
- add-membership flow changed from quick-add button grid to compact dropdown submit
- dropdown options include only missing role targets per business
- per-membership action controls remain in-card and primary-safety guidance remains enforced
- no CRM/reset/i18n/API/schema changes

56P6-C3 fix update (regression hotfix, 2026-04-15):
- issue: consolidated business card could show incomplete role set (runtime observed as only one role visible).
- issue: transfer block emitted runtime warnings:
  - `Undefined variable $has_businesses`
  - `Undefined variable $role_options`
- fix applied in `class-admin-roles-controller.php`:
  - restored render initialization for transfer dependencies (`$has_businesses`, `$role_options`, business options source)
  - adjusted business-card role merge to aggregate role-state from all membership rows in each business card scope
  - preserved per-membership row action rendering inside consolidated business card
- technical validation:
  - `php scripts/php-lint.php --all` -> PASS

---

## 56P6-C4 — ACTIONS STATE SYNC FIX

Fecha de ejecución: 2026-04-15

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P6-C4-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 5

Manual runtime:
- remove_role_persists -> NOT_RUN
- assign_button_reappears -> NOT_RUN
- buttons_match_state -> NOT_RUN
- primary_handoff_stable -> NOT_RUN
- admin_stable -> NOT_RUN

Scope confirmation:
- Actions column now uses persisted membership state by business to determine assign/remove visibility
- Actions POST flow now supports:
  - `assign_business_role` (`user_id`, `business_id`, `role`)
  - `remove_business_role` (`user_id`, `business_id`, `role`)
- role removal now targets exact business-role tuple via membership service and updates persisted state (not UI-only toggle)
- consolidated membership card, compact add-membership flow, and primary handoff validation remain preserved

---

## 56P7-A — CLIENT PANEL BASE

Fecha de ejecución: 2026-04-16

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P7-A-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- client_panel_loads -> NOT_RUN
- client_sections_work -> NOT_RUN
- client_data_renders -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- unified client panel base shortcode added: `sm_client_panel`
- panel composes existing client-facing blocks from existing controllers/shortcodes:
  - enhanced process hub portal
  - vehicles
  - processes
  - quotes
  - invoices
  - notifications
  - process-context documents/timeline
- existing shortcodes remain intact and reusable; no business-logic duplication introduced
- scope stayed UI composition oriented with no CRM/reset/API/schema changes

---

## 56P7-C — MECHANIC PANEL UX

Fecha de ejecución: 2026-04-22

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P7-C-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- mechanic_panel_loads -> NOT_RUN
- mechanic_labels_clear -> NOT_RUN
- mechanic_actions_work -> NOT_RUN
- no_console_errors -> NOT_RUN

Scope confirmation:
- mechanic panel UX readability improved without changing mechanic business logic
- corrected multiple confusing/corrupted mechanic labels in:
  - overview
  - process detail
  - maintenance block
  - attachments/comments/appointments blocks
- action visibility improved with consistent button-style quick actions (`Open details`, `Open`, `Open process`)
- lightweight section-flow clarity added with mechanic quick navigation anchors and filter guidance text
- frontend mechanic panel styling extended in `assets/css/portal.css` for navigation, filter, and action-link readability
- no CRM/reset/API/schema changes

---

## 56P7-D — SHORTCODE REGISTRY ALIGNMENT

Fecha de ejecución: 2026-04-22

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P7-D-validation.md --output=text` -> PASS
  - PASS: 2
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- mekvort_client_panel_in_catalog -> NOT_RUN
- mekvort_mechanic_panel_exists_and_works -> NOT_RUN
- catalog_reflects_active_shortcodes -> NOT_RUN
- legacy_sm_shortcodes_compatible -> NOT_RUN

Scope confirmation:
- new alias shortcode added: `mekvort_mechanic_panel`
  - registration in `Mechanic_Dashboard_Shortcodes::register_hooks()`
  - render path reuses existing mechanic panel logic (`render_mechanic_dashboard`)
- catalog metadata updated to include missing active panel shortcodes:
  - `sm_client_panel`
  - `mekvort_client_panel`
  - `mekvort_mechanic_panel`
- grouping preserved coherently:
  - Cliente
  - Mecánico
  - General (still reserved when no active neutral/public shortcode exists)
- no CRM/reset/i18n/API/schema changes

---

## 56P8-A — EMAIL TRIGGER SYSTEM

Fecha de ejecución: 2026-04-22

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P8-A-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- process_status_trigger -> NOT_RUN
- quote_status_trigger -> NOT_RUN
- invoice_status_trigger -> NOT_RUN

Scope confirmation:
- centralized `Email_Trigger_Service` added and wired from composition root
- service listens to existing domain events (`sm_event_*`) without changing business flow
- structured notification-intent payloads are built for process/quote/invoice status transitions
- debug-friendly persistence implemented via `Log_Service::log_notification_event` (`source=email_trigger`)
- no real email delivery/provider integration yet

---

## 56P8-B — EMAIL TEMPLATES

Fecha de ejecución: 2026-04-22

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P8-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- process_template_builds -> NOT_RUN
- quote_template_builds -> NOT_RUN
- invoice_template_builds -> NOT_RUN

Scope confirmation:
- reusable template layer added in `includes/services/class-email-template-service.php`
- mapping implemented for required events:
  - process status change
  - quote approved
  - quote rejected
  - invoice paid
  - invoice partial
- trigger intents now include reusable `template` payload (`template_key`, `subject`, `body`, `metadata`)
- delivery concerns remain separated (no SMTP/provider sending)

---

## 56P8-C — EMAIL DELIVERY WIRING

Fecha de ejecucion: 2026-04-30

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P8-C-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4

Manual runtime:
- process_email_sent -> NOT_RUN
- quote_email_sent -> NOT_RUN
- invoice_email_sent -> NOT_RUN
- delivery_logs_recorded -> NOT_RUN

Scope confirmation:
- service-layer `Email_Delivery_Service` added in `includes/services/class-email-delivery-service.php`
- email trigger flow now attempts real delivery through WordPress `wp_mail(...)`
- trigger/template flow remains centralized and existing template subject/body payloads are reused
- delivery success/failure is recorded through structured notification logs with source `email_delivery`
- no provider SDK, queue/retry redesign, admin settings UI, CRM/reset/API/schema changes

---

## 56P9-A — GOOGLE CALENDAR CONFIG VALIDATION

Fecha de ejecucion: 2026-04-30

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P9-A-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- config_can_be_saved -> NOT_RUN
- config_validation_works -> NOT_RUN
- readiness_status_correct -> NOT_RUN

Scope confirmation:
- centralized `Google_Calendar_Config_Service` added in `includes/services/class-google-calendar-config-service.php`
- service exposes `get_config()`, `save_config()`, `validate_config()` and `is_ready()`
- config storage reuses existing `Settings_Service` group `google_calendar` in `wp_options`
- required fields covered: `client_id`, `client_secret`, `redirect_uri`, `calendar_id`
- validation is local only; no OAuth, Google API calls, event sync, frontend/UI, API, CRM, process or dashboard changes

---

## 56P9-B — GOOGLE CALENDAR SYNC LOGIC

Fecha de ejecucion: 2026-05-05

Automated:
- `php scripts/php-lint.php --all` -> PASS
- `php scripts/qa-runner.php --contract=docs/contracts/validation/56P9-B-validation.md --output=text` -> PASS
  - PASS: 1
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 3

Manual runtime:
- appointment_payload_builds -> PASS
- process_payload_builds -> PASS
- missing_fields_detected -> PASS

Scope confirmation:
- centralized payload-only `Google_Calendar_Sync_Service` added in `includes/services/class-google-calendar-sync-service.php`
- service builds normalized calendar-ready payloads with `summary`, `description`, `start.datetime`, `end.datetime`, `timezone`, `attendees` and `metadata`
- appointment and process mapping use existing domain row fields only
- validation returns structured missing-field errors
- no OAuth, token exchange, Google API calls, real remote sync, external event ID persistence, frontend/API/CRM/reset/schema changes
