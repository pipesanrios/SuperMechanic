<?php
/**
 * Notification storage installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures persistent notifications storage table exists.
 */
class Notification_Storage_Installer {
	/**
	 * Get storage table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_notifications';
	}

	/**
	 * Create/update notifications table.
	 *
	 * @return void
	 */
	public function ensure_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table_name       = $this->get_table_name();
		$charset_collate  = $wpdb->get_charset_collate();

		// Keep compatibility with existing notification rows while exposing 50C fields.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			business_id bigint(20) unsigned NOT NULL default 1,
			user_id bigint(20) unsigned NOT NULL default 0,
			recipient_type varchar(20) NOT NULL default 'user',
			recipient_id bigint(20) unsigned NOT NULL default 0,
			object_type varchar(50) DEFAULT NULL,
			object_id bigint(20) unsigned DEFAULT NULL,
			process_id bigint(20) unsigned DEFAULT NULL,
			type varchar(80) NOT NULL,
			notification_type varchar(80) NOT NULL,
			title varchar(190) NOT NULL,
			message text DEFAULT NULL,
			status varchar(20) NOT NULL default 'unread',
			is_read tinyint(1) NOT NULL default 0,
			data longtext DEFAULT NULL,
			data_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			read_at datetime DEFAULT NULL,
			is_system tinyint(1) NOT NULL default 1,
			PRIMARY KEY  (id),
			KEY business_id (business_id),
			KEY user_id (user_id),
			KEY recipient_type (recipient_type),
			KEY recipient_id (recipient_id),
			KEY type (type),
			KEY notification_type (notification_type),
			KEY status (status),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
