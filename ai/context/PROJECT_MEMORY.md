# PROJECT MEMORY — SUPER MECHANIC

Memoria de decisiones vigentes para continuidad de IA.

## Decisiones vigentes

- Arquitectura activa unica: `includes/*`
- Legacy aislado: `includes/modules/*` (no extender)
- Patron obligatorio: `Controller -> Service -> Repository`
- SQL solo en repositories (y en `includes/database/*` para schema/migraciones)
- Descargas y documentos: `Document_Service` + `Download_Service`
- Tenancy activa por `business_id` + `sm_businesses`
- API publica separada de API interna

## Estado tecnico actual

- Plugin `0.1.0`
- Schema `1.15.0`
- Fase actual cerrada: `36C-2`
- API publica write minima activa:
  - cancel appointment
  - confirm appointment

## Riesgos/deuda viva

- `Process_Admin_Controller` y `Report_Service` son hotspots de complejidad
- Placeholders no activos (`class-rest-api`, `class-hooks`, `class-post-types`) pueden confundir onboarding
- Falta UX/admin dedicada para API keys y webhooks publicos
- Sin validacion runtime automatizada E2E en WordPress

## Prioridad de continuidad (siguiente bloque)

- consolidar gestion admin de API keys/webhooks publicos
- mejorar observabilidad de webhook deliveries
- formalizar pruebas runtime para 36B/36C
