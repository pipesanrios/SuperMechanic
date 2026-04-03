PHASE: 42B VALIDATION

QA_CONTRACT_START
{
  "phase": "42B",
  "validation_contract_id": "42B-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "workload_service_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "reassignment_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "execute_operational_reassignment",
      "file": "includes/dashboard/class-workload-service.php"
    },
    {
      "id": "crm_task_service_exists",
      "type": "file_exists",
      "target": "includes/crm/class-crm-task-service.php"
    },
    {
      "id": "crm_task_service_reassign_method_exists",
      "type": "method_exists",
      "class": "Crm_Task_Service",
      "method": "reassign_task",
      "file": "includes/crm/class-crm-task-service.php"
    },
    {
      "id": "crm_task_repository_reassign_method_exists",
      "type": "method_exists",
      "class": "Crm_Task_Repository",
      "method": "reassign_task",
      "file": "includes/crm/class-crm-task-repository.php"
    },
    {
      "id": "dashboard_reassign_handler_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "maybe_handle_operational_reassignment_request",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_dashboard_loads",
      "description": "Dashboard carga sin errores."
    },
    {
      "id": "runtime_reassignment_action_visible",
      "description": "Accion Reassign visible donde la propuesta es ejecutable."
    },
    {
      "id": "runtime_reassignment_executes",
      "description": "Reasignacion real de CRM task se ejecuta correctamente."
    },
    {
      "id": "runtime_ownership_and_business_validation",
      "description": "Validaciones de usuario, business_id y entidad funcionan correctamente."
    },
    {
      "id": "runtime_no_regression_dashboard",
      "description": "40A-42A siguen funcionando."
    },
    {
      "id": "runtime_no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue consistente."
    }
  ]
}
QA_CONTRACT_END
