WORKFLOW IA — SUPER MECHANIC

==================================================

1. INICIO DE SESIÓN
   ==================================================

Pegar:

* PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC
* CONTEXTO DEL PROYECTO
* MODO DESARROLLO SEGURO
* SCOPE GUARD

==================================================
2. DEFINICIÓN DE ALCANCE
========================

Describir claramente:

* funcionalidad / bug / fase
* objetivo exacto

==================================================
3. ANÁLISIS PREVIO
==================

Usar:

MODO DESARROLLO SEGURO

El agente debe responder con:

* módulos afectados
* archivos a modificar
* archivos nuevos
* tablas
* dependencias
* riesgos

NO generar código.

Esperar confirmación solo si la sesión lo pide expresamente.
Si el prompt maestro ordena "analizar primero y luego ejecutar directamente", continuar sin una segunda espera.

==================================================
4. IMPLEMENTACIÓN
=================

Después de confirmar el análisis:

* generar código
* respetar arquitectura
* mantener cambios mínimos

==================================================
5. VALIDACIÓN
=============

Confirmar:

* php -l sin errores
* bootstrap no roto
* no SQL fuera de repository
* no ruptura de módulos existentes

==================================================
6. AUDITORÍA (RECOMENDADO)
==========================

Usar:

AUDITORÍA DE INTEGRIDAD DEL SISTEMA

Para validar:

* arquitectura
* integridad de módulos
* coherencia documental

==================================================
7. CORRECCIONES (SI APLICA)
===========================

Aplicar fixes mínimos sin ampliar alcance.

==================================================
8. CIERRE DE FASE
=================

Usar:

ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE

==================================================
REGLA GENERAL
=============

Nunca saltar directamente a implementación sin análisis previo.
