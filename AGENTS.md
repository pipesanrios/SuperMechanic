# AGENTS.md
Super Mechanic - reglas duras para agentes IA

Este archivo es la politica central para desarrollo asistido por IA.
Si una regla de este archivo entra en conflicto con suposiciones del agente, manda este archivo y el codigo real.

==================================================
ENTRYPOINT Y LECTURA MINIMA
==================================================

- ENTRYPOINT oficial: `AGENTS_BOOTSTRAP.md`.
- Ninguna IA debe editar codigo sin completar la lectura obligatoria definida ahi.

Orden minimo obligatorio:
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

==================================================
PATRON OBLIGATORIO
==================================================

Siempre respetar:
`Controller -> Service -> Repository -> Database`

Reglas de capa:
- Controller: hooks WP, request/response, render UI. Sin logica de negocio pesada.
- Service: reglas de negocio, orquestacion, validaciones de dominio.
- Repository: acceso a datos y SQL.
- Database: schema/migraciones/seeders.

==================================================
REGLAS TECNICAS DURAS
==================================================

1) SQL y acceso a datos
- SQL solo en `Repository` o `includes/database/*`.
- `$wpdb` prohibido fuera de esas capas.

2) Schema
- No cambiar schema sin fase/subfase explicita.
- No alterar contratos existentes sin plan de migracion.

3) Tenancy
- `business_id` obligatorio en modulos tenant-aware.
- Evitar fugas cross-tenant en listados, get_by_id, updates, deletes y joins.

4) Compatibilidad
- No romper backward compatibility sin instruccion explicita.
- Mantener nonces, capabilities, query args y rutas admin funcionales.

5) Logica
- No duplicar logica existente si ya hay service/repository apto.
- Preferir extender capa existente antes de introducir rutas paralelas.

==================================================
ARQUITECTURA ACTIVA Y LEGACY
==================================================

Activa:
- `includes/*`

Legacy/no tocar salvo fase explicita:
- `includes/modules/*`
- placeholders: `includes/class-rest-api.php`, `includes/class-hooks.php`, `includes/class-post-types.php`

==================================================
MODULOS ACTIVOS REALES (REFERENCIA)
==================================================

`appointments`, `attachments`, `automation`, `businesses`, `clients`, `communication`, `crm`, `dashboard`, `database`, `flows`, `helpers`, `integrations`, `invoices`, `maintenance`, `paperwork`, `predelivery`, `processes`, `quotes`, `relations`, `reports`, `vehicles`.

==================================================
SEGURIDAD Y ARCHIVOS
==================================================

- No exponer `file_url` ni rutas directas.
- Descargas via `Document_Service` + `Download_Service`.
- Respetar ownership/capabilities y validaciones nonce.

==================================================
REGLAS DOCUMENTALES
==================================================

- Si docs y codigo difieren: manda el codigo.
- Al cerrar fase/subfase/bloque, actualizar al menos:
  - `docs/CURRENT_STATE.md`
  - `docs/PLUGIN_ROADMAP.md`
  - `docs/TEST_SCENARIOS.md`
  - `.vscode/AI_CONTEXT.md`
  - `docs/tasks/<cierre>.md` cuando aplique

==================================================
VALIDACION MINIMA OBLIGATORIA
==================================================

Antes de declarar cierre:
- `php scripts/php-lint.php --all`
- validacion runtime manual (cuando aplique) o dejar explicito que no se ejecuto
- cierre documental alineado

==================================================
FUENTE DE VERDAD
==================================================

Prioridad:
1. codigo real (`includes/*`, bootstrap, schema)
2. documentacion tecnica activa
3. contextos AI
4. prompts

==================================================
REPLICABILIDAD (PLAN B MULTI-IA)
==================================================

Metodo replicable para otros proyectos:
- bootstrap documental unico
- current state vivo
- roadmap continuo
- transfer context fuerte
- reglas operativas duras
- test scenarios
- known traps
- prompt master como director de lectura y ejecucion
