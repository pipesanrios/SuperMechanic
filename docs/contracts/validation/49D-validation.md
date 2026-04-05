PHASE: 49D VALIDATION

QA_CONTRACT_START
{
  "phase": "49D",
  "validation_contract_id": "49D-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    }
  ],
  "manual_checks": [
    {
      "id": "transfer_replace_mode",
      "description": "usuario se mueve correctamente entre negocios"
    },
    {
      "id": "transfer_add_mode",
      "description": "usuario puede pertenecer a multiples negocios"
    },
    {
      "id": "primary_consistency",
      "description": "solo una primaria tras transferencia"
    },
    {
      "id": "no_duplicate_active_memberships",
      "description": "no se crean duplicados activos"
    },
    {
      "id": "warnings_resolved_after_transfer",
      "description": "warnings desaparecen tras transferencia valida"
    },
    {
      "id": "no_regression_dashboard",
      "description": "dashboard sigue estable"
    }
  ]
}
QA_CONTRACT_END
