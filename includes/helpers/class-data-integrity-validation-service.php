<?php
/**
 * Data integrity validation service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Database\Data_Integrity_Validation_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates integrity/orphan validation and returns a structured report.
 */
class Data_Integrity_Validation_Service {
	/**
	 * Integrity repository.
	 *
	 * @var Data_Integrity_Validation_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Data_Integrity_Validation_Repository|null $repository Repository dependency.
	 */
	public function __construct( Data_Integrity_Validation_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Data_Integrity_Validation_Repository();
	}

	/**
	 * Run integrity validation and return a structured report.
	 *
	 * @param bool $auto_fix Whether to run optional trivial safe auto-fix.
	 * @param int  $sample_limit Max sample IDs per check.
	 * @return array<string,mixed>
	 */
	public function run_validation( $auto_fix = false, $sample_limit = 20 ) {
		$rows          = $this->repository->run_integrity_checks( $sample_limit );
		$checks        = array();
		$total_checks  = 0;
		$failed_checks = 0;
		$total_issues  = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key         = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
			$description = isset( $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : '';
			$count       = isset( $row['count'] ) ? max( 0, (int) $row['count'] ) : 0;
			$sample_ids  = isset( $row['sample_ids'] ) && is_array( $row['sample_ids'] ) ? array_values( array_unique( array_map( 'absint', $row['sample_ids'] ) ) ) : array();

			if ( '' === $key ) {
				continue;
			}

			$status = ( $count > 0 ) ? 'FAIL' : 'PASS';
			++$total_checks;
			$total_issues += $count;

			if ( 'FAIL' === $status ) {
				++$failed_checks;
			}

			$checks[] = array(
				'key'         => $key,
				'description' => $description,
				'status'      => $status,
				'count'       => $count,
				'sample_ids'  => $sample_ids,
			);
		}

		$auto_fix_report = $this->build_auto_fix_report( (bool) $auto_fix );

		return array(
			'generated_at' => current_time( 'mysql', true ),
			'overall_status' => ( $failed_checks > 0 ) ? 'FAIL' : 'PASS',
			'summary'      => array(
				'total_checks'  => $total_checks,
				'passed_checks' => max( 0, $total_checks - $failed_checks ),
				'failed_checks' => $failed_checks,
				'total_issues'  => $total_issues,
			),
			'checks'       => $checks,
			'auto_fix'     => $auto_fix_report,
		);
	}

	/**
	 * Build optional auto-fix report.
	 *
	 * @param bool $enabled Whether auto-fix is requested.
	 * @return array<string,mixed>
	 */
	protected function build_auto_fix_report( $enabled ) {
		if ( ! $enabled ) {
			return array(
				'enabled' => false,
				'applied' => array(),
				'skipped' => array(),
			);
		}

		// 56P3-C scope is validation-only. No destructive/traversal auto-fix is applied here.
		return array(
			'enabled' => true,
			'applied' => array(),
			'skipped' => array(
				array(
					'key'    => 'auto_fix_not_applied',
					'reason' => 'Validation-only scope: no trivial safe auto-fix configured in 56P3-C.',
				),
			),
		);
	}
}

