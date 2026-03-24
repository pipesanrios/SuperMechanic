# SUPER MECHANIC — QUICK CONTEXT

Plugin WordPress para talleres y concesionarios.

Arquitectura:
Controller → Service → Repository → Database

Reglas:
- SQL solo en Repository
- Lógica en Services
- Controllers integran con WordPress
- No lógica en templates

Módulo central:
Processes (motor del sistema)

Flujos:
maintenance / pre-delivery / paperwork

Servicios clave:
Process_Service
Maintenance_Service
Quote_Service
Invoice_Service

Seguridad:
- Sin file_url directo
- Uso obligatorio de Download_Service

Fuente de verdad:
ARCHITECTURE.md
CURRENT_STATE.md