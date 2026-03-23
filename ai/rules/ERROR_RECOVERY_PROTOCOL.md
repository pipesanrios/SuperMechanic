ERROR RECOVERY PROTOCOL — SUPER MECHANIC

Este archivo define el procedimiento cuando una implementación
genera errores, rompe arquitectura o produce comportamiento inesperado.

Su objetivo es diagnosticar y corregir problemas sin introducir
cambios peligrosos o refactors innecesarios.

==================================================
PRINCIPIO GENERAL
==================================================

Cuando aparece un error:

NO intentar corregir inmediatamente con código.

Primero diagnosticar.

==================================================
TIPOS DE ERROR POSIBLES
==================================================

1. error PHP
2. error de sintaxis
3. clase no encontrada
4. método inexistente
5. dependencia rota entre módulos
6. error SQL
7. error de ownership o permisos
8. error en descarga de documentos
9. error en shortcodes
10. error en bootstrap del plugin

==================================================
PROCEDIMIENTO DE DIAGNÓSTICO
==================================================

Antes de generar cualquier corrección:

1 identificar archivo afectado
2 identificar módulo afectado
3 identificar clase afectada
4 identificar método afectado
5 identificar dependencia involucrada
6 revisar si el error rompe arquitectura

Nunca proponer cambios sin diagnóstico previo.

==================================================
VERIFICACIONES INICIALES
==================================================

Antes de corregir cualquier error confirmar:

1 la clase existe
2 el archivo está cargado por el autoloader
3 el namespace coincide
4 el módulo está cargado por class-plugin.php
5 la tabla de base de datos existe
6 el método llamado realmente existe

==================================================
REGLA DE CORRECCIÓN
==================================================

La corrección debe ser:

mínima
localizada
compatible con arquitectura existente

Nunca realizar:

refactor global
reescritura completa de archivo
cambios en múltiples módulos sin justificar

==================================================
ERRORES DE ARQUITECTURA
==================================================

Si se detecta:

SQL fuera de Repository
lógica de negocio en Controller
lógica pesada en Shortcode
acceso directo entre módulos

detener corrección y reportar:

archivo afectado
regla arquitectónica violada
impacto potencial

==================================================
ERRORES DE BASE DE DATOS
==================================================

Si ocurre:

tabla inexistente
columna inexistente
query inválida

verificar primero:

DATABASE_MAP.md
schema real del plugin
migraciones existentes

Nunca crear columnas o tablas automáticamente
sin validar el schema del proyecto.

==================================================
ERRORES DE SEGURIDAD
==================================================

Verificar siempre:

current_user_can()
nonces
ownership validation

Especialmente en:

process_id
client_id
quote_id
invoice_id
attachment_id

Nunca permitir acceso a datos de otro cliente.

==================================================
ERRORES DE DOCUMENTOS
==================================================

Si el error afecta:

quotes
invoices
attachments
PDFs

confirmar que las descargas usen:

Download_Service
Document_Service

Nunca exponer file_url directo.

==================================================
FORMATO DE RESPUESTA
==================================================

Cuando se detecta un error responder con:

1 tipo de error
2 archivo afectado
3 módulo afectado
4 causa probable
5 impacto potencial
6 corrección mínima propuesta

No generar código hasta terminar diagnóstico.

==================================================
REGLA FINAL
==================================================

Si el error afecta más de 2 módulos
o rompe flujo de negocio principal:

detener implementación
reportar diagnóstico completo
esperar confirmación antes de corregir.