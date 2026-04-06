<?php
/**
 * Webhook repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Webhooks;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for webhook endpoints.
 */
class Webhook_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Webhook_Installer
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
	 * @param Webhook_Installer|null        $installer Installer.
	 * @param Business_Context_Service|null $business_context_service Business context.
	 */
	public function __construct( Webhook_Installer $installer = null, Business_Context_Service $business_context_service = null ) {
		$this->installer                = $installer ? $installer : new Webhook_Installer();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->installer->ensure_table();
	}

	/**
	 * Get active webhooks subscribed to one event.
	 *
	 * @param string $event_type Event key.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_webhooks_by_event( $event_type ) {
		global $wpdb;

		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			return array();
		}

		$business_id = $this->resolve_business_id();
		$table       = $this->installer->get_table_name();
		$event_like  = '%"' . $wpdb->esc_like( $event_type ) . '"%';

		$sql = "SELECT id, name, url, endpoint_url, event_type, events_json, is_active, status, secret_key
			FROM {$table}
			WHERE business_id = %d
			AND (
				is_active = 1
				OR status = 'active'
			)
			AND (
				event_type = %s
				OR events_json LIKE %s
			)
			ORDER BY id DESC";

		$query = $wpdb->prepare( $sql, $business_id, $event_type, $event_like );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get webhooks list for current business.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_webhooks() {
		global $wpdb;

		$business_id = $this->resolve_business_id();
		$table       = $this->installer->get_table_name();
		$query       = $wpdb->prepare(
			"SELECT id, business_id, name, url, endpoint_url, event_type, events_json, is_active, status, secret_key, created_at, updated_at
			FROM {$table}
			WHERE business_id = %d
			ORDER BY id DESC",
			$business_id
		);
		$rows        = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get one webhook by id and current business scope.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array<string,mixed>|null
	 */
	public function get_webhook_by_id( $webhook_id ) {
		global $wpdb;

		$webhook_id  = absint( $webhook_id );
		$business_id = $this->resolve_business_id();
		if ( $webhook_id <= 0 ) {
			return null;
		}

		$table = $this->installer->get_table_name();
		$query = $wpdb->prepare(
			"SELECT id, business_id, name, url, endpoint_url, event_type, events_json, is_active, status, secret_key, created_at, updated_at
			FROM {$table}
			WHERE id = %d AND business_id = %d
			LIMIT 1",
			$webhook_id,
			$business_id
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert one webhook.
	 *
	 * @param array<string,mixed> $data Webhook data.
	 * @return int Inserted ID.
	 */
	public function create_webhook( array $data ) {
		global $wpdb;

		$table       = $this->installer->get_table_name();
		$business_id = $this->resolve_business_id();
		$now         = current_time( 'mysql' );
		$inserted    = $wpdb->insert(
			$table,
			array(
				'business_id' => $business_id,
				'name'        => isset( $data['name'] ) ? (string) $data['name'] : '',
				'url'         => isset( $data['url'] ) ? (string) $data['url'] : '',
				'endpoint_url'=> isset( $data['url'] ) ? (string) $data['url'] : '',
				'event_type'  => isset( $data['event_type'] ) ? (string) $data['event_type'] : '',
				'events_json' => wp_json_encode(
					array(
						isset( $data['event_type'] ) ? (string) $data['event_type'] : '',
					)
				),
				'is_active'   => ! empty( $data['is_active'] ) ? 1 : 0,
				'status'      => ! empty( $data['is_active'] ) ? 'active' : 'inactive',
				'secret_key'  => isset( $data['secret_key'] ) ? (string) $data['secret_key'] : '',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Update one webhook.
	 *
	 * @param int                $webhook_id Webhook ID.
	 * @param array<string,mixed> $data Webhook data.
	 * @return bool
	 */
	public function update_webhook( $webhook_id, array $data ) {
		global $wpdb;

		$webhook_id  = absint( $webhook_id );
		$business_id = $this->resolve_business_id();
		if ( $webhook_id <= 0 ) {
			return false;
		}

		$table   = $this->installer->get_table_name();
		$updated = $wpdb->update(
			$table,
			array(
				'name'         => isset( $data['name'] ) ? (string) $data['name'] : '',
				'url'          => isset( $data['url'] ) ? (string) $data['url'] : '',
				'endpoint_url' => isset( $data['url'] ) ? (string) $data['url'] : '',
				'event_type'   => isset( $data['event_type'] ) ? (string) $data['event_type'] : '',
				'events_json'  => wp_json_encode(
					array(
						isset( $data['event_type'] ) ? (string) $data['event_type'] : '',
					)
				),
				'is_active'    => ! empty( $data['is_active'] ) ? 1 : 0,
				'status'       => ! empty( $data['is_active'] ) ? 'active' : 'inactive',
				'secret_key'   => isset( $data['secret_key'] ) ? (string) $data['secret_key'] : '',
				'updated_at'   => current_time( 'mysql' ),
			),
			array(
				'id'          => $webhook_id,
				'business_id' => $business_id,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete one webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return bool
	 */
	public function delete_webhook( $webhook_id ) {
		global $wpdb;

		$webhook_id  = absint( $webhook_id );
		$business_id = $this->resolve_business_id();
		if ( $webhook_id <= 0 ) {
			return false;
		}

		$table   = $this->installer->get_table_name();
		$deleted = $wpdb->delete(
			$table,
			array(
				'id'          => $webhook_id,
				'business_id' => $business_id,
			),
			array( '%d', '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Set active status.
	 *
	 * @param int  $webhook_id Webhook ID.
	 * @param bool $is_active Active flag.
	 * @return bool
	 */
	public function set_webhook_active( $webhook_id, $is_active ) {
		global $wpdb;

		$webhook_id  = absint( $webhook_id );
		$business_id = $this->resolve_business_id();
		if ( $webhook_id <= 0 ) {
			return false;
		}

		$table   = $this->installer->get_table_name();
		$updated = $wpdb->update(
			$table,
			array(
				'is_active'  => $is_active ? 1 : 0,
				'status'     => $is_active ? 'active' : 'inactive',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'          => $webhook_id,
				'business_id' => $business_id,
			),
			array( '%d', '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Resolve current business id.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		$business_id = absint( $this->business_context_service->resolve_business_id_for_user( get_current_user_id() ) );
		if ( $business_id > 0 ) {
			return $business_id;
		}

		return 1;
	}
}
