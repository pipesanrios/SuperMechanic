<?php
/**
 * Local update provider.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a local/stub private updates baseline for Fase 31B.
 */
class Local_Update_Provider implements Update_Provider_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function get_update_metadata( array $context = array() ) {
		$current_version = isset( $context['current_version'] ) ? (string) $context['current_version'] : '';
		$default_payload = array(
			'latest_version'     => $current_version,
			'package_source_url' => '',
			'changelog'          => __( 'No private update package configured yet.', 'super-mechanic' ),
			'requires'           => '',
			'tested'             => '',
			'message'            => __( 'Local update provider is running in stub mode.', 'super-mechanic' ),
			'last_result'        => 'no_update',
		);

		$payload = apply_filters( 'sm_updates_local_provider_payload', $default_payload, $context );

		if ( ! is_array( $payload ) ) {
			$payload = $default_payload;
		}

		$latest_version = isset( $payload['latest_version'] ) ? sanitize_text_field( (string) $payload['latest_version'] ) : $current_version;
		$source_url     = isset( $payload['package_source_url'] ) ? esc_url_raw( (string) $payload['package_source_url'] ) : '';

		return array(
			'latest_version'     => '' !== $latest_version ? $latest_version : $current_version,
			'package_source_url' => $source_url,
			'changelog'          => isset( $payload['changelog'] ) ? sanitize_textarea_field( (string) $payload['changelog'] ) : '',
			'requires'           => isset( $payload['requires'] ) ? sanitize_text_field( (string) $payload['requires'] ) : '',
			'tested'             => isset( $payload['tested'] ) ? sanitize_text_field( (string) $payload['tested'] ) : '',
			'message'            => isset( $payload['message'] ) ? sanitize_text_field( (string) $payload['message'] ) : '',
			'last_result'        => isset( $payload['last_result'] ) ? sanitize_key( (string) $payload['last_result'] ) : 'no_update',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_name() {
		return 'local';
	}
}

