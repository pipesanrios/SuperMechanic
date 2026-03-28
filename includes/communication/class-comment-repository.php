<?php
/**
 * Comment repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles comment persistence.
 */
class Comment_Repository {
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['comments'];
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
				'object_type'       => '',
				'object_id'         => 0,
				'process_id'        => 0,
				'client_id'         => 0,
				'vehicle_id'        => 0,
				'parent_id'         => null,
				'author_user_id'    => 0,
				'author_client_id'  => 0,
				'comment_type'      => '',
				'is_internal'       => null,
				'is_client_visible' => null,
				'status'            => '',
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
				'object_type'       => '',
				'object_id'         => 0,
				'process_id'        => 0,
				'client_id'         => 0,
				'vehicle_id'        => 0,
				'parent_id'         => null,
				'author_user_id'    => 0,
				'author_client_id'  => 0,
				'comment_type'      => '',
				'is_internal'       => null,
				'is_client_visible' => null,
				'status'            => '',
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

	public function get_by_object( $object_type, $object_id, $args = array() ) {
		return $this->get_all(
			array_merge(
				$args,
				array(
					'object_type' => sanitize_key( $object_type ),
					'object_id'   => absint( $object_id ),
				)
			)
		);
	}

	public function get_by_process_id( $process_id, $args = array() ) {
		return $this->get_all(
			array_merge(
				$args,
				array(
					'process_id' => absint( $process_id ),
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
		return $this->update(
			$id,
			array(
				'status' => 'archived',
			)
		);
	}

	protected function build_where_clause( $args ) {
		$clauses = array();
		$clauses[] = 'business_id = %d';

		if ( '' !== $args['object_type'] ) {
			$clauses[] = 'object_type = %s';
		}

		if ( ! empty( $args['object_id'] ) ) {
			$clauses[] = 'object_id = %d';
		}

		if ( ! empty( $args['process_id'] ) ) {
			$clauses[] = 'process_id = %d';
		}

		if ( ! empty( $args['client_id'] ) ) {
			$clauses[] = 'client_id = %d';
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$clauses[] = 'vehicle_id = %d';
		}

		if ( null !== $args['parent_id'] ) {
			$clauses[] = 'parent_id = %d';
		}

		if ( ! empty( $args['author_user_id'] ) ) {
			$clauses[] = 'author_user_id = %d';
		}

		if ( ! empty( $args['author_client_id'] ) ) {
			$clauses[] = 'author_client_id = %d';
		}

		if ( '' !== $args['comment_type'] ) {
			$clauses[] = 'comment_type = %s';
		}

		if ( null !== $args['is_internal'] ) {
			$clauses[] = 'is_internal = %d';
		}

		if ( null !== $args['is_client_visible'] ) {
			$clauses[] = 'is_client_visible = %d';
		}

		if ( '' !== $args['status'] ) {
			$clauses[] = 'status = %s';
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = 'content LIKE %s';
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

		if ( '' !== $args['object_type'] ) {
			$params[] = sanitize_key( $args['object_type'] );
		}

		if ( ! empty( $args['object_id'] ) ) {
			$params[] = absint( $args['object_id'] );
		}

		if ( ! empty( $args['process_id'] ) ) {
			$params[] = absint( $args['process_id'] );
		}

		if ( ! empty( $args['client_id'] ) ) {
			$params[] = absint( $args['client_id'] );
		}

		if ( ! empty( $args['vehicle_id'] ) ) {
			$params[] = absint( $args['vehicle_id'] );
		}

		if ( null !== $args['parent_id'] ) {
			$params[] = absint( $args['parent_id'] );
		}

		if ( ! empty( $args['author_user_id'] ) ) {
			$params[] = absint( $args['author_user_id'] );
		}

		if ( ! empty( $args['author_client_id'] ) ) {
			$params[] = absint( $args['author_client_id'] );
		}

		if ( '' !== $args['comment_type'] ) {
			$params[] = sanitize_key( $args['comment_type'] );
		}

		if ( null !== $args['is_internal'] ) {
			$params[] = (int) (bool) $args['is_internal'];
		}

		if ( null !== $args['is_client_visible'] ) {
			$params[] = (int) (bool) $args['is_client_visible'];
		}

		if ( '' !== $args['status'] ) {
			$params[] = sanitize_key( $args['status'] );
		}

		if ( '' !== $args['search'] ) {
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}

		return $params;
	}

	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'         => 'id',
			'created_at' => 'created_at',
			'updated_at' => 'updated_at',
			'status'     => 'status',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}, id DESC";
	}

	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'       => '%d',
			'object_type'       => '%s',
			'object_id'         => '%d',
			'process_id'        => '%d',
			'client_id'         => '%d',
			'vehicle_id'        => '%d',
			'parent_id'         => '%d',
			'author_user_id'    => '%d',
			'author_client_id'  => '%d',
			'comment_type'      => '%s',
			'content'           => '%s',
			'is_internal'       => '%d',
			'is_client_visible' => '%d',
			'status'            => '%s',
			'created_at'        => '%s',
			'updated_at'        => '%s',
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
