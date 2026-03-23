<?php
/**
 * Plugin deactivator.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation cleanup.
 */
class Deactivator {
	/**
	 * Run deactivation routines.
	 *
	 * Roles are preserved on deactivation to avoid unexpected user-role
	 * reassignment. Capabilities are removed so permissions do not linger.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$capabilities = new Capabilities();
		$capabilities->remove_capabilities();
	}
}
