PLUGIN LIFECYCLE — SUPER MECHANIC

Este documento describe el ciclo de vida del plugin Super Mechanic
dentro de WordPress.

Su objetivo es documentar:

- activación
- inicialización
- carga de módulos
- upgrades de schema
- desactivación
- desinstalación

Esto ayuda a mantener consistencia cuando el plugin evoluciona.

==================================================
INSTALACIÓN DEL PLUGIN
==================================================

Cuando el plugin se instala:

WordPress copia los archivos del plugin al directorio:

wp-content/plugins/super-mechanic/

En este momento el plugin aún no ejecuta lógica.

La lógica comienza cuando el plugin se activa.

==================================================
ACTIVACIÓN DEL PLUGIN
==================================================

Archivo responsable:

super-mechanic.php

Se registra el hook:

register_activation_hook()

Durante la activación se deben ejecutar:

1 creación de tablas
2 creación de roles
3 registro de capabilities
4 inicialización de opciones del plugin
5 registro de versión de schema

La creación de tablas se realiza mediante
las clases ubicadas en:

includes/database/

==================================================
CREACIÓN DE TABLAS
==================================================

Las tablas principales del sistema son:

sm_clients
sm_vehicles
sm_client_vehicles

sm_flows
sm_flow_steps

sm_processes
sm_process_step_logs
sm_process_parts
sm_process_meta

sm_maintenance
sm_maintenance_parts
sm_maintenance_labor

sm_pre_delivery

sm_paperwork
sm_paperwork_items

sm_quotes
sm_quote_items

sm_invoices
sm_invoice_items
sm_payments

sm_attachments

sm_comments
sm_notifications

Las definiciones del schema deben coincidir con:

/docs/DATABASE_MAP.md

==================================================
REGISTRO DE ROLES Y CAPABILITIES
==================================================

Durante la activación también se registran:

roles del sistema
capabilities del plugin

Clases responsables:

class-roles.php
class-capabilities.php

==================================================
INICIALIZACIÓN DEL PLUGIN
==================================================

Cuando WordPress carga el plugin:

super-mechanic.php
→ carga autoloader
→ inicia class-plugin.php

class-plugin.php se encarga de:

- cargar módulos
- registrar hooks
- inicializar servicios
- registrar shortcodes
- cargar controllers

==================================================
CARGA DE MÓDULOS
==================================================

Los módulos activos se encuentran en:

includes/

Ejemplos:

clients/
vehicles/
relations/
flows/
processes/
maintenance/
predelivery/
paperwork/
dashboard/
quotes/
invoices/
attachments/
communication/
helpers/

La carpeta:

includes/modules/

es una capa legacy y no forma parte de la arquitectura activa.

==================================================
UPGRADES DE SCHEMA
==================================================

Cuando el plugin se actualiza
puede requerir cambios en la base de datos.

El sistema debe manejar:

versionado de schema
migraciones incrementales

Ejemplo:

schema version
1.9.0

El plugin debe:

comparar versión instalada
vs
versión actual

Si hay diferencias:

ejecutar migraciones necesarias.

==================================================
DESACTIVACIÓN DEL PLUGIN
==================================================

Hook:

register_deactivation_hook()

Durante la desactivación:

- se limpian hooks temporales
- se detienen procesos programados (si existen)

No se deben eliminar datos del sistema.

==================================================
DESINSTALACIÓN DEL PLUGIN
==================================================

Archivo responsable:

uninstall.php

Durante la desinstalación pueden eliminarse:

opciones del plugin
tablas personalizadas

Dependiendo de configuración del sistema.

==================================================
REGLA DE MIGRACIONES
==================================================

Nunca modificar tablas existentes
sin migración controlada.

Los cambios en schema deben:

mantener compatibilidad hacia atrás
registrarse en DATABASE_MAP.md
actualizar versión de schema

==================================================
REGLA DE ESTABILIDAD
==================================================

Los cambios en el ciclo de vida del plugin
pueden afectar:

instalación
actualización
migración
desinstalación

Por lo tanto deben implementarse
con especial cuidado.

==================================================
FUENTE DE VERDAD
==================================================

Si existe diferencia entre este documento
y el comportamiento real del plugin:

la fuente de verdad es el código real del sistema.