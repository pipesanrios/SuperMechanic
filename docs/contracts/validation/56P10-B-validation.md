QA_CONTRACT_START
{
  "validation_contract_id": "56P10-B-validation",
  "phase": "56P10-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "api_folder_exists",
      "type": "file_exists",
      "target": "includes/api"
    }
  ],
  "manual_checks": [
    {
      "id": "unauthenticated_sm_routes_blocked",
      "description": "Unauthenticated requests to protected sm/v1 routes are blocked",
      "status": "NOT_RUN"
    },
    {
      "id": "authenticated_reads_work",
      "description": "Authenticated read requests still work",
      "status": "NOT_RUN"
    },
    {
      "id": "unauthorized_writes_blocked",
      "description": "Unauthorized write/mutation requests are blocked",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_approve_protected",
      "description": "POST /sm/v1/quotes/{id}/approve is protected by a named permission callback",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "sm/v1 does not use __return_true",
    "route-level permission callbacks exist",
    "quote approval hardened",
    "compatibility preserved"
  ]
}
QA_CONTRACT_END