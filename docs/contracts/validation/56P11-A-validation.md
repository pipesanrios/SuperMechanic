QA_CONTRACT_START
{
  "validation_contract_id": "56P11-A-validation",
  "phase": "56P11-A",
  "type": "validation",
  "automated_checks": [
    {
      "id": "pdf_folder_exists",
      "type": "file_exists",
      "target": "includes/pdf"
    }
  ],
  "manual_checks": [
    {
      "id": "invoice_pdf_readable",
      "description": "Invoice PDF layout is readable and professionally formatted",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_pdf_readable",
      "description": "Quote PDF layout is readable and professionally formatted",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_pdf_readable",
      "description": "Reporting PDF layout is readable and professionally formatted",
      "status": "NOT_RUN"
    },
    {
      "id": "totals_preserved",
      "description": "Financial totals remain unchanged after layout improvements",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "PDFs visually improved",
    "totals preserved",
    "branding consistent",
    "export compatibility preserved"
  ]
}
QA_CONTRACT_END