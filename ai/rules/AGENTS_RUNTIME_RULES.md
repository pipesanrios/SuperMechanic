AGENTS RUNTIME RULES — SUPER MECHANIC

Este archivo define cómo los agentes de IA (Codex, ChatGPT u otros)
deben interactuar con el proyecto Super Mechanic.

Los agentes deben leer este archivo antes de realizar análisis,
generar código o modificar archivos del proyecto.

==================================================
OBJETIVO
==================================================

Garantizar que cualquier agente de IA respete:

- arquitectura del plugin
- límites entre módulos
- seguridad del sistema
- integridad de la base de datos
- consistencia de documentación

==================================================
ARCHIVOS DE REFERENCIA OBLIGATORIOS
==================================================

Antes de generar código el agente debe revisar:

ai/context/PROJECT_MEMORY.md  
ai/rules/GUARDRAILS.md  
ai/rules/MODULE_BOUNDARIES.md  
ai/rules/WP_PLUGIN_PATTERNS.md  
ai/rules/ERROR_RECOVERY_PROTOCOL.md  

Y la documentación técnica:

ARCHITECTURE.md  
docs/FINAL_ARCHITECTURE_MAP.md  
docs/SYSTEM_MAP.md  
docs/CURRENT_STATE.md  
docs/MODULE_REGISTRY.md  
docs/DATABASE_MAP.md  
docs/DEV_GUIDE.md  

==================================================
ARQUITECTURA DEL PROYECTO
==================================================

El plugin utiliza arquitectura modular basada en:

Repository  
Service  
Controller  
Shortcodes  
REST Controller (cuando aplique)

Reglas:

Repository → acceso a base de datos  
Service → lógica de negocio  
Controller → UI admin  
Shortcodes → frontend cliente  

SQL solo permitido en Repository.

==================================================
ESTRUCTURA ACTIVA DEL PROYECTO
==================================================

La arquitectura activa del plugin está en:

includes/*

La carpeta:

includes/modules/*

es una capa legacy/paralela y no debe modificarse
salvo migración explícita.

==================================================
REGLA DE IMPLEMENTACIÓN
==================================================

Antes de escribir código el agente debe:

1 revisar documentación  
2 identificar módulos afectados  
3 listar archivos a modificar  
4 listar archivos nuevos  
5 listar tablas involucradas  
6 validar dependencias  
7 validar riesgos  

Solo después generar código.

==================================================
CONTROL DE ALCANCE
==================================================

El agente solo puede modificar:

- archivos incluidos en el alcance confirmado
- módulos relacionados con la tarea

No modificar:

bootstrap del plugin  
módulos críticos  
tablas no relacionadas  

sin justificarlo.

==================================================
SEGURIDAD
==================================================

Siempre validar:

current_user_can()  
nonces  
sanitize_*()  
esc_*()  

Validar ownership para:

client_id  
process_id  
quote_id  
invoice_id  
attachment_id  

==================================================
DESCARGAS Y DOCUMENTOS
==================================================

Nunca exponer:

file_url directo

Las descargas deben usar:

Download_Service  
Document_Service  

==================================================
REGLA DE FUENTE DE VERDAD
==================================================

Si existe diferencia entre:

documentación  
y  
código del proyecto

la fuente de verdad es:

EL CÓDIGO REAL DEL PLUGIN.

La documentación debe actualizarse para reflejar el código real.

==================================================
REGLA DE ACTUALIZACIÓN DE DOCUMENTACIÓN
==================================================

Cuando una fase o cambio esté implementado
el agente debe actualizar:

ARCHITECTURE.md  
FINAL_ARCHITECTURE_MAP.md  
SYSTEM_MAP.md  
CURRENT_STATE.md  
MODULE_REGISTRY.md  
DATABASE_MAP.md  

solo si hubo cambios reales.

==================================================
PROTOCOLO DE ERROR
==================================================

Si se detecta un error:

1 identificar archivo afectado  
2 identificar módulo afectado  
3 identificar causa probable  
4 evaluar impacto  
5 proponer corrección mínima  

==================================================
FORMATO DE RESPUESTA DEL AGENTE
==================================================

Antes de generar código el agente debe:

1 explicar qué va a hacer
2 listar archivos a modificar
3 listar archivos nuevos
4 indicar módulos afectados
5 indicar tablas afectadas
6 indicar riesgos

Solo después generar código.


Nunca realizar refactor global sin diagnóstico previo.