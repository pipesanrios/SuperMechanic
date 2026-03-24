# 2026-03 — SUBFASES 1–3 PREVIA FASE 27

## Nombre del bloque

Subfases 1–3 previa Fase 27

## Objetivo

Ejecutar en un solo bloque:

- Subfase 1: auditoría funcional real
- Subfase 2: corrección crítica de flujo
- Subfase 3: corrección UX estructural mínima

Este bloque existe para estabilizar el producto antes de abrir la capa API de Fase 27.

---

## Alcance

### Incluido

- auditoría funcional basada en smoke test manual
- corrección de botones o acciones que no funcionan
- corrección de enlaces o vistas que no abren
- navegación útil en dashboard admin
- vistas “Ver” en clientes y vehículos
- mejoras mínimas de UX para dejar el sistema usable
- corrección de naming inconsistente en portal / panel mecánico
- corrección del botón copiar en panel de shortcodes
- corrección de rutas/documentos ya implementados si fallan en uso real

### Fuera de alcance

- API / REST
- centro financiero completo
- licencias
- calendario / citas
- SMS / reminders
- multi-store
- tenancy
- features nuevas grandes
- impuestos / descuentos completos si requieren expansión de modelo
- nuevos shortcodes que no existan todavía en runtime real

---

## Prioridades funcionales

### 1. Dashboard admin

- KPIs clicables
- bloques informativos navegables
- accesos a clientes, vehículos y procesos filtrados

### 2. Clientes

- opción Ver
- detalle completo
- vehículos vinculados
- procesos y estado
- email obligatorio
- documento obligatorio
- teléfono obligatorio
- preparar creación/vínculo de usuario WordPress si cabe limpio

### 3. Vehículos

- opción Ver
- detalle completo
- cliente
- procesos activos
- historial relacionado si ya existe base reusable
- cliente obligatorio
- VIN obligatorio si no hay placa
- VIN opcional si hay placa

### 4. Procesos

- comentarios / mensajes editables o eliminables si ya encaja limpio
- acción útil en adjuntos
- corrección de acciones sin efecto
- no expandir todavía lógica financiera compleja

### 5. Invoices

- corregir acción Abrir
- agregar Descargar si ya existe ruta segura
- no convertir esta subfase en Fase 28

### 6. Portal mecánico

- naming unificado
- KPIs corregidos si están semánticamente mal

### 7. Panel de shortcodes

- botón copiar funcional

### 8. Descargas / payment_receipt

- corregir funcionamiento real de lo ya implementado
- si algo no cabe limpio, dejarlo como deuda

---

## Reglas críticas

- no tocar schema salvo necesidad crítica y justificada
- no tocar `includes/modules/*`
- no mover SQL fuera de repository
- no romper ownership
- no romper descargas seguras
- no ampliar alcance

---

## Archivos probables

- `includes/dashboard/class-admin-dashboard-controller.php`
- `includes/clients/class-client-admin-controller.php`
- `includes/clients/class-client-list-table.php`
- `includes/vehicles/class-vehicle-admin-controller.php`
- `includes/vehicles/class-vehicle-list-table.php`
- `includes/processes/class-process-admin-controller.php`
- `includes/attachments/class-attachment-admin-controller.php`
- `includes/invoices/class-invoice-admin-controller.php`
- `includes/dashboard/class-mechanic-dashboard-controller.php`
- `includes/class-shortcode-admin-controller.php`
- `assets/js/admin.js`
- `assets/css/admin.css`

---

## Validaciones esperadas

- `php -l` en todos los PHP modificados
- bootstrap no roto
- sin cambios en `includes/modules/*`
- sin SQL fuera de repository
- scripts técnicos operativos:
  - `php scripts/php-lint.php --all`
  - `php scripts/structure-check.php`
  - `php scripts/technical-checklist.php --task=docs/tasks/2026-03-subfases-1-3-previa-fase-27.md`

---

## Estado

- Estado inicial: pendiente
- Estado final: completar al cierre

---

## Notas técnicas finales

Pendiente de completar al finalizar implementación.

---

## Deuda técnica abierta

Pendiente de completar al finalizar implementación.

---

## Resultado esperado

Producto más usable y estable antes de Fase 27, sin ampliar alcance ni introducir deuda innecesaria.