# MODULE REGISTRY

## Core

Carpeta:
- raiz del plugin
- `includes/`

Proposito:
Bootstrap del plugin, activacion, desactivacion, instalacion, autoload y carga principal de modulos.

Tablas:
- no usa tablas de dominio directamente
- opcion de version `sm_db_version`

Archivos principales:
- `autoloader.php`

Clases principales:
- `class-plugin.php`
- `class-activator.php`
- `class-deactivator.php`
- `class-installer.php`
- `class-admin-menu.php`
- `class-assets.php`
- `class-hooks.php`

Dependencias:
- todos los modulos activos
- wiring compartido de `PDF_Service`, `Document_Service` y `Download_Service`

Estado:
- implementado

Riesgos o puntos sensibles:
- cualquier cambio en `class-plugin.php` afecta el wiring real del sistema
- el bootstrap actual usa `includes/*`; no debe mezclarse con `includes/modules/*` sin migracion explicita

Cambios tecnicos recientes confirmados:
- `class-assets.php` deja de ser placeholder y pasa a registrar styles/scripts propios para admin y frontend
- `class-plugin.php` registra la capa de assets junto al resto del wiring principal
- en Fase 25, la carpeta `scripts/` agrega tooling local reusable para lint, chequeo estructural y checklist tecnico sin alterar el runtime del plugin
- en Fase 26, `class-shortcode-admin-controller.php` agrega un catálogo admin de shortcodes activos y `class-admin-menu.php` incorpora la nueva página `Shortcodes`

--------------------------------------------------

## Security

Carpeta:
- `includes/`
- `includes/helpers/`

Proposito:
Gestionar roles, capabilities, validacion, sanitizacion y reglas de acceso.

Tablas:
- usa roles y capabilities nativos de WordPress
- no tiene tablas propias del plugin

Clases principales:
- `class-roles.php`
- `class-capabilities.php`
- `class-validator.php`
- `class-sanitizer.php`
- `class-utils.php`

Dependencias:
- core
- settings
- admin controllers
- shortcodes cliente
- capa documental segura

Estado:
- implementado

Riesgos o puntos sensibles:
- errores en capabilities rompen acceso admin y cliente
- cualquier cambio debe respetar ownership y permisos existentes

Cambios tecnicos recientes confirmados:
- `Access_Control_Service` centraliza la resolucion de ownership y visibilidad sin mover SQL a controllers ni tocar schema
- la capa de seguridad deja de depender de checks dispersos en `Dashboard_Service` como fuente primaria del ownership cliente

--------------------------------------------------

## Settings

Carpeta:
- `includes/`

Proposito:
Configurar parametros globales del plugin.

Tablas:
- `wp_options` mediante `sm_settings` y fallback legacy `super_mechanic_settings`
- `wp_options` mediante `sm_db_version`

Clases principales:
- `class-settings.php`

Dependencias:
- core
- dashboard
- invoices
- processes

Estado:
- implementado

Riesgos o puntos sensibles:
- cambios en settings afectan moneda, tipos de proceso y panel cliente
- requiere sanitizacion estricta en guardado

Cambios tecnicos recientes confirmados:
- `Settings_Service` agrega una capa central reusable sobre la option `sm_settings` y mantiene compatibilidad de lectura con `super_mechanic_settings`
- la configuracion avanzada queda agrupada en `business`, `process`, `financial` y `notifications`
- los defaults preservan el comportamiento actual y mantienen fallback minimo hacia settings legacy del negocio
- en Fase 24B, `class-settings.php` reutiliza la shell visual admin sobre la Settings API existente sin alterar guardado ni estructura de options
- en Fase 31A, `Settings_Service` incorpora el grupo `license` en `sm_settings` y `class-settings.php` agrega estado visible + acciones locales de licencia (activate/validate/deactivate) dentro de Ajustes
- en Fase 38A-2, la capa monetaria pasa a configuración dinámica:
  - `Settings_Service::get_supported_currencies()`
  - `sm_settings.business.supported_currencies`
  - `default_currency` validada contra lista soportada
  - opciones extensibles por filtro `sm_supported_currencies`
- en Fase 38A-3, `class-settings.php` incorpora acciones admin protegidas para seguridad DB:
  - generación/rotación de master password (visible solo una vez)
  - export JSON canónico protegido por capability + nonce + master password
  - reset de tablas del plugin protegido con confirmación fuerte + master password
- en Fase 38A-3B, se amplía la capa operativa de backup/restauración:
  - `DB_Export_Format_Service` para export `JSON`, `CSV ZIP` (1 CSV por tabla + `manifest.json`) y `Excel XML`
  - `DB_Import_Validator` para validación previa completa de backup JSON antes de tocar DB
  - import soportado solo para JSON canónico con allowlist por `Schema::get_tables()` y rollback transaccional

--------------------------------------------------

## Licensing Base (FASE 31A)

Carpeta:
- `includes/helpers/`
- `includes/`

Proposito:
- proveer base local de licencia comercial sin acoplar aún a backend externo real
- persistir estado de licencia en `sm_settings.license`
- exponer acciones admin seguras: activar, validar y desactivar

Tablas:
- sin tablas nuevas
- `wp_options` option `sm_settings` (grupo `license`)

Clases principales:
- `License_Provider_Interface`
- `Local_License_Provider`
- `License_Service`
- integración UI/acciones en `class-settings.php`

Dependencias:
- settings
- core

Estado:
- implementado en Fase 31A (modo local)

Riesgos o puntos sensibles:
- no exponer ni loguear key completa
- no mover lógica al controller/UI
- mantener nonce + capability en acciones sensibles
- no abrir en esta fase updates privadas ni feature gating

--------------------------------------------------

## Private Updates Base (FASE 31B)

Carpeta:
- `includes/helpers/`
- `includes/`

Proposito:
- proveer base local de updates privadas desacoplada por contrato
- integrar metadata de update al flujo nativo de WordPress
- mantener descarga de paquete por URL firmada y temporal

Tablas:
- sin tablas nuevas
- `wp_options` option `sm_settings` (grupo `updates`)

Clases principales:
- `Update_Provider_Interface`
- `Local_Update_Provider`
- `Update_Service`
- integración UI de estado en `class-settings.php`

Dependencias:
- settings
- licensing base (31A)
- core

Estado:
- implementado en Fase 31B (modo local/stub)

Riesgos o puntos sensibles:
- validar firma y expiración de URL de paquete
- no exponer source URL no permitidas
- no abrir billing ni feature gating en esta fase

--------------------------------------------------

## Plan Access & Feature Flags Base (FASE 31C)

Carpeta:
- `includes/helpers/`
- `includes/`
- `includes/reports/`

Proposito:
- centralizar resolución de plan efectivo y feature flags
- exponer checks reutilizables (`get_effective_plan`, `is_feature_enabled`)
- aplicar gating mínimo no destructivo en superficies admin no críticas

Tablas:
- sin tablas nuevas
- `wp_options` option `sm_settings`:
  - `plan.plan_key`
  - `plan.status`
  - `plan.source`
  - `plan.message`
  - `features.feature_flags`

