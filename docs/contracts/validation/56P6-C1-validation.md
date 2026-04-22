QA_CONTRACT_START
{
  "validation_contract_id": "56P6-C1-validation",
  "phase": "56P6-C1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "users_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    },
    {
      "id": "users_services_exist",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "assign_admin_hidden_if_already_admin",
      "description": "Assign Admin is hidden when user already has Admin role",
      "status": "NOT_RUN"
    },
    {
      "id": "assign_mechanic_hidden_if_already_mechanic",
      "description": "Assign Mechanic is hidden when user already has Mechanic role",
      "status": "NOT_RUN"
    },
    {
      "id": "assign_client_hidden_if_already_client",
      "description": "Assign Client is hidden when user already has Client role",
      "status": "NOT_RUN"
    },
    {
      "id": "add_membership_hidden_if_all_roles_present",
      "description": "Add membership card is hidden when all 3 roles are already covered",
      "status": "NOT_RUN"
    },
    {
      "id": "primary_membership_invalid_deactivation_blocked_in_ui",
      "description": "UI does not offer invalid primary-membership deactivation path",
      "status": "NOT_RUN"
    },
    {
      "id": "role_removal_consistent_final_state",
      "description": "Role removal/deactivation leaves consistent final role state",
      "status": "NOT_RUN"
    },
    {
      "id": "admin_stable",
      "description": "Roles & Access admin stays stable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "action visibility matches role state",
    "invalid UI action path removed",
    "final role state remains consistent",
    "admin remains stable"
  ]
}
QA_CONTRACT_END
