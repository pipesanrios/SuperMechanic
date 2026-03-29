<?php
/**
 * Settings service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized access to workshop/business settings.
 */
class Settings_Service {
	const OPTION_NAME = 'sm_settings';
	const DEFAULT_SUPPORTED_CURRENCIES = array( 'USD', 'EUR', 'COP', 'PAB' );

	/**
	 * Get normalized fallback business id from settings.
	 *
	 * @return int
	 */
	public function get_fallback_business_id() {
		return max( 1, absint( $this->get_setting( 'business', 'business_id', 1 ) ) );
	}

	/**
	 * Get supported currencies for operational UI and filters.
	 *
	 * @return array<string, string>
	 */
	public function get_supported_currencies() {
		$business_group = $this->get_group( 'business' );
		$configured     = isset( $business_group['supported_currencies'] ) ? $business_group['supported_currencies'] : array();
		$normalized     = $this->normalize_currency_codes( is_array( $configured ) ? $configured : array() );

		if ( empty( $normalized ) ) {
			$normalized = self::DEFAULT_SUPPORTED_CURRENCIES;
		}

		$base_currency = strtoupper( sanitize_text_field( (string) $this->get_setting( 'business', 'currency', 'USD' ) ) );
		if ( '' !== $base_currency && ! in_array( $base_currency, $normalized, true ) ) {
			$normalized[] = $base_currency;
		}

		/**
		 * Filter supported currency codes.
		 *
		 * @param array<int, string> $normalized Currency codes.
		 */
		$normalized = apply_filters( 'sm_supported_currencies', $normalized );
		$normalized = $this->normalize_currency_codes( is_array( $normalized ) ? $normalized : array() );

		if ( empty( $normalized ) ) {
			$normalized = self::DEFAULT_SUPPORTED_CURRENCIES;
		}

		$options = array();
		foreach ( $normalized as $currency ) {
			$options[ $currency ] = $currency;
		}

		return $options;
	}

	/**
	 * Persist fallback business id in settings.
	 *
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	public function set_fallback_business_id( $business_id ) {
		return $this->set_setting( 'business', 'business_id', max( 1, absint( $business_id ) ) );
	}

	/**
	 * Cached normalized settings.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	protected $settings = null;

	/**
	 * Get a setting value with normalized defaults.
	 *
	 * @param string $group   Settings group.
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional fallback default.
	 * @return mixed
	 */
	public function get_setting( $group, $key, $default = null ) {
		$group_settings = $this->get_group( $group );

		if ( array_key_exists( $key, $group_settings ) ) {
			return $group_settings[ $key ];
		}

		return $default;
	}

	/**
	 * Persist a setting value inside a group.
	 *
	 * @param string $group Settings group.
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public function set_setting( $group, $key, $value ) {
		$settings = $this->get_all_settings();

		if ( ! isset( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
			$settings[ $group ] = array();
		}

		$settings[ $group ][ $key ] = $value;
		$settings                   = $this->normalize_settings( $settings );
		$this->settings             = $settings;

		return (bool) update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Get a full normalized group.
	 *
	 * @param string $group Settings group.
	 * @return array<string, mixed>
	 */
	public function get_group( $group ) {
		$settings = $this->get_all_settings();

		return isset( $settings[ $group ] ) && is_array( $settings[ $group ] )
			? $settings[ $group ]
			: array();
	}

	/**
	 * Get all normalized settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$this->settings = $this->normalize_settings( $stored );

		return $this->settings;
	}

	/**
	 * Normalize and merge legacy option fallbacks.
	 *
	 * @param array<string, mixed> $stored Raw stored settings.
	 * @return array<string, array<string, mixed>>
	 */
	protected function normalize_settings( array $stored ) {
		$legacy = get_option( Settings::OPTION_NAME, array() );
		$legacy = is_array( $legacy ) ? $legacy : array();

		$defaults = $this->get_default_settings( $legacy );
		$settings = array();

		foreach ( $defaults as $group => $group_defaults ) {
			$group_values       = isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) ? $stored[ $group ] : array();
			$settings[ $group ] = array_merge( $group_defaults, $group_values );
		}

