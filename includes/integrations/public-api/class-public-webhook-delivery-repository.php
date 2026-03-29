<?php
/**
 * Public webhook delivery repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles outbound delivery queue persistence.
 */
class Public_Webhook_Delivery_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['webhook_deliveries'];
	}

	/**
	 * Queue one delivery row.
	 *
	 * @param array<string,mixed> $data Queue payload.
	 * @return int|false
	 */
	public function queue_delivery( array $data ) {
		global $wpdb;

		$payload = array(
			'business_id'    => max( 1, absint( isset( $data['business_id'] ) ? $data['business_id'] : 0 ) ),
			'webhook_id'     => absint( isset( $data['webhook_id'] ) ? $data['webhook_id'] : 0 ),
			'event_key'      => sanitize_text_field( isset( $data['event_key'] ) ? (string) $data['event_key'] : '' ),
			'event_id'       => sanitize_text_field( isset( $data['event_id'] ) ? (string) $data['event_id'] : '' ),
			'payload_json'   => isset( $data['payload_json'] ) ? (string) $data['payload_json'] : '',
			'status'         => 'pending',
			'attempts'       => 0,
			'next_retry_at'  => current_time( 'mysql' ),
			'last_http_code' => 0,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			$payload,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false !== $inserted ) {
			return (int) $wpdb->insert_id;
		}

		$existing_id = $this->find_delivery_id_by_webhook_and_event( $payload['webhook_id'], $payload['event_id'] );
		if ( $existing_id > 0 ) {
			return $existing_id;
		}

		return false;
	}

	/**
	 * Find queued row by unique idempotency key.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $event_id   Event ID.
	 * @return int
	 */
	public function find_delivery_id_by_webhook_and_event( $webhook_id, $event_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$this->get_table_name()}
			WHERE webhook_id = %d
			AND event_id = %s
			LIMIT 1",
			absint( $webhook_id ),
			sanitize_text_field( (string) $event_id )
		);
		$id    = $wpdb->get_var( $query );

		return absint( $id );
	}

	/**
	 * Lock one pending/retrying delivery row for processing.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @return bool
	 */
	public function lock_delivery_for_processing( $delivery_id ) {
		global $wpdb;

		$table  = $this->get_table_name();
		$now    = current_time( 'mysql' );
		$query  = $wpdb->prepare(
			"UPDATE {$table}
			SET status = %s, updated_at = %s
			WHERE id = %d
			AND status IN ('pending', 'retrying')
			AND (next_retry_at = '' OR next_retry_at IS NULL OR next_retry_at <= %s)",
			'processing',
			$now,
			absint( $delivery_id ),
			$now
		);
		$result = $wpdb->query( $query );

		return false !== $result && $result > 0;
	}

	/**
	 * Get delivery row with webhook data.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @return array<string,mixed>|null
	 */
	public function get_delivery_with_webhook( $delivery_id ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$query  = $wpdb->prepare(
			"SELECT d.*, w.endpoint_url, w.secret_encrypted, w.secret_hash, w.status AS webhook_status
			FROM {$tables['webhook_deliveries']} d
			INNER JOIN {$tables['webhooks']} w ON w.id = d.webhook_id
			WHERE d.id = %d
			LIMIT 1",
			absint( $delivery_id )
		);
		$row    = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Record one processing attempt.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $attempts    Attempts count.
	 * @return bool
	 */
	public function record_attempt( $delivery_id, $attempts ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'attempts'        => max( 1, absint( $attempts ) ),
				'last_attempt_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array(
				'id' => absint( $delivery_id ),
			),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark one delivery as delivered.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $http_code   HTTP code.
	 * @return bool
	 */
	public function mark_delivered( $delivery_id, $http_code ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'         => 'delivered',
				'last_http_code' => max( 0, absint( $http_code ) ),
				'last_error'     => '',
				'next_retry_at'  => null,
				'delivered_at'   => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => absint( $delivery_id ) ),
			array( '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark one delivery for retry.
	 *
	 * @param int    $delivery_id  Delivery ID.
	 * @param int    $http_code    HTTP code.
	 * @param string $error        Error.
	 * @param string $next_retry_at Next retry datetime.
	 * @return bool
	 */
	public function mark_retrying( $delivery_id, $http_code, $error, $next_retry_at ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'         => 'retrying',
				'last_http_code' => max( 0, absint( $http_code ) ),
				'last_error'     => sanitize_textarea_field( (string) $error ),
				'next_retry_at'  => sanitize_text_field( (string) $next_retry_at ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => absint( $delivery_id ) ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark one delivery as failed.
	 *
	 * @param int    $delivery_id Delivery ID.
	 * @param int    $http_code   HTTP code.
	 * @param string $error       Error.
	 * @return bool
	 */
	public function mark_failed( $delivery_id, $http_code, $error ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'         => 'failed',
				'last_http_code' => max( 0, absint( $http_code ) ),
				'last_error'     => sanitize_textarea_field( (string) $error ),
				'next_retry_at'  => null,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => absint( $delivery_id ) ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update webhook usage timestamp.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return void
	 */
	public function touch_webhook_usage( $webhook_id ) {
		global $wpdb;
		$tables = Schema::get_tables();

		$wpdb->update(
			$tables['webhooks'],
			array(
				'last_used_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array(
				'id' => absint( $webhook_id ),
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
