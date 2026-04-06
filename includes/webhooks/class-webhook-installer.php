<?php
/**
 * Webhook installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures webhook table exists with 50D-compatible fields.
 */
class Webhook_Installer {
	/**
	 * Get webhooks table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_webhooks';
	}

	/**
	 * Ensure table schema.
	 *
	 * @return void
	 */
	public function ensure_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Keep legacy-compatible columns while exposing 50D canonical fields.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			business_id bigint(20) unsigned NOT NULL default 1,
			name varchar(190) NOT NULL,
			url varchar(255) DEFAULT NULL,
			endpoint_url varchar(255) DEFAULT NULL,
			event_type varchar(120) DEFAULT NULL,
			events_json longtext DEFAULT NULL,
			is_active tinyint(1) NOT NULL default 1,
			status varchar(20) NOT NULL default 'active',
			secret_key longtext DEFAULT NULL,
			secret_encrypted longtext DEFAULT NULL,
			secret_hash varchar(191) DEFAULT NULL,
			last_used_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY is_active (is_active),
			KEY status (status),
			KEY business_id (business_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
