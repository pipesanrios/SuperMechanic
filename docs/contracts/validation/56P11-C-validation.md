QA_CONTRACT_START
{
  "validation_contract_id": "56P11-C-validation",
  "phase": "56P11-C",
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
      "id": "repeated_invoice_export_stable",
      "description": "Repeated invoice PDF exports are stable",
      "status": "NOT_RUN"
    },
    {
      "id": "repeated_quote_export_stable",
      "description": "Repeated quote PDF exports are stable",
      "status": "NOT_RUN"
    },
    {
      "id": "repeated_reporting_export_stable",
      "description": "Repeated reporting PDF exports are stable",
      "status": "NOT_RUN"
    },
    {
      "id": "headers_and_filenames_valid",
      "description": "PDF export headers and filenames are valid",
      "status": "NOT_RUN"
    },
    {
      "id": "errors_handled_cleanly",
      "description": "Invalid/missing export targets fail cleanly",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "exports are stable",
    "headers valid",
    "filenames valid",
    "errors controlled"
  ]
}
QA_CONTRACT_END