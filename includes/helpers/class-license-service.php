<?php
/**
 * License service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Handles local license lifecycle and persistence.
 */
class License_Service {
	const STATUS_INACTIVE = 'inactive';
	const STATUS_ACTIVE   = 'active';
	const STATUS_INVALID  = 'invalid';
	const STATUS_UNKNOWN  = 'unknown';

	/**
	 * Settings service dependency.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * License provider dependency.
	 *
	 * @var License_Provider_Interface
	 */
	protected $provider;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null          $settings_service Settings service.
	 * @param License_Provider_Interface|null $provider        Provider adapter.
	 */
	public function __construct( Settings_Service $settings_service = null, License_Provider_Interface $provider = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->provider         = $provider ? $provider : new Local_License_Provider();
	}

	/**
	 * Get normalized license state.
	 *
	 * @return array<string, string>
	 */
	public function get_license_state() {
		$defaults = $this->get_default_state();
		$stored   = $this->settings_service->get_group( 'license' );
		$state    = array_merge( $defaults, is_array( $stored ) ? $stored : array() );

		$state['license_key']       = isset( $state['license_key'] ) ? trim( sanitize_text_field( (string) $state['license_key'] ) ) : '';
		$state['status']            = $this->sanitize_status( isset( $state['status'] ) ? (string) $state['status'] : self::STATUS_UNKNOWN );
		$state['activated_at']      = isset( $state['activated_at'] ) ? sanitize_text_field( (string) $state['activated_at'] ) : '';
		$state['last_validated_at'] = isset( $state['last_validated_at'] ) ? sanitize_text_field( (string) $state['last_validated_at'] ) : '';
		$state['provider']          = isset( $state['provider'] ) ? sanitize_text_field( (string) $state['provider'] ) : $this->provider->get_provider_name();
		$state['message']           = isset( $state['message'] ) ? sanitize_text_field( (string) $state['message'] ) : '';

		return $state;
	}

	/**
	 * Return a masked representation of the license key.
	 *
	 * @param string $license_key Full key.
	 * @return string
	 */
	public function mask_license_key( $license_key ) {
		$license_key = trim( (string) $license_key );
		$length      = strlen( $license_key );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		return str_repeat( '*', $length - 4 ) . substr( $license_key, -4 );
	}

	/**
	 * Activate a license key in local mode.
	 *
	 * @param string $license_key Raw input key.
	 * @return array<string, mixed>
	 */
	public function activate_license( $license_key ) {
		$license_key = trim( sanitize_text_field( (string) $license_key ) );

		if ( '' === $license_key ) {
			$state = $this->get_license_state();
			$state = array_merge(
				$state,
				array(
					'status'   => self::STATUS_INVALID,
					'message'  => __( 'License key is required.', 'super-mechanic' ),
					'provider' => $this->provider->get_provider_name(),
				)
			);
			$this->persist_state( $state );

			return array(
				'success' => false,
				'message' => $state['message'],
				'state'   => $state,
			);
		}

		if ( ! $this->is_license_key_format_valid( $license_key ) ) {
			$state = $this->get_license_state();
			$state = array_merge(
				$state,
				array(
					'status'   => self::STATUS_INVALID,
					'message'  => __( 'License key format is invalid.', 'super-mechanic' ),
					'provider' => $this->provider->get_provider_name(),
				)
			);
			$this->persist_state( $state );

			return array(
				'success' => false,
				'message' => $state['message'],
				'state'   => $state,
			);
		}

		$current = $this->get_license_state();
		$next    = $this->provider->activate( $license_key, $current );
		$state   = array_merge( $this->get_default_state(), $current, is_array( $next ) ? $next : array() );
		$state['license_key'] = $license_key;
		$state['status']      = $this->sanitize_status( $state['status'] );
		$state['provider']    = sanitize_text_field( (string) $state['provider'] );
		$state['message']     = sanitize_text_field( (string) $state['message'] );

		$this->persist_state( $state );

		return array(
			'success' => self::STATUS_ACTIVE === $state['status'],
			'message' => $state['message'],
			'state'   => $state,
		);
	}

