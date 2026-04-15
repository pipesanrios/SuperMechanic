<?php
/**
 * CRM alerts repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM alerts persistence.
 */
class Crm_Alert_Repository {
	/**
	 * Get alerts table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['crm_alerts'];
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

	/**
	 * Required API for 39E-3.
	 *
	 * Returns grouped active alerts for the active business:
	 * `crm_pipeline_id => [ alert_row, ... ]`.
	 *
	 * @param array<int,int> $pipeline_ids Pipeline IDs.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public function get_active_alerts_by_pipeline_ids( array $pipeline_ids ) {
		return $this->get_active_alerts_by_pipeline_ids_for_business( $this->resolve_business_id(), $pipeline_ids );
	}

	/**
	 * Get grouped active alerts for explicit business.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Pipeline IDs.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public function get_active_alerts_by_pipeline_ids_for_business( $business_id, array $pipeline_ids ) {
		global $wpdb;

		$business_id  = absint( $business_id );
		$pipeline_ids = array_values( array_unique( array_filter( array_map( 'absint', $pipeline_ids ) ) ) );
		if ( empty( $pipeline_ids ) || $business_id <= 0 ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $pipeline_ids ), '%d' ) );
		$params       = array_merge(
			array(
				$business_id,
				'active',
			),
			$pipeline_ids
		);

		$sql   = "SELECT id, business_id, crm_pipeline_id, alert_type, status, message, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND crm_pipeline_id IN ({$placeholders})
			ORDER BY crm_pipeline_id ASC, id ASC";
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );
		$map   = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$pipeline_id = isset( $row['crm_pipeline_id'] ) ? absint( $row['crm_pipeline_id'] ) : 0;
				if ( $pipeline_id <= 0 ) {
					continue;
				}

				if ( ! isset( $map[ $pipeline_id ] ) ) {
					$map[ $pipeline_id ] = array();
				}

				$map[ $pipeline_id ][] = $row;
			}
		}

		return $map;
	}

	/**
	 * Get active alerts map by pipeline IDs keeping one row per type.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Pipeline IDs.
	 * @return array<int,array<string,array<string,mixed>>>
	 */
	public function get_active_alerts_map_by_pipeline_ids( $business_id, array $pipeline_ids ) {
		$grouped = $this->get_active_alerts_by_pipeline_ids_for_business( $business_id, $pipeline_ids );
		$map     = array();

		foreach ( $grouped as $pipeline_id => $rows ) {
			foreach ( $rows as $row ) {
				$alert_type = isset( $row['alert_type'] ) ? sanitize_key( (string) $row['alert_type'] ) : '';
				if ( '' === $alert_type ) {
					continue;
				}

				if ( ! isset( $map[ $pipeline_id ] ) ) {
					$map[ $pipeline_id ] = array();
				}

				if ( ! isset( $map[ $pipeline_id ][ $alert_type ] ) ) {
					$map[ $pipeline_id ][ $alert_type ] = $row;
					continue;
				}

				// Functional uniqueness guard: keep first active, resolve duplicates.
				$this->resolve_alert_by_id( (int) $row['id'] );
			}
		}

		return $map;
	}

	/**
	 * Create active alert.
	 *
	 * @param int    $business_id Business ID.
	 * @param int    $pipeline_id Pipeline ID.
	 * @param string $alert_type Alert type.
	 * @param string $message Deterministic message.
	 * @return int|false
	 */
	public function create_active_alert( $business_id, $pipeline_id, $alert_type, $message ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'business_id'      => absint( $business_id ),
				'crm_pipeline_id'  => absint( $pipeline_id ),
				'alert_type'       => sanitize_key( (string) $alert_type ),
				'status'           => 'active',
				'message'          => sanitize_text_field( (string) $message ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update active alert message only when changed.
	 *
	 * @param int    $id Alert ID.
	 * @param string $message Message.
	 * @return bool True when write happened.
	 */
	public function update_active_alert_message_if_changed( $id, $message ) {
		global $wpdb;

		$id       = absint( $id );
		$message  = sanitize_text_field( (string) $message );
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, message FROM {$this->get_table_name()} WHERE id = %d AND status = %s LIMIT 1",
				$id,
				'active'
			),
			ARRAY_A
		);

		if ( ! is_array( $existing ) ) {
			return false;
		}

		if ( isset( $existing['message'] ) && (string) $existing['message'] === $message ) {
			return false;
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'message'    => $message,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $id,
				'status' => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Resolve alert by ID.
	 *
	 * @param int $id Alert ID.
	 * @return bool
	 */
	public function resolve_alert_by_id( $id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => 'resolved',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => absint( $id ),
				'status' => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Resolve all active alerts for one pipeline scoped to active business.
	 *
	 * @param int $crm_pipeline_id Pipeline ID.
	 * @return bool
	 */
	public function resolve_active_alerts_by_pipeline_id( $crm_pipeline_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => 'resolved',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'crm_pipeline_id' => absint( $crm_pipeline_id ),
				'business_id'     => $this->resolve_business_id(),
				'status'          => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		return false !== $result;
	}
}
