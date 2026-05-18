<?php
/**
 * Vehicle catalog repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles persistence for reusable vehicle catalog records.
 */
class Vehicle_Catalog_Repository {
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get catalog table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicle_catalog'];
	}

	/**
	 * Insert catalog vehicle.
	 *
	 * @param array<string, mixed> $data Catalog data.
	 * @return int
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$inserted = $wpdb->insert( $this->get_table_name(), $data, $this->get_write_formats( true ) );

		return false === $inserted ? 0 : absint( $wpdb->insert_id );
	}

	/**
	 * Update catalog vehicle by business scope.
	 *
	 * @param int                  $id   Catalog ID.
	 * @param array<string, mixed> $data Catalog data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		$data['updated_at'] = current_time( 'mysql' );
		$business_id        = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();
		$data['business_id'] = $business_id;

		$updated = $wpdb->update(
			$this->get_table_name(),
			$data,
			array(
				'id'          => $id,
				'business_id' => $business_id,
			),
			$this->get_write_formats( false ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get catalog vehicle by ID and business.
	 *
	 * @param int $id          Catalog ID.
	 * @param int $business_id Business ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id, $business_id = 0 ) {
		global $wpdb;

		$id          = absint( $id );
		$business_id = $this->normalize_business_id( $business_id );
		if ( $id <= 0 || $business_id <= 0 ) {
			return null;
		}

		$sql = "SELECT *
			FROM {$this->get_table_name()}
			WHERE id = %d
			AND business_id = %d
			LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $id, $business_id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List catalog vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( array $args = array() ) {
		global $wpdb;

		$args   = $this->normalize_query_args( $args );
		$params = array();
		$where  = $this->build_where_clause( $args, $params );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );

		$params[] = $args['per_page'];
		$params[] = ( $args['page'] - 1 ) * $args['per_page'];

		$sql = "SELECT *
			FROM {$this->get_table_name()}
			{$where}
			{$order}
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count catalog vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( array $args = array() ) {
		global $wpdb;

		$args   = $this->normalize_query_args( $args );
		$params = array();
		$where  = $this->build_where_clause( $args, $params );
		$sql    = "SELECT COUNT(id) FROM {$this->get_table_name()} {$where}";
		$query  = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Deactivate catalog vehicle.
	 *
	 * @param int $id          Catalog ID.
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	public function deactivate( $id, $business_id = 0 ) {
		return $this->update(
			$id,
			array(
				'business_id' => $this->normalize_business_id( $business_id ),
				'status'      => 'inactive',
			)
		);
	}

	/**
	 * Normalize query args.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	protected function normalize_query_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'business_id' => 0,
				'search'      => '',
				'status'      => '',
				'make'        => '',
				'model'       => '',
				'year'        => 0,
				'page'        => 1,
				'per_page'    => 20,
				'orderby'     => 'created_at',
				'order'       => 'DESC',
			)
		);

		$args['business_id'] = $this->normalize_business_id( $args['business_id'], true );
		$args['page']        = max( 1, absint( $args['page'] ) );
		$args['per_page']    = max( 1, min( 200, absint( $args['per_page'] ) ) );
		$args['year']        = absint( $args['year'] );

		return $args;
	}

	/**
	 * Build where clause.
	 *
	 * @param array<string, mixed> $args   Query args.
	 * @param array<int, mixed>    $params Params passed by reference.
	 * @return string
	 */
	protected function build_where_clause( array $args, array &$params ) {
		$clauses = array( 'business_id = %d' );
		$params[] = absint( $args['business_id'] );

		if ( '' !== (string) $args['search'] ) {
			$search    = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$clauses[] = '(make LIKE %s OR model LIKE %s OR trim_version LIKE %s OR engine LIKE %s)';
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
		}

		if ( '' !== (string) $args['status'] ) {
			$clauses[] = 'status = %s';
			$params[]  = sanitize_key( (string) $args['status'] );
		}

		if ( '' !== (string) $args['make'] ) {
			$clauses[] = 'make = %s';
			$params[]  = sanitize_text_field( (string) $args['make'] );
		}

		if ( '' !== (string) $args['model'] ) {
			$clauses[] = 'model = %s';
			$params[]  = sanitize_text_field( (string) $args['model'] );
		}

		if ( absint( $args['year'] ) > 0 ) {
			$clauses[] = 'year = %d';
			$params[]  = absint( $args['year'] );
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build order clause.
	 *
	 * @param string $orderby Orderby.
	 * @param string $order   Order.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'         => 'id',
			'make'       => 'make',
			'model'      => 'model',
			'year'       => 'year',
			'status'     => 'status',
			'created_at' => 'created_at',
			'updated_at' => 'updated_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Get write formats.
	 *
	 * @param bool $insert Whether insert format.
	 * @return array<int, string>
	 */
	protected function get_write_formats( $insert ) {
		$formats = array(
			'%d',
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
			'%s',
		);

		if ( $insert ) {
			$formats[] = '%s';
		}

		return $formats;
	}

	/**
	 * Normalize business ID.
	 *
	 * @param int $business_id Business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id, $fallback_to_active = false ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return $fallback_to_active ? $this->resolve_business_id() : 0;
		}

		$normalized = absint( $this->business_context_service->normalize_business_id( $business_id ) );

		return $normalized === $business_id ? $business_id : 0;
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Escape LIKE value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}
}
