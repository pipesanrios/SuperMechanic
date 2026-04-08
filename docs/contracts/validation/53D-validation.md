QA_CONTRACT_START
{
  "phase": "53D",
  "validation_contract_id": "53D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    }
  ],
  "manual_checks": [
    {
      "id": "widgets_visible",
      "description": "widgets visibles y consistentes",
      "status": "NOT_RUN"
    },
    {
      "id": "kpis_clear",
      "description": "KPIs se leen mejor",
      "status": "NOT_RUN"
    },
    {
      "id": "process_summary_useful",
      "description": "process summary card es util",
      "status": "NOT_RUN"
    },
    {
      "id": "portal_ux_improved",
      "description": "portal mejora visualmente",
      "status": "NOT_RUN"
    },
    {
      "id": "no_regression_ui",
      "description": "UI existente sigue estable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "widgets_work",
    "ux_improved",
    "stable_ui"
  ]
}
QA_CONTRACT_END
