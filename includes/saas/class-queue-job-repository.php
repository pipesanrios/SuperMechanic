<?php
/**
 * SaaS queue job repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence layer for future SaaS queue jobs.
 */
class Queue_Job_Repository {
	/**
	 * Get queue table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['saas_queue_jobs'];
	}

	/**
	 * Persist one normalized queue job.
	 *
	 * @param array<string,mixed> $job Queue job.
	 * @return int Inserted row ID.
	 */
	public function create_job( array $job ) {
		global $wpdb;

		$contract = new Queue_Job_Contract( $job );
		if ( ! $contract->is_valid() ) {
			return 0;
		}

		$job       = $contract->to_array();
		$payload   = $this->encode_payload( isset( $job['payload'] ) && is_array( $job['payload'] ) ? $job['payload'] : array() );
		$created   = $this->normalize_datetime( isset( $job['created_at'] ) ? $job['created_at'] : '' );
		$scheduled = $this->normalize_datetime( isset( $job['scheduled_at'] ) ? $job['scheduled_at'] : '' );

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'job_id'       => $job['job_id'],
				'job_type'     => $job['job_type'],
				'business_id'  => absint( $job['business_id'] ),
				'tenant_id'    => isset( $job['tenant_id'] ) ? $job['tenant_id'] : null,
				'payload_json' => $payload,
				'status'       => $job['status'],
				'attempts'     => absint( $job['attempts'] ),
				'max_attempts' => absint( $job['max_attempts'] ),
				'scheduled_at' => $scheduled,
				'available_at' => $scheduled,
				'locked_at'    => null,
				'lock_token'   => null,
				'last_error'   => isset( $job['last_error'] ) ? sanitize_textarea_field( (string) $job['last_error'] ) : '',
				'created_at'   => $created,
				'updated_at'   => $this->current_datetime(),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Get one job by public job ID.
	 *
	 * @param string $job_id Job ID.
	 * @return array<string,mixed>|null
	 */
	public function get_job_by_id( $job_id ) {
		global $wpdb;

		$job_id = sanitize_key( (string) $job_id );
		if ( '' === $job_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, job_id, job_type, business_id, tenant_id, payload_json, status, attempts, max_attempts, scheduled_at, available_at, locked_at, lock_token, last_error, created_at, updated_at
				FROM {$this->get_table_name()}
				WHERE job_id = %s
				LIMIT 1",
				$job_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->normalize_row( $row ) : null;
	}

	/**
	 * List jobs by optional filters.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_jobs( array $filters = array() ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['business_id'] ) ) {
			$where[]  = 'business_id = %d';
			$params[] = absint( $filters['business_id'] );
		}

		if ( ! empty( $filters['job_type'] ) ) {
			$where[]  = 'job_type = %s';
			$params[] = sanitize_key( (string) $filters['job_type'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $filters['status'] );
		}

		$limit  = isset( $filters['limit'] ) ? min( 200, max( 1, absint( $filters['limit'] ) ) ) : 50;
		$offset = isset( $filters['offset'] ) ? absint( $filters['offset'] ) : 0;

		$sql = "SELECT id, job_id, job_type, business_id, tenant_id, payload_json, status, attempts, max_attempts, scheduled_at, available_at, locked_at, lock_token, last_error, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY id DESC
			LIMIT %d OFFSET %d';

		$params[] = $limit;
		$params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize_row' ), $rows );
	}

	/**
	 * Get next available pending/retry job.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @return array<string,mixed>|null
	 */
	public function get_next_available_job( array $filters = array() ) {
		global $wpdb;

		$where  = array(
			'status IN (%s, %s)',
			'(available_at IS NULL OR available_at <= %s)',
		);
		$params = array(
			Queue_Job_Contract::STATUS_PENDING,
			Queue_Job_Contract::STATUS_RETRY_SCHEDULED,
			$this->current_datetime(),
		);

		if ( ! empty( $filters['business_id'] ) ) {
			$where[]  = 'business_id = %d';
			$params[] = absint( $filters['business_id'] );
		}

		if ( ! empty( $filters['job_type'] ) ) {
			$where[]  = 'job_type = %s';
			$params[] = sanitize_key( (string) $filters['job_type'] );
		}

		$sql = "SELECT id, job_id, job_type, business_id, tenant_id, payload_json, status, attempts, max_attempts, scheduled_at, available_at, locked_at, lock_token, last_error, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY available_at ASC, id ASC
			LIMIT 1';

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $row ) ? $this->normalize_row( $row ) : null;
	}

	/**
	 * Claim one available job for manual processing.
	 *
	 * @param string $job_id Job ID.
	 * @param string $lock_token Lock token.
	 * @return bool
	 */
	public function claim_job( $job_id, $lock_token ) {
		global $wpdb;

		$job_id     = sanitize_key( (string) $job_id );
		$lock_token = sanitize_text_field( (string) $lock_token );
		if ( '' === $job_id || '' === $lock_token ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->get_table_name()}
				SET status = %s,
					locked_at = %s,
					lock_token = %s,
					last_error = NULL,
					updated_at = %s
				WHERE job_id = %s
				AND status IN (%s, %s)
				AND (available_at IS NULL OR available_at <= %s)
				LIMIT 1",
				Queue_Job_Contract::STATUS_RUNNING,
				$this->current_datetime(),
				$lock_token,
				$this->current_datetime(),
				$job_id,
				Queue_Job_Contract::STATUS_PENDING,
				Queue_Job_Contract::STATUS_RETRY_SCHEDULED,
				$this->current_datetime()
			)
		);

