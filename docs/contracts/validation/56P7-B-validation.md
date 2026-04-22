QA_CONTRACT_START
{
  "validation_contract_id": "56P7-B-validation",
  "phase": "56P7-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "client_shortcodes_file_exists",
      "type": "file_exists",
      "target": "includes/dashboard/class-client-dashboard-shortcodes.php"
    },
    {
      "id": "clients_service_folder_exists",
      "type": "file_exists",
      "target": "includes/clients"
    }
  ],
  "manual_checks": [
    {
      "id": "linked_user_resolves_client",
      "description": "Authenticated client user with persisted wp_user_id sees own panel data",
      "status": "NOT_RUN"
    },
    {
      "id": "email_fallback_unique_match",
      "description": "If relation is missing, exact email unique match resolves and persists wp_user_id safely",
      "status": "NOT_RUN"
    },
    {
      "id": "email_fallback_safe_on_ambiguity",
      "description": "Ambiguous or absent email matches do not create incorrect links",
      "status": "NOT_RUN"
    },
    {
      "id": "client_panel_sections_load",
      "description": "Client panel sections render (dashboard, vehicles, processes, quotes, invoices; docs/notifications if applicable)",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "authenticated client identity resolution is reliable",
    "fallback migration is safe and backward compatible",
    "client panel data sections load with resolved client context"
  ]
}
QA_CONTRACT_END
