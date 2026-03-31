# KNOWN_TRAPS - SUPER MECHANIC

Trampas conocidas para evitar regresiones en continuidad multi-IA.

==================================================
1) MODULOS LEGACY Y PLACEHOLDERS
==================================================

No tocar sin fase explicita:
- `includes/modules/*`
- `includes/class-rest-api.php`
- `includes/class-hooks.php`
- `includes/class-post-types.php`

Riesgo:
- mezclar arquitectura legacy con activa rompe boundaries y wiring.

==================================================
2) TENANCY (`business_id`) - RIESGO CRITICO
==================================================

Errores tipicos:
- `get_by_id` sin filtro por negocio
- joins cruzados sin `business_id`
- updates/deletes cross-tenant

Regla:
- toda operacion tenant-aware debe validar y filtrar por `business_id`.

==================================================
3) SQL FUERA DE REPOSITORY
==================================================

Error recurrente:
- meter `$wpdb` en controllers/services por rapidez.

Regla:
- SQL solo en Repository o `includes/database/*`.

==================================================
4) HOTFIX-MEM-1 (CASCADAS DE INICIALIZACION)
==================================================

Historial:
- hubo fatal memory exhausted por inicializacion circular de servicios.

Cuidado:
- evitar eager instantiation en constructores en cadena.
- preferir dependencias minimas y llamadas puntuales.

==================================================
5) I18N CARGA TEMPRANA
==================================================

Historial:
- notice `_load_textdomain_just_in_time ... too early`.

Regla:
- cargar textdomain en `init`.
- evitar traducciones evaluadas demasiado pronto en bootstrap.

==================================================
6) CRM PIPELINE VS PROCESSES
==================================================

Trampa:
- mezclar pipeline comercial con entidad operativa `sm_processes`.

Regla:
- pipeline es independiente.
- `process_id` es referencia opcional.
- conversion a proceso solo por accion explicita.

==================================================
7) PROCESS TYPE Y VEHICLE_ID
==================================================

Trampa:
- exigir vehiculo para todos los tipos.

Regla vigente:
- `maintenance` requiere `vehicle_id`
- `pre_delivery` permite sin `vehicle_id`
- `paperwork` permite sin `vehicle_id`

==================================================
8) CALENDAR (APPOINTMENT VS CRM_TASK)
==================================================

Trampas:
- tratar ambos tipos como mismo flujo de update.
- permitir drag/drop a `crm_task`.

Regla:
- feed unificado, entidades separadas.
- `eventDrop` solo para `appointment`.
- `crm_task` bloqueado/revertido.

==================================================
9) QUICK STAGE / NONCES / CONTEXTO
==================================================

Trampa:
- nonce/action mal armada produce "link caducado".

Regla:
- mantener nonce/capability correctos.
- preservar contexto activo: view_mode, search, stage, assigned_user_id, requires_attention, overdue.

==================================================
10) KANBAN (HTML + CSS)
==================================================

Trampa:
- arreglar solo CSS sin validar estructura HTML real.

Regla:
- verificar jerarquia:
  - wrapper kanban
  - columna por stage
  - contenedor de cards por columna
- luego ajustar CSS minimo.

==================================================
11) WOO SNAPSHOT
==================================================

Trampa:
- recalcular precios en tiempo real desde Woo y romper consistencia.

Regla:
- mantener snapshot comercial en quotes/invoices.
- no forzar dependencia de Woo activo para flujos manuales.

==================================================
12) SCHEDULER Y ALERTAS
==================================================

Trampas:
- recálculo agresivo sin limites.
- duplicar alertas activas por tipo/pipeline.
- actualizar siempre aunque no cambie nada.

Regla:
- lotes con limite.
- una alerta `active` por tipo/pipeline/negocio.
- mensajes deterministicos para evitar writes inutiles.

==================================================
13) CIERRES DE FASE SIN ALINEACION
==================================================

Trampa:
- marcar fase completa sin actualizar docs clave.

Regla:
- al cierre, alinear:
  - `docs/CURRENT_STATE.md`
  - `docs/PLUGIN_ROADMAP.md`
  - `docs/TEST_SCENARIOS.md`
  - `.vscode/AI_CONTEXT.md`
  - `docs/tasks/<cierre>.md`

==================================================
14) REGLA FINAL DE CONFLICTO
==================================================

Si docs/prompts/contextos contradicen el codigo real:
- manda codigo
- corregir docs afectadas
