QA_CONTRACT_START
{
  "phase": "53C",
  "validation_contract_id": "53C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    }
  ],
  "manual_checks": [
    {
      "id": "dashboard_mobile_ok",
      "description": "dashboard usable en movil",
      "status": "NOT_RUN"
    },
    {
      "id": "portal_mobile_ok",
      "description": "portal usable en movil",
      "status": "NOT_RUN"
    },
    {
      "id": "no_overflow",
      "description": "no hay overflow grave",
      "status": "NOT_RUN"
    },
    {
      "id": "tap_targets_ok",
      "description": "botones usables en touch",
      "status": "NOT_RUN"
    },
    {
      "id": "no_regression_desktop",
      "description": "desktop sigue bien",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "mobile_usable",
    "layout_responsive",
    "stable_ui"
  ]
}
QA_CONTRACT_END
