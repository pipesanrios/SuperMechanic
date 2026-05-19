<?php
/**
 * Passive queue context.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Describes future queue capabilities without activating workers.
 */
class Queue_Context {
	const BACKOFF_STRATEGY = 'deterministic_manual_backoff';

	/**
	 * Whether passive job persistence is enabled for this context.
	 *
	 * @var bool
	 */
	protected $persistence_enabled = false;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Context arguments.
	 */
	public function __construct( array $args = array() ) {
		if ( isset( $args['persistence_enabled'] ) ) {
			$this->persistence_enabled = ! empty( $args['persistence_enabled'] );
		}
	}

	/**
	 * Check whether persistence is enabled.
	 *
	 * @return bool
	 */
	public function is_persistence_enabled() {
		return (bool) $this->persistence_enabled;
	}

	/**
	 * Enable or disable persistence for this context.
	 *
	 * @param bool $enabled Enabled flag.
	 * @return self
	 */
	public function set_persistence_enabled( $enabled ) {
		$this->persistence_enabled = ! empty( $enabled );

		return $this;
	}

	/**
	 * Get supported future job types.
	 *
	 * @return string[]
	 */
	public function get_supported_job_types() {
		return Queue_Job_Contract::get_supported_job_types();
	}

	/**
	 * Get supported job statuses.
	 *
	 * @return string[]
	 */
	public function get_supported_statuses() {
		return Queue_Job_Contract::get_supported_statuses();
	}

	/**
	 * Resolve default max attempts by job type.
	 *
	 * @param string $job_type Job type.
	 * @return int
	 */
	public function get_default_max_attempts( $job_type ) {
		$job_type = sanitize_key( (string) $job_type );

		if ( Queue_Job_Contract::JOB_EMAIL_DELIVERY === $job_type || Queue_Job_Contract::JOB_WEBHOOK_DELIVERY === $job_type ) {
			return 5;
		}

		if ( Queue_Job_Contract::JOB_PDF_GENERATION === $job_type ) {
			return 2;
		}

		return Queue_Job_Contract::DEFAULT_MAX_ATTEMPTS;
	}

	/**
	 * Get conceptual retry strategy.
	 *
	 * @return array<string,mixed>
	 */
	public function get_backoff_strategy() {
		return array(
			'strategy'           => self::BACKOFF_STRATEGY,
			'attempt_1_seconds'  => 300,
			'attempt_2_seconds'  => 900,
			'attempt_3_plus_seconds' => 1800,
			'jitter_enabled'     => false,
			'scheduler_enabled'  => false,
			'worker_enabled'     => false,
		);
	}

	/**
	 * Get passive queue capability map.
	 *
	 * @return array<string,mixed>
	 */
	public function get_contracts() {
		return array(
			'queue_jobs'          => array(
				'status'          => 'passive_contract',
				'workers_enabled' => false,
				'job_types'       => $this->get_supported_job_types(),
			),
			'retry_jobs'          => array(
				'status'           => 'passive_contract',
				'workers_enabled'  => false,
				'supported_status' => Queue_Job_Contract::STATUS_RETRY_SCHEDULED,
				'backoff'          => $this->get_backoff_strategy(),
			),
			'scheduled_sync_jobs' => array(
				'status'          => 'passive_contract',
				'workers_enabled' => false,
				'job_types'       => array(
					Queue_Job_Contract::JOB_INVENTORY_CONNECTOR_SYNC,
					Queue_Job_Contract::JOB_GOOGLE_CALENDAR_SYNC,
				),
			),
		);
	}

	/**
	 * Export context.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'supported_job_types' => $this->get_supported_job_types(),
			'supported_statuses'  => $this->get_supported_statuses(),
			'backoff_strategy'    => $this->get_backoff_strategy(),
			'contracts'           => $this->get_contracts(),
			'persistence_enabled' => $this->is_persistence_enabled(),
			'execution_enabled'   => false,
		);
	}
}
