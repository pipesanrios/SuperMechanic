<?php
/**
 * Payment repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles payment persistence.
 */
class Payment_Repository {
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['payments'];
	}

	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1",
			absint( $id )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	public function get_by_invoice_id( $invoice_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE invoice_id = %d ORDER BY payment_date DESC, id DESC",
			absint( $invoice_id )
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

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

	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	public function delete_by_invoice_id( $invoice_id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'invoice_id' => absint( $invoice_id ) ), array( '%d' ) );

		return false !== $result;
	}

	public function sum_payments_by_invoice( $invoice_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT SUM(amount) FROM {$this->get_table_name()} WHERE invoice_id = %d",
			absint( $invoice_id )
		);
		$total = $wpdb->get_var( $query );

		return round( (float) $total, 2 );
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'invoice_id'     => '%d',
			'payment_date'   => '%s',
			'amount'         => '%f',
			'payment_method' => '%s',
			'reference'      => '%s',
			'notes'          => '%s',
			'received_by'    => '%d',
			'created_at'     => '%s',
			'updated_at'     => '%s',
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