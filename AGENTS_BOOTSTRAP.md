# AGENTS_BOOTSTRAP.md
Super Mechanic - START HERE oficial para cualquier IA

Objetivo:
- definir el entrypoint unico
- obligar un orden de lectura estable
- evitar implementaciones sin contexto

Si eres una IA nueva en este repositorio, este archivo es el inicio obligatorio.

==================================================
ENTRYPOINT UNICO OFICIAL
==================================================

`AGENTS_BOOTSTRAP.md` = START HERE oficial.

Ninguna IA debe tocar codigo, schema o wiring antes de completar la lectura minima.

==================================================
ORDEN OFICIAL DE LECTURA (OBLIGATORIO)
==================================================

1. `AGENTS_BOOTSTRAP.md`
2. `AGENTS.md`
3. `.vscode/AI_CONTEXT.md`
4. `docs/CURRENT_STATE.md`
5. `docs/PROJECT_TRANSFER_CONTEXT.md`
6. `docs/PLUGIN_ROADMAP.md`
7. `ARCHITECTURE.md`
8. `docs/DATABASE_MAP.md`
9. `docs/MODULE_REGISTRY.md`
10. `docs/SYSTEM_MAP.md`
11. `docs/TEST_SCENARIOS.md`

Lectura adicional segun necesidad:
- `docs/KNOWN_TRAPS.md`
- `ai/rules/*`
- `docs/tasks/*`
- `ai/prompts/*`

==================================================
FUENTE DE VERDAD Y CONFLICTOS
==================================================

Prioridad obligatoria:
1. Codigo real (`includes/*`, bootstrap, schema)
2. Documentacion tecnica activa
3. Contextos AI
4. Prompts

Si hay conflicto: manda el codigo real y actualiza docs afectadas.

==================================================
QUE NO HACER ANTES DE LEER CONTEXTO
==================================================

No hacer antes de completar la lectura obligatoria:
- implementar features
- editar schema
- mover wiring de bootstrap
- refactorizar modulos sensibles
- cerrar fases documentalmente

==================================================
ARRANQUE DE SESION RECOMENDADO
==================================================

1. Confirmar fase/subfase objetivo en `docs/PLUGIN_ROADMAP.md`.
2. Verificar estado real en `docs/CURRENT_STATE.md`.
3. Verificar riesgos/trampas en `docs/KNOWN_TRAPS.md`.
4. Ejecutar analisis previo (modulos, archivos, riesgos, validacion).
5. Implementar cambios minimos dentro de alcance.
6. Validar (`php scripts/php-lint.php --all` + runtime manual cuando aplique).
7. Cerrar docs sin dejar desalineaciones.

==================================================
CRITERIO DE CONTINUIDAD DE FASES
==================================================

Para decidir siguiente continuidad:
- usar `docs/PLUGIN_ROADMAP.md` como referencia oficial
- mantener secuencia historica completa desde Fase 0
- respetar subfases A/B/C existentes
- no inventar renumeraciones ad hoc
- si una fase esta en PARCIAL, no marcar COMPLETA sin validacion requerida

==================================================
REGLA FINAL
==================================================

Si no leiste el orden obligatorio, no estas autorizado a tocar codigo.
