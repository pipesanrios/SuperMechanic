<?php
/**
 * Invoice repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles invoice persistence.
 */
class Invoice_Repository {
	/**
	 * Get invoices table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['invoices'];
	}

	/**
	 * Get invoice by ID.
	 *
	 * @param int $id Invoice ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$query  = $wpdb->prepare(
			"SELECT i.*, p.title AS process_title, p.process_type, p.vehicle_id, p.client_id AS process_client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$tables['invoices']} i
			LEFT JOIN {$tables['processes']} p ON p.id = i.process_id
			LEFT JOIN {$tables['clients']} c ON c.id = i.client_id
			LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
			WHERE i.id = %d
			AND i.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row    = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get invoice by number.
	 *
	 * @param string $invoice_number Invoice number.
	 * @return array<string, mixed>|null
	 */
	public function get_by_invoice_number( $invoice_number ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE invoice_number = %s AND business_id = %d LIMIT 1",
			$invoice_number,
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get invoices by process ID.
	 *
	 * @param int $process_id Process ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_process_id( $process_id ) {
		return $this->get_all(
			array(
				'process_id' => absint( $process_id ),
				'per_page'   => 200,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
	}

	/**
	 * Get invoices by quote ID.
	 *
	 * @param int $quote_id Quote ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_quote_id( $quote_id ) {
		return $this->get_all(
			array(
				'quote_id' => absint( $quote_id ),
				'per_page' => 200,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
	}

	/**
	 * Get invoices.
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
				'process_id' => 0,
				'business_id' => $this->resolve_business_id(),
				'quote_id'   => 0,
				'client_id'  => 0,
				'process_type' => '',
				'status'     => '',
				'search'     => '',
				'date_from'  => '',
				'date_to'    => '',
				'page'       => 1,
				'per_page'   => 20,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);

		$where  = $this->build_where_clause( $args );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page   = max( 1, absint( $args['page'] ) );
		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $limit;
		$sql    = "SELECT i.*, p.title AS process_title, p.process_type, p.vehicle_id, p.client_id AS process_client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$tables['invoices']} i
			LEFT JOIN {$tables['processes']} p ON p.id = i.process_id
			LEFT JOIN {$tables['clients']} c ON c.id = i.client_id
			LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
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
	 * Count invoices.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$args  = wp_parse_args(
			$args,
			array(
				'process_id' => 0,
				'business_id' => $this->resolve_business_id(),
				'quote_id'   => 0,
				'client_id'  => 0,
				'process_type' => '',
				'status'     => '',
				'search'     => '',
				'date_from'  => '',
				'date_to'    => '',
			)
		);
		$where = $this->build_where_clause( $args );
		$sql   = "SELECT COUNT(i.id) FROM {$this->get_table_name()} i {$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert invoice.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 * @return int|false
	 */
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

	/**
	 * Update invoice.
	 *
	 * @param int                  $id   Invoice ID.
	 * @param array<string, mixed> $data Invoice data.
	 * @return bool
	 */
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

	/**
	 * Delete invoice.
	 *
	 * @param int $id Invoice ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['process_id'] ) ) {
			$clauses[] = 'i.process_id = %d';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'i.business_id = %d';
		}

		if ( ! empty( $args['quote_id'] ) ) {
			$clauses[] = 'i.quote_id = %d';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'i.client_id = %d';
		}

		if ( '' !== $args['status'] ) {
			$clauses[] = 'i.status = %s';
		}

		if ( '' !== $args['process_type'] ) {
			$tables    = Schema::get_tables();
			$clauses[] = "i.process_id IN (SELECT id FROM {$tables['processes']} WHERE process_type = %s)";
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = '(i.invoice_number LIKE %s OR i.notes LIKE %s)';
		}

		if ( '' !== $args['date_from'] ) {
			$clauses[] = 'i.created_at >= %s';
		}

		if ( '' !== $args['date_to'] ) {
			$clauses[] = 'i.created_at <= %s';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Get WHERE params.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, mixed>
	 */
	protected function get_where_params( $args ) {
		global $wpdb;

		$params = array();

		if ( ! empty( $args['process_id'] ) ) {
			$params[] = absint( $args['process_id'] );
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( ! empty( $args['quote_id'] ) ) {
			$params[] = absint( $args['quote_id'] );
		}

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( '' !== $args['status'] ) {
			$params[] = (string) $args['status'];
		}

		if ( '' !== $args['process_type'] ) {
			$params[] = sanitize_key( (string) $args['process_type'] );
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
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
			'id'             => 'i.id',
			'invoice_number' => 'i.invoice_number',
			'status'         => 'i.status',
			'grand_total'    => 'i.grand_total',
			'balance_due'    => 'i.balance_due',
			'created_at'     => 'i.created_at',
			'updated_at'     => 'i.updated_at',
			'due_date'       => 'i.due_date',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'i.created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Build formats map.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'    => '%d',
			'process_id'      => '%d',
			'quote_id'        => '%d',
			'client_id'       => '%d',
			'invoice_number'  => '%s',
			'status'          => '%s',
			'currency'        => '%s',
			'subtotal'        => '%f',
			'tax_total'       => '%f',
			'discount_total'  => '%f',
			'grand_total'     => '%f',
			'amount_paid'     => '%f',
			'balance_due'     => '%f',
			'issued_at'       => '%s',
			'due_date'        => '%s',
			'paid_at'         => '%s',
			'notes'           => '%s',
			'created_by'      => '%d',
			'created_at'      => '%s',
			'updated_at'      => '%s',
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
