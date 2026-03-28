<?php
/**
 * Payment repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

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
			"SELECT * FROM {$this->get_table_name()} WHERE invoice_id = %d AND business_id = %d ORDER BY payment_date DESC, id DESC",
			absint( $invoice_id ),
			$this->resolve_business_id()
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get payments with optional filters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$args   = wp_parse_args(
			$args,
			array(
				'invoice_id'      => 0,
				'business_id'     => $this->resolve_business_id(),
				'payment_method'  => '',
				'search'          => '',
				'date_from'       => '',
				'date_to'         => '',
				'page'            => 1,
				'per_page'        => 20,
				'orderby'         => 'payment_date',
				'order'           => 'DESC',
			)
		);

		$where  = $this->build_where_clause( $args );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page   = max( 1, absint( $args['page'] ) );
		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $limit;
		$sql    = "SELECT pay.*, i.invoice_number, i.process_id, i.client_id, i.currency, i.subtotal, i.tax_total, i.discount_total, i.grand_total, i.status AS invoice_status,
				p.title AS process_title,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$tables['payments']} pay
			LEFT JOIN {$tables['invoices']} i ON i.id = pay.invoice_id
			LEFT JOIN {$tables['processes']} p ON p.id = i.process_id
			LEFT JOIN {$tables['clients']} c ON c.id = i.client_id
			{$where}
			{$order}
			LIMIT %d OFFSET %d";
		$params = $this->get_where_params( $args );
		$params[] = $limit;
		$params[] = $offset;

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count payments with optional filters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$args   = wp_parse_args(
			$args,
			array(
				'invoice_id'      => 0,
				'business_id'     => $this->resolve_business_id(),
				'payment_method'  => '',
				'search'          => '',
				'date_from'       => '',
				'date_to'         => '',
			)
		);
		$where  = $this->build_where_clause( $args );
		$sql    = "SELECT COUNT(pay.id)
			FROM {$tables['payments']} pay
			LEFT JOIN {$tables['invoices']} i ON i.id = pay.invoice_id
			LEFT JOIN {$tables['clients']} c ON c.id = i.client_id
			{$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
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

	public function sum_payments_by_invoice( $invoice_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT SUM(amount) FROM {$this->get_table_name()} WHERE invoice_id = %d AND business_id = %d",
			absint( $invoice_id ),
			$this->resolve_business_id()
		);
		$total = $wpdb->get_var( $query );

		return round( (float) $total, 2 );
	}

	/**
	 * Build WHERE clause for listing and counting.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['invoice_id'] ) ) {
			$clauses[] = 'pay.invoice_id = %d';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'pay.business_id = %d';
		}

		if ( '' !== $args['payment_method'] ) {
			$clauses[] = 'pay.payment_method = %s';
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = '(pay.reference LIKE %s OR pay.notes LIKE %s OR i.invoice_number LIKE %s OR CONCAT_WS(\' \', c.first_name, c.last_name) LIKE %s)';
		}

		if ( '' !== $args['date_from'] ) {
			$clauses[] = 'pay.payment_date >= %s';
		}

		if ( '' !== $args['date_to'] ) {
			$clauses[] = 'pay.payment_date <= %s';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build WHERE params for listing and counting.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, mixed>
	 */
	protected function get_where_params( $args ) {
		global $wpdb;

		$params = array();

		if ( ! empty( $args['invoice_id'] ) ) {
			$params[] = absint( $args['invoice_id'] );
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( '' !== $args['payment_method'] ) {
			$params[] = sanitize_key( (string) $args['payment_method'] );
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( '' !== $args['date_from'] ) {
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}

		if ( '' !== $args['date_to'] ) {
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
		}

		return $params;
	}

	/**
	 * Build ORDER BY clause.
	 *
	 * @param string $orderby Order by.
	 * @param string $order   Direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'             => 'pay.id',
			'payment_date'   => 'pay.payment_date',
			'amount'         => 'pay.amount',
			'payment_method' => 'pay.payment_method',
			'invoice_number' => 'i.invoice_number',
			'created_at'     => 'pay.created_at',
			'updated_at'     => 'pay.updated_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'pay.payment_date';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'    => '%d',
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
