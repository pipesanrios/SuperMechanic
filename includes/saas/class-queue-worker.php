<?php
/**
 * Manual SaaS queue worker.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Processes one persisted SaaS queue job only when called manually.
 */
class Queue_Worker {
	/**
	 * Queue repository.
	 *
	 * @var Queue_Job_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Queue_Job_Repository|null $repository Queue repository.
	 */
	public function __construct( Queue_Job_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Queue_Job_Repository();
	}

	/**
	 * Process the next available job manually.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @return array<string,mixed>
	 */
	public function process_next( array $filters = array() ) {
		$job = $this->repository->get_next_available_job( $filters );
		if ( ! is_array( $job ) ) {
			return $this->result(
				false,
				'skipped',
				'no_available_job',
				array(),
				array( 'writes' => 0 )
			);
		}

		$job_id     = isset( $job['job_id'] ) ? sanitize_key( (string) $job['job_id'] ) : '';
		$lock_token = $this->generate_lock_token( $job_id );
		if ( '' === $job_id || ! $this->repository->claim_job( $job_id, $lock_token ) ) {
			return $this->result(
				false,
				'failed',
				'job_claim_failed',
				$job,
				array( 'writes' => 0 )
			);
		}

		$claimed_job = $this->repository->get_job_by_id( $job_id );
		if ( ! is_array( $claimed_job ) ) {
			return $this->result(
				false,
				'failed',
				'claimed_job_not_found',
				$job,
				array( 'writes' => 1 )
			);
		}

		return $this->process_job( $claimed_job );
	}

	/**
	 * Process one already-claimed job.
	 *
	 * @param array<string,mixed> $job Job row.
	 * @return array<string,mixed>
	 */
	public function process_job( array $job ) {
		$job_id   = isset( $job['job_id'] ) ? sanitize_key( (string) $job['job_id'] ) : '';
		$job_type = isset( $job['job_type'] ) ? sanitize_key( (string) $job['job_type'] ) : '';

		if ( '' === $job_id ) {
			return $this->result( false, 'failed', 'job_id_required', $job );
		}

		if ( Queue_Job_Contract::STATUS_RUNNING !== ( isset( $job['status'] ) ? (string) $job['status'] : '' ) ) {
			return $this->fail_job( $job_id, 'job_not_running' );
		}

		if ( empty( $job['lock_token'] ) ) {
			return $this->fail_job( $job_id, 'lock_token_required' );
		}

		if ( Queue_Job_Contract::JOB_INVENTORY_CONNECTOR_SYNC === $job_type ) {
			$result = $this->handle_inventory_connector_sync( $job );

			if ( ! empty( $result['success'] ) ) {
				return $this->complete_job( $job_id, $result );
			}

			return $this->fail_job( $job_id, isset( $result['error_code'] ) ? $result['error_code'] : 'inventory_connector_sync_failed' );
		}

		return $this->fail_job( $job_id, 'unsupported_job_type' );
	}

	/**
	 * Handle simulation-only inventory connector sync jobs.
	 *
	 * @param array<string,mixed> $job Job row.
	 * @return array<string,mixed>
	 */
	public function handle_inventory_connector_sync( array $job ) {
		$payload = isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array();

		if ( empty( $payload ) ) {
			return $this->handler_result( false, 'payload_required' );
		}

		if ( ! $this->is_simulation_payload( $payload ) ) {
			return $this->handler_result( false, 'unsafe_non_simulation_payload' );
		}

		return $this->handler_result(
			true,
			'simulation_completed',
			array(
				'connector_key' => isset( $payload['connector_key'] ) ? sanitize_key( (string) $payload['connector_key'] ) : '',
				'operation'     => isset( $payload['operation'] ) ? sanitize_key( (string) $payload['operation'] ) : '',
				'dry_run'       => ! empty( $payload['dry_run'] ),
				'normalized_count' => isset( $payload['normalized_items']['count'] ) ? absint( $payload['normalized_items']['count'] ) : 0,
				'executed'      => false,
				'writes'        => 0,
			)
		);
	}

	/**
	 * Mark one job as failed.
	 *
	 * @param string $job_id Job ID.
	 * @param string $error Error code/message.
	 * @return array<string,mixed>
	 */
	public function fail_job( $job_id, $error ) {
		$job_id = sanitize_key( (string) $job_id );
		$error  = sanitize_key( (string) $error );
		$job    = $this->repository->get_job_by_id( $job_id );

		if ( is_array( $job ) ) {
			$current_attempts = isset( $job['attempts'] ) ? absint( $job['attempts'] ) : 0;
			$max_attempts     = isset( $job['max_attempts'] ) ? max( 1, absint( $job['max_attempts'] ) ) : Queue_Job_Contract::DEFAULT_MAX_ATTEMPTS;
			$next_attempts    = $current_attempts + 1;

			$this->repository->update_attempts( $job_id, $next_attempts );

			if ( $next_attempts < $max_attempts ) {
				$available_at = $this->get_retry_available_at( $next_attempts );
				$this->repository->schedule_retry( $job_id, $available_at, $error );

				$retry_job = $this->repository->get_job_by_id( $job_id );

				return $this->result(
					false,
					Queue_Job_Contract::STATUS_RETRY_SCHEDULED,
					$error,
					is_array( $retry_job ) ? $retry_job : array(),
					array(
						'writes'       => 1,
						'executed'     => false,
						'available_at' => $available_at,
					)
				);
			}
		}

		$this->repository->mark_failed( $job_id, $error );

		$job = $this->repository->get_job_by_id( $job_id );

		return $this->result(
			false,
			'failed',
			$error,
			is_array( $job ) ? $job : array(),
			array(
				'writes'   => 1,
				'executed' => false,
			)
		);
	}

