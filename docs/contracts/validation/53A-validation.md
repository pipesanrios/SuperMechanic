QA_CONTRACT_START
PHASE: 53A
TYPE: validation

AUTOMATED_CHECKS:
- id: php_lint
  type: php_lint
  target: all

- id: dashboard_service_exists
  type: file_exists
  target: includes/dashboard/class-dashboard-service.php

- id: dashboard_repository_exists
  type: file_exists
  target: includes/dashboard/class-dashboard-repository.php

- id: dashboard_controller_exists
  type: file_exists
  target: includes/admin/class-dashboard-admin-controller.php

MANUAL_CHECKS:
- id: dashboard_visible
  description: dashboard aparece en admin
  status: NOT_RUN

- id: metrics_correct
  description: métricas reflejan datos reales
  status: NOT_RUN

- id: recent_activity_visible
  description: actividad reciente visible
  status: NOT_RUN

- id: no_regression_admin
  description: admin sigue estable
  status: NOT_RUN

- id: performance_ok
  description: carga rápida
  status: NOT_RUN

ACCEPTANCE_CRITERIA:
- dashboard_exists
- metrics_working
- ui_operational
- stable_system

QA_CONTRACT_END