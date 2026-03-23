<?php
/**
 * Plugin installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Database\Migrator;
use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates plugin installation tasks.
 */
class Installer {
	/**
	 * Database schema version option name.
	 */
	const DB_VERSION_OPTION = 'sm_db_version';

	/**
	 * Run installation tasks.
	 *
	 * @return array<string, mixed>
	 */
	public static function install() {
		$results        = Migrator::migrate();
		$schema_version = Schema::get_schema_version();

		update_option( self::DB_VERSION_OPTION, $schema_version );

		// Reserved for future install-time tasks such as optional default seeds.
		return array(
			'schema_version' => $schema_version,
			'migrations'     => $results,
		);
	}
}
