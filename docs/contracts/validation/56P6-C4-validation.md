QA_CONTRACT_START
{
  "validation_contract_id": "56P6-C4-validation",
  "phase": "56P6-C4",
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
      "id": "remove_role_persists",
      "description": "Remove role updates persisted role state for target business",
      "status": "NOT_RUN"
    },
    {
      "id": "assign_button_reappears",
      "description": "After role removal, matching assign button reappears",
      "status": "NOT_RUN"
    },
    {
      "id": "buttons_match_state",
      "description": "Actions buttons match real persisted role state",
      "status": "NOT_RUN"
    },
    {
      "id": "primary_handoff_stable",
      "description": "Primary handoff remains consistent after role removal",
      "status": "NOT_RUN"
    },
    {
      "id": "admin_stable",
      "description": "Roles & Access admin remains stable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "remove action persists real state",
    "actions visibility syncs with persisted state",
    "primary remains consistent",
    "admin stays stable"
  ]
}
QA_CONTRACT_END
