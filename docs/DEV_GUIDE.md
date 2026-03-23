# DEV GUIDE

## 1. Filosofia del proyecto

Super Mechanic debe evolucionar como un plugin WordPress modular, estable y mantenible. La prioridad no es solo agregar funcionalidades, sino preservar la arquitectura real del sistema, evitar regresiones y mantener continuidad tecnica entre sesiones.

Principios obligatorios:

- la arquitectura real del plugin manda sobre cualquier propuesta teorica
- cada cambio debe respetar los modulos ya activos
- el detalle del proceso es el hub actual del dominio operativo
- no se debe introducir complejidad innecesaria
- no se debe duplicar logica si ya existe un service o repository que resuelve el problema
- toda fase nueva debe dejar trazabilidad tecnica y documental

---

## 2. Arquitectura obligatoria

El plugin usa arquitectura modular basada en estas capas:

- Repository
- Service
- Controller
- REST Controller
- Admin UI
- Shortcodes

### Regla crítica de arquitectura activa

- nunca implementar codigo nuevo en `includes/modules/*`
- ese arbol es legacy y no forma parte del sistema activo
- todo desarrollo debe vivir exclusivamente en `includes/*`

---

### Repository

Reglas:

- es el unico lugar donde se permite SQL
- debe usar siempre `$wpdb`
- debe usar siempre consultas preparadas cuando haya parametros dinamicos
- debe concentrar inserts, updates, deletes y selects del modulo
- no debe contener renderizado ni reglas de UI

---

### Service

Reglas:

- contiene la logica de negocio
- centraliza validaciones funcionales
- calcula totales, balances, estados y transiciones
- aplica reglas del sistema
- coordina repositories cuando un flujo requiere multiples operaciones
- no debe renderizar HTML admin ni frontend

---

### Controller

Reglas:

- controla renderizado de UI admin
- valida permisos antes de ejecutar acciones
- usa nonces para operaciones sensibles
- interactua con services, no con SQL directo
- no debe duplicar reglas de negocio que ya existan en services

---

### Shortcodes

Reglas:

- son la salida frontend para clientes
- deben validar ownership y permisos del usuario actual
- deben escapar siempre la salida
- deben usar services existentes en lugar de implementar logica paralela

---

### REST Controller

Reglas:

- expone endpoints solo cuando el modulo ya tenga service estable
- valida permisos, nonces y contexto segun corresponda
- delega la logica al service del modulo
- no debe convertirse en capa de negocio ni de persistencia

---

### Admin UI

Reglas:

- debe apoyarse en controllers y services existentes
- debe mantener consistencia con el menu admin actual y con el detalle del proceso
- no debe introducir pantallas que rompan el flujo operativo vigente sin documentarlo

---

## 3. Reglas de modificacion de codigo

Antes de modificar codigo siempre:

1. identificar modulos afectados
2. revisar dependencias entre modulos
3. revisar tablas utilizadas
4. revisar services existentes
5. revisar si el cambio impacta el detalle del proceso

Reglas obligatorias:

- nunca poner SQL dentro de controllers
- nunca duplicar logica entre services
- nunca reescribir modulos completos innecesariamente
- nunca modificar tablas existentes sin revisar dependencias
- nunca romper compatibilidad con modulos existentes
- siempre extender primero la arquitectura actual antes de crear una paralela
- siempre reutilizar classes, services y repositories ya presentes cuando sea viable

Evitar:

- duplicar logica
- introducir acoplamientos innecesarios
- romper flujos existentes
- mover responsabilidades entre capas sin una razon clara
- crear nuevas rutas de negocio que bypassen el service oficial del modulo

---

## 4. Reglas de base de datos

Reglas obligatorias:

- toda persistencia va en repositories
- usar siempre `$wpdb`
- usar siempre consultas preparadas cuando existan variables dinamicas
- respetar el prefijo `{$wpdb->prefix}sm_`
- no alterar columnas, tipos o relaciones sin revisar impacto en modulos dependientes

---

### Regla de integridad transaccional

Cuando una operacion implique multiples escrituras relacionadas
(ejemplo: proceso + step logs):

- debe evaluarse el uso de transacciones para garantizar consistencia
- no introducir soluciones parciales que simulen atomicidad

Deuda tecnica identificada en:

- `Process_Service`
- `Process_Repository`
- `Invoice_Transaction_Repository`

---

Si se agrega una tabla nueva, documentarla en:

- `ARCHITECTURE.md`
- `docs/SYSTEM_MAP.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`
- `docs/CURRENT_STATE.md`

---

Antes de cambiar una tabla existente, revisar al menos:

- repositories del modulo afectado
- services que calculan estados o totales
- controllers admin relacionados
- shortcodes que leen esos datos
- documentacion tecnica vigente

---

## 5. Reglas de seguridad

Siempre:

- sanitizar datos de entrada
- escapar salidas
- validar permisos
- usar nonces
- usar consultas preparadas

Reglas obligatorias:

- usar `sanitize_text_field`, `sanitize_email` y equivalentes
- usar `esc_html`, `esc_attr`, `esc_url`
- usar `wp_verify_nonce` o `check_admin_referer`
- usar `current_user_can`
- validar ownership en frontend cliente
- no confiar en `$_GET`, `$_POST`, `$_REQUEST`
- no exponer informacion de otros clientes
- no asumir seguridad en REST futura

---

## 6. Reglas de dependencias entre modulos

Dependencias reales:

- Clients y Vehicles → Relations y Processes
- Flows → Processes
- Processes → Maintenance, Pre-Delivery, Paperwork, Quotes, Invoices
- Maintenance → Quotes
- Quotes → Invoices
- Invoices → Payments

Reglas obligatorias:

- no invertir dependencias
- no crear dependencias circulares
- consumir modulos via services o repositories

Ejemplo correcto:

Dashboard → Service → Repository

Ejemplo incorrecto:

Dashboard → SQL directo

---

## 7. Reglas de UI Admin

- UI en controllers admin
- respetar menu existente
- no romper integracion con procesos
- validar permisos + nonce
- no mezclar logica compleja en render

Especial cuidado con:

- `Process_Admin_Controller`
- `Quote_Admin_Controller`
- `Invoice_Admin_Controller`
- `Dashboard_Service`

---

## 8. Reglas de frontend cliente

- validar ownership siempre
- usar services oficiales
- no exponer datos de otros clientes
- escapar toda salida
- validar estados antes de acciones

---

## 9. Convenciones de naming

- prefijo: `sm_`
- namespace: `Super_Mechanic`
- clases: `class-*.php`
- ubicacion: `/includes`
- tablas: `{$wpdb->prefix}sm_*`

---

## 10. Procedimiento para nuevas fases

Antes:

1. revisar arquitectura
2. revisar estado actual
3. identificar modulos
4. validar reutilizacion

Durante:

- respetar capas
- evitar reescrituras
- validar impacto

Despues:

- actualizar docs
- registrar solo cambios reales

---

## 11. Checklist antes de modificar codigo

- revisar docs clave
- identificar impacto
- validar services y repositories
- confirmar no crear arquitectura paralela

---

## 12. Checklist antes de cerrar fase

- codigo correcto
- seguridad correcta
- sin duplicaciones
- compatibilidad intacta
- documentacion actualizada

---

## Integracion con documentacion

Leer junto con:

- `ARCHITECTURE.md`
- `docs/SYSTEM_MAP.md`
- `docs/CURRENT_STATE.md`
- `docs/FINAL_ARCHITECTURE_MAP.md`

Regla final:

Si hay conflicto → prevalece el codigo real