MODULE DEPENDENCY MAP — SUPER MECHANIC

Este documento define las dependencias permitidas entre módulos
del plugin Super Mechanic.

Su objetivo es evitar:

- dependencias circulares
- acoplamientos excesivos
- acceso directo entre módulos incorrectos

==================================================
REGLA GENERAL
==================================================

Las dependencias deben seguir dirección descendente
desde módulos de dominio hacia módulos de soporte.

Ejemplo de jerarquía:

Core
↓
Domain modules
↓
Support modules
↓
Helpers

Nunca invertir esta dirección.

==================================================
NIVELES DE MÓDULOS
==================================================

Nivel 1 — Core

Core / Bootstrap
Security
Settings
Roles
Capabilities

Estos módulos no deben depender de ningún módulo funcional.

--------------------------------------------------

Nivel 2 — Domain

Clients
Vehicles
Relations
Flows
Processes

Estos representan el dominio principal del sistema.

--------------------------------------------------

Nivel 3 — Operational modules

Maintenance
PreDelivery
Paperwork

Operan sobre procesos existentes.

--------------------------------------------------

Nivel 4 — Financial modules

Quotes
Invoices
Payments

Dependen de procesos.

--------------------------------------------------

Nivel 5 — Support modules

Attachments
Communication
Dashboard

Dashboard → Service → Repository

Dependen de módulos anteriores.

--------------------------------------------------

Nivel 6 — Infrastructure

Helpers
Download_Service
Document_Service
Event_Dispatcher

Módulos utilitarios usados por todo el sistema.

==================================================
DEPENDENCIAS PERMITIDAS
==================================================

Clients

sin dependencias

--------------------------------------------------

Vehicles

Clients

--------------------------------------------------

Relations

Clients
Vehicles

--------------------------------------------------

Flows

sin dependencias

--------------------------------------------------

Processes

Clients
Vehicles
Relations
Flows

--------------------------------------------------

Maintenance

Processes
Vehicles

--------------------------------------------------

PreDelivery

Processes
Vehicles

--------------------------------------------------

Paperwork

Processes
Vehicles

--------------------------------------------------

Quotes

Processes
Clients

--------------------------------------------------

Invoices

Quotes
Processes
Clients

--------------------------------------------------

Payments

Invoices

--------------------------------------------------

Attachments

Processes
Quotes
Invoices

--------------------------------------------------

Communication

Processes
Clients

--------------------------------------------------

Dashboard

Processes
Vehicles
Quotes
Invoices
Attachments
Communication

==================================================
DEPENDENCIAS PROHIBIDAS
==================================================

Evitar dependencias inversas.

Ejemplo prohibido:

Clients → Processes

Vehicles → Invoices

Payments → Processes

Esto rompería la jerarquía.

==================================================
DEPENDENCIAS CIRCULARES
==================================================

Nunca permitir:

Module A → Module B
Module B → Module A

Esto crea dependencia circular.

==================================================
DEPENDENCIAS A HELPERS
==================================================

Todos los módulos pueden depender de:

Helpers
Download_Service
Document_Service
Event_Dispatcher

==================================================
REGLA FINAL
==================================================

Si un módulo necesita datos de otro módulo
fuera de las dependencias permitidas,
debe acceder mediante Service intermedio
o evento del sistema.