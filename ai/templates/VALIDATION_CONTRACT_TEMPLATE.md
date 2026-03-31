# VALIDATION CONTRACT

## Validation Contract Reference
{validation_contract_id}

## Related Task Contract
{task_contract_path}

## Functional validation
- [ ] {functional_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Technical validation
- [ ] {technical_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Runtime validation
- [ ] {runtime_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Regression validation
- [ ] {regression_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Security validation
- [ ] {security_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Performance validation
- [ ] {performance_check_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

## Edge cases
- [ ] {edge_case_1}
  - Expected result: {expected_result_1}
  - Result: {pass_or_fail_or_not_run}

QA_CONTRACT_START
{
  "phase": "{phase}",
  "validation_contract_id": "{validation_contract_id}",
  "automated_checks": [
    {
      "id": "{auto_check_id_1}",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "{auto_check_id_2}",
      "type": "file_exists",
      "targets": ["{path_1}"]
    }
  ],
  "manual_checks": [
    {
      "id": "{manual_check_id_1}",
      "description": "{manual_check_description_1}"
    }
  ]
}
QA_CONTRACT_END
