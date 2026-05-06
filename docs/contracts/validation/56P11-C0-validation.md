# 56P11-C0 Validation Contract

## Automated Checks
- `php scripts/php-lint.php --all`

## Runtime / Manual Checks
- TCPDF embedded detected.
- Download PDF Report enabled.
- Reporting PDF downloads.
- PDF opens correctly.
- No "Install Dompdf, mPDF or TCPDF" message when embedded TCPDF exists.

## Closure Rule
- Closure is COMPLETE only if lint passes and runtime/manual checks confirm the embedded engine is detected or a physical dependency absence is documented without asking users to install external plugins.
