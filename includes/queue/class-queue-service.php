<?php
/**
 * Queue service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Queue;

use Super_Mechanic\Logs\Log_Service;
use Super_Mechanic\Notifications\Notification_Service;
use Super_Mechanic\Webhooks\Webhook_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Queue orchestration service.
 */
class Queue_Service {
	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'sm_queue_worker_cron';

	/**
	 * Supported job statuses.
	 *
	 * @var array<int,string>
	 */
	const STATUSES = array( 'pending', 'processing', 'completed', 'failed' );

	/**
	 * Supported job types.
	 *
	 * @var array<int,string>
	 */
	const JOB_TYPES = array( 'notification', 'webhook' );
	const DEFAULT_MAX_ATTEMPTS = 3;

	/**
	 * Repository dependency.
	 *
	 * @var Queue_Repository
	 */
	protected $repository;

	/**
	 * Log service dependency.
	 *
	 * @var Log_Service|null
	 */
	protected $log_service;

	/**
	 * Constructor.
	 *
	 * @param Queue_Repository|null $repository Repository.
	 */
	public function __construct( Queue_Repository $repository = null, Log_Service $log_service = null ) {
		$this->repository = $repository ? $repository : new Queue_Repository();
		$this->log_service = $log_service;
	}

	/**
	 * Register cron hooks and schedules.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_cron_batch' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'sm_every_five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Register queue cron interval.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_custom_schedules( $schedules ) {
		if ( ! isset( $schedules['sm_every_five_minutes'] ) ) {
			$schedules['sm_every_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes (Super Mechanic Queue)', 'super-mechanic' ),
			);
		}

		return $schedules;
	}

	/**
	 * Enqueue a job.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Job payload.
	 * @return int
	 */
	public function enqueue( $job_type, array $payload ) {
		$job_type = sanitize_key( (string) $job_type );
		if ( ! in_array( $job_type, self::JOB_TYPES, true ) ) {
			return 0;
		}

		$max_attempts = isset( $payload['max_attempts'] ) ? absint( $payload['max_attempts'] ) : self::DEFAULT_MAX_ATTEMPTS;
		unset( $payload['max_attempts'] );
		$payload = $this->sanitize_payload( $payload );
		$job_id = $this->repository->insert_job( $job_type, $payload, max( 1, $max_attempts ) );
		$this->log_queue( 'enqueue', $job_id > 0 ? 'success' : 'error', $job_id > 0 ? 'Queue job enqueued.' : 'Queue job enqueue failed.', array(
			'job_type'      => $job_type,
			'max_attempts'  => max( 1, $max_attempts ),
		), $job_id );
		return $job_id;
	}

	/**
	 * Process next pending job.
	 *
	 * @return array<string,mixed>
	 */
	public function process_next_job() {
		$job = $this->repository->claim_next_pending_job();
		if ( ! is_array( $job ) ) {
			return array(
				'success'   => true,
				'processed' => false,
				'message'   => __( 'No pending jobs.', 'super-mechanic' ),
			);
		}

		$job_id   = isset( $job['id'] ) ? absint( $job['id'] ) : 0;
		$job_type = isset( $job['job_type'] ) ? sanitize_key( (string) $job['job_type'] ) : '';
		$attempts = isset( $job['attempts'] ) ? absint( $job['attempts'] ) : 0;
		$payload  = $this->decode_payload( isset( $job['payload'] ) ? (string) $job['payload'] : '' );

		if ( $job_id <= 0 || '' === $job_type ) {
			return array(
				'success'   => false,
				'processed' => false,
				'message'   => __( 'Invalid queue job.', 'super-mechanic' ),
			);
		}

		try {
			$job_result = $this->execute_job( $job_type, $payload );
			$job_ok     = is_array( $job_result ) ? ! empty( $job_result['success'] ) : (bool) $job_result;

			if ( $job_ok ) {
				$this->mark_completed( $job_id );
				$this->log_queue( 'process', 'success', 'Queue job processed successfully.', array(
					'job_type' => $job_type,
					'attempts' => $attempts,
				), $job_id );
				return array(
					'success'   => true,
					'processed' => true,
					'job_id'    => $job_id,
					'job_type'  => $job_type,
				);
			}

			$error_message = is_array( $job_result ) && isset( $job_result['message'] )
				? (string) $job_result['message']
				: __( 'Queue job execution failed.', 'super-mechanic' );
			$retry_result = $this->schedule_retry( $job_id, $attempts, $error_message );
			$this->log_queue(
				! empty( $retry_result['scheduled'] ) ? 'retry' : 'failed',
				! empty( $retry_result['scheduled'] ) ? 'warning' : 'error',
				! empty( $retry_result['scheduled'] ) ? 'Queue job scheduled for retry.' : 'Queue job failed after retries.',
				array(
					'job_type'      => $job_type,
					'attempts'      => $attempts,
					'max_attempts'  => isset( $retry_result['max_attempts'] ) ? $retry_result['max_attempts'] : '',
					'next_retry_at' => isset( $retry_result['next_retry_at'] ) ? $retry_result['next_retry_at'] : '',
				),
				$job_id
			);

			return array(
				'success'   => false,
				'processed' => true,
				'job_id'    => $job_id,
				'job_type'  => $job_type,
				'message'   => $error_message,
				'retry'     => $retry_result,
			);
		} catch ( \Throwable $throwable ) {
			$retry_result = $this->schedule_retry( $job_id, $attempts, $throwable->getMessage() );
			error_log( sprintf( '[SM_QUEUE][ERROR] job_id=%d type=%s error=%s', $job_id, $job_type, $throwable->getMessage() ) );
			$this->log_queue(
				! empty( $retry_result['scheduled'] ) ? 'retry' : 'failed',
				'error',
				'Queue job execution exception.',
				array(
					'job_type'      => $job_type,
					'attempts'      => $attempts,
					'next_retry_at' => isset( $retry_result['next_retry_at'] ) ? $retry_result['next_retry_at'] : '',
				),
				$job_id
			);

			return array(
				'success'   => false,
				'processed' => true,
				'job_id'    => $job_id,
				'job_type'  => $job_type,
				'message'   => $throwable->getMessage(),
				'retry'     => $retry_result,
			);
		}
	}

