PLUGIN VERSIONING — SUPER MECHANIC

Este documento define la estrategia de versionado del plugin
Super Mechanic y cómo se gestionan cambios estructurales,
migraciones de base de datos y releases.

==================================================
TIPO DE VERSIONADO
==================================================

El plugin utiliza versionado semántico:

MAJOR.MINOR.PATCH

Ejemplo:

0.1.0

MAJOR
cambios incompatibles

MINOR
nuevas funcionalidades compatibles

PATCH
correcciones de errores

==================================================
REGLAS DE VERSIONADO
==================================================

PATCH

Incrementar cuando:

- se corrigen bugs
- se optimiza código
- se ajusta UI
- se corrige seguridad

Ejemplo:

0.1.0 → 0.1.1

--------------------------------------------------

MINOR

Incrementar cuando:

- se añade módulo nuevo
- se añade endpoint REST
- se añade funcionalidad compatible
- se amplía lógica existente

Ejemplo:

1.0.1 → 1.1.0

--------------------------------------------------

MAJOR

Incrementar cuando:

- se rompe compatibilidad
- cambia estructura de BD
- cambia API interna
- se eliminan funciones públicas

Ejemplo:

1.5.0 → 2.0.0

==================================================
VERSIÓN DEL PLUGIN
==================================================

La versión oficial se define en:

super-mechanic.php

Ejemplo:

Version: 0.1.0

También debe mantenerse consistente con:

readme.txt

==================================================
VERSIÓN DE SCHEMA
==================================================

El plugin tiene una versión interna de base de datos.

Ejemplo:

SM_DB_VERSION

Ejemplo en código:

define('SM_DB_VERSION', '1.9.0');

Esta versión controla migraciones.

==================================================
MIGRACIONES DE BASE DE DATOS
==================================================

Cuando cambia el schema:

1 actualizar SM_DB_VERSION
2 crear migración
3 ejecutar migración en activación

Ejemplo:

register_activation_hook()

La migración debe:

- añadir columnas
- añadir tablas
- añadir índices

Nunca eliminar columnas existentes sin migración segura.

==================================================
REGLA DE MIGRACIONES
==================================================

Migraciones deben ser:

idempotentes.

Ejemplo:

comprobar si columna existe
antes de crearla.

Nunca asumir estado previo.

==================================================
COMPATIBILIDAD
==================================================

Los cambios deben mantener compatibilidad con:

- datos existentes
- procesos existentes
- invoices existentes
- documentos existentes

Si no es posible mantener compatibilidad:

incrementar versión MAJOR.

==================================================
CAMBIOS DE API INTERNA
==================================================

Si se modifican:

Service
Repository
Controller

verificar impacto en:

- Dashboard
- Shortcodes
- REST API
- Client Portal

==================================================
RELEASE WORKFLOW
==================================================

Proceso recomendado de release.

1 Auditoría del sistema
2 Ejecutar TEST_SCENARIOS.md
3 Actualizar documentación
4 Incrementar versión
5 Verificar migraciones
6 Crear release

==================================================
VERSIONADO DE DOCUMENTACIÓN
==================================================

La documentación debe reflejar:

versión actual del plugin.

Actualizar cuando:

- cambie arquitectura
- cambie schema
- cambien módulos

==================================================
REGLA FINAL
==================================================

Nunca publicar una nueva versión del plugin
sin validar:

migraciones
compatibilidad
documentación.
