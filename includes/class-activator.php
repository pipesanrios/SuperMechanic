<?php
/**
 * Plugin activator.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation tasks.
 */
class Activator {
	/**
	 * Run activation routines.
	 *
	 * @return void
	 */
	public static function activate() {
		$roles        = new Roles();
		$capabilities = new Capabilities();

		$roles->register_roles();
		$capabilities->add_capabilities();
		Installer::install();
	}
}