Clases principales:
- `Feature_Flags`
- `Plan_Access_Service`
- `License_Service` (señal local para plan efectivo)
- integración UI de estado en `class-settings.php`

Dependencias:
- settings
- licensing base (31A)
- updates base (31B)
- core

Estado:
- implementado en Fase 31C

Riesgos o puntos sensibles:
- evitar bloqueo por default de funciones core consolidadas
- evitar checks dispersos fuera de `Plan_Access_Service`
- mantener alcance sin billing ni suscripciones reales

Cambios tecnicos recientes confirmados:
- gating mínimo aplicado en Reportes admin, CSV de reportes y catálogo admin de shortcodes
- defaults de flags preservan compatibilidad hacia atrás
- capa preparada para provider externo futuro vía filtros, sin integración remota real en esta fase

--------------------------------------------------

## Business Context / Tenancy Base

Carpeta:
- `includes/helpers/`

Proposito:
- centralizar la resolucion de contexto de negocio para tenancy real
- soportar seleccion operativa de negocio por usuario con fallback legacy compatible

Tablas:
- `sm_businesses`
- `wp_options` via `sm_settings` (`business.business_context_key`, `business.business_id`)
- `wp_usermeta` via `sm_active_business_id`

Clases principales:
- `Business_Context_Service`

Dependencias:
- settings
- core

Estado:
- implementado y operativo (35A + 35B + 35C)

Riesgos o puntos sensibles:
- no activar filtros tenant-aware antes de definir `business_id` persistente
- no duplicar resolucion de contexto en services de dominio
- no alterar ownership actual mientras el runtime siga en single-business

Cambios tecnicos recientes confirmados:
- `Business_Context_Service` resuelve `business_id` real con prioridad:
  - user meta `sm_active_business_id`
  - `sm_settings.business.business_id`
  - negocio default `id=1`
- `class-plugin.php` cablea `Business_Service` + `Business_Admin_Controller` y activa selector operativo en admin

Actualizacion FASE 35A:
- estado:
  - implementado (activación base multi-business controlada)
- cambios confirmados:
  - `Business_Context_Service` pasa a resolver `business_id` real con fallback legacy `1`
  - `Settings_Service` normaliza `business.business_id` en `sm_settings`
  - `class-migrator.php` ejecuta backfill idempotente separado por `Tenancy_Backfill_Migrator`
- alcance schema 35A:
  - `sm_clients`
  - `sm_vehicles`
  - `sm_client_vehicles`
  - `sm_processes`
  - `sm_quotes`
  - `sm_invoices`
  - `sm_payments`
- restricciones mantenidas:
  - sin tabla `businesses`
  - sin activar aislamiento completo de dashboard/reportes/API en esta subfase
  - sin cambios de numeradores globales `quote_number`/`invoice_number`

Actualizacion FASE 35B:
- estado:
  - implementado (enforcement multi-tenant transversal sobre entidades diferidas)
- cambios confirmados:
  - `Report_Repository` incorpora `Business_Context_Service` y fuerza filtros `business_id` en consultas de procesos/quotes/invoices/payments y actividad agregada
  - `Access_Control_Service` endurece acceso en entidades núcleo con validación adicional de negocio (`ownership + business_id`)
  - repositorios diferidos de 35B operan con `business_id` obligatorio y propagación desde services
  - `Tenancy_Backfill_Migrator` se extiende para backfill idempotente por herencia estructural en tablas diferidas
- alcance schema 35B:
  - `sm_quote_items`
  - `sm_invoice_items`
  - `sm_process_step_logs`
  - `sm_appointments`
  - `sm_appointment_calendar_sync`
  - `sm_attachments`
  - `sm_comments`
  - `sm_notifications`
- restricciones mantenidas:
  - sin tabla `businesses`
  - sin branding/billing SaaS
  - sin cambios de numeradores globales `quote_number`/`invoice_number`

Actualizacion FASE 35C:
- estado:
  - implementado (operacion multi-store visible)
- cambios confirmados:
  - se incorpora modulo `includes/businesses/*`:
    - `Business_Repository`
    - `Business_Service`
    - `Business_Admin_Controller`
    - `Business_List_Table`
  - `Tenancy_Backfill_Migrator` asegura upsert idempotente de negocio default `id=1`
  - `Tenancy_Backfill_Migrator` repara huérfanos `business_id` a `1` cuando apunta a negocio inexistente
  - `Settings_Service` y `class-settings.php` exponen fallback `business.business_id`
- alcance schema 35C:
  - nueva tabla `sm_businesses`
- restricciones mantenidas:
  - sin billing SaaS
  - sin multi-login complejo
  - sin permisos ultrafinos por tenant
  - sin cambios de numeradores globales `quote_number`/`invoice_number`

--------------------------------------------------

## Businesses

Carpeta:
- `includes/businesses/`

Proposito:
- gestionar negocios/talleres en runtime multi-store visible
- proveer CRUD admin basico y datos de configuracion por negocio

Tablas:
- `sm_businesses`

Clases principales:
- `Business_Repository`
- `Business_Service`
- `Business_Admin_Controller`
- `Business_List_Table`

Dependencias:
- settings
- helpers/business_context
- core/admin

Estado:
- implementado en Fase 35C

Riesgos o puntos sensibles:
- no permitir eliminar/desactivar negocio default `id=1` sin estrategia de reemplazo
- validar que branding use `attachment_id` y no exponga `file_url`
- mantener coherencia entre contexto de usuario y fallback global

--------------------------------------------------

## Clients

Carpeta:
- `includes/clients/`

Proposito:
Gestion de clientes.

Tablas:
- `sm_clients`

Clases principales:
- `Client_Repository`
- `Client_Service`
- `Client_Admin_Controller`
- `Client_List_Table`

Dependencias:
- core
- vehicles
- processes
- dashboard
- quotes
- invoices

Estado:
- implementado

Riesgos o puntos sensibles:
- cualquier cambio impacta ownership, procesos, quotes, invoices y Client Portal
- no romper relacion con `sm_client_id` cuando el frontend cliente depende de ese contexto

Cambios tecnicos recientes confirmados:
- en Fase 24B, `Client_Admin_Controller` y `Client_List_Table` modernizan listado, formulario, CTA y mensajes con la capa `sm-*` sin tocar handlers ni queries
- en `HOTFIX-MEM-1`, `Client_Service` adopta resolucion lazy de `Business_Context_Service` para evitar cascadas de inicializacion durante bootstrap/runtime admin
- en Fase 38C-1, `Client_List_Table` agrega atajo contextual `Create process` por fila (`client_id`) para reducir friccion operativa

--------------------------------------------------

## Vehicles

Carpeta:
- `includes/vehicles/`

Proposito:
Gestion de vehiculos.

Tablas:
- `sm_vehicles`

Clases principales:
- `Vehicle_Repository`
- `Vehicle_Service`
- `Vehicle_Admin_Controller`
- `Vehicle_List_Table`

Dependencias:
- clients
- processes
- relations
- dashboard

Estado:
- implementado

Riesgos o puntos sensibles:
- cambios en vehiculos afectan procesos y ownership cliente-vehiculo
- no romper relaciones existentes con clientes y procesos

