AI DEVELOPMENT PLAYBOOK — SUPER MECHANIC

Este documento define el método oficial para desarrollar
el plugin Super Mechanic utilizando agentes de IA
(ChatGPT, Codex u otros).

El objetivo es garantizar:

- desarrollo controlado
- arquitectura consistente
- reducción de errores
- trazabilidad del trabajo realizado

==================================================
PRINCIPIOS DEL DESARROLLO CON IA
==================================================

El desarrollo asistido por IA debe seguir:

1. planificación antes de implementación
2. control de alcance
3. desarrollo por fases
4. auditorías periódicas
5. actualización de documentación

La IA nunca debe modificar el sistema sin contexto completo.

==================================================
ARCHIVOS DE CONTROL
==================================================

Los siguientes archivos controlan el comportamiento
de los agentes de IA.

CONTEXTO DEL PROYECTO

Describe arquitectura, módulos y estado actual.

--------------------------------------------------

MODO DESARROLLO SEGURO — SUPER MECHANIC

Define reglas de implementación segura.

--------------------------------------------------

SCOPE GUARD — CONTROL DE ALCANCE

Evita que la IA implemente cambios fuera
del alcance solicitado.

--------------------------------------------------

PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC

Consolida lectura inicial, análisis previo y criterio de ejecución para nuevas sesiones.

--------------------------------------------------

AUDITORÍA DE INTEGRIDAD DEL SISTEMA

Se usa para verificar arquitectura, base de datos
y dependencias entre módulos.

--------------------------------------------------

ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE

Se usa al finalizar cada fase para actualizar
la documentación técnica.

==================================================
FLUJO DE TRABAJO CON IA
==================================================

El desarrollo debe seguir este flujo.

1 iniciar chat
2 cargar contexto
3 definir tarea
4 implementar
5 auditar
6 actualizar documentación
7 cerrar fase

==================================================
1 INICIO DE CHAT
==================================================

Al iniciar un nuevo chat se deben cargar
los siguientes prompts:

CONTEXTO DEL PROYECTO

PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC
MODO DESARROLLO SEGURO — SUPER MECHANIC
SCOPE GUARD — CONTROL DE ALCANCE DE IMPLEMENTACIÓN

Esto asegura que el agente entienda:

- arquitectura
- reglas
- límites de implementación

==================================================
2 DEFINIR LA TAREA
==================================================

La tarea debe describir claramente:

- módulo afectado
- archivos involucrados
- tablas afectadas
- dependencias
- riesgos

Antes de escribir código el agente debe hacer:

ANÁLISIS PREVIO.

==================================================
3 IMPLEMENTACIÓN
==================================================

El código generado debe incluir:

1 análisis previo
2 módulos afectados
3 archivos a crear/modificar
4 tablas involucradas
5 dependencias
6 riesgos

Después:

código completo o diff claro.

==================================================
4 AUDITORÍA
==================================================

Después de implementar cambios relevantes
se debe ejecutar:

AUDITORÍA DE INTEGRIDAD DEL SISTEMA.

Esto verifica:

- arquitectura
- módulos
- base de datos
- bootstrap
- permisos
- dependencias

Chequeo técnico local mínimo recomendado desde Fase 25:

- `php scripts/php-lint.php --all`
- `php scripts/structure-check.php`
- `php scripts/technical-checklist.php --task=docs/tasks/<task-file>.md`

==================================================
5 ACTUALIZACIÓN DE DOCUMENTACIÓN
==================================================

Al cerrar una fase se debe ejecutar:

ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE.

Esto mantiene actualizados:

CURRENT_STATE.md
MODULE_REGISTRY.md
SYSTEM_MAP.md
DATABASE_MAP.md
ARCHITECTURE.md

==================================================
6 CONTROL DE FASES
==================================================

El desarrollo se divide en fases.

Ejemplo:

Fase 1
estructura base del plugin

Fase 2
clientes y vehículos

Fase 3
motor de procesos

Fase 4
mantenimiento

Fase 5
pre-delivery

Fase 6
trámites

Fase 7
cotizaciones

Fase 8
facturación

Fase 9
pagos

Fase 10
communication

Fase 11
frente documental seguro

Fase 12
reportes

Fase 13
integridad transaccional base de processes

Fase 14
validación funcional y estabilización

Fase 14B
endurecimiento mínimo de quotes y timeline

Fase 15
pagos

Fase 16
automatizaciones y eventos operativos

Fase 17
control de acceso, visibilidad y ownership

Fase 18
portal mecánico real

Fase 19
workflow operativo configurable avanzado

Fase 20
automatización documental y estados derivados

Nota:
esta numeración operativa debe contrastarse siempre con `docs/CURRENT_STATE.md` y con el código real antes de usarla como base de una nueva sesión.

==================================================
REGLAS PARA AGENTES DE IA
==================================================

Antes de escribir código el agente debe:

leer documentación en:

docs/

ARCHITECTURE.md
SYSTEM_MAP.md
MODULE_REGISTRY.md
DATABASE_MAP.md

También debe respetar:

AI_RULES.md
MODULE_DEPENDENCY_MAP.md
SECURITY_MODEL.md

==================================================
REGLA FINAL
==================================================

Si existe diferencia entre:

documentación
y
código

la fuente de verdad es siempre el código actual.

==================================================
BASE DE AUTOMATIZACIÓN LOCAL — FASE 25
==================================================

Desde Fase 25 existe una base local reusable en `scripts/` para:

- lint PHP
- chequeo estructural
- checklist técnico de cierre

Su objetivo es preparar una futura integración CI/CD sin introducir todavía pipelines externos complejos.
