# PROJECT_TRANSFER_CONTEXT - SUPER MECHANIC

Handoff largo y estable para continuidad por otra IA.
Fecha de actualizacion: 2026-03-31

==================================================
RESUMEN EJECUTIVO
==================================================

Super Mechanic es un plugin WordPress modular para operacion de talleres y concesionarios:
- clientes y vehiculos
- procesos operativos
- maintenance/predelivery/paperwork
- quotes/invoices/payments
- CRM comercial
- citas e integraciones
- reportes operativos y financieros

Arquitectura activa: `includes/*`.
Patron obligatorio: `Controller -> Service -> Repository -> Database`.

==================================================
ESTADO REAL DEL SISTEMA
==================================================

- Plugin version real: `0.1.0`
- Schema version real: `1.19.0`
- Fase activa: `Fase 39 - CRM y automatizacion comercial`
- Bloques consolidados:
  - `39B` COMPLETO (pipeline CRM)
  - `39C` COMPLETO (tareas y seguimiento)
  - `39D` COMPLETO (automatizacion basica + refinamiento UX/control)
  - `39E` COMPLETO (scheduler + alertas persistidas + consumo UI)

==================================================
QUE YA ESTA COMPLETO (ALTO IMPACTO)
==================================================

1) Pipeline CRM independiente (`sm_crm_pipeline`)
- CRUD usable
- kanban funcional
- conversion operativa controlada a proceso

2) Tareas CRM (`sm_crm_tasks`)
- create/edit/complete
- vistas pending/overdue/upcoming
- integracion con calendario

3) Scheduler y alertas persistidas (`sm_crm_alerts`)
- cron interno `sm_crm_scheduler_tick`
- recálculo por lotes y resolucion `active -> resolved`
- UI list/kanban/view consume persistido como fuente principal
- fallback runtime controlado

==================================================
QUE SIGUE (CONTINUIDAD RECOMENDADA)
==================================================

Siguiente continuidad recomendada:
- subfase posterior de `Fase 39` enfocada en explotacion operativa de alertas persistidas

Condiciones:
- sin romper tenancy
- sin romper CRUD/kanban/calendar
- sin cambios de schema no planificados

==================================================
ARQUITECTURA Y ORGANIZACION DEL REPO
==================================================

Codigo:
- runtime real en `includes/*`
- bootstrap en `super-mechanic.php` y `includes/class-plugin.php`
- schema/migraciones en `includes/database/*`

Documentacion:
- estado actual: `docs/CURRENT_STATE.md`
- roadmap: `docs/PLUGIN_ROADMAP.md`
- arquitectura: `ARCHITECTURE.md`
- db: `docs/DATABASE_MAP.md`
- inventario modulos: `docs/MODULE_REGISTRY.md`
- mapa funcional: `docs/SYSTEM_MAP.md`
- escenarios de prueba: `docs/TEST_SCENARIOS.md`
- trampas historicas: `docs/KNOWN_TRAPS.md`

Contextos/prompting:
- contexto rapido: `.vscode/AI_CONTEXT.md`
- prompt de arranque: `ai/prompts/PROMPT MASTER — INICIO DE SESIÓN SUPER MECHANIC.txt`
- cierre documental: `ai/prompts/ACTUALIZACIÓN DE DOCUMENTACIÓN Y CIERRE DE FASE.txt`

==================================================
DECISIONES HISTORICAS QUE NO SE DEBEN PERDER
==================================================

- Codigo manda sobre docs en caso de conflicto.
- `includes/modules/*` es legacy, no extender.
- Tenancy por `business_id` es obligatoria.
- SQL solo en repository/database.
- Descargas seguras via `Document_Service` + `Download_Service`.
- CRM pipeline no se mezcla estructuralmente con `sm_processes`.
- En calendario, `crm_task` y `appointment` comparten vista pero no entidad.

==================================================
ERRORES HISTORICOS / MEMORIA OPERATIVA
==================================================

- Hubo fatal de memoria por cascadas de inicializacion (`HOTFIX-MEM-1`): evitar eager wiring circular.
- Hubo bug de i18n por carga temprana de textdomain: mantener carga en `init`.
- Hubo regresiones visuales en kanban: validar siempre estructura HTML + CSS real.
- Hubo bugs de nonce en quick stage: preservar action/nonce/capability/contexto.

==================================================
METODO REPLICABLE PARA OTROS PROYECTOS
==================================================

Modelo recomendado:
1. `AGENTS_BOOTSTRAP.md` (entrypoint)
2. `AGENTS.md` (reglas duras)
3. `CURRENT_STATE` (estado vivo)
4. `ROADMAP` (continuidad)
5. `TRANSFER_CONTEXT` (handoff largo)
6. `KNOWN_TRAPS` (fallos recurrentes)
7. `TEST_SCENARIOS` (regresion funcional)
8. prompt master como director de lectura/ejecucion

Mantenerlo practico: poco ruido, mucha accion verificable.
