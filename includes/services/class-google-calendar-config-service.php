<?php
/**
 * Google Calendar configuration service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Services;

use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes Google Calendar credential/config readiness.
 */
class Google_Calendar_Config_Service {
	/**
	 * Settings group used by the existing settings layer.
	 */
	const SETTINGS_GROUP = 'google_calendar';

	/**
	 * Required config keys for future OAuth/sync phases.
	 *
	 * @var array<int,string>
	 */
	const REQUIRED_KEYS = array(
		'client_id',
		'client_secret',
		'redirect_uri',
		'calendar_id',
	);

	/**
	 * Settings dependency.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 */
	public function __construct( Settings_Service $settings_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
	}

	/**
	 * Get normalized Google Calendar config.
	 *
	 * @return array<string,string>
	 */
	public function get_config() {
		$stored = $this->settings_service->get_group( self::SETTINGS_GROUP );

		return array(
			'client_id'     => isset( $stored['client_id'] ) ? sanitize_text_field( (string) $stored['client_id'] ) : '',
			'client_secret' => isset( $stored['client_secret'] ) ? sanitize_text_field( (string) $stored['client_secret'] ) : '',
			'redirect_uri'  => isset( $stored['redirect_uri'] ) ? esc_url_raw( (string) $stored['redirect_uri'] ) : '',
			'calendar_id'   => isset( $stored['calendar_id'] ) ? sanitize_text_field( (string) $stored['calendar_id'] ) : '',
		);
	}

	/**
	 * Save Google Calendar config through the existing settings option.
	 *
	 * @param array<string,mixed> $config Raw config input.
	 * @return array<string,mixed>
	 */
	public function save_config( array $config ) {
		$clean = $this->sanitize_config( $config );

		$saved = true;
		foreach ( self::REQUIRED_KEYS as $key ) {
			$saved = $this->settings_service->set_setting( self::SETTINGS_GROUP, $key, $clean[ $key ] ) && $saved;
		}

		return array(
			'saved'      => (bool) $saved,
			'config'     => $this->get_config(),
			'validation' => $this->validate_config( $clean ),
		);
	}

	/**
	 * Validate Google Calendar config completeness and basic string sanity.
	 *
	 * @param array<string,mixed>|null $config Optional config. Current config is used when omitted.
	 * @return array<string,mixed>
	 */
	public function validate_config( array $config = null ) {
		$config = null === $config ? $this->get_config() : $this->sanitize_config( $config );
		$errors = array();

		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( ! isset( $config[ $key ] ) || ! is_string( $config[ $key ] ) || '' === trim( $config[ $key ] ) ) {
				$errors[ $key ] = 'required';
			}
		}

		return array(
			'is_valid' => empty( $errors ),
			'errors'   => $errors,
			'config'   => $config,
		);
	}

	/**
	 * Determine whether config is ready for future OAuth/sync phases.
	 *
	 * @return bool
	 */
	public function is_ready() {
		$validation = $this->validate_config();

		return ! empty( $validation['is_valid'] );
	}

	/**
	 * Sanitize config payload.
	 *
	 * @param array<string,mixed> $config Raw config.
	 * @return array<string,string>
	 */
	protected function sanitize_config( array $config ) {
		return array(
			'client_id'     => isset( $config['client_id'] ) ? sanitize_text_field( (string) $config['client_id'] ) : '',
			'client_secret' => isset( $config['client_secret'] ) ? sanitize_text_field( (string) $config['client_secret'] ) : '',
			'redirect_uri'  => isset( $config['redirect_uri'] ) ? esc_url_raw( (string) $config['redirect_uri'] ) : '',
			'calendar_id'   => isset( $config['calendar_id'] ) ? sanitize_text_field( (string) $config['calendar_id'] ) : '',
		);
	}
}
