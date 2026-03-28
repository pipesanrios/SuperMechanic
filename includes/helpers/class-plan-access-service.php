<?php
/**
 * Plan access service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves effective plan and centralized feature access checks.
 */
class Plan_Access_Service {
	/**
	 * Settings service dependency.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * License service dependency.
	 *
	 * @var License_Service
	 */
	protected $license_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 * @param License_Service|null  $license_service License service.
	 */
	public function __construct( Settings_Service $settings_service = null, License_Service $license_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->license_service  = $license_service ? $license_service : new License_Service( $this->settings_service );
	}

	/**
	 * Resolve effective plan state.
	 *
	 * @return array<string, string>
	 */
	public function get_effective_plan() {
		$defaults       = $this->get_default_plan_state();
		$stored_plan    = $this->settings_service->get_group( 'plan' );
		$stored_plan    = is_array( $stored_plan ) ? $stored_plan : array();
		$license_signal = $this->license_service->get_plan_signal();

		$plan_key = ! empty( $stored_plan['plan_key'] )
			? Feature_Flags::sanitize_plan_key( $stored_plan['plan_key'] )
			: Feature_Flags::sanitize_plan_key( $license_signal['suggested_plan_key'] );

		$status = ! empty( $stored_plan['status'] )
			? sanitize_key( (string) $stored_plan['status'] )
			: sanitize_key( (string) $license_signal['license_status'] );

		$source = ! empty( $stored_plan['source'] )
			? sanitize_text_field( (string) $stored_plan['source'] )
			: sanitize_text_field( (string) $license_signal['source'] );

		$message = ! empty( $stored_plan['message'] )
			? sanitize_text_field( (string) $stored_plan['message'] )
			: sanitize_text_field( (string) $license_signal['message'] );

		$plan = array(
			'plan_key' => $plan_key,
			'status'   => '' !== $status ? $status : $defaults['status'],
			'source'   => '' !== $source ? $source : $defaults['source'],
			'message'  => $message,
		);

		/**
		 * Filter effective plan state for future external providers.
		 *
		 * @param array<string, string> $plan Effective plan.
		 * @param array<string, mixed>  $license_signal License-derived signal.
		 * @param array<string, mixed>  $stored_plan Stored plan state.
		 */
		$plan = apply_filters( 'sm_plan_access_effective_plan', $plan, $license_signal, $stored_plan );
		$plan = is_array( $plan ) ? $plan : $defaults;

		$plan['plan_key'] = Feature_Flags::sanitize_plan_key( isset( $plan['plan_key'] ) ? $plan['plan_key'] : $defaults['plan_key'] );
		$plan['status']   = sanitize_key( isset( $plan['status'] ) ? (string) $plan['status'] : $defaults['status'] );
		$plan['source']   = sanitize_text_field( isset( $plan['source'] ) ? (string) $plan['source'] : $defaults['source'] );
		$plan['message']  = sanitize_text_field( isset( $plan['message'] ) ? (string) $plan['message'] : '' );

		return $plan;
	}

	/**
	 * Resolve feature state from plan defaults + local overrides.
	 *
	 * @return array<string, mixed>
	 */
	public function get_feature_flags_state() {
		$plan              = $this->get_effective_plan();
		$default_flags     = Feature_Flags::get_plan_feature_defaults( $plan['plan_key'] );
		$stored_features   = $this->settings_service->get_group( 'features' );
		$stored_features   = is_array( $stored_features ) ? $stored_features : array();
		$raw_feature_flags = isset( $stored_features['feature_flags'] ) && is_array( $stored_features['feature_flags'] )
			? $stored_features['feature_flags']
			: array();
		$overrides = Feature_Flags::normalize_feature_flags( $raw_feature_flags );

		/**
		 * Filter feature overrides for future providers.
		 *
		 * @param array<string, bool>   $overrides Local overrides.
		 * @param array<string, string> $plan Effective plan.
		 * @param array<string, mixed>  $stored_features Stored feature state.
		 */
		$overrides = apply_filters( 'sm_plan_access_feature_overrides', $overrides, $plan, $stored_features );
		$overrides = is_array( $overrides ) ? Feature_Flags::normalize_feature_flags( $overrides ) : array();

		return array(
			'plan_key' => $plan['plan_key'],
			'defaults' => $default_flags,
			'overrides' => $overrides,
			'resolved' => array_merge( $default_flags, $overrides ),
		);
	}

	/**
	 * Check whether a feature is enabled.
	 *
	 * @param string $feature_key Feature key.
	 * @return bool
	 */
	public function is_feature_enabled( $feature_key ) {
		$feature_key = sanitize_key( (string) $feature_key );

		if ( ! Feature_Flags::is_supported_feature( $feature_key ) ) {
			return false;
		}

		$state = $this->get_feature_flags_state();

		return ! empty( $state['resolved'][ $feature_key ] );
	}

	/**
	 * Get default plan state.
	 *
	 * @return array<string, string>
	 */
	protected function get_default_plan_state() {
		return array(
			'plan_key' => Feature_Flags::PLAN_CORE,
			'status'   => 'inactive',
			'source'   => 'local',
			'message'  => '',
		);
	}
}

