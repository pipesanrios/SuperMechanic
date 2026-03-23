AI RULES — SUPER MECHANIC

Este documento define reglas obligatorias que los agentes de IA
deben seguir al generar código para el plugin Super Mechanic.

Estas reglas garantizan coherencia arquitectónica,
seguridad y mantenibilidad.

==================================================
REGLA 1 — RESPETAR LA ARQUITECTURA
==================================================

El plugin usa arquitectura modular:

Repository
Service
Controller
Shortcodes

Responsabilidades:

Repository
→ acceso a base de datos

Service
→ lógica de negocio

Controller
→ UI admin

Shortcodes
→ frontend cliente

Nunca mezclar responsabilidades.

==================================================
REGLA 2 — SQL SOLO EN REPOSITORY
==================================================

Las consultas SQL deben existir únicamente en:

Repository classes.

Nunca colocar SQL en:

Service
Controller
Shortcodes

==================================================
REGLA 3 — USAR SERVICIOS EXISTENTES
==================================================

Antes de crear lógica nueva,
verificar si ya existe un Service adecuado.

Ejemplo:

Process_Service
Invoice_Service
Quote_Service
Attachment_Service

No duplicar lógica.

==================================================
REGLA 4 — USAR REPOSITORIOS EXISTENTES
==================================================

Antes de escribir una consulta nueva,
verificar si ya existe un método en Repository.

Evitar duplicación.

==================================================
REGLA 5 — VALIDACIÓN DE PERMISOS
==================================================

Siempre validar permisos cuando se accede
a recursos sensibles.

Ejemplos:

current_user_can()
ownership validation

==================================================
REGLA 6 — SANITIZACIÓN
==================================================

Datos entrantes deben sanitizarse.

Funciones recomendadas:

sanitize_text_field
sanitize_textarea_field
absint
sanitize_email
sanitize_key

==================================================
REGLA 7 — ESCAPING
==================================================

Datos mostrados en HTML deben escaparse.

Funciones recomendadas:

esc_html
esc_attr
esc_url

==================================================
REGLA 8 — CONSULTAS PREPARADAS
==================================================

Todas las consultas SQL deben usar:

$wpdb->prepare()

Nunca interpolar variables directamente.

==================================================
REGLA 9 — DOCUMENTOS SEGUROS
==================================================

Nunca exponer:

file_url directo.

Las descargas deben pasar por:

Download_Service
Document_Service

==================================================
REGLA 10 — RESPETAR ESTRUCTURA DEL PROYECTO
==================================================

No crear archivos arbitrariamente.

Los módulos deben ubicarse en:

includes/

Ejemplo:

includes/processes/
includes/invoices/
includes/quotes/

==================================================
REGLA 11 — NO MODIFICAR LEGACY
==================================================

La carpeta:

includes/modules/*

es legacy.

No modificar ni reutilizar esa arquitectura.

==================================================
REGLA 12 — USAR CLASES EXISTENTES
==================================================

Antes de crear nuevas clases:

buscar si ya existe una equivalente.

==================================================
REGLA 13 — EVITAR COMPLEJIDAD
==================================================

El código generado debe:

ser claro
ser corto
ser modular

Evitar funciones gigantes.

==================================================
REGLA 14 — RESPETAR DOCUMENTACIÓN
==================================================

Antes de implementar cambios revisar:

docs/

ARCHITECTURE.md
SYSTEM_MAP.md
DATABASE_MAP.md
MODULE_REGISTRY.md

==================================================
REGLA 15 — INTEGRIDAD TRANSACCIONAL
==================================================

Cuando una operación implique múltiples escrituras relacionadas:

ejemplo:
- actualización de proceso
- inserción de step logs
- creación de invoice + items

se debe considerar el uso de transacciones.

No implementar lógica que simule atomicidad sin transacción real.

Casos conocidos:

- Process_Service
- Process_Repository
- Invoice_Transaction_Repository

==================================================
REGLA FINAL
==================================================

Si existe conflicto entre:

documentación
y
código

la fuente de verdad es el código actual.