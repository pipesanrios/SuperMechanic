<?php
/**
 * Paperwork repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Paperwork;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles paperwork persistence.
 */
class Paperwork_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['paperwork'];
	}

	/**
	 * Get row by process ID.
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
	 * Create row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|false
	 */
	public function create( $data ) {
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
	 * Update row by process.
	 *
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $data       Data.
	 * @return bool
	 */
	public function update_by_process_id( $process_id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'process_id' => absint( $process_id ) ),
			$this->get_formats_for_data( $data ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete by process.
	 *
	 * @param int $process_id Process ID.
	 * @return bool
	 */
	public function delete_by_process_id( $process_id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'process_id' => absint( $process_id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Formats map.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'process_id'        => '%d',
			'paperwork_type'    => '%s',
			'target_date'       => '%s',
			'completed_date'    => '%s',
			'assigned_user_id'  => '%d',
			'status'            => '%s',
			'notes'             => '%s',
			'created_at'        => '%s',
			'updated_at'        => '%s',
		);
		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $format_map[ $key ] ) ) {
				$formats[] = $format_map[ $key ];
			}
		}

		return $formats;
	}
}
