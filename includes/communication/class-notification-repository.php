<?php
/**
 * Notification repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles notification persistence.
 */
class Notification_Repository {
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['notifications'];
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

	public function get_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'business_id'       => $this->resolve_business_id(),
				'recipient_type'    => '',
				'recipient_id'      => 0,
				'object_type'       => '',
				'object_id'         => 0,
				'process_id'        => 0,
				'notification_type' => '',
				'is_read'           => null,
				'is_system'         => null,
				'search'            => '',
				'page'              => 1,
				'per_page'          => 50,
				'orderby'           => 'created_at',
				'order'             => 'DESC',
			)
		);

		$where    = $this->build_where_clause( $args );
		$params   = $this->get_where_params( $args );
		$orderby  = $this->build_order_clause( $args['orderby'], $args['order'] );
		$page     = max( 1, absint( $args['page'] ) );
		$limit    = max( 1, absint( $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $limit;
		$params[] = $limit;
		$params[] = $offset;

		$sql   = "SELECT * FROM {$this->get_table_name()} {$where} {$orderby} LIMIT %d OFFSET %d";
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	public function count_all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'business_id'       => $this->resolve_business_id(),
				'recipient_type'    => '',
				'recipient_id'      => 0,
				'object_type'       => '',
				'object_id'         => 0,
				'process_id'        => 0,
				'notification_type' => '',
				'is_read'           => null,
				'is_system'         => null,
				'search'            => '',
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

	public function get_by_recipient( $recipient_type, $recipient_id, $args = array() ) {
		return $this->get_all(
			array_merge(
				$args,
				array(
					'recipient_type' => sanitize_key( $recipient_type ),
					'recipient_id'   => absint( $recipient_id ),
				)
			)
		);
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
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			$this->get_formats_for_data( $data ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

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

	public function mark_as_read( $id ) {
		return $this->update(
			$id,
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			)
		);
	}

	public function mark_all_as_read( $recipient_type, $recipient_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'is_read'    => 1,
				'read_at'    => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'business_id'    => $this->resolve_business_id(),
				'recipient_type' => sanitize_key( $recipient_type ),
				'recipient_id'   => absint( $recipient_id ),
				'is_read'        => 0,
			),
			array( '%d', '%s', '%s' ),
			array( '%d', '%s', '%d', '%d' )
		);

		return false !== $result;
	}

	protected function build_where_clause( $args ) {
		$clauses = array();
		$clauses[] = 'business_id = %d';

		if ( '' !== $args['recipient_type'] ) {
			$clauses[] = 'recipient_type = %s';
		}

		if ( ! empty( $args['recipient_id'] ) ) {
			$clauses[] = 'recipient_id = %d';
		}

		if ( '' !== $args['object_type'] ) {
			$clauses[] = 'object_type = %s';
		}

		if ( ! empty( $args['object_id'] ) ) {
			$clauses[] = 'object_id = %d';
		}

		if ( ! empty( $args['process_id'] ) ) {
			$clauses[] = 'process_id = %d';
		}

		if ( '' !== $args['notification_type'] ) {
			$clauses[] = 'notification_type = %s';
		}

		if ( null !== $args['is_read'] ) {
			$clauses[] = 'is_read = %d';
		}

		if ( null !== $args['is_system'] ) {
			$clauses[] = 'is_system = %d';
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = '(title LIKE %s OR message LIKE %s)';
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	protected function get_where_params( $args ) {
		global $wpdb;

		$params = array();
		$params[] = ! empty( $args['business_id'] ) ? absint( $args['business_id'] ) : $this->resolve_business_id();

		if ( '' !== $args['recipient_type'] ) {
			$params[] = sanitize_key( $args['recipient_type'] );
		}

		if ( ! empty( $args['recipient_id'] ) ) {
			$params[] = absint( $args['recipient_id'] );
		}

		if ( '' !== $args['object_type'] ) {
			$params[] = sanitize_key( $args['object_type'] );
		}

		if ( ! empty( $args['object_id'] ) ) {
			$params[] = absint( $args['object_id'] );
		}

		if ( ! empty( $args['process_id'] ) ) {
			$params[] = absint( $args['process_id'] );
		}

		if ( '' !== $args['notification_type'] ) {
			$params[] = sanitize_key( $args['notification_type'] );
		}

		if ( null !== $args['is_read'] ) {
			$params[] = (int) (bool) $args['is_read'];
		}

		if ( null !== $args['is_system'] ) {
			$params[] = (int) (bool) $args['is_system'];
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
		}

		return $params;
	}

	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'         => 'id',
			'created_at' => 'created_at',
			'updated_at' => 'updated_at',
			'is_read'    => 'is_read',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}, id DESC";
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'       => '%d',
			'recipient_type'    => '%s',
			'recipient_id'      => '%d',
			'object_type'       => '%s',
			'object_id'         => '%d',
			'process_id'        => '%d',
			'notification_type' => '%s',
			'title'             => '%s',
			'message'           => '%s',
			'data_json'         => '%s',
			'is_read'           => '%d',
			'read_at'           => '%s',
			'created_at'        => '%s',
			'updated_at'        => '%s',
			'is_system'         => '%d',
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
