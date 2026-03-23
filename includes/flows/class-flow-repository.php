<?php
/**
 * Flow repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Flows;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles flow persistence.
 *
 * The schema keeps `flow_type` as a legacy column. The module syncs it with the
 * newer `process_type` field for forward compatibility.
 */
class Flow_Repository {
	/**
	 * Get the flows table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['flows'];
	}

	/**
	 * Get a flow by ID.
	 *
	 * @param int $id Flow ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT f.*, COALESCE(f.process_type, f.flow_type) AS normalized_process_type
			FROM {$this->get_table_name()} f
			WHERE f.id = %d
			LIMIT 1",
			absint( $id )
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $result ) ) {
			return null;
		}

		$result['process_type'] = $result['normalized_process_type'];
		unset( $result['normalized_process_type'] );

		return $result;
	}

	/**
	 * Get flows.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'process_type' => '',
				'is_active'    => '',
				'page'         => 1,
				'per_page'     => 20,
				'orderby'      => 'created_at',
				'order'        => 'DESC',
			)
		);

		$where  = $this->build_where_clause( $args );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page   = max( 1, absint( $args['page'] ) );
		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $limit;
		$sql    = "SELECT f.*, COALESCE(f.process_type, f.flow_type) AS normalized_process_type
			FROM {$this->get_table_name()} f
			{$where}
			{$order}
			LIMIT %d OFFSET %d";
		$params = $this->get_where_params( $args );
		$params[] = $limit;
		$params[] = $offset;
		$query  = $wpdb->prepare( $sql, $params );
		$rows   = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['process_type'] = $row['normalized_process_type'];
			unset( $row['normalized_process_type'] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Count flows.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'process_type' => '',
				'is_active'    => '',
			)
		);

		$where = $this->build_where_clause( $args );
		$sql   = "SELECT COUNT(f.id) FROM {$this->get_table_name()} f {$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert a flow.
	 *
	 * @param array<string, mixed> $data Flow data.
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
	 * Update a flow.
	 *
	 * @param int                  $id   Flow ID.
	 * @param array<string, mixed> $data Flow data.
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
	 * Delete a flow.
	 *
	 * @param int $id Flow ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get flows by process type.
	 *
	 * @param string $process_type Process type.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_process_type( $process_type ) {
		return $this->get_all(
			array(
				'process_type' => $process_type,
				'per_page'     => 999,
				'orderby'      => 'name',
				'order'        => 'ASC',
			)
		);
	}

	/**
	 * Get a flow by slug.
	 *
	 * @param string $slug Flow slug.
	 * @return array<string, mixed>|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT f.*, COALESCE(f.process_type, f.flow_type) AS normalized_process_type
			FROM {$this->get_table_name()} f
			WHERE f.slug = %s
			LIMIT 1",
			$slug
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $result ) ) {
			return null;
		}

		$result['process_type'] = $result['normalized_process_type'];
		unset( $result['normalized_process_type'] );

		return $result;
	}

	/**
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( '' !== $args['search'] ) {
			$clauses[] = '(f.name LIKE %s OR COALESCE(f.process_type, f.flow_type) LIKE %s)';
		}

		if ( '' !== $args['process_type'] ) {
			$clauses[] = 'COALESCE(f.process_type, f.flow_type) = %s';
		}

		if ( '' !== $args['is_active'] ) {
			$clauses[] = 'f.is_active = %d';
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

		if ( '' !== $args['search'] ) {
			$search   = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
		}

		if ( '' !== $args['process_type'] ) {
			$params[] = (string) $args['process_type'];
		}

		if ( '' !== $args['is_active'] ) {
			$params[] = absint( $args['is_active'] );
		}

		return $params;
	}

	/**
	 * Build ORDER BY clause.
	 *
	 * @param string $orderby Order by field.
	 * @param string $order   Sort direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'           => 'f.id',
			'name'         => 'f.name',
			'process_type' => 'normalized_process_type',
			'is_active'    => 'f.is_active',
			'created_at'   => 'f.created_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'f.created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Escape LIKE values.
	 *
	 * @param string $value Search value.
	 * @return string
	 */
	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}

	/**
	 * Build formats for the provided keys.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'name'         => '%s',
			'slug'         => '%s',
			'flow_type'    => '%s',
			'process_type' => '%s',
			'description'  => '%s',
			'is_default'   => '%d',
			'is_active'    => '%d',
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
