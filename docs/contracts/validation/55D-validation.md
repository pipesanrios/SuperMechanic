QA_CONTRACT_START
{
  "phase": "55D",
  "validation_contract_id": "55D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "connector_service_exists",
      "type": "file_exists",
      "target": "includes/integrations/connectors/class-connector-service.php"
    },
    {
      "id": "connectors_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-connectors-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "connectors_page_visible",
      "description": "pagina Connectors visible en admin"
    },
    {
      "id": "connector_crud_works",
      "description": "create/update/delete/activate funciona"
    },
    {
      "id": "connector_test_dispatch_works",
      "description": "test dispatch funciona"
    },
    {
      "id": "event_dispatch_to_connectors_works",
      "description": "eventos de 55C llegan a conectores configurados"
    },
    {
      "id": "no_regression_system",
      "description": "sistema sigue estable"
    }
  ],
  "acceptance_criteria": [
    "connector_layer_exists",
    "connector_dispatch_operational",
    "event_integration_operational",
    "stable_system"
  ]
}
QA_CONTRACT_END