Cambios tecnicos recientes confirmados:
- en Fase 24B, `Vehicle_Admin_Controller` y `Vehicle_List_Table` modernizan listado, formulario y jerarquia visual sin alterar acciones, relaciones ni wiring
- en Fase 38C-1, `Vehicle_List_Table` agrega atajo contextual `Create process` por fila (`vehicle_id` + `client_id`)

--------------------------------------------------

## Client-Vehicle Relations

Carpeta:
- `includes/relations/`

Proposito:
Gestionar la relacion cliente-vehiculo y el ownership.

Tablas:
- `sm_client_vehicles`

Clases principales:
- `Client_Vehicle_Repository`
- `Client_Vehicle_Service`

Dependencias:
- clients
- vehicles
- dashboard
- client portal
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- es critico para permisos de frontend cliente
- cualquier cambio puede exponer datos de otros clientes si se rompe ownership
- aunque `transfer_vehicle()` ya usa una frontera transaccional minima en 26B, cualquier futura escritura adicional del modulo debe seguir entrando por infraestructura transaccional dedicada

--------------------------------------------------

## Flows

Carpeta:
- `includes/flows/`

Proposito:
Definir flujos y pasos configurables para procesos.

Tablas:
- `sm_flows`
- `sm_flow_steps`

Clases principales:
- `Flow_Repository`
- `Flow_Service`
- `Flow_Step_Repository`
- `Flow_Step_Service`
- `Flow_Admin_Controller`
- `Flow_List_Table`

Dependencias:
- processes
- core

Estado:
- implementado

Riesgos o puntos sensibles:
- cambios en flows alteran comportamiento de procesos
- revisar impacto en logs y estados antes de modificar estructura de pasos
- `delete_flow()` y `reorder_steps()` ya usan atomicidad minima en 26B; cualquier futura escritura compleja del modulo debe seguir ese mismo patron y no volver a SQL disperso en services

Cambios tecnicos recientes confirmados:
- `Flow_Step_Service` valida transiciones lineales minimas entre pasos activos usando `step_order`
- el modulo sigue sin grafo de transiciones explicito; hoy solo permite movimiento al paso activo inmediatamente siguiente o anterior
- esta validacion se expone para que `Process_Service` reutilice el flujo como fuente de verdad y no replique reglas en controllers
- en Fase 24B, `Flow_Admin_Controller` y `Flow_List_Table` modernizan listado, formularios y vista de pasos sin tocar persistencia ni reorder existente

--------------------------------------------------

## Processes

Carpeta:
- `includes/processes/`

Proposito:
Seguimiento de procesos del vehiculo y orquestacion del flujo operativo.

Tablas:
- `sm_processes`
- `sm_process_step_logs`
- `sm_process_parts`
- `sm_process_meta`

Clases principales:
- `Process_Repository`
- `Process_Service`
- `Process_Admin_Controller`
- `Process_List_Table`

Dependencias:
- clients
- vehicles
- flows
- maintenance
- predelivery
- paperwork
- quotes
- invoices
- attachments
- communication
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- es el modulo mas sensible del admin
- el detalle del proceso es el hub actual para maintenance, quotes, invoices, attachments y communication
- errores aqui rompen multiples modulos a la vez
- la mayor deuda transaccional del sistema ya no reside en `processes`, pero cualquier ampliacion futura debe preservar el patron de `Process_Transaction_Repository`

Cambios tecnicos recientes confirmados:
- `Process_Service` resuelve `flow_id` y `current_step_id` validos antes de insertar o actualizar
- `Process_Service` registra `step_initialized`, `step_transition` y `status_changed` sobre `sm_process_step_logs`
- `Process_Repository` centraliza persistencia y lectura reutilizable de step logs para dashboards y timeline
- `Process_Transaction_Repository` encapsula la frontera transaccional del modulo para create/update/step update
- `Process_Service` construye los payloads de logs y delega la persistencia atomica sin mover logica de negocio fuera del service
- el riesgo principal de estado parcial entre proceso y timeline queda reducido en el flujo principal del modulo
- la implementacion final valida inicio y confirmacion real de transaccion antes de devolver exito
- en Fase 19, `Process_Service` deja de aceptar saltos arbitrarios entre pasos del mismo flujo
- en Fase 19, entrar en un paso final sincroniza el proceso a `completed` por la ruta operativa simple y registra el log asociado
- en Fase 21, `Process_Service` reutiliza `Settings_Service` para permitir o bloquear `allow_step_back`
- en Fase 21, la auto-finalizacion sobre paso final pasa a depender de `auto_complete_on_final_step`
- en Fase 24B, `Process_Admin_Controller` y `Process_List_Table` modernizan listado, filtros, formulario general, tabs y panel `communication` sin alterar services, nonces ni tabs hijas del modulo
- en Fase 38C-1, `Process_List_Table` añade accesos directos `Open maintenance`, `Open quote`, `Open invoice` y acciones rapidas de estado con nonce
- en Fase 38C-1, `Process_Admin_Controller` refuerza feedback visual por `sm_notice` para cambios rapidos de estado y acciones operativas frecuentes

--------------------------------------------------

## Maintenance

Carpeta:
- `includes/maintenance/`

Proposito:
Gestionar diagnostico, repuestos, mano de obra y asignacion de mecanico.

Tablas:
- `sm_maintenance`
- `sm_maintenance_parts`
- `sm_maintenance_labor`

Clases principales:
- `Maintenance_Repository`
- `Maintenance_Service`
- `Maintenance_Part_Repository`
- `Maintenance_Labor_Repository`
- `Maintenance_Admin_Controller`

Dependencias:
- processes
- quotes

Estado:
- implementado

Riesgos o puntos sensibles:
- alimenta cotizaciones
- cualquier cambio en totales o lineas afecta quotes e invoices futuras

--------------------------------------------------

## PreDelivery

Carpeta:
- `includes/predelivery/`

Proposito:
Gestionar checklist de pre-entrega.

Tablas:
- `sm_pre_delivery`

Clases principales:
- `Pre_Delivery_Repository`
- `Pre_Delivery_Service`
- `Pre_Delivery_Admin_Controller`

Dependencias:
- processes

Estado:
- implementado como modulo
- parcial respecto a la Fase 5 completa del roadmap

Riesgos o puntos sensibles:
- depende totalmente del contexto del proceso
- no romper estados de readiness ni asignaciones existentes

--------------------------------------------------

## Paperwork

Carpeta:
- `includes/paperwork/`

Proposito:
Gestionar tramites y checklist administrativo.

Tablas:
- `sm_paperwork`
- `sm_paperwork_items`

Clases principales:
- `Paperwork_Repository`
- `Paperwork_Item_Repository`
- `Paperwork_Service`
- `Paperwork_Admin_Controller`

Dependencias:
- processes

Estado:
- implementado

Riesgos o puntos sensibles:
- depende del proceso como modulo contenedor
- cualquier cambio afecta seguimiento administrativo del tramite

--------------------------------------------------

## Dashboard

Carpeta:
- `includes/dashboard/`

Proposito:
Mostrar paneles admin, mecanico y cliente.

Tablas:
- reutiliza `sm_clients`
- `sm_vehicles`
- `sm_processes`
- `sm_quotes`
- `sm_invoices`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

