<?php
/**
 * Quote item repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles quote item persistence.
 */
class Quote_Item_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['quote_items'];
	}

	/**
	 * Get item by ID.
	 *
	 * @param int $id Item ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1", absint( $id ) );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get items by quote.
	 *
	 * @param int $quote_id Quote ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_quote_id( $quote_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE quote_id = %d ORDER BY sort_order ASC, id ASC",
			absint( $quote_id )
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert item.
	 *
	 * @param array<string, mixed> $data Item data.
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
	 * @param array<string, mixed> $data Item data.
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
	 * Delete all items by quote.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public function delete_by_quote_id( $quote_id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'quote_id' => absint( $quote_id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Build formats.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'quote_id'     => '%d',
			'item_type'    => '%s',
			'reference_id' => '%d',
			'label'        => '%s',
			'description'  => '%s',
			'quantity'     => '%f',
			'unit_price'   => '%f',
			'line_total'   => '%f',
			'sort_order'   => '%d',
			'created_at'   => '%s',
			'updated_at'   => '%s',
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
