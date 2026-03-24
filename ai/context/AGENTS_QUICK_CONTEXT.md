AGENTS QUICK CONTEXT — SUPER MECHANIC

Este archivo es un punto de arranque rápido para agentes de IA.

NO contiene detalle completo del sistema.

==================================================
LECTURA OBLIGATORIA
==================================================

Antes de trabajar, revisar:

1. ARCHITECTURE.md
2. docs/CURRENT_STATE.md
3. .vscode/AI_CONTEXT.md

==================================================
PROYECTO
==================================================

Super Mechanic

Plugin WordPress modular para:

- talleres mecánicos
- concesionarios
- gestión de vehículos
- mantenimiento
- procesos
- cotizaciones
- facturación
- portal cliente

==================================================
ARQUITECTURA BASE
==================================================

Patrón obligatorio:

Controller → Service → Repository

Reglas clave:

- SQL solo en Repository
- usar `includes/*`
- NO usar `includes/modules/*` (legacy)
- no duplicar lógica

==================================================
REGLAS CRÍTICAS
==================================================

- validar ownership siempre
- no exponer `file_url`
- usar `Document_Service` + `Download_Service`
- mantener sanitización y escaping
- no romper UI existente ni query args
- reutilizar sistema visual `sm-*`

==================================================
FUENTE DE VERDAD
==================================================

Siempre manda:

1. código real (`includes/*`)
2. documentación técnica
3. contextos AI

==================================================
NOTA
==================================================

Este archivo NO reemplaza:

- AI_CONTEXT.md (contexto operativo)
- CURRENT_STATE.md (estado real)
- ARCHITECTURE.md (arquitectura completa)

Su función es únicamente orientar el arranque del agente.