Clases principales:
- `Dashboard_Service`
- `Client_Process_View_Service`
- `Admin_Dashboard_Controller`
- `Mechanic_Dashboard_Controller`
- `Client_Dashboard_Controller`
- `Client_Dashboard_Shortcodes`

Dependencias:
- clients
- vehicles
- processes
- quotes
- invoices
- relations
- attachments
- communication
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- `Dashboard_Service` es transversal y de alto impacto
- aunque ya no ejecuta SQL directo, sigue siendo una capa sensible por agregacion de multiples modulos
- cualquier error afecta la experiencia operativa y el Client Portal

Cambios tecnicos recientes confirmados:
- `Dashboard_Service` delega conteos, agrupaciones, actividad reciente y procesos de mecanico a `Process_Service`
- en Fase 14, la actividad reciente del Client Portal queda filtrada por visibilidad del log (`customer_visible`) para no exponer mensajes internos
- `Process_Service` expone wrappers orientados a dashboard
- `Process_Repository` absorbe las consultas SQL reutilizadas por dashboards sin cambiar schema
- `Client_Dashboard_Controller` ya no expone `file_url` directo para adjuntos visibles; usa `Download_Service` para enlaces protegidos
- la auditoria pre-Fase 12 confirma compatibilidad funcional minima de admin dashboard, Mechanic Panel y client dashboard sobre esa arquitectura
- en Fase 17, `Dashboard_Service` deja de resolver ownership por cuenta propia y delega validaciones de cliente/vehiculo/proceso a `Access_Control_Service`
- en Fase 18, `Mechanic_Dashboard_Controller` pasa a exponer listado, detalle y acciones operativas minimas para procesos accesibles de mecanico
- en Fase 18, el portal mecanico reutiliza `Process_Service` para cambios de paso/estado, `Comment_Service` para notas internas y `Process_Timeline_Service` para timeline consolidada
- en Fase 20, `Dashboard_Service` agrega decoracion reusable de estados derivados de proceso para Client Portal y portal mecanico
- en Fase 24, `Admin_Dashboard_Controller`, `Client_Dashboard_Controller` y `Mechanic_Dashboard_Controller` modernizan markup y jerarquia visual sin tocar logica de negocio
- en Fase 24, la capa visual pasa a depender del wiring comun de `Assets` en lugar de assets sueltos no registrados
- en Fase 26B, `Client_Process_View_Service` extrae agregacion de lectura del Client Portal sin mover SQL fuera de repositories ni duplicar ownership
- en Fase 38C-1, `Admin_Dashboard_Controller` incorpora bloque `Quick actions` para flujos operativos de proceso/maintenance/quote/invoice
- en hotfix 38C-1, el acceso rapido `Create quote` deja de compartir destino con `Open maintenance`

--------------------------------------------------

## Quotes

Carpeta:
- `includes/quotes/`

Proposito:
Gestionar cotizaciones, aprobacion del cliente y salida documental PDF.

Tablas:
- `sm_quotes`
- `sm_quote_items`

Clases principales:
- `Quote_Repository`
- `Quote_Item_Repository`
- `Quote_Service`
- `Quote_Admin_Controller`
- `Client_Quote_Shortcodes`

Dependencias:
- processes
- maintenance
- clients
- dashboard
- invoices
- communication
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- depende del proceso para el admin
- su aprobacion controla la generacion de invoices
- no romper validaciones de ownership, estados ni salida PDF

Cambios tecnicos recientes confirmados:
- en Fase 21, `Quote_Service` reutiliza `Settings_Service` para moneda por defecto y nombre del negocio en salida imprimible

--------------------------------------------------

## Invoices

Carpeta:
- `includes/invoices/`

Proposito:
Gestionar facturas, documento HTML imprimible y PDF reutilizable.

Tablas:
- `sm_invoices`
- `sm_invoice_items`

Clases principales:
- `Invoice_Repository`
- `Invoice_Item_Repository`
- `Invoice_Transaction_Repository`
- `Invoice_Service`
- `Invoice_Admin_Controller`
- `Invoice_Finance_Admin_Controller`
- `Invoice_Finance_List_Table`
- `Client_Invoice_Shortcodes`

Dependencias:
- quotes
- processes
- clients
- settings
- dashboard
- payments
- communication
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- depende del contexto del proceso en admin
- cualquier cambio en balance o estados impacta pagos y Client Portal
- la salida PDF depende de un motor compatible instalado en el entorno
- el flujo `create_invoice_from_quote()` depende de mantener coordinacion atomica entre invoice e items aunque la transaccion ya este encapsulada en `Invoice_Transaction_Repository`
- la consistencia documental del flujo quote -> invoice -> payment debe preservarse al ampliar timeline o reportes

Cambios tecnicos recientes confirmados:
- la auditoria pre-Fase 12 confirma que `Invoice_Service` conserva logica de negocio, pagos, balance, PDF y access checks cliente tras el refactor transaccional
- el hardening final de Fase 15 deja `sm_payments` como fuente de verdad financiera para validacion, saldo y resumen de cobranza
- `Invoice_Service` expone `get_invoice_payment_summary()` y reutiliza ese calculo para validar ediciones de pago sin doble conteo
- `Invoice_Admin_Controller` mantiene la UI del proceso, pero separa con labels explicitos `Estado de factura` y `Estado de pago`
- en Fase 20B, `Invoice_Service` expone acceso reusable a `payment_id`, contexto consolidado del comprobante y render HTML del payment receipt
- en Fase 21, `Invoice_Service` reutiliza `Settings_Service` para moneda, nombre del negocio y `allow_partial_payments`
- en Fase 28, el módulo incorpora un panel admin dedicado de invoices (`Finanzas: Invoices`) sin romper el tab invoice del proceso
- en Fase 28, la UI dedicada de invoices expone explícitamente `subtotal`, `tax_total`, `discount_total` y `grand_total`
- en Fase 38C-1, `Invoice_Finance_Admin_Controller` mejora claridad operativa con labels/filtros de estado y busqueda orientada a flujo diario

--------------------------------------------------

## Payments

Carpeta:
- `includes/invoices/`

Nota:
- el modulo Payments vive dentro de `includes/invoices/` y no tiene carpeta propia

Proposito:
Registrar pagos y recalcular balance de invoices.

Tablas:
- `sm_payments`

Clases principales:
- `Payment_Repository`
- soporte funcional en `Invoice_Service`
- `Payment_Finance_Admin_Controller`
- `Payment_Finance_List_Table`

Dependencias:
- invoices
- dashboard
- communication

Estado:
- implementado

Riesgos o puntos sensibles:
- errores afectan `amount_paid`, `balance_due`, `paid_at` y estado de invoice
- no hay pasarela de pago real; solo registro operativo de pagos