		$settings['business']['business_name']                = sanitize_text_field( $settings['business']['business_name'] );
		$settings['business']['business_context_key']         = sanitize_key( $settings['business']['business_context_key'] );
		$settings['business']['business_id']                  = max( 1, absint( $settings['business']['business_id'] ) );
		$settings['business']['currency']                     = strtoupper( sanitize_text_field( (string) $settings['business']['currency'] ) );
		$settings['business']['supported_currencies']         = $this->normalize_currency_codes(
			isset( $settings['business']['supported_currencies'] ) && is_array( $settings['business']['supported_currencies'] )
				? $settings['business']['supported_currencies']
				: self::DEFAULT_SUPPORTED_CURRENCIES
		);
		if ( empty( $settings['business']['supported_currencies'] ) ) {
			$settings['business']['supported_currencies'] = self::DEFAULT_SUPPORTED_CURRENCIES;
		}
		if ( ! in_array( $settings['business']['currency'], $settings['business']['supported_currencies'], true ) ) {
			$settings['business']['currency'] = $settings['business']['supported_currencies'][0];
		}
		$settings['business']['timezone']                     = sanitize_text_field( $settings['business']['timezone'] );
		$settings['business']['locale']                       = sanitize_text_field( $settings['business']['locale'] );
		if ( ! in_array( $settings['business']['locale'], array( 'en_US', 'es_ES', 'it_IT' ), true ) ) {
			$settings['business']['locale'] = 'en_US';
		}
		$settings['business']['date_format']                  = sanitize_text_field( $settings['business']['date_format'] );
		$settings['process']['enabled_process_types']         = array_values(
			array_intersect(
				array( 'maintenance', 'pre_delivery', 'paperwork' ),
				is_array( $settings['process']['enabled_process_types'] ) ? array_map( 'sanitize_key', $settings['process']['enabled_process_types'] ) : array()
			)
		);
		$settings['process']['allow_step_back']               = ! empty( $settings['process']['allow_step_back'] );
		$settings['process']['auto_complete_on_final_step']   = ! empty( $settings['process']['auto_complete_on_final_step'] );
		$settings['financial']['default_tax_rate']            = round( (float) str_replace( ',', '.', (string) $settings['financial']['default_tax_rate'] ), 2 );
		$settings['financial']['allow_partial_payments']      = ! empty( $settings['financial']['allow_partial_payments'] );
		$settings['notifications']['enable_client_notifications'] = ! empty( $settings['notifications']['enable_client_notifications'] );
		$settings['notifications']['enable_email_notifications']  = ! empty( $settings['notifications']['enable_email_notifications'] );
		$settings['automation']['enable_automation_runtime']      = ! empty( $settings['automation']['enable_automation_runtime'] );
		$settings['automation']['enable_appointment_reminders']   = ! empty( $settings['automation']['enable_appointment_reminders'] );
		$settings['automation']['appointment_reminder_minutes_before'] = max( 5, min( 1440, absint( $settings['automation']['appointment_reminder_minutes_before'] ) ) );
		$settings['automation']['appointment_reminder_window_minutes'] = max( 5, min( 120, absint( $settings['automation']['appointment_reminder_window_minutes'] ) ) );
		$settings['portal']['client_panel_enabled']           = ! empty( $settings['portal']['client_panel_enabled'] );
		$settings['license']['license_key']                   = isset( $settings['license']['license_key'] ) ? trim( sanitize_text_field( (string) $settings['license']['license_key'] ) ) : '';
		$settings['license']['status']                        = isset( $settings['license']['status'] ) ? sanitize_key( (string) $settings['license']['status'] ) : 'unknown';
		$settings['license']['activated_at']                  = isset( $settings['license']['activated_at'] ) ? sanitize_text_field( (string) $settings['license']['activated_at'] ) : '';
		$settings['license']['last_validated_at']             = isset( $settings['license']['last_validated_at'] ) ? sanitize_text_field( (string) $settings['license']['last_validated_at'] ) : '';
		$settings['license']['provider']                      = isset( $settings['license']['provider'] ) ? sanitize_text_field( (string) $settings['license']['provider'] ) : 'local';
		$settings['license']['message']                       = isset( $settings['license']['message'] ) ? sanitize_text_field( (string) $settings['license']['message'] ) : '';
		$settings['updates']['provider']                      = isset( $settings['updates']['provider'] ) ? sanitize_text_field( (string) $settings['updates']['provider'] ) : 'local';
		$settings['updates']['last_check_at']                 = isset( $settings['updates']['last_check_at'] ) ? sanitize_text_field( (string) $settings['updates']['last_check_at'] ) : '';
		$settings['updates']['latest_version']                = isset( $settings['updates']['latest_version'] ) ? sanitize_text_field( (string) $settings['updates']['latest_version'] ) : '';
		$settings['updates']['package_available']             = ! empty( $settings['updates']['package_available'] );
		$settings['updates']['message']                       = isset( $settings['updates']['message'] ) ? sanitize_text_field( (string) $settings['updates']['message'] ) : '';
		$settings['updates']['last_result']                   = isset( $settings['updates']['last_result'] ) ? sanitize_key( (string) $settings['updates']['last_result'] ) : 'no_update';
		$settings['updates']['requires']                      = isset( $settings['updates']['requires'] ) ? sanitize_text_field( (string) $settings['updates']['requires'] ) : '';
		$settings['updates']['tested']                        = isset( $settings['updates']['tested'] ) ? sanitize_text_field( (string) $settings['updates']['tested'] ) : '';
		$settings['updates']['changelog']                     = isset( $settings['updates']['changelog'] ) ? sanitize_textarea_field( (string) $settings['updates']['changelog'] ) : '';
		$settings['updates']['package_source_url']            = isset( $settings['updates']['package_source_url'] ) ? esc_url_raw( (string) $settings['updates']['package_source_url'] ) : '';
		$settings['plan']['plan_key']                         = isset( $settings['plan']['plan_key'] ) ? Feature_Flags::sanitize_plan_key( $settings['plan']['plan_key'] ) : Feature_Flags::PLAN_CORE;
		$settings['plan']['status']                           = isset( $settings['plan']['status'] ) ? sanitize_key( (string) $settings['plan']['status'] ) : 'inactive';
		$settings['plan']['source']                           = isset( $settings['plan']['source'] ) ? sanitize_text_field( (string) $settings['plan']['source'] ) : 'local';
		$settings['plan']['message']                          = isset( $settings['plan']['message'] ) ? sanitize_text_field( (string) $settings['plan']['message'] ) : '';
		$settings['features']['feature_flags']                = isset( $settings['features']['feature_flags'] ) && is_array( $settings['features']['feature_flags'] )
			? Feature_Flags::normalize_feature_flags( $settings['features']['feature_flags'] )
			: array();
		$settings['google_calendar']['sync_enabled']          = ! empty( $settings['google_calendar']['sync_enabled'] );
		$settings['google_calendar']['client_id']             = isset( $settings['google_calendar']['client_id'] ) ? sanitize_text_field( (string) $settings['google_calendar']['client_id'] ) : '';
		$settings['google_calendar']['client_secret']         = isset( $settings['google_calendar']['client_secret'] ) ? sanitize_text_field( (string) $settings['google_calendar']['client_secret'] ) : '';
		$settings['google_calendar']['redirect_uri']          = isset( $settings['google_calendar']['redirect_uri'] ) ? esc_url_raw( (string) $settings['google_calendar']['redirect_uri'] ) : '';
		$settings['google_calendar']['calendar_id']           = isset( $settings['google_calendar']['calendar_id'] ) ? sanitize_text_field( (string) $settings['google_calendar']['calendar_id'] ) : 'primary';
		$settings['google_calendar']['access_token']          = isset( $settings['google_calendar']['access_token'] ) ? sanitize_text_field( (string) $settings['google_calendar']['access_token'] ) : '';
		$settings['google_calendar']['refresh_token']         = isset( $settings['google_calendar']['refresh_token'] ) ? sanitize_text_field( (string) $settings['google_calendar']['refresh_token'] ) : '';
		$settings['google_calendar']['token_expires_at']      = isset( $settings['google_calendar']['token_expires_at'] ) ? sanitize_text_field( (string) $settings['google_calendar']['token_expires_at'] ) : '';
		$settings['google_calendar']['oauth_state']           = isset( $settings['google_calendar']['oauth_state'] ) ? sanitize_text_field( (string) $settings['google_calendar']['oauth_state'] ) : '';
		$settings['google_calendar']['oauth_state_expires_at'] = isset( $settings['google_calendar']['oauth_state_expires_at'] ) ? sanitize_text_field( (string) $settings['google_calendar']['oauth_state_expires_at'] ) : '';
		$settings['google_calendar']['oauth_state_user_id']   = isset( $settings['google_calendar']['oauth_state_user_id'] ) ? absint( $settings['google_calendar']['oauth_state_user_id'] ) : 0;
		$settings['google_calendar']['last_sync_result']      = isset( $settings['google_calendar']['last_sync_result'] ) ? sanitize_key( (string) $settings['google_calendar']['last_sync_result'] ) : '';
		$settings['google_calendar']['last_sync_message']     = isset( $settings['google_calendar']['last_sync_message'] ) ? sanitize_text_field( (string) $settings['google_calendar']['last_sync_message'] ) : '';
		$settings['google_calendar']['watch_channel_id']      = isset( $settings['google_calendar']['watch_channel_id'] ) ? sanitize_text_field( (string) $settings['google_calendar']['watch_channel_id'] ) : '';
		$settings['google_calendar']['watch_resource_id']     = isset( $settings['google_calendar']['watch_resource_id'] ) ? sanitize_text_field( (string) $settings['google_calendar']['watch_resource_id'] ) : '';
		$settings['google_calendar']['watch_resource_uri']    = isset( $settings['google_calendar']['watch_resource_uri'] ) ? esc_url_raw( (string) $settings['google_calendar']['watch_resource_uri'] ) : '';
		$settings['google_calendar']['watch_expiration']      = isset( $settings['google_calendar']['watch_expiration'] ) ? absint( $settings['google_calendar']['watch_expiration'] ) : 0;
		$settings['google_calendar']['watch_token_hash']      = isset( $settings['google_calendar']['watch_token_hash'] ) ? sanitize_text_field( (string) $settings['google_calendar']['watch_token_hash'] ) : '';
		$settings['google_calendar']['watch_last_message_number'] = isset( $settings['google_calendar']['watch_last_message_number'] ) ? absint( $settings['google_calendar']['watch_last_message_number'] ) : 0;
		$settings['google_calendar']['watch_last_webhook_at'] = isset( $settings['google_calendar']['watch_last_webhook_at'] ) ? sanitize_text_field( (string) $settings['google_calendar']['watch_last_webhook_at'] ) : '';
		$settings['google_calendar']['watch_next_sync_token'] = isset( $settings['google_calendar']['watch_next_sync_token'] ) ? sanitize_text_field( (string) $settings['google_calendar']['watch_next_sync_token'] ) : '';
		$settings['security']['master_password_hash']         = isset( $settings['security']['master_password_hash'] ) ? sanitize_text_field( (string) $settings['security']['master_password_hash'] ) : '';
		$settings['security']['master_password_generated_at'] = isset( $settings['security']['master_password_generated_at'] ) ? sanitize_text_field( (string) $settings['security']['master_password_generated_at'] ) : '';
		$settings['public_api']['enabled']                    = ! empty( $settings['public_api']['enabled'] );
		$settings['public_api']['webhooks_enabled']           = ! empty( $settings['public_api']['webhooks_enabled'] );
		$settings['public_api']['api_keys']                   = $this->normalize_public_api_keys(
			isset( $settings['public_api']['api_keys'] ) && is_array( $settings['public_api']['api_keys'] )
				? $settings['public_api']['api_keys']
				: array()
		);

