QA_CONTRACT_START
{
  "validation_contract_id": "57C-validation",
  "phase": "57C",
  "type": "validation",
  "automated_checks": [
    {
      "id": "saas_folder_exists",
      "type": "file_exists",
      "target": "includes/saas"
    },
    {
      "id": "license_context_exists",
      "type": "file_exists",
      "target": "includes/saas/class-license-context.php"
    },
    {
      "id": "subscription_context_exists",
      "type": "file_exists",
      "target": "includes/saas/class-subscription-context.php"
    },
    {
      "id": "license_entitlement_snapshot_method",
      "type": "method_exists",
      "file": "includes/saas/class-license-context.php",
      "class": "License_Context",
      "method": "get_entitlement_snapshot"
    },
    {
      "id": "license_is_active_method",
      "type": "method_exists",
      "file": "includes/saas/class-license-context.php",
      "class": "License_Context",
      "method": "is_active"
    },
    {
      "id": "license_is_trial_method",
      "type": "method_exists",
      "file": "includes/saas/class-license-context.php",
      "class": "License_Context",
      "method": "is_trial"
    },
    {
      "id": "license_is_expired_method",
      "type": "method_exists",
      "file": "includes/saas/class-license-context.php",
      "class": "License_Context",
      "method": "is_expired"
    },
    {
      "id": "license_from_license_service_method",
      "type": "method_exists",
      "file": "includes/saas/class-license-context.php",
      "class": "License_Context",
      "method": "from_license_service"
    },
    {
      "id": "subscription_to_array_method",
      "type": "method_exists",
      "file": "includes/saas/class-subscription-context.php",
      "class": "Subscription_Context",
      "method": "to_array"
    }
  ],
  "manual_checks": [
    {
      "id": "subscription_context_passive",
      "description": "Subscription context is passive and does not enforce billing",
      "status": "NOT_RUN"
    },
    {
      "id": "no_external_billing_calls",
      "description": "No Stripe/payment provider/API calls were introduced",
      "status": "NOT_RUN"
    },
    {
      "id": "current_license_behavior_preserved",
      "description": "Existing Mekvort license behavior remains unchanged",
      "status": "NOT_RUN"
    },
    {
      "id": "no_schema_changes",
      "description": "No schema/database changes were introduced",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "subscription abstraction exists",
    "entitlement snapshot exists",
    "billing remains deferred",
    "current license behavior preserved"
  ]
}
QA_CONTRACT_END
