<?php
/**
 * Central feature catalog and plan defaults.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Provides centralized feature and plan metadata.
 */
class Feature_Flags {
	const PLAN_CORE       = 'core';
	const PLAN_PRO        = 'pro';
	const PLAN_ENTERPRISE = 'enterprise';

	const FEATURE_ADMIN_REPORTS           = 'admin_reports';
	const FEATURE_ADMIN_SHORTCODE_CATALOG = 'admin_shortcode_catalog';
	const FEATURE_REPORTS_CSV_EXPORT      = 'reports_csv_export';

	/**
	 * Get supported plans.
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_plan_keys() {
		return array(
			self::PLAN_CORE,
			self::PLAN_PRO,
			self::PLAN_ENTERPRISE,
		);
	}

	/**
	 * Get supported feature map.
	 *
	 * @return array<string, string>
	 */
	public static function get_supported_features() {
		return array(
			self::FEATURE_ADMIN_REPORTS           => __( 'Admin reports screen', 'super-mechanic' ),
			self::FEATURE_ADMIN_SHORTCODE_CATALOG => __( 'Admin shortcode catalog', 'super-mechanic' ),
			self::FEATURE_REPORTS_CSV_EXPORT      => __( 'Reports CSV export', 'super-mechanic' ),
		);
	}

	/**
	 * Check if a feature key is supported.
	 *
	 * @param string $feature_key Feature key.
	 * @return bool
	 */
	public static function is_supported_feature( $feature_key ) {
		return array_key_exists( (string) $feature_key, self::get_supported_features() );
	}

	/**
	 * Sanitize plan key against supported values.
	 *
	 * @param string $plan_key Raw plan key.
	 * @return string
	 */
	public static function sanitize_plan_key( $plan_key ) {
		$plan_key = sanitize_key( (string) $plan_key );
		$allowed  = self::get_supported_plan_keys();

		return in_array( $plan_key, $allowed, true ) ? $plan_key : self::PLAN_CORE;
	}

	/**
	 * Get default features by plan.
	 *
	 * Defaults preserve backward compatibility: no existing non-critical admin
	 * surface is disabled unless explicitly overridden in settings.
	 *
	 * @param string $plan_key Plan key.
	 * @return array<string, bool>
	 */
	public static function get_plan_feature_defaults( $plan_key ) {
		$plan_key = self::sanitize_plan_key( $plan_key );

		$all_enabled = array(
			self::FEATURE_ADMIN_REPORTS           => true,
			self::FEATURE_ADMIN_SHORTCODE_CATALOG => true,
			self::FEATURE_REPORTS_CSV_EXPORT      => true,
		);

		if ( self::PLAN_ENTERPRISE === $plan_key ) {
			return $all_enabled;
		}

		if ( self::PLAN_PRO === $plan_key ) {
			return $all_enabled;
		}

		return $all_enabled;
	}

	/**
	 * Normalize user/provider feature flags to supported keys only.
	 *
	 * @param array<string, mixed> $flags Raw flags.
	 * @return array<string, bool>
	 */
	public static function normalize_feature_flags( array $flags ) {
		$normalized = array();

		foreach ( self::get_supported_features() as $feature_key => $label ) {
			if ( ! array_key_exists( $feature_key, $flags ) ) {
				continue;
			}

			$normalized[ $feature_key ] = ! empty( $flags[ $feature_key ] );
		}

		return $normalized;
	}
}