		if ( empty( $settings['process']['enabled_process_types'] ) ) {
			$settings['process']['enabled_process_types'] = $defaults['process']['enabled_process_types'];
		}

		return $settings;
	}

	/**
	 * Default settings preserving current behavior.
	 *
	 * @param array<string, mixed> $legacy Legacy settings option.
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_default_settings( array $legacy ) {
		$timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';

		if ( '' === $timezone ) {
			$timezone = 'UTC';
		}

		return array(
			'business'      => array(
				'business_name'        => ! empty( $legacy['company_name'] ) ? sanitize_text_field( $legacy['company_name'] ) : 'Super Mechanic',
				'business_context_key' => 'default',
				'business_id'          => 1,
				'currency'             => ! empty( $legacy['default_currency'] ) ? sanitize_text_field( $legacy['default_currency'] ) : 'USD',
				'supported_currencies' => self::DEFAULT_SUPPORTED_CURRENCIES,
				'timezone'             => $timezone,
				'locale'               => 'en_US',
				'date_format'          => ! empty( $legacy['date_format'] ) ? sanitize_text_field( $legacy['date_format'] ) : 'Y-m-d',
			),
			'process'       => array(
				'enabled_process_types'      => ! empty( $legacy['enabled_process_types'] ) && is_array( $legacy['enabled_process_types'] ) ? array_values( array_map( 'sanitize_key', $legacy['enabled_process_types'] ) ) : array( 'maintenance', 'pre_delivery', 'paperwork' ),
				'allow_step_back'             => true,
				'auto_complete_on_final_step' => true,
			),
			'financial'     => array(
				'default_tax_rate'       => 0,
				'allow_partial_payments' => true,
			),
			'notifications' => array(
				'enable_client_notifications' => true,
				'enable_email_notifications'  => false,
			),
			'automation'   => array(
				'enable_automation_runtime'         => true,
				'enable_appointment_reminders'      => true,
				'appointment_reminder_minutes_before' => 120,
				'appointment_reminder_window_minutes' => 15,
			),
			'portal'        => array(
				'client_panel_enabled' => isset( $legacy['client_panel_enabled'] ) ? ! empty( $legacy['client_panel_enabled'] ) : true,
			),
			'license'       => array(
				'license_key'       => '',
				'status'            => 'inactive',
				'activated_at'      => '',
				'last_validated_at' => '',
				'provider'          => 'local',
				'message'           => '',
			),
			'updates'       => array(
				'provider'           => 'local',
				'last_check_at'      => '',
				'latest_version'     => defined( 'SM_PLUGIN_VERSION' ) ? sanitize_text_field( (string) SM_PLUGIN_VERSION ) : '0.1.0',
				'package_available'  => false,
				'message'            => '',
				'last_result'        => 'no_update',
				'requires'           => '',
				'tested'             => '',
				'changelog'          => '',
				'package_source_url' => '',
			),
			'plan'          => array(
				'plan_key' => Feature_Flags::PLAN_CORE,
				'status'   => 'inactive',
				'source'   => 'local',
				'message'  => '',
			),
			'features'      => array(
				'feature_flags' => array(),
			),
			'google_calendar' => array(
				'sync_enabled'           => false,
				'client_id'              => '',
				'client_secret'          => '',
				'redirect_uri'           => '',
				'calendar_id'            => 'primary',
				'access_token'           => '',
				'refresh_token'          => '',
				'token_expires_at'       => '',
				'oauth_state'            => '',
				'oauth_state_expires_at' => '',
				'oauth_state_user_id'    => 0,
				'last_sync_result'       => '',
				'last_sync_message'      => '',
				'watch_channel_id'       => '',
				'watch_resource_id'      => '',
				'watch_resource_uri'     => '',
				'watch_expiration'       => 0,
				'watch_token_hash'       => '',
				'watch_last_message_number' => 0,
				'watch_last_webhook_at'  => '',
				'watch_next_sync_token'  => '',
			),
			'security' => array(
				'master_password_hash'         => '',
				'master_password_generated_at' => '',
			),
			'public_api' => array(
				'enabled'          => false,
				'webhooks_enabled' => true,
				'api_keys'         => array(),
			),
		);
	}

	/**
	 * Normalize currency codes to uppercase unique values.
	 *
	 * @param array<int, mixed> $codes Raw codes.
	 * @return array<int, string>
	 */
	protected function normalize_currency_codes( array $codes ) {
		$normalized = array();

		foreach ( $codes as $raw_code ) {
			$code = strtoupper( sanitize_text_field( (string) $raw_code ) );
			$code = preg_replace( '/[^A-Z]/', '', $code );

			if ( ! is_string( $code ) || strlen( $code ) < 3 || strlen( $code ) > 5 ) {
				continue;
			}

			if ( ! in_array( $code, $normalized, true ) ) {
				$normalized[] = $code;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize public API key records.
	 *
	 * @param array<int,mixed> $keys Raw keys.
	 * @return array<int,array<string,mixed>>
	 */
	protected function normalize_public_api_keys( array $keys ) {
		$normalized = array();

		foreach ( $keys as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key_hash = isset( $row['key_hash'] ) ? sanitize_text_field( (string) $row['key_hash'] ) : '';
			$raw_key  = isset( $row['key'] ) ? trim( sanitize_text_field( (string) $row['key'] ) ) : '';

			if ( '' === $key_hash && '' !== $raw_key ) {
				$key_hash = $this->hash_public_api_key( $raw_key );
			}

			if ( '' === $key_hash ) {
				continue;
			}

			$key_id = isset( $row['key_id'] ) ? sanitize_key( (string) $row['key_id'] ) : '';
			if ( '' === $key_id ) {
				$key_id = 'key_' . substr( $key_hash, 0, 12 );
			}

			$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'inactive';
			if ( ! in_array( $status, array( 'active', 'inactive', 'revoked' ), true ) ) {
				$status = 'inactive';
			}

			$scopes = isset( $row['scopes'] ) && is_array( $row['scopes'] )
				? array_values( array_unique( array_filter( array_map( array( $this, 'normalize_public_api_scope' ), $row['scopes'] ) ) ) )
				: array();

			$normalized[] = array(
				'key_id'       => $key_id,
				'label'        => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'key_hash'     => $key_hash,
				'business_id'  => max( 1, absint( isset( $row['business_id'] ) ? $row['business_id'] : 1 ) ),
				'scopes'       => $scopes,
				'status'       => $status,
				'last_used_at' => isset( $row['last_used_at'] ) ? sanitize_text_field( (string) $row['last_used_at'] ) : '',
				'created_at'   => isset( $row['created_at'] ) ? sanitize_text_field( (string) $row['created_at'] ) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Hash a raw public API key.
	 *
	 * @param string $raw_key Raw key.
	 * @return string
	 */
	protected function hash_public_api_key( $raw_key ) {
		return hash_hmac( 'sha256', (string) $raw_key, wp_salt( 'sm_public_api_keys' ) );
	}

	/**
	 * Normalize one public API scope while preserving `:` semantics.
	 *
	 * @param mixed $scope Raw scope.
	 * @return string
	 */
	protected function normalize_public_api_scope( $scope ) {
		$scope = strtolower( trim( sanitize_text_field( (string) $scope ) ) );

		if ( '*' === $scope ) {
			return '*';
		}

		if ( '' === $scope || ! preg_match( '/^[a-z0-9_:-]+$/', $scope ) ) {
			return '';
		}

		return $scope;
	}
}
