<?php
/**
 * Client repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client persistence.
 */
class Client_Repository {
	/**
	 * Get the clients table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get a client by ID.
	 *
	 * @param int $id Client ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1",
			absint( $id )
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get clients list.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'            => '',
				'page'              => 1,
				'per_page'          => 20,
				'orderby'           => 'created_at',
				'order'             => 'DESC',
				'exclude_id'        => 0,
				'exact_email'       => '',
				'exact_document_id' => '',
			)
		);

		$where  = $this->build_where_clause( $args );
		$order  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page   = max( 1, absint( $args['page'] ) );
		$limit  = max( 1, absint( $args['per_page'] ) );
		$offset = ( $page - 1 ) * $limit;
		$sql    = "SELECT * FROM {$this->get_table_name()} {$where} {$order} LIMIT %d OFFSET %d";
		$params = $this->get_where_params( $args );
		$params[] = $limit;
		$params[] = $offset;

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count clients.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'            => '',
				'exclude_id'        => 0,
				'exact_email'       => '',
				'exact_document_id' => '',
			)
		);

		$where = $this->build_where_clause( $args );
		$sql   = "SELECT COUNT(id) FROM {$this->get_table_name()} {$where}";

		if ( '' === $where ) {
			return (int) $wpdb->get_var( $sql );
		}

		$query = $wpdb->prepare( $sql, $this->get_where_params( $args ) );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Insert a new client.
	 *
	 * @param array<string, mixed> $data Client data.
	 * @return int|false
	 */
	public function insert( $data ) {
		global $wpdb;

		$now               = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats() );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a client.
	 *
	 * @param int                 $id   Client ID.
	 * @param array<string, mixed> $data Client data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => absint( $id ) ),
			$this->get_update_formats(),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a client.
	 *
	 * @param int $id Client ID.
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
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function build_where_clause( $args ) {
		$clauses = array();

		if ( ! empty( $args['search'] ) ) {
			$clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR document_id LIKE %s)';
		}

		if ( ! empty( $args['exact_email'] ) ) {
			$clauses[] = 'email = %s';
		}

		if ( ! empty( $args['exact_document_id'] ) ) {
			$clauses[] = 'document_id = %s';
		}

		if ( ! empty( $args['exclude_id'] ) ) {
			$clauses[] = 'id != %d';
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

		if ( ! empty( $args['exact_email'] ) ) {
			$params[] = (string) $args['exact_email'];
		}

		if ( ! empty( $args['exact_document_id'] ) ) {
			$params[] = (string) $args['exact_document_id'];
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
			'id',
			'first_name',
			'last_name',
			'email',
			'phone',
			'document_id',
			'created_at',
		);

		$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
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
			'%s',
			'%s',
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
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);
	}
}

