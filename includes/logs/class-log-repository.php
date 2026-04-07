<?php
/**
 * Log repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Logs;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Data access layer for structured logs.
 */
class Log_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Log_Installer
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
	 * @param Log_Installer|null             $installer Installer.
	 * @param Business_Context_Service|null  $business_context_service Business context service.
	 */
	public function __construct( Log_Installer $installer = null, Business_Context_Service $business_context_service = null ) {
		$this->installer                = $installer ? $installer : new Log_Installer();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->installer->ensure_table();
	}

	/**
	 * Insert one log row.
	 *
	 * @param string $log_type Log type.
	 * @param string $source Source.
	 * @param int    $reference_id Reference ID.
	 * @param string $status Status.
	 * @param string $message Message.
	 * @param string $context_json Context JSON.
	 * @return int
	 */
	public function insert_log( $log_type, $source, $reference_id, $status, $message, $context_json ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->installer->get_table_name(),
			array(
				'business_id' => $this->resolve_business_id(),
				'log_type'    => sanitize_key( (string) $log_type ),
				'source'      => sanitize_key( (string) $source ),
				'reference_id'=> absint( $reference_id ),
				'status'      => sanitize_key( (string) $status ),
				'message'     => sanitize_text_field( (string) $message ),
				'context_json'=> (string) $context_json,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Resolve business scope.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		$business_id = absint( $this->business_context_service->resolve_business_id_for_user( get_current_user_id() ) );
		return $business_id > 0 ? $business_id : 1;
	}
}

