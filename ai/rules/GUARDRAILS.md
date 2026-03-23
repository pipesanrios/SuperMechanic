ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE

Acabamos de terminar una fase o subfase del plugin Super Mechanic.

Antes de actualizar la documentación debes:

1. revisar el código implementado en esta fase
2. comparar los cambios con la documentación existente
3. identificar archivos, clases, tablas y módulos realmente afectados
4. usar el código real del proyecto como fuente principal de verdad

- confirmar que el código implementado compila
- confirmar que no hay errores de sintaxis PHP
- confirmar que el bootstrap del plugin no se rompió

Solo después actualizar la documentación.

Debes actualizar los archivos de documentación del proyecto para reflejar el estado real actual del sistema.

==================================================
ARCHIVOS A ACTUALIZAR
==================================================

/ARCHITECTURE.md
/docs/FINAL_ARCHITECTURE_MAP.md
/docs/SYSTEM_MAP.md
/docs/CURRENT_STATE.md
/docs/MODULE_REGISTRY.md
/docs/DATABASE_MAP.md
/docs/DEV_GUIDE.md solo si cambiaron reglas de desarrollo, convenciones o criterios de implementación
/docs/tasks/<task-file-de-la-fase>.md (si existe)
/readme.txt solo si cambió versión, changelog o descripción funcional
/AGENTS.md solo si cambiaron reglas operativas de trabajo con IA

==================================================
TAREAS
==================================================

1. En ARCHITECTURE.md
- agregar la nueva entrada en la bitácora de fases
- registrar archivos creados o modificados
- registrar tablas nuevas o modificadas
- registrar clases nuevas
- registrar integraciones realizadas
- actualizar el estado actual del proyecto si cambió

2. En FINAL_ARCHITECTURE_MAP.md
- actualizar el estado de los módulos modificados
- ajustar dependencias si cambió algo
- actualizar roadmap técnico si aplica

3. En SYSTEM_MAP.md
- actualizar módulos activos
- actualizar clases clave
- actualizar tablas por módulo
- actualizar shortcodes o menús si se agregaron
- actualizar riesgos arquitectónicos si aparecieron

4. En CURRENT_STATE.md
Actualizar:
- fase actual completada
- estado general del sistema
- módulos activos
- módulos parciales
- últimos cambios técnicos
- riesgos actuales
- pendientes inmediatos

5. En MODULE_REGISTRY.md
- registrar módulos nuevos o modificados
- actualizar clases principales por módulo
- actualizar dependencias entre módulos
- actualizar estado del módulo: implementado / parcial / pendiente

6. En DATABASE_MAP.md
- registrar tablas nuevas o modificadas
- actualizar relaciones entre tablas si cambió algo
- actualizar reglas de integridad si aplica
- actualizar tablas críticas si aplica
- corregir nombres de tablas si se detectó desalineación con el código real

7. En DEV_GUIDE.md
- actualizar solo si cambiaron reglas de arquitectura, seguridad, flujo de trabajo o convenciones del proyecto

8. En el task file de la fase
- actualizar estado: pendiente / en progreso / completado / parcial / bloqueado
- registrar notas técnicas finales
- registrar desviaciones respecto al alcance original si existieron

9. En readme.txt
- actualizar solo si hubo cambio de versión, changelog o alcance funcional visible del plugin

10. En AGENTS.md
- actualizar solo si cambiaron reglas de uso de ChatGPT / Codex dentro del proyecto

==================================================
RESUMEN TÉCNICO DE LA FASE
==================================================

Generar también un resumen técnico con:

- archivos creados o modificados
- clases implementadas
- tablas creadas o modificadas
- métodos principales agregados
- hooks o shortcodes registrados
- cambios en class-plugin.php
- integración con módulos existentes
- mejoras o tareas pendientes

==================================================
REGLAS
==================================================

- no duplicar información
- no borrar historial útil
- registrar solo cambios reales del sistema
- no reescribir archivos completos si no es necesario
- mantener coherencia con el código real del proyecto
- usar siempre la documentación existente como referencia
- si hay conflicto entre documentación y código, priorizar el código real y corregir la documentación

==================================================
FORMATO DE RESPUESTA
==================================================

Responder con:

1. Archivos de documentación actualizados
2. Bloques modificados de cada archivo
3. Estado final del task file de la fase, si aplica
4. Resumen técnico de la fase


==================================================
REGLA CRÍTICA — ARQUITECTURA ACTIVA
==================================================

La arquitectura activa del sistema vive en:

includes/*

La carpeta:

includes/modules/*

es legacy.

Prohibido:
- crear código nuevo ahí
- reutilizar clases de ese árbol
- mezclar ambas arquitecturas

Cualquier implementación debe usar exclusivamente includes/*