	/**
	 * Resolve retry availability timestamp for a failed attempt.
	 *
	 * @param int $attempt Attempt number after failure.
	 * @return string
	 */
	public function get_retry_available_at( $attempt ) {
		$base_timestamp = function_exists( 'current_time' ) ? strtotime( current_time( 'mysql' ) ) : time();
		if ( false === $base_timestamp ) {
			$base_timestamp = time();
		}

		return date( 'Y-m-d H:i:s', $base_timestamp + $this->get_retry_delay_seconds( $attempt ) );
	}

	/**
	 * Resolve deterministic retry delay in seconds.
	 *
	 * @param int $attempt Attempt number after failure.
	 * @return int
	 */
	public function get_retry_delay_seconds( $attempt ) {
		$attempt = max( 1, absint( $attempt ) );

		if ( 1 === $attempt ) {
			return 5 * MINUTE_IN_SECONDS;
		}

		if ( 2 === $attempt ) {
			return 15 * MINUTE_IN_SECONDS;
		}

		return 30 * MINUTE_IN_SECONDS;
	}

	/**
	 * Mark one job as completed.
	 *
	 * @param string              $job_id Job ID.
	 * @param array<string,mixed> $result Handler result.
	 * @return array<string,mixed>
	 */
	public function complete_job( $job_id, array $result ) {
		$job_id = sanitize_key( (string) $job_id );
		$this->repository->mark_completed( $job_id );

		$job = $this->repository->get_job_by_id( $job_id );

		return $this->result(
			true,
			'completed',
			'job_completed',
			is_array( $job ) ? $job : array(),
			array(
				'writes'   => 1,
				'executed' => false,
				'handler'  => $result,
			)
		);
	}

	/**
	 * Determine whether payload is safe simulation-only work.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return bool
	 */
	protected function is_simulation_payload( array $payload ) {
		if ( ! empty( $payload['dry_run'] ) ) {
			return true;
		}

		$operation = isset( $payload['operation'] ) ? sanitize_key( (string) $payload['operation'] ) : '';
		if ( in_array( $operation, array( 'dry_run', 'sync_simulation', 'simulation' ), true ) ) {
			return true;
		}

		if ( ! empty( $payload['simulation'] ) ) {
			return true;
		}

		if ( isset( $payload['execution'] ) && is_array( $payload['execution'] ) ) {
			return empty( $payload['execution']['worker_enabled'] )
				&& empty( $payload['execution']['connector_executed'] )
				&& empty( $payload['execution']['import_executed'] )
				&& empty( $payload['execution']['external_api_called'] );
		}

		return false;
	}

	/**
	 * Build handler result.
	 *
	 * @param bool                $success Success.
	 * @param string              $code Code.
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>
	 */
	protected function handler_result( $success, $code, array $data = array() ) {
		return array(
			'success'    => ! empty( $success ),
			'error_code' => empty( $success ) ? sanitize_key( (string) $code ) : '',
			'code'       => sanitize_key( (string) $code ),
			'data'       => $data,
			'writes'     => 0,
			'executed'   => false,
		);
	}

	/**
	 * Build worker result.
	 *
	 * @param bool                $success Success.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $job Job row.
	 * @param array<string,mixed> $meta Meta.
	 * @return array<string,mixed>
	 */
	protected function result( $success, $status, $message, array $job = array(), array $meta = array() ) {
		return array(
			'success'  => ! empty( $success ),
			'status'   => sanitize_key( (string) $status ),
			'message'  => sanitize_key( (string) $message ),
			'job'      => $job,
			'writes'   => isset( $meta['writes'] ) ? absint( $meta['writes'] ) : 0,
			'executed' => false,
			'passive'  => true,
			'handler'  => isset( $meta['handler'] ) && is_array( $meta['handler'] ) ? $meta['handler'] : array(),
			'available_at' => isset( $meta['available_at'] ) ? sanitize_text_field( (string) $meta['available_at'] ) : '',
		);
	}

	/**
	 * Generate lock token.
	 *
	 * @param string $job_id Job ID.
	 * @return string
	 */
	protected function generate_lock_token( $job_id ) {
		$base = sanitize_key( (string) $job_id ) . ':' . microtime( true ) . ':' . uniqid( '', true );

		return 'smq_lock_' . md5( $base );
	}
}
