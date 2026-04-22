QA_CONTRACT_START
{
  "validation_contract_id": "56P7-D-validation",
  "phase": "56P7-D",
  "type": "validation",
  "automated_checks": [
    {
      "id": "dashboard_folder_exists",
      "type": "file_exists",
      "target": "includes/dashboard"
    },
    {
      "id": "shortcode_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/class-shortcode-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "mekvort_client_panel_in_catalog",
      "description": "[mekvort_client_panel] appears in shortcode catalog",
      "status": "NOT_RUN"
    },
    {
      "id": "mekvort_mechanic_panel_exists_and_works",
      "description": "[mekvort_mechanic_panel] exists and renders mechanic panel",
      "status": "NOT_RUN"
    },
    {
      "id": "catalog_reflects_active_shortcodes",
      "description": "catalog lists real active shortcodes and coherent groups",
      "status": "NOT_RUN"
    },
    {
      "id": "legacy_sm_shortcodes_compatible",
      "description": "existing sm_client_* and sm_mechanic_* shortcodes remain functional",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "new mechanic general shortcode is available without logic duplication",
    "mekvort and sm shortcodes coexist without regressions",
    "shortcode catalog matches active runtime shortcodes"
  ]
}
QA_CONTRACT_END
