QA_CONTRACT_START
{
  "validation_contract_id": "56P2-B1-validation",
  "phase": "56P2-B1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "role_access_service_exists",
      "type": "file_exists",
      "target": "includes/users/class-role-access-service.php"
    },
    {
      "id": "admin_roles_controller_exists",
      "type": "file_exists",
      "target": "includes/users/class-admin-roles-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "promoted_superadmin_locked_superadmin",
      "description": "Promoted superadmin is shown as Locked superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "promoted_superadmin_global_scope",
      "description": "Promoted superadmin is shown with Global scope",
      "status": "NOT_RUN"
    },
    {
      "id": "promoted_superadmin_no_add_membership",
      "description": "Promoted superadmin does not show Add membership",
      "status": "NOT_RUN"
    },
    {
      "id": "promoted_superadmin_no_membership_controls",
      "description": "Promoted superadmin does not show normal membership/transfer controls",
      "status": "NOT_RUN"
    },
    {
      "id": "managed_superadmin_revocation_still_works",
      "description": "Managed superadmin revocation still works for authorized superadmin",
      "status": "NOT_RUN"
    },
    {
      "id": "admin_stable",
      "description": "Admin loads correctly with no runtime regression",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "managed superadmin parity is active",
    "normal membership controls hidden for all superadmins",
    "bootstrap superadmin protection preserved",
    "managed revocation preserved"
  ]
}
QA_CONTRACT_END