	/**
	 * Validate current local license state.
	 *
	 * @return array<string, mixed>
	 */
	public function validate_license() {
		$current = $this->get_license_state();
		$next    = $this->provider->validate( $current );
		$state   = array_merge( $this->get_default_state(), $current, is_array( $next ) ? $next : array() );
		$state['status']   = $this->sanitize_status( $state['status'] );
		$state['provider'] = sanitize_text_field( (string) $state['provider'] );
		$state['message']  = sanitize_text_field( (string) $state['message'] );

		$this->persist_state( $state );

		return array(
			'success' => self::STATUS_ACTIVE === $state['status'],
			'message' => $state['message'],
			'state'   => $state,
		);
	}

	/**
	 * Deactivate current local license state.
	 *
	 * @return array<string, mixed>
	 */
	public function deactivate_license() {
		$current = $this->get_license_state();
		$next    = $this->provider->deactivate( $current );
		$state   = array_merge( $this->get_default_state(), $current, is_array( $next ) ? $next : array() );
		$state['status']      = $this->sanitize_status( $state['status'] );
		$state['license_key'] = '';
		$state['provider']    = sanitize_text_field( (string) $state['provider'] );
		$state['message']     = sanitize_text_field( (string) $state['message'] );

		$this->persist_state( $state );

		return array(
			'success' => self::STATUS_INACTIVE === $state['status'],
			'message' => $state['message'],
			'state'   => $state,
		);
	}

	/**
	 * Provide a normalized signal that can be reused by plan access services.
	 *
	 * @return array<string, mixed>
	 */
	public function get_plan_signal() {
		$state     = $this->get_license_state();
		$is_active = self::STATUS_ACTIVE === $state['status'];

		return array(
			'license_status'      => sanitize_key( (string) $state['status'] ),
			'is_license_active'   => $is_active,
			'suggested_plan_key'  => $is_active ? Feature_Flags::PLAN_PRO : Feature_Flags::PLAN_CORE,
			'source'              => 'license_local',
			'message'             => isset( $state['message'] ) ? sanitize_text_field( (string) $state['message'] ) : '',
			'last_validated_at'   => isset( $state['last_validated_at'] ) ? sanitize_text_field( (string) $state['last_validated_at'] ) : '',
		);
	}

	/**
	 * Persist full state via Settings_Service.
	 *
	 * @param array<string, mixed> $state State to persist.
	 * @return void
	 */
	protected function persist_state( array $state ) {
		$this->settings_service->set_setting( 'license', 'license_key', trim( sanitize_text_field( (string) $state['license_key'] ) ) );
		$this->settings_service->set_setting( 'license', 'status', $this->sanitize_status( (string) $state['status'] ) );
		$this->settings_service->set_setting( 'license', 'activated_at', sanitize_text_field( (string) $state['activated_at'] ) );
		$this->settings_service->set_setting( 'license', 'last_validated_at', sanitize_text_field( (string) $state['last_validated_at'] ) );
		$this->settings_service->set_setting( 'license', 'provider', sanitize_text_field( (string) $state['provider'] ) );
		$this->settings_service->set_setting( 'license', 'message', sanitize_text_field( (string) $state['message'] ) );
	}

	/**
	 * Validate license key format for local baseline.
	 *
	 * @param string $license_key License key input.
	 * @return bool
	 */
	protected function is_license_key_format_valid( $license_key ) {
		return 1 === preg_match( '/^[A-Z0-9._-]{10,128}$/i', (string) $license_key );
	}

	/**
	 * Sanitize status to known values.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	protected function sanitize_status( $status ) {
		$allowed = array(
			self::STATUS_INACTIVE,
			self::STATUS_ACTIVE,
			self::STATUS_INVALID,
			self::STATUS_UNKNOWN,
		);

		return in_array( $status, $allowed, true ) ? $status : self::STATUS_UNKNOWN;
	}

	/**
	 * Default local state.
	 *
	 * @return array<string, string>
	 */
	protected function get_default_state() {
		return array(
			'license_key'       => '',
			'status'            => self::STATUS_INACTIVE,
			'activated_at'      => '',
			'last_validated_at' => '',
			'provider'          => $this->provider->get_provider_name(),
			'message'           => '',
		);
	}
}
