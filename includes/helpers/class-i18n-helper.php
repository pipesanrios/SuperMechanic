<?php
/**
 * I18N helper baseline.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized language helper for baseline i18n behavior.
 */
class I18N_Helper {
	/**
	 * Modern settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Optional settings service.
	 */
	public function __construct( Settings_Service $settings_service = null ) {
		$this->settings_service = $settings_service instanceof Settings_Service
			? $settings_service
			: new Settings_Service();
	}

	/**
	 * Get available bundled languages.
	 *
	 * @return array<string,string>
	 */
	public function get_available_languages() {
		return array(
			'en_US' => 'English',
			'es_ES' => 'Español',
			'it_IT' => 'Italiano',
		);
	}

	/**
	 * Resolve current language from persisted settings with safe fallback.
	 *
	 * @return string
	 */
	public function get_current_language() {
		$language = $this->settings_service->get_setting( 'business', 'locale', 'en_US' );
		$language = is_string( $language ) ? sanitize_text_field( $language ) : '';

		return $this->normalize_language( $language );
	}

	/**
	 * Persist current language using existing settings baseline.
	 *
	 * @param string $language Language code.
	 * @return bool
	 */
	public function set_current_language( $language ) {
		$language      = $this->normalize_language( (string) $language );
		$modern_saved  = true;
		if ( $this->get_current_language() !== $language ) {
			$modern_saved = $this->settings_service->set_setting( 'business', 'locale', $language );
		}
		$legacy_saved  = true;
		$legacy_option = get_option( 'super_mechanic_settings', array() );

		if ( is_array( $legacy_option ) ) {
			$current_legacy = isset( $legacy_option['language_locale'] ) ? sanitize_text_field( (string) $legacy_option['language_locale'] ) : '';
			if ( $current_legacy !== $language ) {
				$legacy_option['language_locale'] = $language;
				$legacy_saved                     = (bool) update_option( 'super_mechanic_settings', $legacy_option );
			}
		}

		return $modern_saved && $legacy_saved;
	}

	/**
	 * Translate one key with fallback behavior.
	 *
	 * @param string $key Translation key.
	 * @param string $fallback Optional fallback text.
	 * @return string
	 */
	public function translate( $key, $fallback = '' ) {
		$key      = is_string( $key ) ? trim( $key ) : '';
		$fallback = is_string( $fallback ) ? $fallback : '';
		if ( '' === $key ) {
			return $fallback;
		}

		$current_language = $this->get_current_language();
		$catalog          = $this->get_catalog();

		if ( isset( $catalog[ $current_language ][ $key ] ) && '' !== $catalog[ $current_language ][ $key ] ) {
			return (string) $catalog[ $current_language ][ $key ];
		}

		if ( isset( $catalog['en_US'][ $key ] ) && '' !== $catalog['en_US'][ $key ] ) {
			return (string) $catalog['en_US'][ $key ];
		}

		return '' !== $fallback ? $fallback : $key;
	}

	/**
	 * Normalize language code with safe fallback to English.
	 *
	 * @param string $language Language code.
	 * @return string
	 */
	protected function normalize_language( $language ) {
		$language  = sanitize_text_field( (string) $language );
		$available = $this->get_available_languages();

		if ( isset( $available[ $language ] ) ) {
			return $language;
		}

		return 'en_US';
	}

	/**
	 * Minimal translation catalog baseline.
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_catalog() {
		return array(
			'en_US' => array(),
			'es_ES' => array(),
			'it_IT' => array(),
		);
	}
}
