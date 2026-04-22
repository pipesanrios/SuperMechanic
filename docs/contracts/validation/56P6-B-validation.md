QA_CONTRACT_START
{
  "validation_contract_id": "56P6-B-validation",
  "phase": "56P6-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "roles_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    },
    {
      "id": "roles_js_exists",
      "type": "file_exists",
      "target": "assets/js/admin-roles-access.js"
    }
  ],
  "manual_checks": [
    {
      "id": "visible_columns_has_id_name_email",
      "description": "Visible Columns shows ID, Name, Email",
      "status": "NOT_RUN"
    },
    {
      "id": "toggle_id_works",
      "description": "Hide/show ID column works",
      "status": "NOT_RUN"
    },
    {
      "id": "toggle_name_works",
      "description": "Hide/show Name column works",
      "status": "NOT_RUN"
    },
    {
      "id": "toggle_email_works",
      "description": "Hide/show Email column works",
      "status": "NOT_RUN"
    },
    {
      "id": "existing_toggles_work",
      "description": "Existing visible-column toggles remain functional",
      "status": "NOT_RUN"
    },
    {
      "id": "table_readable_no_console_errors",
      "description": "Roles table remains readable and has no console errors",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "visible columns extended safely",
    "existing toggles preserved",
    "table remains stable"
  ]
}
QA_CONTRACT_END
