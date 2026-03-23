<?php
/**
 * Paperwork item repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Paperwork;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles paperwork checklist items persistence.
 */
class Paperwork_Item_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['paperwork_items'];
	}

	/**
	 * Get items by paperwork ID.
	 *
	 * @param int $paperwork_id Paperwork ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_paperwork_id( $paperwork_id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE paperwork_id = %d ORDER BY sort_order ASC, id ASC", absint( $paperwork_id ) );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert item.
	 *
	 * @param array<string, mixed> $data Data.
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
	 * Update item.
	 *
	 * @param int                  $id   Item ID.
	 * @param array<string, mixed> $data Data.
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
	 * Delete item.
	 *
	 * @param int $id Item ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Delete all items by paperwork ID.
	 *
	 * @param int $paperwork_id Paperwork ID.
	 * @return bool
	 */
	public function delete_by_paperwork_id( $paperwork_id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'paperwork_id' => absint( $paperwork_id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Build formats map.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'paperwork_id' => '%d',
			'item_key'     => '%s',
			'item_label'   => '%s',
			'is_required'  => '%d',
			'is_completed' => '%d',
			'completed_at' => '%s',
			'notes'        => '%s',
			'sort_order'   => '%d',
			'created_at'   => '%s',
			'updated_at'   => '%s',
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
