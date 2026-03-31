PHASE: 40B VALIDATION

==================================================
AUTOMATED CHECKS
==================================================

- php_lint → PASS
- file_exists:
  - class-workload-service.php
- class_exists:
  - Workload_Service
- method_exists:
  - get_user_workload

==================================================
MANUAL CHECKS
==================================================

- dashboard muestra sección "Mi trabajo"
- muestra:
  - tareas
  - alertas
  - procesos
  - citas

- clasificación correcta:
  - overdue → CRITICAL
  - follow_up → WARNING

- orden correcto:
  - prioridad
  - fecha

- links funcionales

- no rompe:
  - CRM
  - calendar
  - tasks

==================================================
ACCEPTANCE CRITERIA VALIDATION
==================================================

- agregación correcta
- priorización correcta
- UX usable