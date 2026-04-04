PHASE: 43D VALIDATION

QA_CONTRACT_START
{
  "phase": "43D",
  "validation_contract_id": "43D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "guard_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_execution_guardrails",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "rollback_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "rollback_controlled_execution",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "execution_safety_overview_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_execution_safety_overview",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "crm_task_service_exists",
      "type": "file_exists",
      "target": "includes/crm/class-crm-task-service.php"
    },
    {
      "id": "crm_task_repository_exists",
      "type": "file_exists",
      "target": "includes/crm/class-crm-task-repository.php"
    },
    {
      "id": "dashboard_rollback_handler_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "maybe_handle_controlled_execution_rollback_request",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "dashboard_safety_render_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_execution_safety_section",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_loads",
      "description": "dashboard carga sin errores"
    },
    {
      "id": "guardrails_block_visible",
      "description": "bloque o estado de guardrails visible"
    },
    {
      "id": "rollback_available_after_execution",
      "description": "rollback aparece solo despues de ejecucion soportada"
    },
    {
      "id": "rollback_reverts_bulk_resolve",
      "description": "rollback devuelve tasks a pending"
    },
    {
      "id": "rollback_reverts_bulk_reassign",
      "description": "rollback devuelve assigned_user_id previo"
    },
    {
      "id": "unsupported_actions_not_reversible",
      "description": "acciones no soportadas no muestran rollback"
    },
    {
      "id": "no_regression_dashboard",
      "description": "40A-43C siguen funcionando"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente"
    }
  ]
}
QA_CONTRACT_END
