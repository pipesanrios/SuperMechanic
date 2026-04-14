QA_CONTRACT_START
{
  "validation_contract_id": "56P1-B-validation",
  "phase": "56P1-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "settings_file_exists",
      "type": "file_exists",
      "target": "includes/class-settings.php"
    }
  ],
  "manual_checks": [
    {
      "id": "language_settings_visible",
      "description": "Language Settings section is visible in Settings",
      "status": "NOT_RUN"
    },
    {
      "id": "language_selector_visible",
      "description": "Language selector is visible and usable",
      "status": "NOT_RUN"
    },
    {
      "id": "bundled_languages_visible",
      "description": "English, Español and Italiano are visible as bundled languages",
      "status": "NOT_RUN"
    },
    {
      "id": "future_language_placeholder_visible",
      "description": "Future language expansion placeholder/area is visible",
      "status": "NOT_RUN"
    },
    {
      "id": "no_settings_regression",
      "description": "Settings/admin still load correctly after this change",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "language settings visible",
    "bundled languages visible",
    "no admin regression"
  ]
}
QA_CONTRACT_END