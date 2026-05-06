QA_CONTRACT_START
{
  "validation_contract_id": "56P11-B-validation",
  "phase": "56P11-B",
  "type": "validation",
  "automated_checks": [
    {
      "id": "invoice_service_exists",
      "type": "file_exists",
      "target": "includes/invoices/class-invoice-service.php"
    },
    {
      "id": "quote_service_exists",
      "type": "file_exists",
      "target": "includes/quotes/class-quote-service.php"
    },
    {
      "id": "report_pdf_service_exists",
      "type": "file_exists",
      "target": "includes/reporting/class-report-pdf-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "invoice_pdf_data_matches_source",
      "description": "Invoice PDF data matches invoice source data",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_pdf_data_matches_source",
      "description": "Quote PDF data matches quote source data",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_pdf_data_matches_source",
      "description": "Reporting PDF data matches reporting source metrics",
      "status": "NOT_RUN"
    },
    {
      "id": "financial_totals_preserved",
      "description": "Totals, taxes, discounts, payments and balances are preserved",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "invoice PDF mapping verified",
    "quote PDF mapping verified",
    "reporting PDF mapping verified",
    "financial data preserved"
  ]
}
QA_CONTRACT_END