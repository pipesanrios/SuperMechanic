PHASE: 44E VALIDATION

QA_CONTRACT_START
{
  "phase": "44E",
  "validation_contract_id": "44E-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "dashboard_controller_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-admin-dashboard-controller.php"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css/admin.css"
    },
    {
      "id": "rules_notice_renderer_exists",
      "type": "method_exists",
      "class": "Admin_Dashboard_Controller",
      "method": "render_operational_rules_update_notice",
      "file": "includes/dashboard/class-admin-dashboard-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "success_notice_visible",
      "description": "notice de exito visible: Rule updated successfully"
    },
    {
      "id": "error_notice_visible",
      "description": "notice de error visible: Invalid rule configuration"
    },
    {
      "id": "empty_states_clear",
      "description": "estados vacios claros cuando no hay reglas custom"
    },
    {
      "id": "consistent_with_dashboard",
      "description": "mensajes consistentes con el resto del dashboard"
    }
  ]
}
QA_CONTRACT_END
