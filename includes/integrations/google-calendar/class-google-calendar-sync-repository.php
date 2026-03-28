<?php
/**
 * Google Calendar sync repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Persists appointment <> external event sync metadata.
 */
class Google_Calendar_Sync_Repository {
	/**
	 * Get sync table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['appointment_calendar_sync'];
	}

	/**
	 * Get sync row by appointment and provider.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param string $provider Provider key.
	 * @return array<string,mixed>|null
	 */
	public function get_by_appointment( $appointment_id, $provider ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT *
			FROM {$this->get_table_name()}
			WHERE appointment_id = %d AND provider = %s AND business_id = %d
			LIMIT 1",
			absint( $appointment_id ),
			sanitize_key( (string) $provider ),
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get sync row by provider/external_event_id.
	 *
	 * @param string $provider Provider key.
	 * @param string $external_event_id External event ID.
	 * @return array<string,mixed>|null
	 */
	public function get_by_external_event( $provider, $external_event_id ) {
		global $wpdb;

		$provider          = sanitize_key( (string) $provider );
		$external_event_id = sanitize_text_field( (string) $external_event_id );

		if ( '' === $external_event_id ) {
			return null;
		}

		$query = $wpdb->prepare(
			"SELECT *
			FROM {$this->get_table_name()}
			WHERE provider = %s AND external_event_id = %s AND business_id = %d
			LIMIT 1",
			$provider,
			$external_event_id,
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List linked rows for one provider.
	 *
	 * @param string $provider Provider key.
	 * @param int    $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_linked_rows( $provider, $limit = 100 ) {
		global $wpdb;

		$provider = sanitize_key( (string) $provider );
		$limit    = max( 1, min( 500, absint( $limit ) ) );
		$query    = $wpdb->prepare(
			"SELECT *
			FROM {$this->get_table_name()}
			WHERE provider = %s AND external_event_id <> '' AND business_id = %d
			ORDER BY updated_at DESC
			LIMIT %d",
			$provider,
			$this->resolve_business_id(),
			$limit
		);
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Upsert sync metadata by appointment/provider.
	 *
	 * @param int                 $appointment_id Appointment ID.
	 * @param string              $provider Provider.
	 * @param array<string,mixed> $data Sync data.
	 * @return bool
	 */
	public function upsert_by_appointment( $appointment_id, $provider, array $data ) {
		global $wpdb;

		$appointment_id = absint( $appointment_id );
		$provider       = sanitize_key( (string) $provider );
		$existing       = $this->get_by_appointment( $appointment_id, $provider );
		$payload        = array(
			'business_id'          => ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id(),
			'external_calendar_id' => isset( $data['external_calendar_id'] ) ? sanitize_text_field( (string) $data['external_calendar_id'] ) : '',
			'external_event_id'    => isset( $data['external_event_id'] ) ? sanitize_text_field( (string) $data['external_event_id'] ) : '',
			'sync_status'          => isset( $data['sync_status'] ) ? sanitize_key( (string) $data['sync_status'] ) : 'pending',
			'last_synced_at'       => isset( $data['last_synced_at'] ) ? sanitize_text_field( (string) $data['last_synced_at'] ) : '',
			'last_sync_hash'       => isset( $data['last_sync_hash'] ) ? sanitize_text_field( (string) $data['last_sync_hash'] ) : '',
			'last_error'           => isset( $data['last_error'] ) ? sanitize_textarea_field( (string) $data['last_error'] ) : '',
			'updated_at'           => current_time( 'mysql' ),
		);

		if ( is_array( $existing ) ) {
			$result = $wpdb->update(
				$this->get_table_name(),
				$payload,
				array(
					'id'          => absint( $existing['id'] ),
					'business_id' => $this->resolve_business_id(),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d', '%d' )
			);

			return false !== $result;
		}

		$payload['appointment_id'] = $appointment_id;
		$payload['provider']       = $provider;
		$payload['created_at']     = current_time( 'mysql' );

		$result = $wpdb->insert(
			$this->get_table_name(),
			$payload,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		$context_service = new Business_Context_Service();

		return absint( $context_service->resolve_business_id() );
	}
}