Cambios tecnicos recientes confirmados:
- `Invoice_Service` valida que un pago nuevo o editado no exceda el saldo disponible real de la invoice
- la validacion de alta y edicion usa solo `sm_payments`; `amount_paid` y `balance_due` quedan como cache legado de compatibilidad
- `Invoice_Service` expone un resumen reusable de cobranza con estados visibles `pending`, `partial` y `paid` sin romper los estados internos del modulo de invoices
- `Invoice_Admin_Controller` muestra estado de cobro por invoice y restringe la captura admin a metodos de pago soportados
- `Reports` ahora reutiliza `sm_payments` y `sm_invoices` para exponer estado de cobro agregado e ingresos basicos por periodo
- en Fase 20B, cada pago puede resolverse documentalmente como `payment_receipt` unico por `payment_id`, sin persistencia de archivos ni attachments nuevos
- en Fase 28, `Payment_Repository` agrega listados paginados/filtrados para panel admin dedicado manteniendo SQL solo en repository
- en Fase 28, el panel admin `Finanzas: Payments` consolida relación invoice ↔ payments y acción segura de `payment_receipt`
- en Fase 38C-1, `Payment_Finance_Admin_Controller` refuerza feedback visual (`notice-success` / `notice-error`) y claridad de acciones financieras frecuentes

--------------------------------------------------

## Documents / Secure Downloads

Carpeta:
- `includes/helpers/`

Proposito:
- generar PDF real reutilizable para invoices y quotes
- centralizar descargas seguras de recursos protegidos
- servir adjuntos visibles al cliente sin exponer enlaces publicos inseguros

Tablas:
- reutiliza `sm_quotes`
- `sm_quote_items`
- `sm_invoices`
- `sm_invoice_items`
- `sm_attachments`
- `sm_processes`
- `sm_client_vehicles`

Clases principales:
- `Document_Service`
- `PDF_Service`
- `Download_Service`
- `Settings_Service`

Dependencias:
- quotes
- invoices
- attachments
- relations
- dashboard
- client portal

Estado:
- implementado en su base operativa
- consolidado en Fase 11D como capa documental reusable

Riesgos o puntos sensibles:
- depende de ownership correcto y validacion estricta por recurso
- no reintroducir `file_url` directo en frontend cliente para recursos protegidos
- si no hay motor PDF disponible, la descarga PDF debe degradar de forma controlada
- attachments con `file_path` no resoluble deben devolver error limpio
- `Document_Service` debe seguir siendo la unica orquestacion activa para no recrear flujos paralelos
- la auditoria pre-Fase 12 confirma que dashboard cliente y flujo de adjuntos protegidos siguen usando esta capa comun
- la Fase 17 exige que cualquier nuevo entry point documental reutilice la politica central de `Access_Control_Service` y no vuelva a introducir checks divergentes
- en Fase 20, la automatizacion documental sigue siendo logica y no persistente; no debe evolucionar a persistencia automatica sin una ruta deduplicada por objeto logico
- en Fase 20B, `Document_Service` agrega `payment_receipt` como documento logico reusable por `payment_id`
- en Fase 20B, `PDF_Service` genera el comprobante de pago bajo demanda reutilizando `Invoice_Service`

--------------------------------------------------

## Attachments

Carpeta:
- `includes/attachments/`

Proposito:
- gestionar documentos adjuntos por proceso
- controlar visibilidad interna o cliente
- consolidar timeline del proceso

Tablas:
- `sm_attachments`

Clases principales:
- `Attachment_Repository`
- `Attachment_Service`
- `Attachment_Admin_Controller`
- `Process_Timeline_Service`
- `Client_Attachment_Shortcodes`

Dependencias:
- processes
- dashboard
- quotes
- invoices
- payments
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- no exponer documentos internos al cliente
- validar MIME, ownership, visibilidad y resolucion real del archivo antes de servirlo
- no reintroducir `file_url` directo en dashboard cliente o shortcodes de documentos del proceso
- no reintroducir `file_url` directo tampoco en UI admin cuando ya exista una ruta segura reusable

--------------------------------------------------

## Communication

Carpeta:
- `includes/communication/`

Proposito:
- gestionar comentarios internos y mensajes cliente/staff
- gestionar notificaciones internas por usuario o cliente
- centralizar eventos internos reutilizables

Tablas:
- `sm_comments`
- `sm_notifications`

Clases principales:
- `Comment_Repository`
- `Comment_Service`
- `Notification_Repository`
- `Notification_Service`
- `Notification_Event_Catalog`
- `Notification_Channel_Interface`
- `Email_Notification_Channel`
- `Event_Dispatcher`
- `Client_Comment_Shortcodes`

Dependencias:
- processes
- quotes
- invoices
- attachments
- dashboard
- client portal

Estado:
- implementado en su base operativa

Integracion real confirmada:
- `class-plugin.php` registra `Event_Dispatcher`, `Comment_Service`, `Notification_Service` y `Client_Comment_Shortcodes`
- `class-process-admin-controller.php` agrega la pestana `communication` en el detalle del proceso
- `class-client-comment-shortcodes.php` registra shortcodes de comentarios, formulario cliente y notificaciones
- `class-notification-service.php` genera notificaciones desde eventos de proceso, quote, invoice, pago, adjunto y comentario
- en Fase 16, `class-event-dispatcher.php` amplía el catalogo operativo con eventos especificos de creacion, paso, finalizacion, cancelacion y cobranza pagada
- en Fase 16, `class-notification-service.php` consume ese catalogo ampliado sin absorber persistencia ni logica de negocio ajena

Riesgos o puntos sensibles:
- no exponer comentarios internos al cliente
- evitar notificaciones ajenas por fallo de ownership
- no duplicar eventos en timeline y feed
- no volver a usar `process_updated` como evento paraguas para creacion, cambio de paso o estado final
- mantener alineado el acceso a comentarios y notificaciones con el ownership del proceso u objeto real, no solo con el destinatario

Cambios tecnicos recientes confirmados:
- en Fase 17, `Comment_Service` y `Notification_Service` endurecen acceso apoyandose en `Access_Control_Service`
- las notificaciones cliente validan ahora recipient + ownership del proceso u objeto cuando aplica
- en FASE 33, `Notification_Service` consolida validación de tipos desde `Notification_Event_Catalog` y mantiene `sm_notifications` como canal in-app principal
- en FASE 33, `Email_Notification_Channel` añade primer canal externo desacoplado por `wp_mail`, habilitable desde `sm_settings.notifications.enable_email_notifications`
- en FASE 33, `Event_Dispatcher` incorpora triggers de citas (`appointment_created`, `appointment_updated`, `appointment_status_changed`, `appointment_cancelled`)
- en FASE 34, `Event_Dispatcher` incorpora trigger `appointment_reminder` y lo enruta a `Notification_Service::notify_appointment_reminder()`

--------------------------------------------------

## Client Portal

Carpeta:
- `includes/dashboard/`
- `includes/quotes/`
- `includes/invoices/`
- `includes/attachments/`
- `includes/communication/`
- `includes/helpers/`

Nota:
- Client Portal no es un modulo fisico independiente
- es una capa funcional construida sobre varios modulos existentes

Proposito:
Exponer el frontend cliente mediante shortcodes.

Tablas:
- reutiliza `sm_clients`
- `sm_client_vehicles`
- `sm_processes`
- `sm_quotes`
- `sm_invoices`
- `sm_payments`
- `sm_attachments`
- `sm_comments`
- `sm_notifications`

