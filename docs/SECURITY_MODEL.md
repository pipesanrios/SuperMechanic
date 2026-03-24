SECURITY MODEL — SUPER MECHANIC

Este documento define el modelo de seguridad del plugin Super Mechanic.

El objetivo es garantizar:

- protección de datos de clientes
- control de acceso por rol
- protección de documentos
- prevención de exposición cruzada de información

==================================================
ROLES DEL SISTEMA
==================================================

El plugin define tres roles principales.

Administrator

Acceso completo al sistema.

Puede:

- gestionar clientes
- gestionar vehículos
- crear y editar procesos
- gestionar flujos
- crear cotizaciones
- emitir facturas
- registrar pagos
- acceder a todos los documentos

--------------------------------------------------

Mechanic

Acceso operativo.

Puede:

- ver procesos asignados
- registrar mantenimiento
- añadir notas técnicas
- subir documentos técnicos
- ver vehículos asociados al proceso

No puede:

- acceder a facturación completa
- acceder a configuraciones globales

--------------------------------------------------

Client

Acceso limitado al Client Portal.

Puede:

- ver sus vehículos
- ver sus procesos
- ver cotizaciones
- ver facturas
- descargar documentos permitidos

No puede:

- ver datos de otros clientes
- ver documentos internos
- modificar procesos

==================================================
CONTROL DE ACCESO
==================================================

El acceso debe validarse en tres niveles:

1. Capability check
2. Ownership validation
3. Visibilidad documental

--------------------------------------------------

Capability check

Siempre validar con:

current_user_can()

Ejemplo:

current_user_can('manage_options')

--------------------------------------------------

Ownership validation

Los clientes solo pueden acceder a recursos
que pertenezcan a ellos.

Ejemplo:

client_id
process_id
invoice_id
quote_id

==================================================
DOCUMENTOS
==================================================

Los documentos no deben exponerse directamente.

Nunca exponer:

file_url

Las descargas deben pasar por:

Download_Service
Document_Service

Esto garantiza:

- verificación de permisos
- validación de ownership
- protección contra acceso directo

==================================================
TIPOS DE DOCUMENTOS
==================================================

Los documentos pueden tener dos estados.

Cliente visible

is_client_visible = 1

Disponible en Client Portal.

--------------------------------------------------

Interno

is_internal = 1

Solo visible para:

Administrator
Mechanic

==================================================
PROTECCIÓN DE CONSULTAS
==================================================

Todas las consultas deben usar:

consultas preparadas

Ejemplo:

$wpdb->prepare()

Nunca interpolar variables directamente
en consultas SQL.

==================================================
SANITIZACIÓN
==================================================

Datos entrantes deben sanitizarse.

Funciones recomendadas:

sanitize_text_field
sanitize_textarea_field
absint
sanitize_key
sanitize_email

==================================================
ESCAPING
==================================================

Datos mostrados en pantalla deben escaparse.

Funciones recomendadas:

esc_html
esc_attr
esc_url
esc_js

==================================================
NONCES
==================================================

Acciones sensibles deben usar:

wp_nonce_field
check_admin_referer

Ejemplos:

crear invoice
registrar pago
subir documento
cambiar estado de proceso

==================================================
Client Portal
==================================================

El Client Portal es el punto más sensible.

Siempre validar:

client_id
vehicle_id
process_id
invoice_id
quote_id
attachment_id

Nunca confiar en parámetros URL.

==================================================
API FUTURA
==================================================

Cuando se habilite REST API:

validar:

- permisos
- ownership
- autenticación

Nunca exponer datos de procesos
sin verificar el cliente asociado.

==================================================
REGLA FINAL
==================================================

Si existe duda sobre acceso a datos,
la operación debe fallar por defecto.

Security > convenience