<?php
/**
 * Maintenance part repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Maintenance;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles maintenance parts persistence.
 */
class Maintenance_Part_Repository {
	/**
	 * Get the parts table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['maintenance_parts'];
	}

	/**
	 * Get parts by maintenance ID.
	 *
	 * @param int $maintenance_id Maintenance ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_maintenance_id( $maintenance_id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE maintenance_id = %d ORDER BY id ASC", absint( $maintenance_id ) );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert part.
	 *
	 * @param array<string, mixed> $data Part data.
	 * @return int|false
	 */
	public function insert( $data ) {
		global $wpdb;

		$data['created_at'] = current_time( 'mysql' );
		$result             = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete part.
	 *
	 * @param int $id Part ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

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
			'maintenance_id' => '%d',
			'part_name'      => '%s',
			'quantity'       => '%f',
			'unit_price'     => '%f',
			'total_price'    => '%f',
			'notes'          => '%s',
			'created_at'     => '%s',
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
