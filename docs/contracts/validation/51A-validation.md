QA_CONTRACT_START
PHASE: 51A
TYPE: validation

AUTOMATED_CHECKS:
- id: php_lint
  type: php_lint
  target: all

- id: license_installer_exists
  type: file_exists
  target: includes/licensing/class-license-installer.php

- id: license_repository_exists
  type: file_exists
  target: includes/licensing/class-license-repository.php

- id: license_service_exists
  type: file_exists
  target: includes/licensing/class-license-service.php

- id: license_admin_controller_exists
  type: file_exists
  target: includes/admin/class-license-admin-controller.php

MANUAL_CHECKS:
- id: license_page_visible
  description: página License visible en admin
  status: NOT_RUN

- id: license_activate_works
  description: activación local de licencia funciona
  status: NOT_RUN

- id: domain_resolves_correctly
  description: dominio actual se detecta correctamente
  status: NOT_RUN

- id: license_status_visible
  description: estado de licencia visible y claro
  status: NOT_RUN

- id: no_regression_dashboard
  description: dashboard sigue estable
  status: NOT_RUN

ACCEPTANCE_CRITERIA:
- licensing_base_exists
- admin_ui_operational
- local_activation_operational
- stable_system

QA_CONTRACT_END