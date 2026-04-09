PHASE: 55A VALIDATION

QA_CONTRACT_START
{
  "phase": "55A",
  "validation_contract_id": "55A-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "elementor_loader_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/class-elementor-loader.php"
    },
    {
      "id": "client_dashboard_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-client-dashboard-widget.php"
    },
    {
      "id": "client_vehicles_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-client-vehicles-widget.php"
    },
    {
      "id": "client_processes_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-client-processes-widget.php"
    },
    {
      "id": "client_quotes_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-client-quotes-widget.php"
    },
    {
      "id": "client_invoices_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-client-invoices-widget.php"
    },
    {
      "id": "mechanic_dashboard_widget_exists",
      "type": "file_exists",
      "target": "includes/integrations/elementor/widgets/class-mechanic-dashboard-widget.php"
    }
  ],
  "manual_checks": [
    {
      "id": "elementor_widgets_visible",
      "description": "Widgets visibles en el panel Elementor"
    },
    {
      "id": "elementor_widgets_draggable",
      "description": "Widgets arrastrables a una página"
    },
    {
      "id": "elementor_widgets_render_real_content",
      "description": "Widgets renderizan contenido real via shortcodes"
    },
    {
      "id": "no_regression_shortcodes",
      "description": "Shortcodes existentes no presentan regresión"
    },
    {
      "id": "no_js_php_errors",
      "description": "Sin errores JS/PHP en carga o render"
    }
  ],
  "acceptance_criteria": [
    "elementor_integration_wired",
    "widgets_registered",
    "shortcode_reuse",
    "stable_runtime"
  ]
}
QA_CONTRACT_END