		return false !== $updated && $updated > 0;
	}

	/**
	 * Mark one job as running with a lock token.
	 *
	 * @param string $job_id Job ID.
	 * @param string $lock_token Lock token.
	 * @return bool
	 */
	public function mark_running( $job_id, $lock_token ) {
		return $this->claim_job( $job_id, $lock_token );
	}

	/**
	 * Release lock without changing current status.
	 *
	 * @param string $job_id Job ID.
	 * @return bool
	 */
	public function release_lock( $job_id ) {
		return $this->update_status(
			$job_id,
			$this->resolve_current_status( $job_id ),
			array(
				'locked_at'  => '',
				'lock_token' => '',
			)
		);
	}

	/**
	 * Update attempts count.
	 *
	 * @param string $job_id Job ID.
	 * @param int    $attempts Attempts.
	 * @return bool
	 */
	public function update_attempts( $job_id, $attempts ) {
		$status = $this->resolve_current_status( $job_id );

		return $this->update_status(
			$job_id,
			$status,
			array(
				'attempts' => absint( $attempts ),
			)
		);
	}

	/**
	 * Update job status and optional metadata.
	 *
	 * @param string              $job_id Job ID.
	 * @param string              $status Status.
	 * @param array<string,mixed> $meta Metadata fields.
	 * @return bool
	 */
	public function update_status( $job_id, $status, array $meta = array() ) {
		global $wpdb;

		$job_id = sanitize_key( (string) $job_id );
		$status = sanitize_key( (string) $status );
		if ( '' === $job_id || ! in_array( $status, Queue_Job_Contract::get_supported_statuses(), true ) ) {
			return false;
		}

		$data    = array(
			'status'     => $status,
			'updated_at' => $this->current_datetime(),
		);
		$formats = array( '%s', '%s' );

		$allowed_datetime = array( 'scheduled_at', 'available_at', 'locked_at' );
		foreach ( $allowed_datetime as $field ) {
			if ( array_key_exists( $field, $meta ) ) {
				$data[ $field ] = '' === (string) $meta[ $field ] ? null : $this->normalize_datetime( $meta[ $field ] );
				$formats[]      = '%s';
			}
		}

		if ( array_key_exists( 'lock_token', $meta ) ) {
			$data['lock_token'] = '' === (string) $meta['lock_token'] ? null : sanitize_text_field( (string) $meta['lock_token'] );
			$formats[]          = '%s';
		}

		if ( array_key_exists( 'last_error', $meta ) ) {
			$data['last_error'] = sanitize_textarea_field( (string) $meta['last_error'] );
			$formats[]          = '%s';
		}

		if ( array_key_exists( 'attempts', $meta ) ) {
			$data['attempts'] = absint( $meta['attempts'] );
			$formats[]        = '%d';
		}

		if ( array_key_exists( 'max_attempts', $meta ) ) {
			$data['max_attempts'] = max( 1, absint( $meta['max_attempts'] ) );
			$formats[]            = '%d';
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'job_id' => $job_id ),
			$formats,
			array( '%s' )
		);

		return false !== $updated;
	}

	/**
	 * Resolve current persisted status.
	 *
	 * @param string $job_id Job ID.
	 * @return string
	 */
	protected function resolve_current_status( $job_id ) {
		$job = $this->get_job_by_id( $job_id );
		if ( is_array( $job ) && ! empty( $job['status'] ) && in_array( (string) $job['status'], Queue_Job_Contract::get_supported_statuses(), true ) ) {
			return (string) $job['status'];
		}

		return Queue_Job_Contract::STATUS_PENDING;
	}

	/**
	 * Mark one job as failed.
	 *
	 * @param string $job_id Job ID.
	 * @param string $error Error message.
	 * @return bool
	 */
	public function mark_failed( $job_id, $error ) {
		return $this->update_status(
			$job_id,
			Queue_Job_Contract::STATUS_FAILED,
			array(
				'last_error' => $error,
				'locked_at'  => '',
				'lock_token' => '',
			)
		);
	}

	/**
	 * Mark one job as completed.
	 *
	 * @param string $job_id Job ID.
	 * @return bool
	 */
	public function mark_completed( $job_id ) {
		return $this->update_status(
			$job_id,
			Queue_Job_Contract::STATUS_COMPLETED,
			array(
				'last_error' => '',
				'locked_at'  => '',
				'lock_token' => '',
			)
		);
	}

	/**
	 * Schedule retry for one job.
	 *
	 * @param string $job_id Job ID.
	 * @param string $available_at Available timestamp.
	 * @param string $error Error message.
	 * @return bool
	 */
	public function schedule_retry( $job_id, $available_at, $error = '' ) {
		return $this->update_status(
			$job_id,
			Queue_Job_Contract::STATUS_RETRY_SCHEDULED,
			array(
				'available_at' => $available_at,
				'last_error'   => $error,
				'locked_at'    => '',
				'lock_token'   => '',
			)
		);
	}

	/**
	 * Normalize one DB row.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	protected function normalize_row( array $row ) {
		$row['id']           = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$row['business_id']  = isset( $row['business_id'] ) ? absint( $row['business_id'] ) : 0;
		$row['attempts']     = isset( $row['attempts'] ) ? absint( $row['attempts'] ) : 0;
		$row['max_attempts'] = isset( $row['max_attempts'] ) ? absint( $row['max_attempts'] ) : 0;
		$row['payload']      = $this->decode_payload( isset( $row['payload_json'] ) ? $row['payload_json'] : '' );

		return $row;
	}

	/**
	 * Encode payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	protected function encode_payload( array $payload ) {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );

		return false === $encoded ? '{}' : (string) $encoded;
	}

	/**
	 * Decode payload.
	 *
	 * @param string $payload_json Payload JSON.
	 * @return array<string,mixed>
	 */
	protected function decode_payload( $payload_json ) {
		$decoded = json_decode( (string) $payload_json, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Current MySQL datetime.
	 *
	 * @return string
	 */
	protected function current_datetime() {
		return function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Normalize datetime for MySQL storage.
	 *
	 * @param string $value Datetime.
	 * @return string|null
	 */
	protected function normalize_datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $this->current_datetime();
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
