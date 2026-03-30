<?php
/**
 * Client repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client persistence.
 */
class Client_Repository {
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service|null
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service;
	}

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
	 * Get the client CRM meta table name.
	 *
	 * @return string
	 */
	public function get_client_crm_meta_table_name() {
		$tables = Schema::get_tables();

		return $tables['client_crm_meta'];
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
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d AND business_id = %d LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $result ) ) {
			return null;
		}

		return array_merge(
			$result,
			$this->get_crm_defaults(),
			$this->get_crm_meta( absint( $result['id'] ) )
		);
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
				'status'            => '',
				'date_from'         => '',
				'date_to'           => '',
				'page'              => 1,
				'per_page'          => 20,
				'orderby'           => 'created_at',
				'order'             => 'DESC',
				'business_id'       => $this->resolve_business_id(),
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
				'status'            => '',
				'date_from'         => '',
				'date_to'           => '',
				'business_id'       => $this->resolve_business_id(),
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
		$data['business_id'] = ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id();

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
	 * Delete a client.
	 *
	 * @param int $id Client ID.
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
	 * Get CRM metadata by client ID.
	 *
	 * @param int $client_id Client ID.
	 * @return array<string, mixed>
	 */
	public function get_crm_meta( $client_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT crm_status, assigned_user_id, last_contact_at, next_follow_up_at, commercial_notes
			FROM {$this->get_client_crm_meta_table_name()}
			WHERE client_id = %d AND business_id = %d
			LIMIT 1",
			absint( $client_id ),
			$this->resolve_business_id()
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $result ) ) {
			return $this->get_crm_defaults();
		}

		return array_merge( $this->get_crm_defaults(), $result );
	}

	/**
	 * Upsert CRM metadata by client ID.
	 *
	 * @param int                 $client_id Client ID.
	 * @param array<string, mixed> $data CRM data.
	 * @return bool
	 */
	public function upsert_crm_meta( $client_id, $data ) {
		global $wpdb;

		$client_id   = absint( $client_id );
		$business_id = $this->resolve_business_id();
		$now         = current_time( 'mysql' );
		$payload     = array(
			'business_id'       => $business_id,
			'client_id'         => $client_id,
			'crm_status'        => isset( $data['crm_status'] ) ? (string) $data['crm_status'] : 'lead',
			'assigned_user_id'  => ! empty( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : null,
			'last_contact_at'   => ! empty( $data['last_contact_at'] ) ? (string) $data['last_contact_at'] : null,
			'next_follow_up_at' => ! empty( $data['next_follow_up_at'] ) ? (string) $data['next_follow_up_at'] : null,
			'commercial_notes'  => isset( $data['commercial_notes'] ) ? (string) $data['commercial_notes'] : '',
			'updated_at'        => $now,
		);

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->get_client_crm_meta_table_name()} WHERE client_id = %d AND business_id = %d LIMIT 1",
				$client_id,
				$business_id
			)
		);

		if ( $existing_id > 0 ) {
			$result = $wpdb->update(
				$this->get_client_crm_meta_table_name(),
				$payload,
				array(
					'id'          => $existing_id,
					'business_id' => $business_id,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d', '%d' )
			);

			return false !== $result;
		}

		$payload['created_at'] = $now;

		$result = $wpdb->insert(
			$this->get_client_crm_meta_table_name(),
			$payload,
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete CRM metadata by client ID.
	 *
	 * @param int $client_id Client ID.
	 * @return bool
	 */
	public function delete_crm_meta( $client_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_client_crm_meta_table_name(),
			array(
				'client_id'   => absint( $client_id ),
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
			$clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR document_id LIKE %s)';
		}

		if ( ! empty( $args['business_id'] ) ) {
			$clauses[] = 'business_id = %d';
		}

		if ( ! empty( $args['exact_email'] ) ) {
			$clauses[] = 'email = %s';
		}

		if ( ! empty( $args['exact_document_id'] ) ) {
			$clauses[] = 'document_id = %s';
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
		}

		if ( ! empty( $args['date_from'] ) ) {
			$clauses[] = 'created_at >= %s';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$clauses[] = 'created_at <= %s';
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

		if ( ! empty( $args['business_id'] ) ) {
			$params[] = absint( $args['business_id'] );
		}

		if ( ! empty( $args['exact_email'] ) ) {
			$params[] = (string) $args['exact_email'];
		}

		if ( ! empty( $args['exact_document_id'] ) ) {
			$params[] = (string) $args['exact_document_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$params[] = sanitize_key( (string) $args['status'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
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
			'%d',
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
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		if ( null === $this->business_context_service ) {
			$this->business_context_service = new Business_Context_Service();
		}

		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Get default CRM metadata payload.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_crm_defaults() {
		return array(
			'crm_status'        => 'lead',
			'assigned_user_id'  => 0,
			'last_contact_at'   => '',
			'next_follow_up_at' => '',
			'commercial_notes'  => '',
		);
	}
}