Clases principales:
- `Client_Dashboard_Shortcodes`
- `Client_Quote_Shortcodes`
- `Client_Invoice_Shortcodes`
- `Client_Attachment_Shortcodes`
- `Client_Comment_Shortcodes`
- `Client_Dashboard_Controller`
- `Download_Service`

Dependencias:
- dashboard
- relations
- quotes
- invoices
- processes
- attachments
- communication
- documents

Estado:
- implementado

Riesgos o puntos sensibles:
- depende de ownership correcto
- cualquier relajacion de permisos puede exponer datos de otros clientes
- no se deben mezclar enlaces directos y flujo seguro para recursos protegidos; dashboard y shortcode de documentos ya usan la ruta segura comun
- cualquier listado cliente nuevo debe filtrar por usuario mediante la capa central de access control y no solo por `client_id` en la query

--------------------------------------------------

## REST API

Carpeta:
- `includes/`
- `includes/modules/`

Proposito:
Exponer API interna y API publica activas, manteniendo aislamiento de la capa legacy experimental.

Tablas:
- reutiliza tablas de modulos existentes

Clases principales:
- `includes/dashboard/class-client-rest-controller.php`
- `includes/dashboard/class-admin-rest-controller.php`
- `includes/integrations/public-api/class-public-rest-controller.php`
- `includes/integrations/public-api/class-public-api-auth-service.php`
- `includes/integrations/public-api/class-public-api-service.php`
- `includes/integrations/public-api/class-public-webhook-service.php`
- `class-rest-api.php` (placeholder legacy/no activo)
- `modules/*` (REST experimental legacy/no activo)

Dependencias:
- clients
- vehicles
- processes
- flows
- services estables por modulo

Estado:
- API interna activa en runtime (`super-mechanic/v1`)
- API publica activa en runtime (`super-mechanic-public/v1`)
- capa REST en `includes/modules/*` sigue como legacy experimental no activa

Riesgos o puntos sensibles:
- mantener el scope de 27A read-only para no abrir regresiones de seguridad
- no asumir write/API pública funcional mientras no se abra 27B/27C con hardening específico

Actualización Fase 27A:
- estado:
  - implementado (base segura cliente, alcance read-only)
- integración real:
  - `includes/dashboard/class-client-rest-controller.php`
  - wiring en `includes/class-plugin.php`
- endpoints activos:
  - `GET /wp-json/super-mechanic/v1/client/processes`
  - `GET /wp-json/super-mechanic/v1/client/processes/{id}`
  - `GET /wp-json/super-mechanic/v1/client/vehicles`
  - `GET /wp-json/super-mechanic/v1/client/vehicles/{id}`
  - `GET /wp-json/super-mechanic/v1/client/quotes`
  - `GET /wp-json/super-mechanic/v1/client/quotes/{id}`
  - `GET /wp-json/super-mechanic/v1/client/invoices`
  - `GET /wp-json/super-mechanic/v1/client/invoices/{id}`
- seguridad aplicada:
  - autenticación WordPress obligatoria
  - `Permission_Service` para acceso de portal cliente
  - `Access_Control_Service` para ownership por recurso
  - no expone `file_url` ni rutas de descarga
- exclusiones deliberadas de 27A:
  - sin endpoints write
  - sin `sm_public_tracking`
  - sin endpoint de comentarios cliente en API

Actualización Fase 27B/27C:
- integración real adicional:
  - `includes/dashboard/class-admin-rest-controller.php`
- alcance:
  - API interna admin activa (read-only + acciones internas mínimas controladas en 27C-B)

Actualización Fase 36A:
- integración real adicional (API pública separada):
  - `includes/integrations/public-api/class-public-rest-controller.php`
  - `includes/integrations/public-api/class-public-api-auth-service.php`
  - `includes/integrations/public-api/class-public-api-service.php`
- namespace:
  - `super-mechanic-public/v1`
- endpoints read-only públicos:
  - `GET /business`
  - `GET /processes`
  - `GET /appointments`
- seguridad:
  - API key propia del plugin con `business_id` por credencial y scopes

Actualización Fase 36B:
- integración real adicional (webhooks outbound públicos):
  - `includes/integrations/public-api/class-public-webhook-event-catalog.php`
  - `includes/integrations/public-api/class-public-webhook-repository.php`
  - `includes/integrations/public-api/class-public-webhook-delivery-repository.php`
  - `includes/integrations/public-api/class-public-webhook-service.php`
  - `includes/integrations/public-api/class-public-webhook-delivery-service.php`
- tablas operativas:
  - `sm_webhooks`
  - `sm_webhook_deliveries`
- hardening:
  - firma `HMAC-SHA256`
  - delivery asíncrona
  - idempotencia por `webhook_id + event_id`
  - retries acotados

Actualización Fase 36C-1:
- integración real adicional (write pública mínima de cita):
  - `POST /appointments/{id}/cancel`
- scope:
  - `appointments:cancel`
- hardening:
  - tenant boundary explícito por `appointment_id + business_id` de credencial
  - idempotencia por transient (24h) con `idempotency_key`
  - idempotencia natural por estado cuando no hay key

Actualización Fase 36C-2:
- integración real adicional (segunda write pública mínima de cita):
  - `POST /appointments/{id}/confirm`
- scope:
  - `appointments:confirm`
- hardening:
  - tenant boundary explícito por `appointment_id + business_id` de credencial
  - confirmación solo desde `scheduled`
  - bloqueo `409` para `cancelled`, `completed` e `in_progress`
  - idempotencia por transient (24h) con `idempotency_key`

--------------------------------------------------

Actualización Fase 37A:
- integración real adicional (calendario operativo admin):
  - submenu `Calendar` en admin
  - assets locales FullCalendar (sin CDN)
  - endpoints internos:
    - `GET /admin/appointments/calendar`
    - `POST /admin/appointments/{id}/status`
- hardening:
  - `register_rest_hooks()` de citas cableado fuera de `is_admin()` para evitar 404 por runtime/hook
  - permiso por `sm_manage_processes`
  - mutación de estado pasando por service (sin update directo en repository)

## WooCommerce Integration

Carpeta:
- `includes/integrations/woocommerce/`
- `includes/helpers/`
- `includes/quotes/`
- `includes/invoices/`
- `includes/maintenance/`

Proposito:
Integracion comercial base con WooCommerce como catalogo de productos.

Tablas:
- sin tablas propias activas del plugin
- uso de `reference_id` en `sm_quote_items` y `sm_invoice_items` para `woo_product_id` (snapshot en datos del plugin)

Clases principales:
- `Woo_Product_Service`
- `Quote_Service`
- `Invoice_Service`
- `Maintenance_Service`

Dependencias:
- quotes
- invoices
- maintenance

Estado:
- implementado (alcance 38B consolidado)

Riesgos o puntos sensibles:
- no abrir integracion de orders/checkout/pagos/taxes Woo fuera del alcance comercial actual
- mantener `snapshot-only` (`reference_id`, `label`, `unit_price`) sin recalculo dinamico de precio desde Woo
- en Woo inactivo, preservar flujo manual sin dependencia forzada

--------------------------------------------------

## Orden de dependencia del sistema

