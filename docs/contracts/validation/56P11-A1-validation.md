QA_CONTRACT_START
{
  "validation_contract_id": "56P11-A1-validation",
  "phase": "56P11-A1",
  "type": "validation",
  "automated_checks": [
    {
      "id": "report_pdf_service_exists",
      "type": "file_exists",
      "target": "includes/reporting/class-report-pdf-service.php"
    }
  ],
  "manual_checks": [
    {
      "id": "reporting_pdf_runtime_generates",
      "description": "Reporting PDF runtime generation succeeds",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_pdf_layout_readable",
      "description": "Reporting PDF layout is clean and professionally formatted",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_pdf_data_preserved",
      "description": "Reporting PDF metrics and comparison data remain unchanged",
      "status": "NOT_RUN"
    },
    {
      "id": "reporting_pdf_no_plain_html",
      "description": "Generated PDF does not expose raw HTML/CSS text as document content",
      "status": "NOT_RUN"
    }
  ],
  "acceptance_criteria": [
    "Reporting PDF visually improved",
    "same metrics preserved",
    "same engine and export flow preserved",
    "runtime PDF generation passes"
  ]
}
QA_CONTRACT_END
