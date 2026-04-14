QA_CONTRACT_START
{
  "validation_contract_id": "56P1-A1-validation",
  "phase": "56P1-A1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "main_plugin_file_exists",
      "type": "file_exists",
      "target": "super-mechanic.php"
    },
    {
      "id": "branding_service_exists",
      "type": "file_exists",
      "target": "includes/branding/class-branding-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "plugin_visible_branding_is_mekvort",
      "description": "Visible plugin/product identity shows Mekvort in safe branding surfaces",
      "status": "NOT_RUN"
    },
    {
      "id": "branding_default_name_is_mekvort",
      "description": "Default branding system_name is Mekvort",
      "status": "NOT_RUN"
    },
    {
      "id": "no_admin_regression",
      "description": "Admin still loads correctly after safe branding-only rename",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "safe visible branding applied",
    "technical identifiers unchanged",
    "admin stable"
  ]
}
QA_CONTRACT_END