Core
Security
Settings
Clients
Vehicles
Client-Vehicle Relations
Flows
Processes
Maintenance
PreDelivery
Paperwork
Quotes
Invoices
Payments
Documents / Secure Downloads
Attachments
Communication
Dashboard
Client Portal
REST API
WooCommerce Integration

Nota:
- este orden representa dependencias logicas
- no necesariamente corresponde al orden de carga en WordPress

--------------------------------------------------

## Reports

Carpeta:
- `includes/reports/`

Proposito:
- exponer reportes operativos base para administracion interna
- exponer reportes financieros base para administracion interna
- consolidar consultas reutilizables de reporting fuera de dashboards y controllers

Tablas:
- `sm_processes`
- `sm_maintenance`
- `sm_clients`
- `sm_vehicles`
- `sm_quotes`
- `sm_invoices`
- `sm_payments`

Clases principales:
- `Report_Repository`
- `Report_Service`
- `Report_Admin_Controller`

Dependencias:
- processes
- maintenance
- clients
- vehicles
- quotes
- invoices
- payments
- core

Estado:
- implementado y consolidado para Fases 12A, 12B, 12C y 12D

Riesgos o puntos sensibles:
- no mezclar SQL analitico nuevo dentro de `Dashboard_Service`
- mantener filtros acotados y listados con limite para no degradar rendimiento
- no convertir el modulo en BI avanzado antes de las siguientes subfases
- mantener separados los filtros financieros (`quote_status`, `invoice_status`) de la semantica operativa de procesos para no degradar la claridad de la UI admin
- mantener los totales financieros agrupados por moneda para no mezclar importes incompatibles
- mantener acotadas las vistas permitidas para exportacion CSV admin
- mantener las comparativas avanzadas como capa analitica simple, sin charts ni dashboard paralelo

Cambios tecnicos recientes confirmados:
- `Report_Service` centraliza filtros compartidos y separa datasets operativos y financieros
- `Report_Admin_Controller` separa la UI admin por bloques y registra `admin_post_sm_export_report_csv`
- la exportacion CSV del modulo queda limitada a `recent_processes`, `recent_quotes`, `recent_invoices` y `recent_payments`
- `Report_Repository` y `Report_Service` aplican limites explicitos para listados recientes sin cambiar schema
- `Report_Repository` agrega comparativas base de procesos, quotes, invoices y payments por rango usando consultas agregadas
- `Report_Service` calcula periodo actual vs periodo anterior equivalente cuando el rango esta completo y expone un bloque `advanced`
- `Report_Service` devuelve comparacion no disponible cuando el baseline no es valido y la UI renderiza `N/A`
- `Report_Service` agrega un resumen ejecutivo simple con metricas de alto nivel
- `Report_Admin_Controller` agrega la seccion `Reportes avanzados base` sin tocar bootstrap ni frontend cliente
- en 12E, `Report_Service` reutiliza los limites de `Report_Repository` como fuente unica para listados recientes
- en 12E, `Report_Admin_Controller` endurece la lectura de filtros admin y deja de renderizar comparativas monetarias sinteticas cuando no hay datos
- en 12E, la deuda tecnica del modulo queda explicitada en la documentacion sin cambiar schema ni exportaciones soportadas
- en Fase 24, `Report_Admin_Controller` mejora la presentacion visual y de filtros sin alterar datasets, filtros ni exportacion
- en Fase 38A-2, `Report_Service::get_currency_options()` deja lista hardcodeada y consume monedas soportadas desde `Settings_Service`

Deuda tecnica vigente:
- `Report_Service` sigue siendo grande y conviene vigilarlo si el modulo incorpora nuevas capas analiticas
- cualquier indice nuevo sugerido por performance debe tratarse como mejora futura hasta existir en `class-schema.php`

Actualizacion Fase 22:
- `Report_Repository` agrega agregados avanzados para estados derivados, readiness operativa, aging, pagos por metodo y top clientes
- `Report_Service` amplía filtros con `derived_status`, `currency` y `payment_method` manteniendo el patron por bloques
- `Report_Admin_Controller` expone tablas avanzadas nuevas sin romper exportacion CSV previa

Actualizacion Fase 29:
- `Report_Service::validate_filters()` amplía filtros operativos con `mechanic_id`, `client_id` y `vehicle_id`
- `Report_Repository` agrega breakdowns operativos por mecánico, cliente y vehículo sobre `sm_processes`
- criterio de mecánico en reportes operativos definido de forma única en `sm_processes.assigned_to` (sin mezclar `sm_maintenance.mechanic_id`)
- `Report_Repository` agrega agregados financieros por moneda para `subtotal`, `tax_total`, `discount_total` y `grand_total` de invoices
- `Report_Admin_Controller` expone nuevos filtros y tablas de FASE 29 manteniendo separación explícita entre `invoice_status` y estado de cobranza
- la fase mantiene alcance interno admin, sin API pública de reportes y sin cambios de schema

--------------------------------------------------

## Process Derived States

Carpeta:
- `includes/processes/`

Proposito:
- centralizar estados derivados seguros de procesos sin persistencia nueva

Clases principales:
- `Process_Derived_State_Service`

Dependencias:
- processes
- quotes
- invoices
- predelivery

Estado:
- implementado como soporte transversal en Fase 20

Riesgos o puntos sensibles:
- no debe inventar derivados sin criterio objetivo ya persistido
- `ready_for_delivery` solo debe derivarse desde `pre_delivery.delivery_ready`
- cualquier derivado futuro mas complejo debe mantenerse fuera de controllers y sin duplicar reglas en dashboard

--------------------------------------------------

## Access Control / Ownership

Carpeta:
- `includes/helpers/`

Proposito:
- centralizar ownership, visibilidad y reglas de acceso por rol
- resolver acceso a vehiculos, procesos, quotes, invoices y attachments desde una sola capa reutilizable

Clases principales:
- `Access_Control_Service`

Dependencias:
- clients
- relations
- processes
- quotes
- invoices
- attachments

Estado:
- implementado como infraestructura transversal en Fase 17

Riesgos o puntos sensibles:
- evitar dependencias circulares con services de dominio
- no volver a mover ownership al dashboard o a shortcodes cliente
- mantener alineado el acceso documental con esta politica comun
- llamadas repetitivas de contexto/cliente en el mismo request pueden escalar memoria si no hay memoizacion por request

Cambios tecnicos recientes confirmados:
- en `HOTFIX-MEM-1`, `Access_Control_Service` adopta inicializacion lazy de dependencias y cache por request para `client_id` / `business_id` con el objetivo de cortar cascadas y evitar consumo masivo de memoria
## Actualizacion Fase 23. Client Portal premium con acciones reales

### Dashboard / Client Portal
- Cambios tecnicos recientes confirmados:
  - `Client_Dashboard_Controller` agrega detalle integrado de proceso para cliente con resumen operativo y financiero
  - el Client Portal ahora permite registrar comentarios de proceso reutilizando `Comment_Service`
  - el detalle integrado reutiliza `Attachment_Service`, `Process_Timeline_Service`, `Quote_Service` e `Invoice_Service` sin mover logica de negocio a controllers
  - `Client_Invoice_Shortcodes` expone descarga segura de `payment_receipt`
  - `Client_Quote_Shortcodes` y `Client_Invoice_Shortcodes` refuerzan accesos a detalle y documentos seguros
  - en Fase 24, `Client_Dashboard_Controller`, `Client_Quote_Shortcodes` y `Client_Invoice_Shortcodes` modernizan la capa visual sin tocar ownership, nonces ni descargas

