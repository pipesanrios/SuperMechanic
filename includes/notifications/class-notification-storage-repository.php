<?php
/**
 * Notification storage repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Data access layer for persistent user notifications.
 */
class Notification_Storage_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Notification_Storage_Installer
	 */
	protected $installer;

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Notification_Storage_Installer|null $installer Installer.
	 * @param Business_Context_Service|null       $business_context_service Business context.
	 */
	public function __construct( Notification_Storage_Installer $installer = null, Business_Context_Service $business_context_service = null ) {
		$this->installer                = $installer ? $installer : new Notification_Storage_Installer();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->installer->ensure_table();
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->installer->get_table_name();
	}

	/**
	 * Insert notification row.
	 *
	 * @param array<string,mixed> $payload Row payload.
	 * @return int|false
	 */
	public function insert_notification( array $payload ) {
		global $wpdb;

		$now        = current_time( 'mysql' );
		$data_array = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
		$data_json  = wp_json_encode( $data_array );
		if ( false === $data_json ) {
			$data_json = '{}';
		}

		$row = array(
			'business_id'        => isset( $payload['business_id'] ) ? absint( $payload['business_id'] ) : $this->resolve_business_id( isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0 ),
			'user_id'            => isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0,
			'recipient_type'     => 'user',
			'recipient_id'       => isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0,
			'type'               => isset( $payload['type'] ) ? sanitize_key( (string) $payload['type'] ) : '',
			'notification_type'  => isset( $payload['type'] ) ? sanitize_key( (string) $payload['type'] ) : '',
			'title'              => isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '',
			'message'            => isset( $payload['message'] ) ? wp_kses_post( (string) $payload['message'] ) : '',
			'status'             => 'unread',
			'is_read'            => 0,
			'data'               => (string) $data_json,
			'data_json'          => (string) $data_json,
			'created_at'         => $now,
			'updated_at'         => $now,
			'is_system'          => 1,
		);

		$formats = array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' );
		$result  = $wpdb->insert( $this->get_table_name(), $row, $formats );
		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * List user notifications.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $filters Optional filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_user_notifications( $user_id, array $filters = array() ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$filters = wp_parse_args(
			$filters,
			array(
				'status'   => '',
				'type'     => '',
				'page'     => 1,
				'per_page' => 50,
			)
		);

		$where  = array( 'user_id = %d' );
		$params = array( $user_id );

		$status = sanitize_key( (string) $filters['status'] );
		if ( in_array( $status, array( 'unread', 'read' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$type = sanitize_key( (string) $filters['type'] );
		if ( '' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}

		$page     = max( 1, absint( $filters['page'] ) );
		$per_page = max( 1, min( 100, absint( $filters['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$params[] = $per_page;
		$params[] = $offset;

		$sql   = "SELECT id, user_id, type, title, message, status, data, created_at, read_at FROM {$this->get_table_name()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * List notifications for admin table.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @param int                 $page Page.
	 * @param int                 $per_page Per page.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_admin_notifications( array $filters = array(), $page = 1, $per_page = 50 ) {
		global $wpdb;

		$filters = wp_parse_args(
			$filters,
			array(
				'user_id' => 0,
				'status'  => '',
				'type'    => '',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$user_id = absint( $filters['user_id'] );
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		}

		$status = sanitize_key( (string) $filters['status'] );
		if ( in_array( $status, array( 'unread', 'read' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$type = sanitize_key( (string) $filters['type'] );
		if ( '' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 200, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$params[] = $per_page;
		$params[] = $offset;

		$sql   = "SELECT id, user_id, type, title, message, status, created_at, read_at FROM {$this->get_table_name()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count notifications for admin table.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @return int
	 */
	public function count_admin_notifications( array $filters = array() ) {
		global $wpdb;

		$filters = wp_parse_args(
			$filters,
			array(
				'user_id' => 0,
				'status'  => '',
				'type'    => '',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		$user_id = absint( $filters['user_id'] );
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		}

		$status = sanitize_key( (string) $filters['status'] );
		if ( in_array( $status, array( 'unread', 'read' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$type = sanitize_key( (string) $filters['type'] );
		if ( '' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}

		$sql = "SELECT COUNT(*) FROM {$this->get_table_name()} WHERE " . implode( ' AND ', $where );
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$count = $wpdb->get_var( $sql );
		return absint( $count );
	}

	/**
	 * Mark one notification as read.
	 *
	 * @param int $notification_id Notification ID.
	 * @param int $user_id Optional user owner validation.
	 * @return bool
	 */
	public function mark_as_read( $notification_id, $user_id = 0 ) {
		global $wpdb;

		$notification_id = absint( $notification_id );
		$user_id         = absint( $user_id );
		if ( $notification_id <= 0 ) {
			return false;
		}

		$where = array( 'id' => $notification_id );
		$where_format = array( '%d' );
		if ( $user_id > 0 ) {
			$where['user_id'] = $user_id;
			$where_format[]   = '%d';
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => 'read',
				'is_read'    => 1,
				'read_at'    => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			$where,
			array( '%s', '%d', '%s', '%s' ),
			$where_format
		);

		return false !== $result;
	}

	/**
	 * Resolve business ID.
	 *
	 * @param int $user_id Optional user ID.
	 * @return int
	 */
	protected function resolve_business_id( $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( $user_id > 0 ) {
			$business_id = absint( $this->business_context_service->resolve_business_id_for_user( $user_id ) );
			if ( $business_id > 0 ) {
				return $business_id;
			}
		}

		return absint( $this->business_context_service->resolve_business_id_for_user( get_current_user_id() ) );
	}
}
