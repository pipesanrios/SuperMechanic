<?php
/**
 * License provider contract.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the contract for license providers.
 */
interface License_Provider_Interface {
	/**
	 * Activate a license key.
	 *
	 * @param string               $license_key   License key.
	 * @param array<string, mixed> $current_state Current stored state.
	 * @return array<string, mixed>
	 */
	public function activate( $license_key, array $current_state = array() );

	/**
	 * Validate current license state.
	 *
	 * @param array<string, mixed> $current_state Current stored state.
	 * @return array<string, mixed>
	 */
	public function validate( array $current_state = array() );

	/**
	 * Deactivate current license state.
	 *
	 * @param array<string, mixed> $current_state Current stored state.
	 * @return array<string, mixed>
	 */
	public function deactivate( array $current_state = array() );

	/**
	 * Get provider identifier.
	 *
	 * @return string
	 */
	public function get_provider_name();
}