--------------------------------------------------

## Appointments (FASE 32A)

Carpeta:
- `includes/appointments/`

Proposito:
- base operativa de agenda/citas del taller
- CRUD admin de citas con cliente, vehiculo y mecanico
- vinculo opcional con proceso existente
- emision de eventos internos para notificaciones operativas centralizadas

Tablas:
- `sm_appointments`

Clases principales:
- `Appointment_Repository`
- `Appointment_Service`
- `Appointment_Reminder_Scheduler`
- `Appointment_Admin_Controller`
- `Appointment_List_Table`

Dependencias:
- clients
- vehicles
- processes
- communication
- automation
- core

Estado:
- implementado en Fase 32A

Riesgos o puntos sensibles:
- mantener `assigned_to` como fuente unica de asignacion de mecanico en citas
- validar coherencia cliente/vehiculo/proceso para evitar vinculos cruzados invalidos
- no ampliar alcance a automatizaciones o calendario JS complejo en esta fase

Cambios tecnicos recientes confirmados:
- submenu admin nuevo `Citas` en `class-admin-menu.php`
- wiring runtime nuevo en `class-plugin.php`
- filtros admin por fecha, mecanico y estado
- estados basicos implementados: `scheduled`, `confirmed`, `in_progress`, `completed`, `cancelled`
- en FASE 33, `Appointment_Service` integra `Event_Dispatcher` y emite `sm_event_appointment_*` en alta, edición, cambio de estado y cancelación (incluyendo flujo inbound controlado)
- en FASE 34, `Appointment_Reminder_Scheduler` añade recordatorios automáticos por `wp_cron` y dispatch controlado de `appointment_reminder`
- en FASE 37A se agrega vista admin `Calendar` con FullCalendar local y carga por rango visible
- en FASE 37A se habilitan endpoints internos admin:
  - `GET /super-mechanic/v1/admin/appointments/calendar`
  - `POST /super-mechanic/v1/admin/appointments/{id}/status`
- en FASE 37A el cambio rapido de estado desde calendario pasa por `Appointment_Service::update_appointment_status_from_calendar()`

--------------------------------------------------

## Automation (FASE 34)

Carpeta:
- `includes/automation/`

Proposito:
- ejecutar automatizaciones operativas simples basadas en eventos internos
- mantener reglas activables/desactivables sin builder complejo
- coordinar refresco controlado de scheduler de recordatorios

Clases principales:
- `Automation_Service`
- `Automation_Rule_Engine`

Dependencias:
- communication (dispatcher/eventos)
- appointments (scheduler)
- helpers/settings
- core

Estado:
- implementado en FASE 34

Riesgos o puntos sensibles:
- evitar loops o ejecuciones duplicadas sobre el mismo evento
- no escalar a motor complejo de reglas en esta fase
- mantener alcance operativo (sin campañas ni marketing)

Cambios tecnicos recientes confirmados:
- `Automation_Service` escucha `sm_event_appointment_*` y ejecuta acciones simples por rule engine
- `Automation_Rule_Engine` mantiene resolución mínima de acciones por evento
- integración con `Appointment_Reminder_Scheduler::schedule_near_term_scan()` para refresco no destructivo del cron

--------------------------------------------------

## Integrations / Google Calendar (FASE 32B-1, 32B-2, 32B-3A)

Carpeta:
- `includes/integrations/google-calendar/`

Proposito:
- mantener sync de citas con Google Calendar sin perder la cita local como fuente de verdad
- exponer reconciliación inbound controlada y manual, con política de conflicto explícita

Tablas:
- `sm_appointment_calendar_sync` (sin cambios de schema en 32B-3A)

Clases principales:
- `Google_Calendar_Auth_Controller`
- `Google_Calendar_Client`
- `Google_Calendar_Service`
- `Google_Calendar_Sync_Repository`
- `Google_Calendar_Sync_Service`
- `Google_Calendar_Inbound_Reconcile_Service`

Dependencias:
- appointments
- helpers/settings
- core

Estado:
- implementado para 32B-1 (feed firmado), 32B-2 (outbound 1-way) y 32B-3A (inbound controlada)

Politica inbound 32B-3A:
- campos permitidos: `start_at`, `appointment_date` derivada, `notes` sanitizada/acotada, `appointment_status` solo para `cancelled`
- campos prohibidos: `client_id`, `vehicle_id`, `process_id`, `assigned_to`, IDs estructurales, relaciones, `created_at` y datos financieros/documentales
- conflicto: `conflict` cuando cambió local y remoto desde base previa
- rechazo: `rejected` cuando Google altera campos no permitidos

Cambios tecnicos recientes confirmados:
- lectura remota puntual por `external_event_id`
- remapeo por `external_event_id` en repository de sync
- estado sync operativo consolidado para reconciliación (`synced`, `error`, `conflict`, `rejected`)
- accion manual admin `Reconcile inbound now` en `Settings`
- hash de estado evolucionado a formato local/remoto sin migración de tabla
- endpoint REST dedicado de webhook en `super-mechanic/v1/google-calendar/webhook`
- ciclo de vida de watch channel (create/renew/stop best effort) desde `Google_Calendar_Service`
- reconciliación disparada por webhook en cola interna usando lógica 32B-3A (sin update directo en webhook)
- renovación preventiva por cron (`sm_google_calendar_watch_renew`) + acción manual `Renew watch channel now`
- idempotencia base por `watch_last_message_number` + lock corto por fingerprint de notificación

Riesgos o puntos sensibles:
- no relajar la regla de plugin como fuente maestra
- mantener validación estricta de headers `X-Goog-*` en webhook
- no introducir updates inbound sobre campos estructurales
- vigilar drift de timezone en `start_at` durante reconciliación

--------------------------------------------------

## CRM Scheduler & Alerts (FASE 39E)

Carpeta:
- `includes/crm/`

Proposito:
- ejecutar recálculo controlado de alertas CRM por `WP-Cron`
- persistir señales CRM operativas en storage propio para consumo futuro

Tablas:
- `sm_crm_alerts`

Clases principales:
- `Crm_Scheduler_Service`
- `Crm_Alert_Service`
- `Crm_Alert_Repository`

Dependencias:
- `crm_pipeline`
- `crm_tasks`
- core bootstrap

Estado:
- `39E-1` implementado
- `39E-2` implementado y validado en runtime WordPress real
- `39E-3` implementado y validado en runtime WordPress real (consumo UI persistido en list/kanban/view con fallback runtime controlado)

Riesgos o puntos sensibles:
- evitar recálculo agresivo en cada tick (usar lotes/límites)
- evitar duplicación de alertas activas por tipo/pipeline
- mantener mensajes determinísticos para minimizar writes
- no introducir automatizaciones externas (email/webhook) en esta fase
