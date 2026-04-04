PHASE: 47C VALIDATION

QA_CONTRACT_START
{
  "phase": "47C",
  "validation_contract_id": "47C-validation-v1",
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
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "actions_prioritized",
      "description": "acciones aparecen ordenadas por prioridad util"
    },
    {
      "id": "suggestions_prioritized",
      "description": "sugerencias aparecen ordenadas por prioridad util"
    },
    {
      "id": "critical_first",
      "description": "critical aparece antes que warning/normal cuando corresponde"
    },
    {
      "id": "executable_first",
      "description": "items ejecutables relevantes aparecen antes"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    },
    {
      "id": "no_regression_crm_pipeline",
      "description": "CRM Pipeline sigue estable"
    }
  ]
}
QA_CONTRACT_END
