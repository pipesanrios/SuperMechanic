QA_CONTRACT_START
{
  "validation_contract_id": "56P11-C1-validation",
  "phase": "56P11-C1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "shared_pdf_service_exists",
      "type": "file_exists",
      "target": "includes/helpers/class-pdf-service.php"
    },
    {
      "id": "report_pdf_service_exists",
      "type": "file_exists",
      "target": "includes/reporting/class-report-pdf-service.php"
    },
    {
      "id": "embedded_tcpdf_exists",
      "type": "file_exists",
      "target": "includes/libs/pdf/tcpdf/tcpdf.php"
    }
  ],
  "manual_checks": [
    {
      "id": "fresh_pdf_service_can_generate",
      "description": "Shared PDF_Service detects embedded TCPDF in a fresh request",
      "status": "NOT_RUN"
    },
    {
      "id": "invoice_export_fresh_request",
      "description": "Invoice PDF export passes 3/3 in fresh request",
      "status": "NOT_RUN"
    },
    {
      "id": "quote_export_fresh_request",
      "description": "Quote PDF export passes 3/3 in fresh request",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_export_still_passes",
      "description": "Reporting PDF export remains stable",
      "status": "NOT_RUN"
    },
    {
      "id": "invalid_ids_not_engine_masked",
      "description": "Invalid invoice/quote IDs fail cleanly without engine unavailable",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "shared PDF engine loader works in fresh request",
    "invoice export stable",
    "quote export stable",
    "reporting export stable",
    "invalid IDs are controlled and not masked by engine failure"
  ]
}
QA_CONTRACT_END
