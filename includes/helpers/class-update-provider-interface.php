<?php
/**
 * Update provider contract.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the contract for private update providers.
 */
interface Update_Provider_Interface {
	/**
	 * Resolve update metadata for the current plugin runtime.
	 *
	 * @param array<string, mixed> $context Context payload.
	 * @return array<string, mixed>
	 */
	public function get_update_metadata( array $context = array() );

	/**
	 * Get provider identifier.
	 *
	 * @return string
	 */
	public function get_provider_name();
}

