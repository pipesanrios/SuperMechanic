GUARDRAILS — SUPER MECHANIC

Reglas de seguridad y alcance para cualquier agente IA.

==================================================
1) FUENTE DE VERDAD
==================================================

Prioridad:
1. codigo real (`includes/*`)
2. schema real (`includes/database/class-schema.php`)
3. docs tecnicos
4. contextos IA
5. prompts

Si hay conflicto: manda el codigo y corrige docs/contexto.

==================================================
2) ARQUITECTURA INNEGOCIABLE
==================================================

- usar solo `includes/*` para desarrollo activo
- `includes/modules/*` es legacy (no extender)
- patron: `Controller -> Service -> Repository -> Database`
- SQL solo en repositories
- `$wpdb` fuera de repositories/database infra: prohibido

==================================================
3) SEGURIDAD INNEGOCIABLE
==================================================

- validar `current_user_can()` y nonces en acciones sensibles
- sanitizar input y escapar output
- validar ownership y `business_id` en recursos tenant-aware
- no exponer secretos (API keys crudas, tokens, client_secret)
- no exponer `file_url`/`file_path` directos en cliente
- usar `Document_Service` + `Download_Service` para descargas

==================================================
4) CONTROL DE ALCANCE
==================================================

- no agregar features no pedidas
- no refactor global salvo instruccion explicita
- cambios minimos, localizados y verificables
- no cambiar schema sin requerimiento expreso
- no tocar bootstrap sin necesidad justificada

==================================================
5) CONTINUIDAD DOCUMENTAL
==================================================

Al cerrar cambios reales:
- actualizar solo docs desalineadas
- no mezclar historia extensa con estado actual
- mantener continuidad del roadmap desde Fase 0

Docs base:
- `ARCHITECTURE.md`
- `docs/CURRENT_STATE.md`
- `docs/PLUGIN_ROADMAP.md`
- `docs/SYSTEM_MAP.md`
- `docs/MODULE_REGISTRY.md`
- `docs/DATABASE_MAP.md`
- `.vscode/AI_CONTEXT.md`

==================================================
6) SALIDA DE AUDITORIA
==================================================

Toda auditoria debe declarar:
- que se confirmo en codigo
- que no se pudo confirmar
- desalineaciones detectadas
- correcciones hechas
- deuda tecnica pendiente
