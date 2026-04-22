QA_CONTRACT_START
{
  "validation_contract_id": "56P6-C3-validation",
  "phase": "56P6-C3",
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
    }
  ],
  "manual_checks": [
    {
      "id": "one_business_one_card",
      "description": "Current memberships renders one card per business",
      "status": "NOT_RUN"
    },
    {
      "id": "roles_badges_not_dropdown",
      "description": "Roles are rendered as badges/text instead of role dropdown in current memberships",
      "status": "NOT_RUN"
    },
    {
      "id": "compact_add_membership_dropdown",
      "description": "Add membership renders compact dropdown flow",
      "status": "NOT_RUN"
    },
    {
      "id": "only_missing_roles_offered",
      "description": "Add membership offers only missing roles per business",
      "status": "NOT_RUN"
    },
    {
      "id": "actions_visibility_consistent",
      "description": "Actions hide when not applicable and reappear after role removal",
      "status": "NOT_RUN"
    },
    {
      "id": "admin_stable",
      "description": "Roles & Access admin remains stable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "memberships are rendered per business",
    "roles are clearly represented without role dropdown in state view",
    "add-membership UI is compact and constrained to missing roles",
    "actions and persisted state remain consistent"
  ]
}
QA_CONTRACT_END
