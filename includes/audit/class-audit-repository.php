<?php
/**
 * Audit repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Audit;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for deep audit events.
 */
class Audit_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Audit_Installer
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
	 * @param Audit_Installer|null          $installer Installer dependency.
	 * @param Business_Context_Service|null $business_context_service Business context dependency.
	 */
	public function __construct( Audit_Installer $installer = null, Business_Context_Service $business_context_service = null ) {
		$this->installer                = $installer ? $installer : new Audit_Installer();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->installer->ensure_table();
	}

	/**
	 * Insert one audit record.
	 *
	 * @param array<string,mixed> $data Audit payload.
	 * @return int Inserted ID.
	 */
	public function insert_audit( array $data ) {
		global $wpdb;

		$business_id = isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0;
		if ( $business_id <= 0 ) {
			$business_id = $this->resolve_business_id();
		}

		$inserted = $wpdb->insert(
			$this->installer->get_table_name(),
			array(
				'audit_type'    => isset( $data['audit_type'] ) ? sanitize_key( (string) $data['audit_type'] ) : 'configuration',
				'entity_type'   => isset( $data['entity_type'] ) ? sanitize_key( (string) $data['entity_type'] ) : 'system',
				'entity_id'     => isset( $data['entity_id'] ) ? absint( $data['entity_id'] ) : 0,
				'action'        => isset( $data['action'] ) ? sanitize_key( (string) $data['action'] ) : 'update',
				'actor_user_id' => isset( $data['actor_user_id'] ) ? absint( $data['actor_user_id'] ) : 0,
				'business_id'   => $business_id,
				'before_json'   => isset( $data['before_json'] ) ? wp_json_encode( $data['before_json'] ) : wp_json_encode( array() ),
				'after_json'    => isset( $data['after_json'] ) ? wp_json_encode( $data['after_json'] ) : wp_json_encode( array() ),
				'context_json'  => isset( $data['context_json'] ) ? wp_json_encode( $data['context_json'] ) : wp_json_encode( array() ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
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

