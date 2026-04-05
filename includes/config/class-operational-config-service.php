<?php
/**
 * Operational config service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Provides per-business operational thresholds and feature flags.
 */
class Operational_Config_Service {
	/**
	 * Request-level cache.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected $cache = array();

	/**
	 * Get one configuration value by key for one business.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $key Config key.
	 * @param mixed  $default Optional fallback.
	 * @return mixed
	 */
	public function get( $business_id, $key, $default = null ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			return $default;
		}

		$config = $this->get_all( $business_id );
		if ( array_key_exists( $key, $config ) ) {
			return $config[ $key ];
		}

		return $default;
	}

	/**
	 * Get full configuration with defaults merged.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_all( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return $this->get_defaults();
		}

		if ( isset( $this->cache[ $business_id ] ) && is_array( $this->cache[ $business_id ] ) ) {
			return $this->cache[ $business_id ];
		}

		$defaults = $this->get_defaults();
		$stored   = get_option( $this->build_option_key( $business_id ), array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$merged   = array_merge( $defaults, $stored );
		$sanitized = $this->sanitize_config( $merged, $defaults );

		$this->cache[ $business_id ] = $sanitized;

		return $sanitized;
	}

	/**
	 * Get one threshold as integer.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $key Threshold key.
	 * @return int|null
	 */
	public function get_threshold( $business_id, $key ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			return null;
		}

		$value = $this->get( $business_id, $key, null );
		if ( null === $value ) {
			return null;
		}

		return absint( $value );
	}

	/**
	 * Check if one feature flag is enabled.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $flag Flag key.
	 * @return bool
	 */
	public function is_enabled( $business_id, $flag ) {
		$flag = sanitize_key( (string) $flag );
		return (bool) $this->get( $business_id, $flag, false );
	}

	/**
	 * Build option key for one business.
	 *
	 * @param int $business_id Business ID.
	 * @return string
	 */
	protected function build_option_key( $business_id ) {
		return 'sm_operational_config_' . absint( $business_id );
	}

	/**
	 * Return default config values.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_defaults() {
		return array(
			'overdue_tasks_threshold'   => 1,
			'delayed_process_threshold' => 1,
			'critical_signal_threshold' => 2,
			'user_saturation_threshold' => 3,
			'enable_recommendations'    => true,
			'enable_internal_flags'     => true,
			'enable_escalation'         => true,
		);
	}

	/**
	 * Sanitize config values using defaults as schema.
	 *
	 * @param array<string,mixed> $values Values to sanitize.
	 * @param array<string,mixed> $defaults Defaults map.
	 * @return array<string,mixed>
	 */
	protected function sanitize_config( array $values, array $defaults ) {
		$sanitized = array();

		foreach ( $defaults as $key => $default_value ) {
			$raw = array_key_exists( $key, $values ) ? $values[ $key ] : $default_value;
			if ( is_bool( $default_value ) ) {
				$sanitized[ $key ] = (bool) $raw;
				continue;
			}

			$sanitized[ $key ] = absint( $raw );
		}

		return $sanitized;
	}
}
