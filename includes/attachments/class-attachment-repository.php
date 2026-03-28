<?php
/**
 * Attachment repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Attachments;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles attachment persistence.
 */
class Attachment_Repository {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['attachments'];
	}

	/**
	 * Get attachment by ID.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string, mixed>|null
	 */
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

	/**
	 * Get attachments.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
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
				'attachment_type'   => '',
				'is_internal'       => null,
				'is_client_visible' => null,
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

	/**
	 * Get attachments by object.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param array  $args        Extra args.
	 * @return array<int, array<string, mixed>>
	 */
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

	/**
	 * Get attachments by process.
	 *
	 * @param int   $process_id Process ID.
	 * @param array $args       Extra args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_process_id( $process_id, $args = array() ) {
		return $this->get_all( array_merge( $args, array( 'process_id' => absint( $process_id ) ) ) );
	}

	/**
	 * Get attachments by client.
	 *
	 * @param int   $client_id Client ID.
	 * @param array $args      Extra args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_client_id( $client_id, $args = array() ) {
		return $this->get_all( array_merge( $args, array( 'client_id' => absint( $client_id ) ) ) );
	}

	/**
	 * Get attachments by vehicle.
	 *
	 * @param int   $vehicle_id Vehicle ID.
	 * @param array $args       Extra args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_vehicle_id( $vehicle_id, $args = array() ) {
		return $this->get_all( array_merge( $args, array( 'vehicle_id' => absint( $vehicle_id ) ) ) );
	}

	/**
	 * Insert attachment row.
	 *
	 * @param array<string, mixed> $data Row data.
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
	 * Update attachment row.
	 *
	 * @param int                  $id   Attachment ID.
	 * @param array<string, mixed> $data Row data.
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
			$this->get_formats_for_data( $data ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete attachment row.
	 *
	 * @param int $id Attachment ID.
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

		if ( '' !== $args['attachment_type'] ) {
			$clauses[] = 'attachment_type = %s';
		}

		if ( null !== $args['is_internal'] ) {
			$clauses[] = 'is_internal = %d';
		}

		if ( null !== $args['is_client_visible'] ) {
			$clauses[] = 'is_client_visible = %d';
		}

		if ( '' !== $args['search'] ) {
			$clauses[] = '(title LIKE %s OR description LIKE %s OR mime_type LIKE %s)';
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

		if ( '' !== $args['attachment_type'] ) {
			$params[] = sanitize_key( $args['attachment_type'] );
		}

		if ( null !== $args['is_internal'] ) {
			$params[] = (int) (bool) $args['is_internal'];
		}

		if ( null !== $args['is_client_visible'] ) {
			$params[] = (int) (bool) $args['is_client_visible'];
		}

		if ( '' !== $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		return $params;
	}

	/**
	 * Build ORDER clause.
	 *
	 * @param string $orderby Field.
	 * @param string $order   Direction.
	 * @return string
	 */
	protected function build_order_clause( $orderby, $order ) {
		$allowed = array(
			'id'                => 'id',
			'title'             => 'title',
			'attachment_type'   => 'attachment_type',
			'mime_type'         => 'mime_type',
			'file_size'         => 'file_size',
			'is_client_visible' => 'is_client_visible',
			'created_at'        => 'created_at',
			'updated_at'        => 'updated_at',
		);

		$orderby = isset( $allowed[ $orderby ] ) ? $allowed[ $orderby ] : 'created_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY {$orderby} {$order}, id DESC";
	}

	/**
	 * Get formats for insert/update.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'business_id'       => '%d',
			'object_type'       => '%s',
			'object_id'         => '%d',
			'process_id'        => '%d',
			'client_id'         => '%d',
			'vehicle_id'        => '%d',
			'attachment_type'   => '%s',
			'title'             => '%s',
			'description'       => '%s',
			'file_url'          => '%s',
			'file_path'         => '%s',
			'mime_type'         => '%s',
			'file_size'         => '%d',
			'is_internal'       => '%d',
			'is_client_visible' => '%d',
			'uploaded_by'       => '%d',
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
