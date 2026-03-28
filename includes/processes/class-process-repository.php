<?php
/**
 * Process repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles process persistence.
 *
 * The schema keeps legacy extension columns such as flow data for future phases,
 * but this repository only relies on the base process fields required now.
 */
class Process_Repository {
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['processes'];
	}

	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	protected function get_vehicles_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	protected function get_process_step_logs_table_name() {
		$tables = Schema::get_tables();

		return $tables['process_step_logs'];
	}

	public function get_by_id( $id ) {
		global $wpdb;

		$processes_table = $this->get_table_name();
		$clients_table   = $this->get_clients_table_name();
		$vehicles_table  = $this->get_vehicles_table_name();
		$query           = $wpdb->prepare(
			"SELECT p.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$processes_table} p
			LEFT JOIN {$clients_table} c ON c.id = p.client_id
			LEFT JOIN {$vehicles_table} v ON v.id = p.vehicle_id
			WHERE p.id = %d
			AND p.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$result          = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'business_id'  => $this->resolve_business_id(),
				'vehicle_id'   => 0,
				'client_id'    => 0,
				'process_type' => '',
				'status'       => '',
				'date_from'    => '',
				'date_to'      => '',
				'page'         => 1,
				'per_page'     => 20,
				'orderby'      => 'created_at',
				'order'        => 'DESC',
			)
		);

		$processes_table = $this->get_table_name();
		$clients_table   = $this->get_clients_table_name();
		$vehicles_table  = $this->get_vehicles_table_name();
		$where           = $this->build_where_clause( $args );
		$order           = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page            = max( 1, absint( $args['page'] ) );
		$limit           = max( 1, absint( $args['per_page'] ) );
		$offset          = ( $page - 1 ) * $limit;
		$sql             = "SELECT p.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$processes_table} p
			LEFT JOIN {$clients_table} c ON c.id = p.client_id
			LEFT JOIN {$vehicles_table} v ON v.id = p.vehicle_id
			{$where}
			{$order}
			LIMIT %d OFFSET %d";
		$params          = $this->get_where_params( $args );
		$params[]        = $limit;
		$params[]        = $offset;
		$query           = $wpdb->prepare( $sql, $params );
		$rows            = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'business_id'  => $this->resolve_business_id(),
				'vehicle_id'   => 0,
				'client_id'    => 0,
				'process_type' => '',
				'status'       => '',
				'date_from'    => '',
				'date_to'      => '',
			)
		);

		$processes_table = $this->get_table_name();
		$where           = $this->build_where_clause( $args );
		$sql             = "SELECT COUNT(p.id) FROM {$processes_table} p {$where}";

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

		$result = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	public function get_step_logs_by_process_id( $process_id, $limit = 100 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_process_step_logs_table_name()} WHERE process_id = %d AND business_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
			absint( $process_id ),
			$this->resolve_business_id(),
			max( 1, absint( $limit ) )
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count open processes excluding terminal statuses.
	 *
	 * @return int
	 */
	public function count_open_processes() {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$this->get_table_name()} WHERE business_id = %d AND status NOT IN ('completed', 'delivered', 'cancelled')",
			$this->resolve_business_id()
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get grouped process counts for dashboard summaries.
	 *
	 * @param string $field Grouping field.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_grouped_counts( $field ) {
		global $wpdb;

		if ( ! in_array( $field, array( 'status', 'process_type' ), true ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$field} AS label, COUNT(id) AS total
			FROM {$this->get_table_name()}
			WHERE business_id = %d
			GROUP BY {$field}
			ORDER BY total DESC, {$field} ASC",
				$this->resolve_business_id()
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent visible activity rows for a set of process IDs.
	 *
	 * @param array<int, int> $process_ids Process IDs.
	 * @param int             $limit       Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_activity_by_process_ids( array $process_ids, $limit = 20, $customer_visible_only = false ) {
		global $wpdb;

		$process_ids = array_values( array_filter( array_map( 'absint', $process_ids ) ) );

		if ( empty( $process_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $process_ids ), '%d' ) );
		$where_sql    = "process_id IN ({$placeholders})";

		if ( $customer_visible_only ) {
			$where_sql .= ' AND customer_visible = 1';
		}

		$sql          = $wpdb->prepare(
			"SELECT process_id, action_type, message, created_at
			FROM {$this->get_process_step_logs_table_name()}
			WHERE {$where_sql}
			AND process_id IN (
				SELECT id FROM {$this->get_table_name()} WHERE business_id = %d
			)
			ORDER BY created_at DESC
			LIMIT %d",
			array_merge( $process_ids, array( $this->resolve_business_id(), max( 1, absint( $limit ) ) ) )
		);
		$rows         = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get process rows assigned to a mechanic through maintenance.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $args    Query args.
	 * @param int                 $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_mechanic_processes( $user_id, array $args = array(), $limit = 20 ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$tables     = Schema::get_tables();
		$args       = wp_parse_args(
			$args,
			array(
				'status'           => '',
				'process_type'     => '',
				'exclude_statuses' => array(),
			)
		);
		$conditions = array( '(m.mechanic_id = %d OR p.assigned_to = %d)' );
		$params     = array( $user_id );
		$params[]   = $user_id;
		$conditions[] = 'p.business_id = %d';
		$params[]     = $this->resolve_business_id();

		if ( '' !== $args['status'] ) {
			$conditions[] = 'p.status = %s';
			$params[]     = sanitize_key( $args['status'] );
		}

		if ( '' !== $args['process_type'] ) {
			$conditions[] = 'p.process_type = %s';
			$params[]     = sanitize_key( $args['process_type'] );
		}

		if ( ! empty( $args['exclude_statuses'] ) ) {
			$exclude_statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $args['exclude_statuses'] ) ) );

			if ( ! empty( $exclude_statuses ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $exclude_statuses ), '%s' ) );
				$conditions[] = "p.status NOT IN ({$placeholders})";
				$params       = array_merge( $params, $exclude_statuses );
			}
		}

		$params[] = max( 1, absint( $limit ) );
		$where    = implode( ' AND ', $conditions );
		$sql      = $wpdb->prepare(
			"SELECT p.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin
			FROM {$this->get_table_name()} p
			LEFT JOIN {$tables['maintenance']} m ON m.process_id = p.id
			LEFT JOIN {$tables['clients']} c ON c.id = p.client_id
			LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
			WHERE {$where}
			ORDER BY p.updated_at DESC, p.id DESC
			LIMIT %d",
			$params
		);
		$rows     = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert a process step log row.
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return int|false
	 */
	public function insert_process_step_log( $data ) {
		global $wpdb;

		$data['created_at'] = ! empty( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' );
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

		$result = $wpdb->insert(
			$this->get_process_step_logs_table_name(),
			$data,
			$this->get_step_log_formats_for_data( $data )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the latest process step log.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>|null
	 */
	public function get_latest_process_step_log( $process_id ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT * FROM {$this->get_process_step_logs_table_name()} WHERE process_id = %d AND business_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
			absint( $process_id ),
			$this->resolve_business_id()
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['search'] ) ) {
			$clauses[] = '(p.title LIKE %s OR p.process_type LIKE %s OR p.status LIKE %s)';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'p.business_id = %d';
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$clauses[] = 'p.vehicle_id = %d';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'p.client_id = %d';
		}

		if ( ! empty( $args['process_type'] ) ) {
			$clauses[] = 'p.process_type = %s';
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'p.status = %s';
		}

		if ( ! empty( $args['date_from'] ) ) {
			$clauses[] = 'p.created_at >= %s';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$clauses[] = 'p.created_at <= %s';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	protected function get_where_params( $args ) {
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$params[] = absint( $args['vehicle_id'] );
		}

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( ! empty( $args['process_type'] ) ) {
			$params[] = (string) $args['process_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$params[] = (string) $args['status'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
		}

		return $params;
	}

	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'           => 'p.id',
			'title'        => 'p.title',
			'process_type' => 'p.process_type',
			'status'       => 'p.status',
			'vehicle'      => 'v.make',
			'client'       => 'client_name',
			'opened_at'    => 'p.opened_at',
			'due_date'     => 'p.due_date',
			'created_at'   => 'p.created_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'p.created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}";
	}

	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'    => '%d',
			'vehicle_id'      => '%d',
			'client_id'       => '%d',
			'flow_id'         => '%d',
			'process_type'    => '%s',
			'title'           => '%s',
			'description'     => '%s',
			'internal_notes'  => '%s',
			'current_step_id' => '%d',
			'status'          => '%s',
			'priority'        => '%s',
			'opened_at'       => '%s',
			'due_date'        => '%s',
			'completed_at'    => '%s',
			'closed_at'       => '%s',
			'created_by'      => '%d',
			'assigned_to'     => '%d',
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

	/**
	 * Get formats for step log persistence.
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return array<int, string>
	 */
	protected function get_step_log_formats_for_data( $data ) {
		$format_map = array(
			'business_id'      => '%d',
			'process_id'        => '%d',
			'flow_step_id'      => '%d',
			'action_type'       => '%s',
			'message'           => '%s',
			'internal_note'     => '%s',
			'customer_visible'  => '%d',
			'created_by'        => '%d',
			'created_at'        => '%s',
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
