ERROR_RECOVERY_PROTOCOL.md

PURPOSE:
Provide a minimal and safe path for diagnosing and fixing failures.

SCOPE:
Error diagnosis and correction planning.

WHEN TO USE:
When syntax/runtime/flow/security errors appear.

WHEN NOT TO USE:
Do not use as a feature implementation guide.

## Recovery Steps
1. Identify file and module.
2. Identify probable root cause.
3. Evaluate impact radius.
4. Propose minimal fix.
5. Validate with lint/runtime checks.

## Constraint
No global refactor as first response to an error.
