PERFORMANCE STRATEGY — SUPER MECHANIC

Este documento define las reglas de rendimiento y escalabilidad
del plugin Super Mechanic.

Su objetivo es garantizar que el sistema pueda operar con miles
de clientes, vehículos, procesos, cotizaciones e invoices sin
degradación significativa del rendimiento.

==================================================
OBJETIVO DE ESCALABILIDAD
==================================================

El sistema debe poder manejar:

10 000 clientes
25 000 vehículos
50 000 procesos
100 000 eventos de timeline
50 000 documentos
25 000 cotizaciones
25 000 facturas

sin degradación severa del backend ni del Client Portal.

==================================================
PRINCIPIOS DE RENDIMIENTO
==================================================

1. SQL eficiente
2. consultas paginadas
3. uso mínimo de joins pesados
4. índices en columnas críticas
5. evitar loops N+1
6. evitar consultas repetidas
7. caching cuando sea necesario

==================================================
REGLA 1 — PAGINACIÓN OBLIGATORIA
==================================================

Nunca cargar listas completas cuando el volumen pueda crecer.

Ejemplos:

Procesos
Vehículos
Clientes
Invoices
Quotes
Attachments
Timeline

Siempre usar:

LIMIT
OFFSET

Ejemplo:

SELECT *
FROM sm_processes
ORDER BY created_at DESC
LIMIT 50
OFFSET 0

==================================================
REGLA 2 — CONSULTAS OPTIMIZADAS
==================================================

Evitar consultas que mezclen demasiadas tablas.

Ejemplo incorrecto:

Process
JOIN Vehicles
JOIN Clients
JOIN Quotes
JOIN Invoices
JOIN Payments

Estrategia correcta:

Consulta base
+
resolución por servicios específicos

Ejemplo:

Process_Service
→ obtiene procesos

Vehicle_Service
→ obtiene vehículos

Invoice_Service
→ obtiene facturas

==================================================
REGLA 3 — ÍNDICES ACTUALES Y CANDIDATOS FUTUROS
==================================================

Las siguientes columnas mezclan dos categorías:

- índices ya presentes en el schema real actual
- candidatos razonables a futuro si el volumen crece

No asumir que toda la lista ya existe en `includes/database/class-schema.php`.
Si hay diferencia entre esta guía y el schema real, manda el schema; cualquier índice faltante debe tratarse como mejora futura planificada, no como realidad ya implementada.

sm_clients

email
created_at

sm_vehicles

client_id
vin
plate

sm_processes

vehicle_id
client_id
status
flow_id
current_step_id
created_at

sm_process_step_logs

process_id
flow_step_id
created_at

==================================================
REGLA 3B — AGREGACIÓN DE VISTAS
==================================================

Si un controller cliente o admin necesita armar vistas compuestas:

- priorizar services de lectura/agregacion acotados
- no reconstruir datasets pesados dentro del controller
- no mover SQL fuera de repositories

Ejemplo alineado a Fase 26B:

Client_Dashboard_Controller
→ Client_Process_View_Service
→ Dashboard_Service / Quote_Service / Invoice_Service / Comment_Service

sm_quotes

process_id
status

sm_invoices

process_id
status
client_id
created_at

sm_payments

invoice_id
created_at

sm_attachments

process_id
created_at

==================================================
REGLA 4 — EVITAR N+1 QUERIES
==================================================

Ejemplo incorrecto:

foreach process:
    query vehicle

Esto genera cientos de consultas.

Solución:

resolver IDs primero
y luego consultar en lote.

==================================================
REGLA 5 — CACHING SELECTIVO
==================================================

Algunas consultas pueden cachearse usando:

WordPress Object Cache

Ejemplos apropiados:

configuración
flows
flow steps
settings

Ejemplo:

wp_cache_get
wp_cache_set

Nunca cachear:

procesos activos
timeline
facturación

==================================================
REGLA 6 — DESCARGA DE DOCUMENTOS
==================================================

Documentos deben descargarse mediante:

Download_Service
Document_Service

Nunca exponer:

file_url directo.

==================================================
REGLA 7 — Client Portal
==================================================

El Client Portal debe evitar cargar todo.

Ejemplo:

procesos del cliente
LIMIT 20

facturas
LIMIT 20

documentos
LIMIT 20

==================================================
REGLA 8 — TIMELINE DE PROCESOS
==================================================

sm_process_step_logs puede crecer rápidamente.

Regla:

cargar solo últimos eventos.

Ejemplo:

SELECT *
FROM sm_process_step_logs
WHERE process_id = ?
ORDER BY created_at DESC
LIMIT 30

==================================================
REGLA 9 — REPORTES FUTUROS
==================================================

Reportes pesados deben:

usar consultas agregadas
no cargar objetos completos.

Ejemplo:

COUNT
SUM
GROUP BY

==================================================
REGLA 10 — CRON Y TAREAS
==================================================

Tareas pesadas deben ejecutarse mediante:

WP Cron

Ejemplos:

notificaciones
limpieza de logs
archivado de procesos antiguos

==================================================
REGLA FINAL
==================================================

Siempre priorizar:

Repository
Service
Controller

Las optimizaciones deben mantenerse dentro
de esta arquitectura.

Evitar queries analíticas en Dashboard_Service
→ usar Report_Repository
