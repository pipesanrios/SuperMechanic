<?php
/**
 * Appointment repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles appointments persistence.
 */
class Appointment_Repository {
	/**
	 * Get appointments table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['appointments'];
	}

	/**
	 * Get one appointment.
	 *
	 * @param int $id Appointment ID.
	 * @return array<string,mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT a.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin, p.title AS process_title, u.display_name AS mechanic_name
			FROM {$this->get_table_name()} a
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = a.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = a.vehicle_id
			LEFT JOIN {$this->get_processes_table_name()} p ON p.id = a.process_id
			LEFT JOIN {$this->get_users_table_name()} u ON u.ID = a.assigned_to
			WHERE a.id = %d
			AND a.business_id = %d
			LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get one appointment by explicit tenant boundary.
	 *
	 * @param int $id          Appointment ID.
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	public function get_by_id_for_business( $id, $business_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT a.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin, p.title AS process_title, u.display_name AS mechanic_name
			FROM {$this->get_table_name()} a
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = a.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = a.vehicle_id
			LEFT JOIN {$this->get_processes_table_name()} p ON p.id = a.process_id
			LEFT JOIN {$this->get_users_table_name()} u ON u.ID = a.assigned_to
			WHERE a.id = %d
			AND a.business_id = %d
			LIMIT 1",
			absint( $id ),
			max( 1, absint( $business_id ) )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get appointments list.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_all( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'             => '',
				'business_id'        => $this->resolve_business_id(),
				'appointment_status' => '',
				'assigned_to'        => 0,
				'client_id'          => 0,
				'vehicle_id'         => 0,
				'date_from'          => '',
				'date_to'            => '',
				'page'               => 1,
				'per_page'           => 20,
				'orderby'            => 'start_at',
				'order'              => 'DESC',
			)
		);

		$where      = $this->build_where_clause( $args );
		$params     = $this->get_where_params( $args );
		$order      = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page       = max( 1, absint( $args['page'] ) );
		$per_page   = max( 1, absint( $args['per_page'] ) );
		$offset     = ( $page - 1 ) * $per_page;
		$params[]   = $per_page;
		$params[]   = $offset;
		$query      = $wpdb->prepare(
			"SELECT a.*, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name, v.make AS vehicle_make, v.model AS vehicle_model, v.plate AS vehicle_plate, v.vin AS vehicle_vin, p.title AS process_title, u.display_name AS mechanic_name
			FROM {$this->get_table_name()} a
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = a.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = a.vehicle_id
			LEFT JOIN {$this->get_processes_table_name()} p ON p.id = a.process_id
			LEFT JOIN {$this->get_users_table_name()} u ON u.ID = a.assigned_to
			{$where}
			{$order}
			LIMIT %d OFFSET %d",
			$params
		);
		$results    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count appointments.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return int
	 */
	public function count_all( array $args = array() ) {
		global $wpdb;

		$args  = wp_parse_args(
			$args,
			array(
				'search'             => '',
				'business_id'        => $this->resolve_business_id(),
				'appointment_status' => '',
				'assigned_to'        => 0,
				'client_id'          => 0,
				'vehicle_id'         => 0,
				'date_from'          => '',
				'date_to'            => '',
			)
		);
		$where = $this->build_where_clause( $args );
		$sql   = "SELECT COUNT(a.id)
			FROM {$this->get_table_name()} a
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = a.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = a.vehicle_id
			{$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $this->get_where_params( $args ) ) );
	}

	/**
	 * Get appointments for iCal export.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_for_ical_feed( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'business_id'        => $this->resolve_business_id(),
				'appointment_status' => '',
				'assigned_to'        => 0,
				'date_from'          => '',
				'date_to'            => '',
				'limit'              => 250,
			)
		);

		$where    = $this->build_where_clause(
			array(
				'search'             => '',
				'business_id'        => $this->resolve_business_id(),
				'appointment_status' => $args['appointment_status'],
				'assigned_to'        => $args['assigned_to'],
				'client_id'          => 0,
				'vehicle_id'         => 0,
				'date_from'          => $args['date_from'],
				'date_to'            => $args['date_to'],
			)
		);
		$params   = $this->get_where_params(
			array(
				'search'             => '',
				'business_id'        => $this->resolve_business_id(),
				'appointment_status' => $args['appointment_status'],
				'assigned_to'        => $args['assigned_to'],
				'client_id'          => 0,
				'vehicle_id'         => 0,
				'date_from'          => $args['date_from'],
				'date_to'            => $args['date_to'],
			)
		);
		$limit    = max( 1, min( 500, absint( $args['limit'] ) ) );
		$params[] = $limit;

		$query = $wpdb->prepare(
			"SELECT a.id, a.client_id, a.vehicle_id, a.process_id, a.assigned_to, a.appointment_status, a.appointment_date, a.start_at, a.notes, a.created_at, a.updated_at,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				c.email AS client_email,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate,
				v.vin AS vehicle_vin,
				u.display_name AS mechanic_name
			FROM {$this->get_table_name()} a
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = a.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = a.vehicle_id
			LEFT JOIN {$this->get_users_table_name()} u ON u.ID = a.assigned_to
			{$where}
			ORDER BY a.start_at ASC, a.id ASC
			LIMIT %d",
			$params
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert appointment.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int|false
	 */
	public function insert( array $data ) {
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
	 * Update appointment.
	 *
	 * @param int                 $id   Appointment ID.
	 * @param array<string,mixed> $data Data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
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

	/**
	 * Update appointment status by explicit tenant boundary.
	 *
	 * @param int    $id                 Appointment ID.
	 * @param int    $business_id        Business ID.
	 * @param string $appointment_status Next status.
	 * @return bool
	 */
	public function update_status_for_business( $id, $business_id, $appointment_status ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'appointment_status' => sanitize_key( (string) $appointment_status ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array(
				'id'          => absint( $id ),
				'business_id' => max( 1, absint( $business_id ) ),
			),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete appointment.
	 *
	 * @param int $id Appointment ID.
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
	 * @param array<string,mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( array $args ) {
		$clauses = array();

		if ( '' !== (string) $args['search'] ) {
			$clauses[] = '(a.notes LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR v.make LIKE %s OR v.model LIKE %s OR v.plate LIKE %s)';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'a.business_id = %d';
		}

		if ( '' !== (string) $args['appointment_status'] ) {
			$clauses[] = 'a.appointment_status = %s';
		}

		if ( ! empty( $args['assigned_to'] ) ) {
			$clauses[] = 'a.assigned_to = %d';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'a.client_id = %d';
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$clauses[] = 'a.vehicle_id = %d';
		}

		if ( '' !== (string) $args['date_from'] ) {
			$clauses[] = 'a.appointment_date >= %s';
		}

		if ( '' !== (string) $args['date_to'] ) {
			$clauses[] = 'a.appointment_date <= %s';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build params for WHERE.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,mixed>
	 */
	protected function get_where_params( array $args ) {
		$params = array();

		if ( '' !== (string) $args['search'] ) {
			$search    = '%' . $this->escape_like( (string) $args['search'] ) . '%';
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
			$params[]  = $search;
		}

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( '' !== (string) $args['appointment_status'] ) {
			$params[] = sanitize_key( (string) $args['appointment_status'] );
		}

		if ( ! empty( $args['assigned_to'] ) ) {
			$params[] = absint( $args['assigned_to'] );
		}

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$params[] = absint( $args['vehicle_id'] );
		}

		if ( '' !== (string) $args['date_from'] ) {
			$params[] = sanitize_text_field( (string) $args['date_from'] );
		}

		if ( '' !== (string) $args['date_to'] ) {
			$params[] = sanitize_text_field( (string) $args['date_to'] );
		}

		return $params;
	}

	/**
	 * Build order clause.
	 *
	 * @param string $orderby Order by.
	 * @param string $order   Order.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'                 => 'a.id',
			'appointment_date'   => 'a.appointment_date',
			'start_at'           => 'a.start_at',
			'appointment_status' => 'a.appointment_status',
			'assigned_to'        => 'a.assigned_to',
			'created_at'         => 'a.created_at',
		);
		$field   = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'a.start_at';
		$dir     = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$field} {$dir}";
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

	/**
	 * Get insert/update formats.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return array<int,string>
	 */
	protected function get_formats_for_data( array $data ) {
		$map = array(
			'business_id'         => '%d',
			'client_id'          => '%d',
			'vehicle_id'         => '%d',
			'process_id'         => '%d',
			'assigned_to'        => '%d',
			'appointment_status' => '%s',
			'appointment_date'   => '%s',
			'start_at'           => '%s',
			'notes'              => '%s',
			'created_at'         => '%s',
			'updated_at'         => '%s',
		);

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$formats[] = $map[ $key ];
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
	 * Get clients table.
	 *
	 * @return string
	 */
	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get vehicles table.
	 *
	 * @return string
	 */
	protected function get_vehicles_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	/**
	 * Get processes table.
	 *
	 * @return string
	 */
	protected function get_processes_table_name() {
		$tables = Schema::get_tables();

		return $tables['processes'];
	}

	/**
	 * Get users table.
	 *
	 * @return string
	 */
	protected function get_users_table_name() {
		global $wpdb;

		return $wpdb->users;
	}
}
