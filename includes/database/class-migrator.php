<?php
/**
 * Database migrator.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Executes dbDelta-based schema migrations.
 */
class Migrator {
	/**
	 * Database schema version option name.
	 */
	const DB_VERSION_OPTION = 'sm_db_version';

	/**
	 * Execute all schema migrations.
	 *
	 * @return array<string, array<string>>
	 */
	public static function migrate() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$results = array();

		foreach ( Schema::get_sql() as $sql ) {
			$results[ md5( $sql ) ] = dbDelta( $sql );
		}

		return $results;
	}

	/**
	 * Run migrations when the stored schema version is outdated.
	 *
	 * @return array<string, array<string>>
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( self::DB_VERSION_OPTION, '' );
		$current_version   = Schema::get_schema_version();

		if ( version_compare( (string) $installed_version, $current_version, '<' ) ) {
			return self::migrate();
		}

		return array();
	}
}
