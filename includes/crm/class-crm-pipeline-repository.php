<?php
/**
 * CRM pipeline repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM pipeline persistence.
 */
class Crm_Pipeline_Repository {
	/**
	 * Get clients table name.
	 *
	 * @return string
	 */
	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get vehicles table name.
	 *
	 * @return string
	 */
	protected function get_vehicles_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	/**
	 * Get processes table name.
	 *
	 * @return string
	 */
	protected function get_processes_table_name() {
		$tables = Schema::get_tables();

		return $tables['processes'];
	}

	/**
	 * Get CRM pipeline table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['crm_pipeline'];
	}

	/**
	 * Get single opportunity by ID scoped to active business.
	 *
	 * @param int $id Opportunity ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$pipeline_table = $this->get_table_name();
		$clients_table  = $this->get_clients_table_name();
		$vehicles_table = $this->get_vehicles_table_name();
		$processes_table = $this->get_processes_table_name();
		$query = $wpdb->prepare(
			"SELECT p.*,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				c.phone AS client_phone,
				c.email AS client_email,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate,
				pr.title AS process_title,
				pr.status AS process_status
			FROM {$pipeline_table} p
			LEFT JOIN {$clients_table} c ON c.id = p.client_id AND c.business_id = p.business_id
			LEFT JOIN {$vehicles_table} v ON v.id = p.vehicle_id AND v.business_id = p.business_id
			LEFT JOIN {$processes_table} pr ON pr.id = p.process_id AND pr.business_id = p.business_id
			WHERE p.id = %d AND p.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get list of opportunities.
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
				'stage'       => '',
				'client_id'   => 0,
				'business_id' => $this->resolve_business_id(),
				'page'        => 1,
				'per_page'    => 20,
				'orderby'     => 'updated_at',
				'order'       => 'DESC',
			)
		);

		$where  = $this->build_where_clause( $args );
		$params = $this->get_where_params( $args );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$pipeline_table = $this->get_table_name();
		$clients_table  = $this->get_clients_table_name();
		$vehicles_table = $this->get_vehicles_table_name();
		$processes_table = $this->get_processes_table_name();
		$page   = max( 1, absint( $args['page'] ) );
		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $limit;

		$sql      = "SELECT p.*,
			CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
			c.phone AS client_phone,
			c.email AS client_email,
			v.make AS vehicle_make,
			v.model AS vehicle_model,
			v.plate AS vehicle_plate,
			pr.title AS process_title,
			pr.status AS process_status
		FROM {$pipeline_table} p
		LEFT JOIN {$clients_table} c ON c.id = p.client_id AND c.business_id = p.business_id
		LEFT JOIN {$vehicles_table} v ON v.id = p.vehicle_id AND v.business_id = p.business_id
		LEFT JOIN {$processes_table} pr ON pr.id = p.process_id AND pr.business_id = p.business_id
		{$where}
		{$order}
		LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count list rows.
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
				'stage'       => '',
				'client_id'   => 0,
				'business_id' => $this->resolve_business_id(),
			)
		);

		$where          = $this->build_where_clause( $args );
		$pipeline_table = $this->get_table_name();
		$clients_table  = $this->get_clients_table_name();
		$sql            = "SELECT COUNT(p.id)
			FROM {$pipeline_table} p
			LEFT JOIN {$clients_table} c ON c.id = p.client_id AND c.business_id = p.business_id
			{$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert opportunity.
	 *
	 * @param array<string, mixed> $data Opportunity payload.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now                 = current_time( 'mysql' );
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();
		$data['created_at']  = $now;
		$data['updated_at']  = $now;

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update opportunity.
	 *
	 * @param int                 $id Opportunity ID.
	 * @param array<string, mixed> $data Payload.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();
		$data['updated_at']  = current_time( 'mysql' );

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

	/**
	 * Delete opportunity.
	 *
	 * @param int $id Opportunity ID.
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
	 * Get next position in a stage for active business.
	 *
	 * @param string $stage Stage key.
	 * @return int
	 */
	public function get_next_position( $stage ) {
		global $wpdb;

		$max_position = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(position) FROM {$this->get_table_name()} WHERE business_id = %d AND stage = %s",
				$this->resolve_business_id(),
				sanitize_key( $stage )
			)
		);

		return $max_position + 1;
	}

	/**
	 * Update stage and position.
	 *
	 * @param int    $id Opportunity ID.
	 * @param string $stage Stage.
	 * @param int    $position Position in stage.
	 * @return bool
	 */
	public function update_stage( $id, $stage, $position ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'stage'      => sanitize_key( (string) $stage ),
				'position'   => absint( $position ),
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update linked process reference.
	 *
	 * @param int $id Opportunity ID.
	 * @param int $process_id Process ID.
	 * @return bool
	 */
	public function update_process_link( $id, $process_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'process_id'  => absint( $process_id ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
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

	/**
	 * Build where clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( array $args ) {
		$clauses = array();

		if ( ! empty( $args['search'] ) ) {
			$clauses[] = '(p.title LIKE %s OR p.notes LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'p.business_id = %d';
		}

		if ( ! empty( $args['stage'] ) ) {
			$clauses[] = 'p.stage = %s';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'p.client_id = %d';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build where parameters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, mixed>
	 */
	protected function get_where_params( array $args ) {
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( ! empty( $args['stage'] ) ) {
			$params[] = sanitize_key( (string) $args['stage'] );
		}

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		return $params;
	}

	/**
	 * Build order clause.
	 *
	 * @param string $orderby Order key.
	 * @param string $order Direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'              => 'p.id',
			'title'           => 'p.title',
			'stage'           => 'p.stage',
			'estimated_value' => 'p.estimated_value',
			'position'        => 'p.position',
			'created_at'      => 'p.created_at',
			'updated_at'      => 'p.updated_at',
			'client'          => 'client_name',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'p.updated_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	/**
	 * Escape like values.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}

	/**
	 * Get dynamic format map for insert/update payload.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( array $data ) {
		$format_map = array(
			'business_id'      => '%d',
			'client_id'        => '%d',
			'vehicle_id'       => '%d',
			'process_id'       => '%d',
			'stage'            => '%s',
			'title'            => '%s',
			'estimated_value'  => '%f',
			'currency'         => '%s',
			'assigned_user_id' => '%d',
			'notes'            => '%s',
			'position'         => '%d',
			'created_at'       => '%s',
			'updated_at'       => '%s',
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
