<?php
/**
 * Queue repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Queue;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for queue jobs.
 */
class Queue_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Queue_Installer
	 */
	protected $installer;

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Queue_Installer|null          $installer Installer.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Queue_Installer $installer = null, Business_Context_Service $business_context_service = null ) {
		$this->installer                = $installer ? $installer : new Queue_Installer();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->installer->ensure_table();
	}

	/**
	 * Insert one queue job.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Payload.
	 * @return int
	 */
	public function insert_job( $job_type, array $payload, $max_attempts = 3 ) {
		global $wpdb;

		$job_type = sanitize_key( (string) $job_type );
		if ( '' === $job_type ) {
			return 0;
		}
		$max_attempts = max( 1, absint( $max_attempts ) );

		$encoded_payload = wp_json_encode( $payload );
		if ( false === $encoded_payload ) {
			$encoded_payload = '{}';
		}

		$inserted = $wpdb->insert(
			$this->installer->get_table_name(),
			array(
				'business_id' => $this->resolve_business_id(),
				'job_type'    => $job_type,
				'payload'     => $encoded_payload,
				'status'      => 'pending',
				'attempts'    => 0,
				'max_attempts'=> $max_attempts,
				'next_retry_at' => null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Claim the next pending job.
	 *
	 * @return array<string,mixed>|null
	 */
	public function claim_next_pending_job() {
		global $wpdb;

		$table = $this->installer->get_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, business_id, job_type, payload, status, attempts, max_attempts, next_retry_at, created_at, processed_at, last_error
				FROM {$table}
				WHERE status = %s
				AND (next_retry_at IS NULL OR next_retry_at <= %s)
				ORDER BY id ASC
				LIMIT 1",
				'pending',
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$job_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		if ( $job_id <= 0 ) {
			return null;
		}

		$updated = $wpdb->update(
			$table,
			array(
				'status'    => 'processing',
				'attempts'  => absint( $row['attempts'] ) + 1,
				'last_error'=> null,
				'processed_at' => null,
			),
			array(
				'id'     => $job_id,
				'status' => 'pending',
			),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated || 0 === $updated ) {
			return null;
		}

		return $this->get_job_by_id( $job_id );
	}

	/**
	 * Get job by id.
	 *
	 * @param int $job_id Job ID.
	 * @return array<string,mixed>|null
	 */
	public function get_job_by_id( $job_id ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( $job_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, business_id, job_type, payload, status, attempts, max_attempts, next_retry_at, created_at, processed_at, last_error
				FROM {$this->installer->get_table_name()}
				WHERE id = %d
				LIMIT 1",
				$job_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Mark job as completed.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public function mark_completed( $job_id ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( $job_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->installer->get_table_name(),
			array(
				'status'       => 'completed',
				'processed_at' => current_time( 'mysql' ),
				'last_error'   => null,
				'next_retry_at'=> null,
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Mark job as failed.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $error Error message.
	 * @return bool
	 */
	public function mark_failed( $job_id, $error ) {
		global $wpdb;

		$job_id = absint( $job_id );
		$error  = sanitize_textarea_field( (string) $error );
		if ( $job_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->installer->get_table_name(),
			array(
				'status'       => 'failed',
				'processed_at' => current_time( 'mysql' ),
				'last_error'   => $error,
				'next_retry_at'=> null,
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Schedule retry for one job.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $next_retry_at Retry timestamp (mysql datetime).
	 * @param string $error Error message.
	 * @return bool
	 */
	public function schedule_retry( $job_id, $next_retry_at, $error ) {
		global $wpdb;

		$job_id        = absint( $job_id );
		$next_retry_at = sanitize_text_field( (string) $next_retry_at );
		$error         = sanitize_textarea_field( (string) $error );
		if ( $job_id <= 0 || '' === $next_retry_at ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->installer->get_table_name(),
			array(
				'status'        => 'pending',
				'next_retry_at' => $next_retry_at,
				'last_error'    => $error,
				'processed_at'  => null,
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Resolve business scope.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		$business_id = absint( $this->business_context_service->resolve_business_id_for_user( get_current_user_id() ) );
		return $business_id > 0 ? $business_id : 1;
	}
}
