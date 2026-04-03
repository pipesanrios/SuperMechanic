## 📘 Manual de Supervivencia: AI Execution Engine (Offline)
Este manual activa el flujo de trabajo de SuperMechanic sin internet, utilizando la RTX 5070 (8GB/12GB VRAM) y los 16GB de RAM de tu laptop MSI como motor principal.
## 🚀 1. Modelos en Ollama (Backend)
Verifica que estos modelos estén listos con ollama list:

* Estratega (Cerebro/PM): deepseek-coder-v2:16b (Arquitectura y lógica).
* Constructor (Músculo/Codex): qwen2.5-coder:7b (Escritura y autocompletado).

## ⌨️ 2. Atajos de Teclado (VS Code + Continue)

| Atajo | Función |
|---|---|
| Ctrl + L | Abre el chat lateral con el Estratega. |
| Ctrl + I | Edición rápida con el Constructor (escribe código sobre el archivo). |
| @ | Menciona archivos críticos (@AGENTS.md, @AI_CONTEXT.md). |
| Ctrl + Shift + Enter | Aceptar y aplicar los cambios generados al archivo. |

## 🤖 3. Comandos de Ejecución (/)
Estos comandos siguen el flujo mandatorio de tu Prompt Master:

* /engine-start: Fase 0. Ejecuta el Bootstrap. Carga @AGENTS_BOOTSTRAP.md y @AI_CONTEXT.md. Valida si existe un contrato de tarea activo.
* /fase-analisis: Fase 1. Identifica módulos afectados, riesgos técnicos y plan de validación basado en @ARCHITECTURE.md.
* /fase-validar: Fase 3 & 3.5. Actúa como QA Runner. Revisa el código contra @ai/rules/ y solicita resultados de php-lint.
* /fase-cierre: Fase 4. Alinea @PROJECT_MEMORY.md y @CURRENT_STATE.md con el código real.

## 📋 4. Protocolo Mandatorio (Reglas de Oro)

   1. Context Load First: Nunca pidas editar código antes de que la IA confirme que leyó el Bootstrap y el AI_CONTEXT.
   2. Code Wins: Si la documentación entra en conflicto con el código, el código es la verdad. Corrige la doc con /fase-cierre.
   3. Task Contract: Toda tarea no trivial debe iniciar con la validación o creación de su contrato en /docs/tasks/.

## 🔋 5. Notas de Hardware (MSI Crosshair)

* RAM Management: Usa el script IA_BOOST.bat antes de cada sesión para vaciar la VRAM de la RTX 5070.
* Ahorro de Energía: Si estás en batería, usa el modelo qwen2.5-coder:1.5b-base para el autocompletado (tabAutocompleteModel) en el YAML.
* Indexación: Al abrir un proyecto nuevo, espera a que el icono de "Index" en el chat de Continue se ponga en verde para que los archivos @ funcionen offline.

## 🚀 Comandos Rápidos de Teclado

| Atajo | Función |
|---|---|
| Ctrl + L | Abre el chat lateral con el Estratega. |
| Ctrl + I | Edición rápida con el Constructor (escribe código sobre el archivo). |
| Ctrl + Shift + Enter | Aceptar y aplicar los cambios generados al archivo. |


<START EDITING HERE>

