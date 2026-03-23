WP PLUGIN PATTERNS — SUPER MECHANIC

Este archivo define los patrones correctos de desarrollo
para plugins WordPress dentro del proyecto Super Mechanic.

El objetivo es garantizar que todo el código generado
sea compatible con la arquitectura WordPress y con
la arquitectura interna del plugin.

==================================================
BOOTSTRAP DEL PLUGIN
==================================================

Archivo principal:

super-mechanic.php

Responsabilidades:

- registrar hooks de activación
- registrar hooks de desactivación
- cargar el autoloader
- iniciar class-plugin.php

Ejemplo patrón:

register_activation_hook()
register_deactivation_hook()

No colocar lógica de negocio en el archivo principal del plugin.

==================================================
PLUGIN CORE
==================================================

Archivo central:

includes/class-plugin.php

Responsabilidades:

- inicializar módulos
- registrar servicios
- cargar controllers
- conectar hooks
- inicializar shortcodes

class-plugin.php es el punto de arranque del sistema.

Evitar agregar lógica de negocio aquí.

==================================================
AUTOLOADER
==================================================

Archivo:

includes/autoloader.php

Responsabilidad:

cargar automáticamente clases del plugin
según namespace y nombre de archivo.

Las clases deben seguir el patrón:

class-nombre-de-clase.php

Ejemplo:

Quote_Service
→ class-quote-service.php

Invoice_Repository
→ class-invoice-repository.php

==================================================
ESTRUCTURA DE CLASES
==================================================

Clases del plugin siguen patrón:

class-nombre-de-clase.php

Ejemplos:

class-process-service.php
class-quote-repository.php
class-invoice-controller.php

Namespaces deben coincidir con la carpeta.

Ejemplo:

includes/quotes/

namespace Super_Mechanic\Quotes;

==================================================
HOOKS WORDPRESS
==================================================

Hooks deben registrarse en:

- class-plugin.php
- controllers
- servicios específicos

Nunca registrar hooks en:

repositories
helpers

==================================================
ADMIN SCREENS
==================================================

Pantallas admin deben implementarse mediante:

Controllers

Ejemplo:

Quote_Admin_Controller
Invoice_Admin_Controller

Responsabilidades:

- renderizar UI
- validar permisos
- sanitizar datos

La lógica de negocio debe delegarse a Services.

==================================================
SHORTCODES
==================================================

Shortcodes son el punto de entrada del frontend cliente.

Deben:

- validar login si aplica
- validar ownership
- delegar lógica en Services

Nunca ejecutar lógica de negocio compleja directamente.

==================================================
ACCESO A BASE DE DATOS
==================================================

Acceso a base de datos solo mediante:

Repositories.

Ejemplo:

Quote_Repository
Invoice_Repository
Process_Repository

Reglas:

usar $wpdb
usar consultas preparadas
sanitizar datos

Nunca ejecutar SQL en:

Controllers
Shortcodes
Services

==================================================
SERVICES
==================================================

Los Services contienen:

lógica de negocio del módulo.

Ejemplo:

Quote_Service
Invoice_Service
Process_Service

Pueden utilizar:

Repositories
otros Services

No deben ejecutar SQL directo.

==================================================
SEGURIDAD
==================================================

Siempre usar:

current_user_can()
nonces
sanitize_*()
esc_*()

Validar ownership en datos de cliente.

Ejemplos críticos:

client_id
process_id
quote_id
invoice_id
attachment_id

==================================================
DESCARGAS DE DOCUMENTOS
==================================================

Documentos protegidos deben descargarse mediante:

Download_Service
Document_Service

Nunca exponer file_url directo para:

quotes
invoices
attachments
PDFs

==================================================
TABLAS PERSONALIZADAS
==================================================

Tablas del plugin utilizan prefijo:

sm_

Ejemplo:

sm_clients
sm_processes
sm_quotes
sm_invoices

El schema real debe coincidir con:

/docs/DATABASE_MAP.md

==================================================
REGLA FINAL
==================================================

Antes de generar código WordPress:

1 verificar patrón correcto de plugin
2 respetar arquitectura interna del proyecto
3 reutilizar clases existentes
4 evitar duplicar lógica
5 validar seguridad y ownership