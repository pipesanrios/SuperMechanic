WORKFLOW IA — SUPER MECHANIC (OPTIMIZADO)

Define el flujo estándar de trabajo con IA.

==================================================
1. INICIO DE SESIÓN
==================================================

Cargar:

- PROMPT MASTER — SUPER MECHANIC

Opcional según necesidad:

- AI_CONTEXT.md
- AGENTS_QUICK_CONTEXT.md

==================================================
2. DEFINICIÓN DE ALCANCE
==================================================

Definir claramente:

- funcionalidad / bug / fase
- objetivo específico

==================================================
3. ANÁLISIS PREVIO (OBLIGATORIO)
==================================================

El agente debe identificar:

- módulos afectados
- archivos a modificar
- archivos nuevos
- tablas involucradas
- dependencias
- riesgos

NO generar código en esta etapa.

==================================================
4. IMPLEMENTACIÓN
==================================================

- cambios mínimos y seguros
- respetar arquitectura
- no duplicar lógica existente

==================================================
5. VALIDACIÓN
==================================================

Validar:

- `php -l`
- bootstrap no roto
- SQL solo en repository

Desde Fase 25:

- `php scripts/php-lint.php --all`
- `php scripts/structure-check.php`
- `php scripts/technical-checklist.php --task=<task>.md`

==================================================
6. CORRECCIONES (SI APLICA)
==================================================

- fixes mínimos
- no ampliar alcance

==================================================
7. CIERRE
==================================================

Usar:

- ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE

==================================================
REGLA CLAVE
==================================================

Nunca implementar sin análisis previo.