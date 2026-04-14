QA_CONTRACT_START
{
  "validation_contract_id": "56P1-C-validation",
  "phase": "56P1-C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "i18n_helper_exists",
      "type": "file_exists",
      "target": "includes/helpers/class-i18n-helper.php"
    },
    {
      "id": "settings_file_exists",
      "type": "file_exists",
      "target": "includes/class-settings.php"
    }
  ],
  "manual_checks": [
    {
      "id": "helper_uses_persisted_language",
      "description": "I18N helper resolves the persisted default language correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "english_fallback_works",
      "description": "I18N helper falls back safely to English when value is missing/invalid",
      "status": "NOT_RUN"
    },
    {
      "id": "available_languages_correct",
      "description": "Helper returns English, Español and Italiano correctly",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after helper integration",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "central i18n helper exists",
    "persisted language resolution works",
    "safe fallback works",
    "admin stable"
  ]
}
QA_CONTRACT_END