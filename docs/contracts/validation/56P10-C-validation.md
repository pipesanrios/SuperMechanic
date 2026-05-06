QA_CONTRACT_START
{
  "validation_contract_id": "56P10-C-validation",
  "phase": "56P10-C",
  "type": "validation",
  "automated_checks": [],
  "manual_checks": [
    {
      "id": "unauthenticated_reads_blocked",
      "description": "Unauthenticated GET requests to sm/v1 are blocked",
      "status": "NOT_RUN"
    },
    {
      "id": "unauthenticated_quote_approve_blocked",
      "description": "Unauthenticated quote approval is blocked",
      "status": "NOT_RUN"
    },
    {
      "id": "authenticated_reads_work",
      "description": "Authenticated users can access allowed routes",
      "status": "NOT_RUN"
    },
    {
      "id": "unauthorized_quote_approve_blocked",
      "description": "Authenticated user without permission cannot approve quote",
      "status": "NOT_RUN"
    },
    {
      "id": "response_compatibility_preserved",
      "description": "Response payloads remain compatible after hardening",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "protected routes block unauthenticated access",
    "authorized access still works",
    "quote approval protected",
    "response compatibility preserved"
  ]
}
QA_CONTRACT_END