	/**
	 * Process queue batch.
	 *
	 * @param int $limit Batch limit.
	 * @return array<string,mixed>
	 */
	public function process_batch( $limit = 10 ) {
		$limit      = max( 1, min( 100, absint( $limit ) ) );
		$processed  = 0;
		$successful = 0;
		$failed     = 0;

		for ( $i = 0; $i < $limit; $i++ ) {
			$result = $this->process_next_job();
			if ( empty( $result['processed'] ) ) {
				break;
			}

			++$processed;
			if ( ! empty( $result['success'] ) ) {
				++$successful;
			} else {
				++$failed;
			}
		}

		return array(
			'success'    => true,
			'processed'  => $processed,
			'completed'  => $successful,
			'failed'     => $failed,
		);
	}

	/**
	 * Mark one job as completed.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public function mark_completed( $job_id ) {
		return $this->repository->mark_completed( $job_id );
	}

	/**
	 * Mark one job as failed.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $error Error message.
	 * @return bool
	 */
	public function mark_failed( $job_id, $error ) {
		return $this->repository->mark_failed( $job_id, $error );
	}

	/**
	 * Schedule retry for one failed job.
	 *
	 * @param int    $job_id Job ID.
	 * @param int    $attempts Current attempts count.
	 * @param string $error Error message.
	 * @return array<string,mixed>
	 */
	public function schedule_retry( $job_id, $attempts, $error ) {
		$job_id    = absint( $job_id );
		$attempts  = absint( $attempts );
		$error     = sanitize_textarea_field( (string) $error );
		$job       = $this->repository->get_job_by_id( $job_id );
		$max_attempts = is_array( $job ) && isset( $job['max_attempts'] ) ? absint( $job['max_attempts'] ) : self::DEFAULT_MAX_ATTEMPTS;
		$max_attempts = max( 1, $max_attempts );

		if ( $attempts >= $max_attempts ) {
			$this->mark_failed( $job_id, $error );
			return array(
				'scheduled'    => false,
				'exhausted'    => true,
				'next_retry_at'=> '',
				'max_attempts' => $max_attempts,
			);
		}

		$delay_seconds = $this->get_retry_delay_seconds( $attempts );
		$next_retry_at = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay_seconds );
		$this->repository->schedule_retry( $job_id, $next_retry_at, $error );

		return array(
			'scheduled'     => true,
			'exhausted'     => false,
			'next_retry_at' => $next_retry_at,
			'delay_seconds' => $delay_seconds,
			'max_attempts'  => $max_attempts,
		);
	}

	/**
	 * Get retry delay by attempts count.
	 *
	 * @param int $attempts Current attempts count.
	 * @return int
	 */
	public function get_retry_delay_seconds( $attempts ) {
		$attempts = max( 1, absint( $attempts ) );
		if ( 1 === $attempts ) {
			return 5 * MINUTE_IN_SECONDS;
		}
		if ( 2 === $attempts ) {
			return 15 * MINUTE_IN_SECONDS;
		}

		return 30 * MINUTE_IN_SECONDS;
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public function run_cron_batch() {
		$this->process_batch( 10 );
	}

	/**
	 * Execute one queue job by type.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>|bool
	 */
	protected function execute_job( $job_type, array $payload ) {
		switch ( $job_type ) {
			case 'notification':
				$service = new Notification_Service();
				return $service->send_notification_from_queue(
					isset( $payload['type'] ) ? (string) $payload['type'] : '',
					isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0,
					isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array()
				);

			case 'webhook':
				$service = new Webhook_Service();
				return $service->dispatch_from_queue(
					isset( $payload['event_type'] ) ? (string) $payload['event_type'] : '',
					isset( $payload['payload'] ) && is_array( $payload['payload'] ) ? $payload['payload'] : array()
				);
		}

		return false;
	}

	/**
	 * Decode JSON payload.
	 *
	 * @param string $payload_json Raw payload.
	 * @return array<string,mixed>
	 */
	protected function decode_payload( $payload_json ) {
		$decoded = json_decode( (string) $payload_json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Sanitize payload recursively.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	protected function sanitize_payload( array $payload ) {
		$sanitized = array();
		foreach ( $payload as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_payload( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Write queue log using structured logging layer.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return void
	 */
	protected function log_queue( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		$logger = $this->get_log_service();
		if ( ! $logger instanceof Log_Service ) {
			return;
		}

		$logger->log_queue_event( $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Resolve log service lazily.
	 *
	 * @return Log_Service|null
	 */
	protected function get_log_service() {
		if ( $this->log_service instanceof Log_Service ) {
			return $this->log_service;
		}

		try {
			$this->log_service = new Log_Service();
			return $this->log_service;
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}
}
