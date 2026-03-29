<?php
/**
 * Client vehicle relation repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Relations;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client vehicle relation persistence.
 */
class Client_Vehicle_Repository {
	/**
	 * Get the relation table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['client_vehicles'];
	}

	/**
	 * Get the clients table name.
	 *
	 * @return string
	 */
	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get the vehicles table name.
	 *
	 * @return string
	 */
	protected function get_vehicles_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	/**
	 * Get relations for a vehicle.
	 *
	 * @param int                 $vehicle_id Vehicle ID.
	 * @param array<string, mixed> $args      Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_vehicle( $vehicle_id, $args = array() ) {
		global $wpdb;

		$args            = wp_parse_args(
			$args,
			array(
				'current_only' => false,
			)
		);
		$table           = $this->get_table_name();
		$clients_table   = $this->get_clients_table_name();
		$current_clause  = $args['current_only'] ? 'AND cv.end_date IS NULL' : '';
		$query           = $wpdb->prepare(
			"SELECT cv.*, c.first_name, c.last_name, c.email
			FROM {$table} cv
			LEFT JOIN {$clients_table} c ON c.id = cv.client_id
			WHERE cv.vehicle_id = %d
			AND cv.business_id = %d {$current_clause}
			ORDER BY cv.is_primary DESC, cv.start_date DESC, cv.id DESC",
			absint( $vehicle_id ),
			$this->resolve_business_id()
		);
		$rows            = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get relations for a client.
	 *
	 * @param int                 $client_id Client ID.
	 * @param array<string, mixed> $args     Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_client( $client_id, $args = array() ) {
		global $wpdb;

		$args             = wp_parse_args(
			$args,
			array(
				'current_only' => false,
			)
		);
		$table            = $this->get_table_name();
		$vehicles_table   = $this->get_vehicles_table_name();
		$current_clause   = $args['current_only'] ? 'AND cv.end_date IS NULL' : '';
		$query            = $wpdb->prepare(
			"SELECT cv.*, v.make, v.model, v.plate, v.vin
			FROM {$table} cv
			LEFT JOIN {$vehicles_table} v ON v.id = cv.vehicle_id
			WHERE cv.client_id = %d
			AND cv.business_id = %d {$current_clause}
			ORDER BY cv.is_primary DESC, cv.start_date DESC, cv.id DESC",
			absint( $client_id ),
			$this->resolve_business_id()
		);
		$rows             = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create a new relation.
	 *
	 * @param array<string, mixed> $data Relation data.
	 * @return int|false
	 */
	public function create_relation( $data ) {
		global $wpdb;

		$data['created_at'] = current_time( 'mysql' );
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$result = $wpdb->insert(
			$this->get_table_name(),
			$data,
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			)
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * End a relation.
	 *
	 * @param int         $relation_id Relation ID.
	 * @param string|null $end_date    End date.
	 * @return bool
	 */
	public function end_relation( $relation_id, $end_date = null ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'end_date'   => $end_date ? $end_date : current_time( 'Y-m-d' ),
				'is_primary' => 0,
			),
			array(
				'id'          => absint( $relation_id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%s', '%d' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get the current owner relation for a vehicle.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array<string, mixed>|null
	 */
	public function get_current_owner( $vehicle_id ) {
		global $wpdb;

		$table         = $this->get_table_name();
		$clients_table = $this->get_clients_table_name();
		$query         = $wpdb->prepare(
			"SELECT cv.*, c.first_name, c.last_name, c.email
			FROM {$table} cv
			LEFT JOIN {$clients_table} c ON c.id = cv.client_id
			WHERE cv.vehicle_id = %d
			AND cv.business_id = %d
			AND cv.end_date IS NULL
			AND cv.is_primary = 1
			ORDER BY cv.start_date DESC, cv.id DESC
			LIMIT 1",
			absint( $vehicle_id ),
			$this->resolve_business_id()
		);
		$row           = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get active relations for a vehicle.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active_relations_by_vehicle( $vehicle_id ) {
		return $this->get_by_vehicle(
			$vehicle_id,
			array(
				'current_only' => true,
			)
		);
	}

	/**
	 * Sync the legacy client_id field on the vehicles table.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $client_id  Client ID.
	 * @return bool
	 */
	public function sync_vehicle_primary_client( $vehicle_id, $client_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_vehicles_table_name(),
			array( 'client_id' => absint( $client_id ) ),
			array(
				'id'          => absint( $vehicle_id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%d' ),
			array( '%d', '%d' )
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
