QA_CONTRACT_START
{
  "validation_contract_id": "56P4-D-validation",
  "phase": "56P4-D",
  "type": "validation",
  "automated_checks": [
    {
      "id": "settings_file_exists",
      "type": "file_exists",
      "target": "includes/class-settings.php"
    },
    {
      "id": "admin_css_exists",
      "type": "file_exists",
      "target": "assets/css/admin.css"
    }
  ],
  "manual_checks": [
    {
      "id": "settings_clearer",
      "description": "Settings page is clearer regarding licensing",
      "status": "NOT_RUN"
    },
    {
      "id": "license_page_still_works",
      "description": "License page still loads and works",
      "status": "NOT_RUN"
    },
    {
      "id": "duplication_reduced",
      "description": "License-related duplication/confusion is reduced",
      "status": "NOT_RUN"
    },
    {
      "id": "no_console_errors",
      "description": "No JS errors on Settings/License pages",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "settings/license UX clarified",
    "license flow preserved",
    "admin stable"
  ]
}
QA_CONTRACT_END