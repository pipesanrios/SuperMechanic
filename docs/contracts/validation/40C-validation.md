PHASE: 40C VALIDATION

QA_CONTRACT_START
{
  "phase": "40C",
  "validation_contract_id": "40C-validation-v1",
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
      "id": "operational_metrics_method_exists",
      "type": "method_exists",
      "class": "Workload_Service",
      "method": "get_operational_metrics",
      "file": "includes/dashboard/class-workload-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "runtime_tasks_sla_coherent",
      "description": "Metricas SLA de tareas coherentes con datos reales."
    },
    {
      "id": "runtime_processes_sla_coherent",
      "description": "Metricas SLA de procesos coherentes con datos reales."
    },
    {
      "id": "runtime_alerts_counts_aligned_with_pipeline",
      "description": "Conteos de alerts critical/warning alineados con politica de CRM Pipeline."
    },
    {
      "id": "runtime_appointments_sla_coherent",
      "description": "Metricas de citas coherentes con estado operativo real."
    }
  ]
}
QA_CONTRACT_END
