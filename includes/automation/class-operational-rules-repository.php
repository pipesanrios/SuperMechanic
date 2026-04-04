<?php
/**
 * Operational rules repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence access for operational rule configs.
 */
class Operational_Rules_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Operational_Rules_Installer
	 */
	protected $installer;

	/**
	 * Constructor.
	 *
	 * @param Operational_Rules_Installer|null $installer Installer.
	 */
	public function __construct( Operational_Rules_Installer $installer = null ) {
		$this->installer = $installer ? $installer : new Operational_Rules_Installer();
		$this->installer->ensure_table();
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->installer->get_table_name();
	}

	/**
	 * Get one rule config by business and rule key.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $rule_key Rule key.
	 * @return array<string,mixed>|null
	 */
	public function get_rule_config( $business_id, $rule_key ) {
		global $wpdb;

		$business_id = absint( $business_id );
		$rule_key    = sanitize_key( (string) $rule_key );

		if ( $business_id <= 0 || '' === $rule_key ) {
			return null;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE business_id = %d AND rule_key = %s LIMIT 1",
			$business_id,
			$rule_key
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->normalize_row( $business_id, $rule_key, $row );
	}

	/**
	 * Get persisted rule configs by business.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_rule_configs_by_business( $business_id ) {
		global $wpdb;

		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE business_id = %d ORDER BY rule_key ASC",
			$business_id
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$configs = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rule_key = isset( $row['rule_key'] ) ? sanitize_key( (string) $row['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}

			$configs[] = $this->normalize_row( $business_id, $rule_key, $row );
		}

		return $configs;
	}

	/**
	 * Save one rule config by business and key.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $rule_key Rule key.
	 * @param array<string,mixed> $config Config payload.
	 * @return bool
	 */
	public function save_rule_config( $business_id, $rule_key, array $config ) {
		global $wpdb;

		$business_id = absint( $business_id );
		$rule_key    = sanitize_key( (string) $rule_key );
		if ( $business_id <= 0 || '' === $rule_key ) {
			return false;
		}

		$enabled = ! empty( $config['enabled'] ) ? 1 : 0;
		if ( isset( $config['enabled'] ) && ! is_bool( $config['enabled'] ) && ! in_array( $config['enabled'], array( 0, 1, '0', '1' ), true ) ) {
			return false;
		}
		$execution_mode = isset( $config['execution_mode'] ) ? sanitize_key( (string) $config['execution_mode'] ) : 'manual';
		if ( ! in_array( $execution_mode, array( 'manual', 'confirmable', 'auto' ), true ) ) {
			return false;
		}

		if ( isset( $config['limits'] ) && is_array( $config['limits'] ) && isset( $config['limits']['max_items_auto'] ) ) {
			$max_items_auto = $config['limits']['max_items_auto'];
			if ( ! is_numeric( $max_items_auto ) || (string) $max_items_auto !== (string) absint( $max_items_auto ) ) {
				return false;
			}
		}

		$thresholds_json = wp_json_encode( isset( $config['thresholds'] ) && is_array( $config['thresholds'] ) ? $config['thresholds'] : array() );
		if ( false === $thresholds_json ) {
			$thresholds_json = '{}';
		}

		$limits_json = wp_json_encode( isset( $config['limits'] ) && is_array( $config['limits'] ) ? $config['limits'] : array() );
		if ( false === $limits_json ) {
			$limits_json = '{}';
		}

		$now = current_time( 'mysql' );
		$sql = $wpdb->prepare(
			"INSERT INTO {$this->get_table_name()} (business_id, rule_key, enabled, execution_mode, thresholds_json, limits_json, created_at, updated_at)
			VALUES (%d, %s, %d, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				enabled = VALUES(enabled),
				execution_mode = VALUES(execution_mode),
				thresholds_json = VALUES(thresholds_json),
				limits_json = VALUES(limits_json),
				updated_at = VALUES(updated_at)",
			$business_id,
			$rule_key,
			$enabled,
			$execution_mode,
			(string) $thresholds_json,
			(string) $limits_json,
			$now,
			$now
		);

		$result = $wpdb->query( $sql );
		return false !== $result;
	}

	/**
	 * Normalize DB row into repository config payload.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $rule_key Rule key.
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	protected function normalize_row( $business_id, $rule_key, array $row ) {
		$thresholds = array();
		if ( ! empty( $row['thresholds_json'] ) ) {
			$decoded = json_decode( (string) $row['thresholds_json'], true );
			if ( is_array( $decoded ) ) {
				$thresholds = $decoded;
			}
		}

		$limits = array();
		if ( ! empty( $row['limits_json'] ) ) {
			$decoded = json_decode( (string) $row['limits_json'], true );
			if ( is_array( $decoded ) ) {
				$limits = $decoded;
			}
		}

		return array(
			'business_id'    => $business_id,
			'rule_key'       => $rule_key,
			'enabled'        => ! empty( $row['enabled'] ),
			'execution_mode' => isset( $row['execution_mode'] ) ? sanitize_key( (string) $row['execution_mode'] ) : 'manual',
			'thresholds'     => $thresholds,
			'limits'         => $limits,
		);
	}
}
