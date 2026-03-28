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

	/**
	 * Get normalized fallback business id from settings.
	 *
	 * @return int
	 */
	public function get_fallback_business_id() {
		return max( 1, absint( $this->get_setting( 'business', 'business_id', 1 ) ) );
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
		$settings['business']['currency']                     = sanitize_text_field( $settings['business']['currency'] );
		$settings['business']['timezone']                     = sanitize_text_field( $settings['business']['timezone'] );
		$settings['business']['locale']                       = sanitize_text_field( $settings['business']['locale'] );
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
				'timezone'             => $timezone,
				'locale'               => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
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
		);
	}
}
