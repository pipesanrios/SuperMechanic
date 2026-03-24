# SMOKE TEST — SUPER MECHANIC

## Objetivo

Validar rápidamente el funcionamiento real del sistema antes de correcciones.

Tiempo estimado: 10–20 minutos

---

## Preparación

- Usuario admin disponible
- Usuario mechanic disponible
- Usuario client disponible
- Datos de prueba cargados:
  - al menos 2 clientes
  - al menos 2 vehículos
  - al menos 2 procesos
  - al menos 1 invoice
  - al menos 1 attachment

---

# 1. DASHBOARD ADMIN

## Validaciones

- [ ] El dashboard carga sin errores
- [ ] No hay warnings visibles
- [ ] KPIs muestran datos reales
- [ ] KPIs son clicables (si aplica)
- [ ] Cada KPI lleva a la pantalla correcta

## Fallos comunes a detectar

- KPI no clicable
- KPI redirige mal
- datos en 0 incorrectamente

---

# 2. CLIENTES

## Listado

- [ ] Lista carga correctamente
- [ ] Filtros funcionan
- [ ] Botones (Editar / Ver) funcionan

## Crear cliente

- [ ] Permite crear cliente
- [ ] Valida email obligatorio
- [ ] Valida documento obligatorio
- [ ] Valida teléfono obligatorio

## Vista detalle

- [ ] Muestra datos completos
- [ ] Muestra vehículos asociados
- [ ] Muestra procesos relacionados
- [ ] No rompe layout

---

# 3. VEHÍCULOS

## Listado

- [ ] Lista carga correctamente
- [ ] Filtros funcionan
- [ ] Botón Ver funciona

## Crear vehículo

- [ ] Requiere cliente
- [ ] Permite placa o VIN
- [ ] Valida VIN si no hay placa

## Vista detalle

- [ ] Muestra cliente
- [ ] Muestra procesos
- [ ] No rompe layout

---

# 4. PROCESOS

## Listado

- [ ] Lista carga
- [ ] Estados visibles correctamente
- [ ] Filtros funcionan

## Detalle proceso

- [ ] Carga sin errores
- [ ] Muestra timeline
- [ ] Muestra comentarios

## Acciones

- [ ] Agregar comentario funciona
- [ ] Editar comentario (si existe)
- [ ] Eliminar comentario (si existe)
- [ ] Adjuntos tienen acción útil (ver / abrir)

## Fallos comunes

- botones sin acción
- timeline inconsistente
- adjuntos no abren

---

# 5. INVOICES

## Listado

- [ ] Lista carga correctamente
- [ ] Botón Abrir funciona

## Detalle

- [ ] Se puede visualizar invoice
- [ ] Totales visibles
- [ ] Botón Descargar funciona (si existe)

## Fallos comunes

- botón abrir no funciona
- descarga rota

---

# 6. ADJUNTOS / DOCUMENTOS

## Validaciones

- [ ] Se pueden abrir archivos
- [ ] No hay accesos rotos
- [ ] No hay enlaces vacíos

## Seguridad básica

- [ ] No expone rutas inseguras visibles
- [ ] No permite acceso sin contexto (validación básica)

---

# 7. PORTAL CLIENTE

(Ingresar como usuario client)

## Validaciones

- [ ] Accede sin errores
- [ ] Ve sus vehículos
- [ ] Ve sus procesos
- [ ] Ve documentos visibles
- [ ] Ve invoices

## Fallos comunes

- ve datos de otro cliente
- no ve datos propios
- navegación rota

---

# 8. PORTAL MECÁNICO

(Ingresar como mechanic)

## Validaciones

- [ ] Dashboard carga
- [ ] Ve procesos asignados
- [ ] KPIs tienen sentido

## Fallos comunes

- naming inconsistente
- métricas incorrectas
- accesos rotos

---

# 9. SHORTCODES PANEL

## Validaciones

- [ ] Panel carga
- [ ] Botón copiar funciona

---

# 10. DESCARGAS / PAYMENT RECEIPT

## Validaciones

- [ ] Archivos descargan correctamente
- [ ] No hay errores 404
- [ ] No abre rutas inválidas

---

# RESULTADO FINAL

## Estado general

- [ ] usable
- [ ] parcialmente usable
- [ ] no usable

---

## Observaciones rápidas

(Escribe aquí problemas detectados)

---

## Issues detectados

(Trasladar al QA_REPORT.md)

- [ID-001]
- [ID-002]
- [ID-003]