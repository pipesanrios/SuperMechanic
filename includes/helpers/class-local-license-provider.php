<?php
/**
 * Local license provider.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Provides local-only license lifecycle responses for Fase 31A.
 */
class Local_License_Provider implements License_Provider_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function activate( $license_key, array $current_state = array() ) {
		return array(
			'status'            => License_Service::STATUS_ACTIVE,
			'license_key'       => $license_key,
			'activated_at'      => gmdate( 'c' ),
			'last_validated_at' => gmdate( 'c' ),
			'provider'          => $this->get_provider_name(),
			'message'           => __( 'License activated in local mode.', 'super-mechanic' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate( array $current_state = array() ) {
		$has_key = ! empty( $current_state['license_key'] );

		return array(
			'status'            => $has_key ? License_Service::STATUS_ACTIVE : License_Service::STATUS_INACTIVE,
			'license_key'       => $has_key ? (string) $current_state['license_key'] : '',
			'activated_at'      => ! empty( $current_state['activated_at'] ) ? (string) $current_state['activated_at'] : '',
			'last_validated_at' => gmdate( 'c' ),
			'provider'          => $this->get_provider_name(),
			'message'           => $has_key
				? __( 'License validated in local mode.', 'super-mechanic' )
				: __( 'No license key configured.', 'super-mechanic' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function deactivate( array $current_state = array() ) {
		return array(
			'status'            => License_Service::STATUS_INACTIVE,
			'license_key'       => '',
			'activated_at'      => '',
			'last_validated_at' => gmdate( 'c' ),
			'provider'          => $this->get_provider_name(),
			'message'           => __( 'License deactivated in local mode.', 'super-mechanic' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_name() {
		return 'local';
	}
}

