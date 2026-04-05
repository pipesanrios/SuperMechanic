QA_CONTRACT_START
PHASE: 49A
TYPE: validation

AUTOMATED_CHECKS:
- id: php_lint
  type: php_lint
  target: all

- id: membership_installer_exists
  type: file_exists
  target: includes/users/class-business-membership-installer.php

- id: membership_repository_exists
  type: file_exists
  target: includes/users/class-business-membership-repository.php

- id: membership_service_exists
  type: file_exists
  target: includes/users/class-business-membership-service.php

MANUAL_CHECKS:
- id: membership_table_created
  description: tabla de membresías creada correctamente
  status: NOT_RUN

- id: membership_queries_work
  description: consultas básicas de membresía funcionan
  status: NOT_RUN

- id: primary_membership_resolves
  description: membresía principal se resuelve correctamente
  status: NOT_RUN

- id: no_regression_dashboard
  description: dashboard sigue estable
  status: NOT_RUN

- id: no_regression_roles
  description: roles/capabilities actuales siguen funcionando
  status: NOT_RUN

ACCEPTANCE_CRITERIA:
- membership_model_exists
- repository_and_service_work
- stable_system
- no_logic_regression

QA_CONTRACT_END