<?php
/**
 * Public webhook repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles webhook endpoint persistence and event snapshot reads.
 */
class Public_Webhook_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['webhooks'];
	}

	/**
	 * Get one webhook row by id.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array<string,mixed>|null
	 */
	public function get_webhook( $webhook_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT *
			FROM {$this->get_table_name()}
			WHERE id = %d
			LIMIT 1",
			absint( $webhook_id )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List active webhooks by business id.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_webhooks_by_business( $business_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT *
			FROM {$this->get_table_name()}
			WHERE business_id = %d
			AND status = %s
			ORDER BY id ASC",
			max( 1, absint( $business_id ) ),
			'active'
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert webhook row.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int|false
	 */
	public function insert_webhook( array $data ) {
		global $wpdb;

		$payload = array(
			'business_id'       => max( 1, absint( isset( $data['business_id'] ) ? $data['business_id'] : 0 ) ),
			'name'              => sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ),
			'endpoint_url'      => esc_url_raw( isset( $data['endpoint_url'] ) ? (string) $data['endpoint_url'] : '' ),
			'secret_encrypted'  => sanitize_text_field( isset( $data['secret_encrypted'] ) ? (string) $data['secret_encrypted'] : '' ),
			'secret_hash'       => sanitize_text_field( isset( $data['secret_hash'] ) ? (string) $data['secret_hash'] : '' ),
			'events_json'       => sanitize_textarea_field( isset( $data['events_json'] ) ? (string) $data['events_json'] : '' ),
			'status'            => sanitize_key( isset( $data['status'] ) ? (string) $data['status'] : 'inactive' ),
			'last_used_at'      => sanitize_text_field( isset( $data['last_used_at'] ) ? (string) $data['last_used_at'] : '' ),
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			$payload,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update webhook row.
	 *
	 * @param int                 $webhook_id  Webhook ID.
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $data        Data.
	 * @return bool
	 */
	public function update_webhook( $webhook_id, $business_id, array $data ) {
		global $wpdb;

		$payload = array();

		if ( array_key_exists( 'name', $data ) ) {
			$payload['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'endpoint_url', $data ) ) {
			$payload['endpoint_url'] = esc_url_raw( (string) $data['endpoint_url'] );
		}
		if ( array_key_exists( 'secret_encrypted', $data ) ) {
			$payload['secret_encrypted'] = sanitize_text_field( (string) $data['secret_encrypted'] );
		}
		if ( array_key_exists( 'secret_hash', $data ) ) {
			$payload['secret_hash'] = sanitize_text_field( (string) $data['secret_hash'] );
		}
		if ( array_key_exists( 'events_json', $data ) ) {
			$payload['events_json'] = sanitize_textarea_field( (string) $data['events_json'] );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$payload['status'] = sanitize_key( (string) $data['status'] );
		}
		if ( array_key_exists( 'last_used_at', $data ) ) {
			$payload['last_used_at'] = sanitize_text_field( (string) $data['last_used_at'] );
		}

		if ( empty( $payload ) ) {
			return false;
		}

		$payload['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$payload,
			array(
				'id'          => absint( $webhook_id ),
				'business_id' => max( 1, absint( $business_id ) ),
			)
		);

		return false !== $result;
	}

	/**
	 * Load process snapshot by process ID.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string,mixed>|null
	 */
	public function get_process_snapshot( $process_id ) {
		global $wpdb;

		$tables  = Schema::get_tables();
		$query   = $wpdb->prepare(
			"SELECT p.id, p.business_id, p.client_id, p.vehicle_id, p.title, p.process_type, p.status, p.priority, p.opened_at, p.due_date, p.completed_at, p.created_at, p.updated_at
			FROM {$tables['processes']} p
			WHERE p.id = %d
			LIMIT 1",
			absint( $process_id )
		);
		$result  = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Load appointment snapshot by appointment ID.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return array<string,mixed>|null
	 */
	public function get_appointment_snapshot( $appointment_id ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$query  = $wpdb->prepare(
			"SELECT a.id, a.business_id, a.process_id, a.client_id, a.vehicle_id, a.assigned_to, a.appointment_status, a.appointment_date, a.start_at, a.created_at, a.updated_at
			FROM {$tables['appointments']} a
			WHERE a.id = %d
			LIMIT 1",
			absint( $appointment_id )
		);
		$row    = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}
}
