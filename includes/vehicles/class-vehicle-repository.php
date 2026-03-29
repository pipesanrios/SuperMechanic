<?php
/**
 * Vehicle repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles vehicle persistence.
 *
 * The current schema stores the brand in the `make` column, so the repository
 * exposes both `make` and a UI-friendly `brand` alias.
 */
class Vehicle_Repository {
	/**
	 * Get the vehicles table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	/**
	 * Get the clients table name.
	 *
	 * @return string
	 */
	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get a vehicle by ID.
	 *
	 * @param int $id Vehicle ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$vehicles_table = $this->get_table_name();
		$clients_table  = $this->get_clients_table_name();
		$query          = $wpdb->prepare(
			"SELECT v.*, v.make AS brand, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$vehicles_table} v
			LEFT JOIN {$clients_table} c ON c.id = v.client_id
			WHERE v.id = %d
			AND v.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$result         = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get vehicles list.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'status'      => '',
				'type'        => '',
				'date_from'   => '',
				'date_to'     => '',
				'page'        => 1,
				'per_page'    => 20,
				'orderby'     => 'created_at',
				'order'       => 'DESC',
				'business_id' => $this->resolve_business_id(),
				'exclude_id'  => 0,
				'exact_vin'   => '',
				'exact_plate' => '',
				'client_id'   => null,
			)
		);

		$vehicles_table = $this->get_table_name();
		$clients_table  = $this->get_clients_table_name();
		$where          = $this->build_where_clause( $args );
		$order          = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page           = max( 1, absint( $args['page'] ) );
		$limit          = max( 1, absint( $args['per_page'] ) );
		$offset         = ( $page - 1 ) * $limit;
		$sql            = "SELECT v.*, v.make AS brand, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$vehicles_table} v
			LEFT JOIN {$clients_table} c ON c.id = v.client_id
			{$where}
			{$order}
			LIMIT %d OFFSET %d";
		$params         = $this->get_where_params( $args );
		$params[]       = $limit;
		$params[]       = $offset;
		$query          = $wpdb->prepare( $sql, $params );
		$rows           = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'status'      => '',
				'type'        => '',
				'date_from'   => '',
				'date_to'     => '',
				'business_id' => $this->resolve_business_id(),
				'exclude_id'  => 0,
				'exact_vin'   => '',
				'exact_plate' => '',
				'client_id'   => null,
			)
		);

		$vehicles_table = $this->get_table_name();
		$where          = $this->build_where_clause( $args );
		$sql            = "SELECT COUNT(v.id) FROM {$vehicles_table} v {$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert a vehicle.
	 *
	 * @param array<string, mixed> $data Vehicle data.
	 * @return int|false
	 */
	public function insert( $data ) {
		global $wpdb;

		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats() );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a vehicle.
	 *
	 * @param int                  $id   Vehicle ID.
	 * @param array<string, mixed> $data Vehicle data.
	 * @return bool
	 */
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
			$this->get_update_formats(),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a vehicle.
	 *
	 * @param int $id Vehicle ID.
	 * @return bool
	 */
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

	/**
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['search'] ) ) {
			$clauses[] = '(v.vin LIKE %s OR v.plate LIKE %s OR v.make LIKE %s OR v.model LIKE %s)';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'v.business_id = %d';
		}

		if ( ! empty( $args['exact_vin'] ) ) {
			$clauses[] = 'v.vin = %s';
		}

		if ( ! empty( $args['exact_plate'] ) ) {
			$clauses[] = 'v.plate = %s';
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'v.status = %s';
		}

		if ( ! empty( $args['type'] ) ) {
			$clauses[] = 'v.type = %s';
		}

		if ( ! empty( $args['date_from'] ) ) {
			$clauses[] = 'v.created_at >= %s';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$clauses[] = 'v.created_at <= %s';
		}

		if ( null !== $args['client_id'] ) {
			$clauses[] = 'v.client_id = %d';
		}

		if ( ! empty( $args['exclude_id'] ) ) {
			$clauses[] = 'v.id != %d';
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
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( ! empty( $args['exact_vin'] ) ) {
			$params[] = (string) $args['exact_vin'];
		}

		if ( ! empty( $args['exact_plate'] ) ) {
			$params[] = (string) $args['exact_plate'];
		}

		if ( ! empty( $args['status'] ) ) {
			$params[] = sanitize_key( (string) $args['status'] );
		}

		if ( ! empty( $args['type'] ) ) {
			$params[] = sanitize_key( (string) $args['type'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
		}

		if ( null !== $args['client_id'] ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( ! empty( $args['exclude_id'] ) ) {
			$params[] = absint( $args['exclude_id'] );
		}

		return $params;
	}

	/**
	 * Build ORDER BY clause.
	 *
	 * @param string $orderby Orderby key.
	 * @param string $order   Order direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'         => 'v.id',
			'client'     => 'client_name',
			'vin'        => 'v.vin',
			'plate'      => 'v.plate',
			'brand'      => 'v.make',
			'model'      => 'v.model',
			'year'       => 'v.year',
			'color'      => 'v.color',
			'created_at' => 'v.created_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'v.created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Escape LIKE values.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}

	/**
	 * Get insert formats.
	 *
	 * @return array<int, string>
	 */
	protected function get_formats() {
		return array(
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);
	}

	/**
	 * Get update formats.
	 *
	 * @return array<int, string>
	 */
	protected function get_update_formats() {
		return array(
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);
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
