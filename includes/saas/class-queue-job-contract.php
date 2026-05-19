<?php
/**
 * Passive queue job contract.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized async job structure for future SaaS queues.
 */
class Queue_Job_Contract {
	const JOB_INVENTORY_IMPORT         = 'inventory_import';
	const JOB_INVENTORY_CONNECTOR_SYNC = 'inventory_connector_sync';
	const JOB_EMAIL_DELIVERY           = 'email_delivery';
	const JOB_WEBHOOK_DELIVERY         = 'webhook_delivery';
	const JOB_GOOGLE_CALENDAR_SYNC     = 'google_calendar_sync';
	const JOB_PDF_GENERATION           = 'pdf_generation';

	const STATUS_PENDING         = 'pending';
	const STATUS_RUNNING         = 'running';
	const STATUS_COMPLETED       = 'completed';
	const STATUS_FAILED          = 'failed';
	const STATUS_RETRY_SCHEDULED = 'retry_scheduled';
	const STATUS_CANCELLED       = 'cancelled';

	const DEFAULT_MAX_ATTEMPTS = 3;

	/**
	 * Normalized job fields.
	 *
	 * @var array<string,mixed>
	 */
	protected $job;

	/**
	 * Validation errors.
	 *
	 * @var string[]
	 */
	protected $errors = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $job Raw job data.
	 */
	public function __construct( array $job = array() ) {
		$this->job = $this->normalize( $job );
		$this->errors = $this->validate( $this->job );
	}

	/**
	 * Build a normalized job from raw data.
	 *
	 * @param array<string,mixed> $job Raw job data.
	 * @return array<string,mixed>
	 */
	public function normalize( array $job ) {
		$job_type    = isset( $job['job_type'] ) ? sanitize_key( (string) $job['job_type'] ) : '';
		$business_id = isset( $job['business_id'] ) ? absint( $job['business_id'] ) : 0;
		$tenant_id   = isset( $job['tenant_id'] ) && '' !== (string) $job['tenant_id'] ? sanitize_key( (string) $job['tenant_id'] ) : null;
		$status      = isset( $job['status'] ) ? sanitize_key( (string) $job['status'] ) : self::STATUS_PENDING;
		$attempts    = isset( $job['attempts'] ) ? absint( $job['attempts'] ) : 0;
		$max_attempts = isset( $job['max_attempts'] ) ? absint( $job['max_attempts'] ) : self::DEFAULT_MAX_ATTEMPTS;
		$created_at   = isset( $job['created_at'] ) ? sanitize_text_field( (string) $job['created_at'] ) : gmdate( 'c' );
		$scheduled_at = isset( $job['scheduled_at'] ) ? sanitize_text_field( (string) $job['scheduled_at'] ) : $created_at;
		$last_error   = isset( $job['last_error'] ) ? sanitize_textarea_field( (string) $job['last_error'] ) : '';
		$payload      = isset( $job['payload'] ) ? $job['payload'] : array();
		$job_id       = isset( $job['job_id'] ) ? sanitize_key( (string) $job['job_id'] ) : '';

		if ( '' === $job_id ) {
			$job_id = $this->generate_job_id( $job_type, $business_id );
		}

		if ( $max_attempts <= 0 ) {
			$max_attempts = self::DEFAULT_MAX_ATTEMPTS;
		}

		if ( ! in_array( $status, self::get_supported_statuses(), true ) ) {
			$status = self::STATUS_PENDING;
		}

		if ( is_array( $payload ) ) {
			$payload = $this->sanitize_payload( $payload );
		}

		return array(
			'job_id'       => $job_id,
			'job_type'     => $job_type,
			'business_id'  => $business_id,
			'tenant_id'    => $tenant_id,
			'payload'      => $payload,
			'status'       => $status,
			'attempts'     => $attempts,
			'max_attempts' => $max_attempts,
			'scheduled_at' => $scheduled_at,
			'created_at'   => $created_at,
			'last_error'   => $last_error,
		);
	}

	/**
	 * Get supported future job types.
	 *
	 * @return string[]
	 */
	public static function get_supported_job_types() {
		return array(
			self::JOB_INVENTORY_IMPORT,
			self::JOB_INVENTORY_CONNECTOR_SYNC,
			self::JOB_EMAIL_DELIVERY,
			self::JOB_WEBHOOK_DELIVERY,
			self::JOB_GOOGLE_CALENDAR_SYNC,
			self::JOB_PDF_GENERATION,
		);
	}

	/**
	 * Get supported job statuses.
	 *
	 * @return string[]
	 */
	public static function get_supported_statuses() {
		return array(
			self::STATUS_PENDING,
			self::STATUS_RUNNING,
			self::STATUS_COMPLETED,
			self::STATUS_FAILED,
			self::STATUS_RETRY_SCHEDULED,
			self::STATUS_CANCELLED,
		);
	}

	/**
	 * Determine whether the job is valid.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return empty( $this->errors );
	}

	/**
	 * Get validation errors.
	 *
	 * @return string[]
	 */
	public function get_validation_errors() {
		return $this->errors;
	}

	/**
	 * Export normalized job.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return $this->job;
	}

	/**
	 * Validate normalized job data.
	 *
	 * @param array<string,mixed> $job Normalized job.
	 * @return string[]
	 */
	protected function validate( array $job ) {
		$errors = array();

		if ( empty( $job['job_id'] ) ) {
			$errors[] = 'job_id_required';
		}

		if ( empty( $job['job_type'] ) || ! in_array( (string) $job['job_type'], self::get_supported_job_types(), true ) ) {
			$errors[] = 'unsupported_job_type';
		}

		if ( empty( $job['business_id'] ) || absint( $job['business_id'] ) <= 0 ) {
			$errors[] = 'business_id_required';
		}

		if ( ! is_array( $job['payload'] ) ) {
			$errors[] = 'payload_must_be_array';
		}

		if ( absint( $job['attempts'] ) > absint( $job['max_attempts'] ) ) {
			$errors[] = 'attempts_exceed_max_attempts';
		}

		return $errors;
	}

	/**
	 * Sanitize a payload recursively while preserving basic scalar types.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	protected function sanitize_payload( array $payload ) {
		$sanitized = array();

		foreach ( $payload as $key => $value ) {
			$normalized_key = sanitize_key( (string) $key );
			if ( '' === $normalized_key ) {
				continue;
			}

			$sanitized[ $normalized_key ] = $this->sanitize_payload_value( $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize one payload value.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	protected function sanitize_payload_value( $value ) {
		if ( is_array( $value ) ) {
			return $this->sanitize_payload( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		return null;
	}

	/**
	 * Generate a local ephemeral job ID for passive diagnostics.
	 *
	 * @param string $job_type Job type.
	 * @param int    $business_id Business ID.
	 * @return string
	 */
	protected function generate_job_id( $job_type, $business_id ) {
		$base = sanitize_key( (string) $job_type ) . ':' . absint( $business_id ) . ':' . microtime( true ) . ':' . uniqid( '', true );

		return 'smq_' . md5( $base );
	}
}
