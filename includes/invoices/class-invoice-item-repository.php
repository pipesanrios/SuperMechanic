<?php
/**
 * Invoice item repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles invoice item persistence.
 */
class Invoice_Item_Repository {
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['invoice_items'];
	}

	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d AND business_id = %d LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	public function get_by_invoice_id( $invoice_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE invoice_id = %d AND business_id = %d ORDER BY sort_order ASC, id ASC",
			absint( $invoice_id ),
			$this->resolve_business_id()
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	public function insert( $data ) {
		global $wpdb;

		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	public function update( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			$this->get_formats_for_data( $data ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	public function delete_by_invoice_id( $invoice_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			array(
				'invoice_id'  => absint( $invoice_id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id' => '%d',
			'invoice_id'   => '%d',
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
