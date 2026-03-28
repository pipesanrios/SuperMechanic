<?php
/**
 * Quote repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles quote persistence.
 */
class Quote_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['quotes'];
	}

	/**
	 * Get quote by ID.
	 *
	 * @param int $id Quote ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$tables = Schema::get_tables();
		$query  = $wpdb->prepare(
			"SELECT q.*, p.title AS process_title, p.process_type, p.vehicle_id, p.client_id AS process_client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$tables['quotes']} q
			LEFT JOIN {$tables['processes']} p ON p.id = q.process_id
			LEFT JOIN {$tables['clients']} c ON c.id = q.client_id
			LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
			WHERE q.id = %d
			AND q.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row    = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get quotes by process ID.
	 *
	 * @param int $process_id Process ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_process_id( $process_id ) {
		return $this->get_all(
			array(
				'process_id' => absint( $process_id ),
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
	}

	/**
	 * Get quote by quote number.
	 *
	 * @param string $quote_number Quote number.
	 * @return array<string, mixed>|null
	 */
	public function get_by_quote_number( $quote_number ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE quote_number = %s AND business_id = %d LIMIT 1",
			$quote_number,
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get quotes.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'process_id' => 0,
				'business_id' => $this->resolve_business_id(),
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

		$tables   = Schema::get_tables();
		$where    = $this->build_where_clause( $args );
		$params   = $this->get_where_params( $args );
		$orderby  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page     = max( 1, absint( $args['page'] ) );
		$limit    = max( 1, absint( $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $limit;
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT q.*, p.title AS process_title, p.process_type, p.vehicle_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$tables['quotes']} q
			LEFT JOIN {$tables['processes']} p ON p.id = q.process_id
			LEFT JOIN {$tables['clients']} c ON c.id = q.client_id
			LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
			{$where}
			{$orderby}
			LIMIT %d OFFSET %d";

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count quotes.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'process_id' => 0,
				'business_id' => $this->resolve_business_id(),
				'client_id'  => 0,
				'process_type' => '',
				'status'     => '',
				'search'     => '',
				'date_from'  => '',
				'date_to'    => '',
			)
		);

		$where = $this->build_where_clause( $args );
		$sql   = "SELECT COUNT(q.id) FROM {$this->get_table_name()} q {$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert quote.
	 *
	 * @param array<string, mixed> $data Quote data.
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
	 * Update quote.
	 *
	 * @param int                  $id   Quote ID.
	 * @param array<string, mixed> $data Quote data.
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
	 * Delete quote.
	 *
	 * @param int $id Quote ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Build where clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['process_id'] ) ) {
			$clauses[] = 'q.process_id = %d';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'q.business_id = %d';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'q.client_id = %d';
		}

		if ( '' !== $args['status'] ) {
			$clauses[] = 'q.status = %s';
		}

		if ( '' !== $args['process_type'] ) {
			$tables    = Schema::get_tables();
			$clauses[] = "q.process_id IN (SELECT id FROM {$tables['processes']} WHERE process_type = %s)";
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = '(q.quote_number LIKE %s OR q.notes LIKE %s)';
		}

		if ( '' !== $args['date_from'] ) {
			$clauses[] = 'q.created_at >= %s';
		}

		if ( '' !== $args['date_to'] ) {
			$clauses[] = 'q.created_at <= %s';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Get where params.
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

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( '' !== $args['status'] ) {
			$params[] = $args['status'];
		}

		if ( '' !== $args['process_type'] ) {
			$params[] = sanitize_key( (string) $args['process_type'] );
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
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
	 * Build order by clause.
	 *
	 * @param string $orderby Order field.
	 * @param string $order   Direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'           => 'q.id',
			'quote_number' => 'q.quote_number',
			'status'       => 'q.status',
			'grand_total'  => 'q.grand_total',
			'created_at'   => 'q.created_at',
			'updated_at'   => 'q.updated_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'q.created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Build formats.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'        => '%d',
			'process_id'         => '%d',
			'client_id'          => '%d',
			'quote_number'       => '%s',
			'status'             => '%s',
			'currency'           => '%s',
			'subtotal'           => '%f',
			'tax_total'          => '%f',
			'discount_total'     => '%f',
			'grand_total'        => '%f',
			'notes'              => '%s',
			'approved_by_client' => '%d',
			'approved_at'        => '%s',
			'rejected_at'        => '%s',
			'rejection_reason'   => '%s',
			'created_by'         => '%d',
			'created_at'         => '%s',
			'updated_at'         => '%s',
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
