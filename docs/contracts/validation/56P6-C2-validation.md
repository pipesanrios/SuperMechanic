QA_CONTRACT_START
{
  "validation_contract_id": "56P6-C2-validation",
  "phase": "56P6-C2",
  "type": "validation",
  "automated_checks": [
    {
      "id": "users_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    },
    {
      "id": "membership_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-business-membership-service.php"
    },
    {
      "id": "role_access_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "add_mechanic_keeps_admin",
      "description": "Adding mechanic role in same business does not remove existing admin role",
      "status": "NOT_RUN"
    },
    {
      "id": "add_client_keeps_mechanic",
      "description": "Adding client role in same business does not remove existing mechanic role",
      "status": "NOT_RUN"
    },
    {
      "id": "remove_admin_keeps_other_roles",
      "description": "Removing/deactivating admin role does not remove unrelated roles",
      "status": "NOT_RUN"
    },
    {
      "id": "current_memberships_matches_state",
      "description": "Current memberships reflects persisted membership rows accurately",
      "status": "NOT_RUN"
    },
    {
      "id": "add_membership_only_missing_roles",
      "description": "Add membership offers only missing roles per business",
      "status": "NOT_RUN"
    },
    {
      "id": "admin_stable",
      "description": "Roles & Access admin remains stable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "multi-role coexistence per business preserved",
    "remove/deactivate affects only intended role membership",
    "UI and persisted state stay consistent",
    "admin page remains stable"
  ]
}
QA_CONTRACT_END
