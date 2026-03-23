<?php
/**
 * Maintenance repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Maintenance;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles maintenance persistence.
 */
class Maintenance_Repository {
	/**
	 * Get the maintenance table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['maintenance'];
	}

	/**
	 * Get maintenance record by ID.
	 *
	 * @param int $id Maintenance ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1", absint( $id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get maintenance record by process ID.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_process_id( $process_id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE process_id = %d LIMIT 1", absint( $process_id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert maintenance record.
	 *
	 * @param array<string, mixed> $data Maintenance data.
	 * @return int|false
	 */
	public function insert( $data ) {
		global $wpdb;

		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update maintenance record.
	 *
	 * @param int                  $id   Maintenance ID.
	 * @param array<string, mixed> $data Maintenance data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => absint( $id ) ),
			$this->get_formats_for_data( $data ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Build data formats.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'process_id'       => '%d',
			'diagnosis'        => '%s',
			'client_approved'  => '%d',
			'approved_at'      => '%s',
			'mechanic_id'      => '%d',
			'estimated_hours'  => '%f',
			'created_at'       => '%s',
			'updated_at'       => '%s',
		);
		$formats    = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $format_map[ $key ] ) ) {
				$formats[] = $format_map[ $key ];
			}
		}

		return $formats;
	}
}
