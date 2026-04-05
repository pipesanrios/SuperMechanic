<?php
/**
 * Operational rules service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

use Super_Mechanic\Config\Operational_Config_Service;
use Super_Mechanic\Dashboard\Workload_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates configurable operational rules without executing actions.
 */
class Operational_Rules_Service {
	/**
	 * Workload service dependency.
	 *
	 * @var Workload_Service
	 */
	protected $workload_service;
	/**
	 * Rules repository dependency.
	 *
	 * @var Operational_Rules_Repository
	 */
	protected $repository;
	/**
	 * Execution log service dependency.
	 *
	 * @var Execution_Log_Service
	 */
	protected $execution_log_service;
	/**
	 * Operational config service dependency (read-only).
	 *
	 * @var Operational_Config_Service
	 */
	protected $operational_config_service;

	/**
	 * Request-level memoization cache.
	 *
	 * @var array<string,mixed>
	 */
	protected $request_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Workload_Service|null $workload_service Workload service.
	 */
	public function __construct( Workload_Service $workload_service = null, Operational_Rules_Repository $repository = null, Execution_Log_Service $execution_log_service = null, Operational_Config_Service $operational_config_service = null ) {
		$this->workload_service     = $workload_service ? $workload_service : new Workload_Service();
		$this->repository           = $repository ? $repository : new Operational_Rules_Repository();
		$this->execution_log_service = $execution_log_service ? $execution_log_service : new Execution_Log_Service();
		$this->operational_config_service = $operational_config_service ? $operational_config_service : new Operational_Config_Service();
	}

	/**
	 * Build request cache key.
	 *
	 * @param string           $method Method name.
	 * @param array<int,mixed> $args Arguments.
	 * @return string
	 */
	protected function build_request_cache_key( $method, array $args = array() ) {
		$encoded = wp_json_encode( $args );
		if ( false === $encoded ) {
			$encoded = serialize( $args );
		}

		return sanitize_key( (string) $method ) . ':' . md5( (string) $encoded );
	}

	/**
	 * Read one memoized value.
	 *
	 * @param string $key Cache key.
	 * @param bool   $hit Whether cache hit happened.
	 * @return mixed
	 */
	protected function get_request_cache( $key, &$hit = false ) {
		$key = (string) $key;
		if ( array_key_exists( $key, $this->request_cache ) ) {
			$hit = true;
			return $this->request_cache[ $key ];
		}

		$hit = false;
		return null;
	}

	/**
	 * Save one memoized value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	protected function set_request_cache( $key, $value ) {
		$this->request_cache[ (string) $key ] = $value;
		return $value;
	}

	/**
	 * Clear request memoization cache.
	 *
	 * @return void
	 */
	protected function clear_request_cache() {
		$this->request_cache = array();
	}

	/**
	 * Get one rule config for business with fallback to defaults.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $rule_key Rule key.
	 * @return array<string,mixed>
	 */
	public function get_rule_config( $business_id, $rule_key ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), sanitize_key( (string) $rule_key ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$business_id = absint( $business_id );
		$rule_key    = sanitize_key( (string) $rule_key );
		$default     = $this->get_default_rule_config( $rule_key );

		if ( empty( $default ) ) {
			return $this->set_request_cache( $cache_key, array() );
		}

		$db_config = $this->repository->get_rule_config( $business_id, $rule_key );
		if ( empty( $db_config ) || ! is_array( $db_config ) ) {
			$default['source'] = 'default';
			return $this->set_request_cache( $cache_key, $default );
		}

		$config = array(
			'rule_key'       => $rule_key,
			'enabled'        => array_key_exists( 'enabled', $db_config ) ? (bool) $db_config['enabled'] : (bool) $default['enabled'],
			'execution_mode' => isset( $db_config['execution_mode'] ) ? sanitize_key( (string) $db_config['execution_mode'] ) : (string) $default['execution_mode'],
			'thresholds'     => $default['thresholds'],
			'limits'         => $default['limits'],
			'source'         => 'db',
		);

		if ( isset( $db_config['thresholds'] ) && is_array( $db_config['thresholds'] ) ) {
			$config['thresholds'] = array_merge( $config['thresholds'], $db_config['thresholds'] );
		}
		if ( isset( $db_config['limits'] ) && is_array( $db_config['limits'] ) ) {
			$config['limits'] = array_merge( $config['limits'], $db_config['limits'] );
		}

		if ( ! in_array( $config['execution_mode'], array( 'manual', 'confirmable', 'auto' ), true ) ) {
			$config['execution_mode'] = (string) $default['execution_mode'];
		}

		return $this->set_request_cache( $cache_key, $config );
	}

	/**
	 * Return operational rules definition.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_operational_rules( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$business_id = absint( $business_id );
		$overdue_cfg = $this->get_rule_config( $business_id, 'overdue_tasks_cleanup' );
		$rebalance_cfg = $this->get_rule_config( $business_id, 'critical_saturation_rebalance' );
		$critical_cfg  = $this->get_rule_config( $business_id, 'multi_critical_alert' );

		$rules = array(
			array(
				'rule_key'     => 'overdue_tasks_cleanup',
				'name'         => __( 'Overdue tasks cleanup', 'super-mechanic' ),
				'description'  => __( 'Detects overdue CRM task pressure and previews a safe bulk resolve action.', 'super-mechanic' ),
				'enabled'      => ! empty( $overdue_cfg['enabled'] ),
				'execution_mode' => isset( $overdue_cfg['execution_mode'] ) ? sanitize_key( (string) $overdue_cfg['execution_mode'] ) : 'auto',
				'conditions'   => array(
					array(
						'metric'    => 'overdue_tasks',
						'operator'  => '>',
						'threshold' => isset( $overdue_cfg['thresholds']['overdue_tasks'] ) ? absint( $overdue_cfg['thresholds']['overdue_tasks'] ) : 3,
					),
				),
				'action_type'  => 'bulk_resolve',
				'auto_executable' => isset( $overdue_cfg['execution_mode'] ) && 'auto' === $overdue_cfg['execution_mode'],
				'action_config' => array(
					'entity_type' => 'crm_task',
					'source_group' => 'overdue_tasks',
					'limit'       => isset( $overdue_cfg['limits']['max_items_auto'] ) ? absint( $overdue_cfg['limits']['max_items_auto'] ) : 25,
				),
				'business_id'  => $business_id,
				'source'       => isset( $overdue_cfg['source'] ) ? sanitize_key( (string) $overdue_cfg['source'] ) : 'default',
			),
			array(
				'rule_key'     => 'critical_saturation_rebalance',
				'name'         => __( 'Critical saturation rebalance', 'super-mechanic' ),
				'description'  => __( 'Detects overloaded users and previews controlled workload rebalancing.', 'super-mechanic' ),
				'enabled'      => ! empty( $rebalance_cfg['enabled'] ),
				'execution_mode' => isset( $rebalance_cfg['execution_mode'] ) ? sanitize_key( (string) $rebalance_cfg['execution_mode'] ) : 'confirmable',
				'conditions'   => array(
					array(
						'metric'    => 'overloaded_users',
						'operator'  => '>',
						'threshold' => isset( $rebalance_cfg['thresholds']['overloaded_users'] ) ? absint( $rebalance_cfg['thresholds']['overloaded_users'] ) : 0,
					),
				),
				'action_type'  => 'bulk_reassign',
				'auto_executable' => false,
				'action_config' => array(
					'entity_type' => 'crm_task',
					'source'      => 'assignment_proposals',
				),
				'business_id'  => $business_id,
				'source'       => isset( $rebalance_cfg['source'] ) ? sanitize_key( (string) $rebalance_cfg['source'] ) : 'default',
			),
			array(
				'rule_key'     => 'multi_critical_alert',
				'name'         => __( 'Multiple critical alert pattern', 'super-mechanic' ),
				'description'  => __( 'Detects accumulation of critical operational flags to elevate visibility.', 'super-mechanic' ),
				'enabled'      => ! empty( $critical_cfg['enabled'] ),
				'execution_mode' => isset( $critical_cfg['execution_mode'] ) ? sanitize_key( (string) $critical_cfg['execution_mode'] ) : 'manual',
				'conditions'   => array(
					array(
						'metric'    => 'critical_flags',
						'operator'  => '>=',
						'threshold' => isset( $critical_cfg['thresholds']['critical_flags'] ) ? absint( $critical_cfg['thresholds']['critical_flags'] ) : 2,
					),
				),
				'action_type'  => 'flag',
				'auto_executable' => false,
				'action_config' => array(
					'level'  => 'critical',
					'source' => 'automation_console',
				),
				'business_id'  => $business_id,
				'source'       => isset( $critical_cfg['source'] ) ? sanitize_key( (string) $critical_cfg['source'] ) : 'default',
			),
		);

		return $this->set_request_cache( $cache_key, $rules );
	}

	/**
	 * Return admin listing payload for persisted/default rule configs.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_rules_admin_listing( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$business_id = absint( $business_id );
		$rule_keys   = $this->get_supported_rule_keys();
		$persisted   = $this->repository->get_rule_configs_by_business( $business_id );

		$persisted_map = array();
		foreach ( $persisted as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			$rule_key = isset( $config['rule_key'] ) ? sanitize_key( (string) $config['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$persisted_map[ $rule_key ] = $config;
		}

		$rows = array();
		foreach ( $rule_keys as $rule_key ) {
			$rule_key = sanitize_key( (string) $rule_key );
			if ( '' === $rule_key ) {
				continue;
			}

			$config = $this->get_rule_config( $business_id, $rule_key );
			if ( empty( $config ) || ! is_array( $config ) ) {
				continue;
			}

			$rows[] = array(
				'rule_key'       => $rule_key,
				'enabled'        => ! empty( $config['enabled'] ),
				'execution_mode' => isset( $config['execution_mode'] ) ? sanitize_key( (string) $config['execution_mode'] ) : 'manual',
				'thresholds'     => isset( $config['thresholds'] ) && is_array( $config['thresholds'] ) ? $config['thresholds'] : array(),
				'limits'         => isset( $config['limits'] ) && is_array( $config['limits'] ) ? $config['limits'] : array(),
				'source'         => isset( $persisted_map[ $rule_key ] ) ? 'db' : 'default',
			);
		}

		$persisted_count = 0;
		foreach ( $rows as $row ) {
			if ( isset( $row['source'] ) && 'db' === $row['source'] ) {
				++$persisted_count;
			}
		}

		$payload = array(
			'business_id' => $business_id,
			'rules'       => $rows,
			'summary'     => array(
				'total'           => count( $rows ),
				'persisted'       => $persisted_count,
				'defaults'        => max( 0, count( $rows ) - $persisted_count ),
				'has_persisted'   => $persisted_count > 0,
				'mutations'       => 'none',
			),
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Save basic editable rule configuration.
	 *
	 * @param int         $business_id Business ID.
	 * @param string      $rule_key Rule key.
	 * @param mixed $enabled Enabled flag raw value.
	 * @param mixed $execution_mode Execution mode raw value.
	 * @param mixed $max_items_auto Max items for auto execution raw value.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function save_basic_rule_config( $business_id, $rule_key, $enabled = null, $execution_mode = null, $max_items_auto = null ) {
		$business_id = absint( $business_id );
		$rule_key    = sanitize_key( (string) $rule_key );

		if ( $business_id <= 0 ) {
			return new \WP_Error( 'invalid_business_id', __( 'Invalid business context for rule save.', 'super-mechanic' ) );
		}
		if ( ! in_array( $rule_key, $this->get_supported_rule_keys(), true ) ) {
			return new \WP_Error( 'invalid_rule_key', __( 'Unsupported operational rule key.', 'super-mechanic' ) );
		}

		$current_config = $this->get_rule_config( $business_id, $rule_key );
		if ( empty( $current_config ) || ! is_array( $current_config ) ) {
			return new \WP_Error( 'missing_rule_config', __( 'Rule configuration could not be loaded.', 'super-mechanic' ) );
		}

		$enabled_parse = $this->parse_enabled_input( $enabled, ! empty( $current_config['enabled'] ) );
		if ( is_wp_error( $enabled_parse ) ) {
			return $enabled_parse;
		}

		$execution_mode_parse = $this->parse_execution_mode_input( $execution_mode, isset( $current_config['execution_mode'] ) ? (string) $current_config['execution_mode'] : 'manual' );
		if ( is_wp_error( $execution_mode_parse ) ) {
			return $execution_mode_parse;
		}

		$max_items_parse = $this->parse_max_items_auto_input( $max_items_auto, isset( $current_config['limits']['max_items_auto'] ) ? $current_config['limits']['max_items_auto'] : null );
		if ( is_wp_error( $max_items_parse ) ) {
			return $max_items_parse;
		}

		$thresholds       = isset( $current_config['thresholds'] ) && is_array( $current_config['thresholds'] ) ? $current_config['thresholds'] : array();
		$limits           = isset( $current_config['limits'] ) && is_array( $current_config['limits'] ) ? $current_config['limits'] : array();
		$resolved_enabled = (bool) $enabled_parse;
		$resolved_mode    = (string) $execution_mode_parse;
		$resolved_max     = $max_items_parse;
		$previous_max     = isset( $limits['max_items_auto'] ) ? absint( $limits['max_items_auto'] ) : null;

		$old_basic = array(
			'enabled'        => ! empty( $current_config['enabled'] ),
			'execution_mode' => isset( $current_config['execution_mode'] ) ? sanitize_key( (string) $current_config['execution_mode'] ) : 'manual',
			'max_items_auto' => $previous_max,
		);
		$new_basic = array(
			'enabled'        => $resolved_enabled,
			'execution_mode' => sanitize_key( $resolved_mode ),
			'max_items_auto' => null !== $resolved_max ? absint( $resolved_max ) : $previous_max,
		);
		$changed_fields = array();
		if ( $old_basic['enabled'] !== $new_basic['enabled'] ) {
			$changed_fields[] = 'enabled';
		}
		if ( $old_basic['execution_mode'] !== $new_basic['execution_mode'] ) {
			$changed_fields[] = 'execution_mode';
		}
		if ( $old_basic['max_items_auto'] !== $new_basic['max_items_auto'] ) {
			$changed_fields[] = 'max_items_auto';
		}

		if ( empty( $changed_fields ) ) {
			return $current_config;
		}

		if ( null !== $resolved_max ) {
			$limits['max_items_auto'] = absint( $resolved_max );
		}

		$saved = $this->repository->save_rule_config(
			$business_id,
			$rule_key,
			array(
				'enabled'        => $resolved_enabled,
				'execution_mode' => $resolved_mode,
				'thresholds'     => $thresholds,
				'limits'         => $limits,
			)
		);

		if ( ! $saved ) {
			return new \WP_Error( 'save_failed', __( 'Could not save operational rule configuration.', 'super-mechanic' ) );
		}

		$this->clear_request_cache();
		$updated_config = $this->get_rule_config( $business_id, $rule_key );

		$actor_user_id = get_current_user_id();
		if ( $actor_user_id > 0 ) {
			$this->execution_log_service->register_rule_update_audit(
				$business_id,
				$rule_key,
				$actor_user_id,
				$new_basic['execution_mode'],
				$old_basic,
				$new_basic,
				$changed_fields
			);
		}

		return $updated_config;
	}

	/**
	 * Parse enabled raw input.
	 *
	 * @param mixed $enabled Enabled raw value.
	 * @param bool  $fallback Fallback enabled value.
	 * @return bool|\WP_Error
	 */
	protected function parse_enabled_input( $enabled, $fallback = false ) {
		if ( null === $enabled || '' === $enabled ) {
			return (bool) $fallback;
		}
		if ( is_bool( $enabled ) ) {
			return $enabled;
		}
		if ( is_numeric( $enabled ) ) {
			$int_enabled = (int) $enabled;
			if ( 0 === $int_enabled || 1 === $int_enabled ) {
				return 1 === $int_enabled;
			}
		}
		if ( is_string( $enabled ) ) {
			$value = strtolower( trim( $enabled ) );
			if ( in_array( $value, array( '0', '1', 'true', 'false' ), true ) ) {
				return in_array( $value, array( '1', 'true' ), true );
			}
		}

		return new \WP_Error( 'invalid_enabled', __( 'Invalid enabled value. Expected boolean input.', 'super-mechanic' ) );
	}

	/**
	 * Parse execution mode input and enforce whitelist.
	 *
	 * @param mixed  $execution_mode Raw execution mode.
	 * @param string $fallback Fallback mode when input missing.
	 * @return string|\WP_Error
	 */
	protected function parse_execution_mode_input( $execution_mode, $fallback = 'manual' ) {
		$fallback = sanitize_key( (string) $fallback );
		if ( ! in_array( $fallback, array( 'manual', 'confirmable', 'auto' ), true ) ) {
			$fallback = 'manual';
		}

		if ( null === $execution_mode || '' === $execution_mode ) {
			return $fallback;
		}

		$mode = sanitize_key( (string) $execution_mode );
		if ( ! in_array( $mode, array( 'manual', 'confirmable', 'auto' ), true ) ) {
			return new \WP_Error( 'invalid_execution_mode', __( 'Invalid execution mode. Allowed: manual, confirmable, auto.', 'super-mechanic' ) );
		}

		return $mode;
	}

	/**
	 * Parse max_items_auto input.
	 *
	 * @param mixed $max_items_auto Raw max_items_auto.
	 * @param mixed $fallback Fallback value.
	 * @return int|null|\WP_Error
	 */
	protected function parse_max_items_auto_input( $max_items_auto, $fallback = null ) {
		if ( null === $max_items_auto || '' === $max_items_auto ) {
			if ( null === $fallback || '' === $fallback ) {
				return null;
			}
			return absint( $fallback );
		}

		if ( is_string( $max_items_auto ) && ! preg_match( '/^\d+$/', $max_items_auto ) ) {
			return new \WP_Error( 'invalid_max_items_auto', __( 'max_items_auto must be a non-negative integer.', 'super-mechanic' ) );
		}
		if ( is_int( $max_items_auto ) || is_float( $max_items_auto ) || is_numeric( $max_items_auto ) ) {
			$number = (string) $max_items_auto;
			if ( preg_match( '/^-/', $number ) ) {
				return new \WP_Error( 'invalid_max_items_auto', __( 'max_items_auto cannot be negative.', 'super-mechanic' ) );
			}
			if ( ! preg_match( '/^\d+$/', $number ) ) {
				return new \WP_Error( 'invalid_max_items_auto', __( 'max_items_auto must be an integer.', 'super-mechanic' ) );
			}
			return absint( $max_items_auto );
		}

		return new \WP_Error( 'invalid_max_items_auto', __( 'max_items_auto must be numeric.', 'super-mechanic' ) );
	}

	/**
	 * Evaluate operational rules with action previews only.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function evaluate_operational_rules( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$business_id = absint( $business_id );
		$user_id     = get_current_user_id();
		$rules       = $this->get_operational_rules( $business_id );

		$console      = $this->workload_service->get_operational_automation_console( $business_id, $user_id );
		$bulk_actions = $this->workload_service->get_operational_bulk_actions( $business_id, $user_id );
		$assignments  = $this->workload_service->get_operational_assignments( $business_id );
		$summary      = $this->workload_service->get_global_operational_summary( $business_id );
		$feature_flags = array(
			'enable_recommendations' => $this->operational_config_service->is_enabled( $business_id, 'enable_recommendations' ),
			'enable_internal_flags'  => $this->operational_config_service->is_enabled( $business_id, 'enable_internal_flags' ),
			'enable_escalation'      => $this->operational_config_service->is_enabled( $business_id, 'enable_escalation' ),
		);

		$overdue_group = $this->find_bulk_group( $bulk_actions, 'overdue_tasks' );
		$critical_group = $this->find_bulk_group( $bulk_actions, 'critical_pending_tasks' );
		$overdue_count = isset( $summary['tasks_overdue_total'] ) ? absint( $summary['tasks_overdue_total'] ) : 0;
		$overloaded_users = isset( $assignments['summary']['overloaded_users'] ) ? absint( $assignments['summary']['overloaded_users'] ) : 0;
		$critical_flags = $feature_flags['enable_internal_flags'] && isset( $console['flags']['summary']['critical_flags'] ) ? absint( $console['flags']['summary']['critical_flags'] ) : 0;
		$global_level   = $feature_flags['enable_escalation'] && isset( $console['system_status']['global_level'] ) ? sanitize_key( (string) $console['system_status']['global_level'] ) : 'normal';

		$evaluations = array();
		foreach ( $rules as $rule ) {
			$rule_key   = isset( $rule['rule_key'] ) ? sanitize_key( (string) $rule['rule_key'] ) : '';
			$enabled    = ! empty( $rule['enabled'] );
			$execution_mode = isset( $rule['execution_mode'] ) ? sanitize_key( (string) $rule['execution_mode'] ) : 'manual';
			$threshold  = isset( $rule['conditions'][0]['threshold'] ) ? absint( $rule['conditions'][0]['threshold'] ) : 0;
			$triggered  = false;
			$impact     = 'info';
			$trigger_reason  = __( 'Rule is disabled for this business.', 'super-mechanic' );
			$execution_state = 'skipped';
			$execution_reason = __( 'Enable this rule to evaluate and prepare an action.', 'super-mechanic' );
			$preview    = array(
				'action_type' => isset( $rule['action_type'] ) ? sanitize_key( (string) $rule['action_type'] ) : 'flag',
				'executable'  => false,
				'note'        => __( 'Preview only. No automatic execution.', 'super-mechanic' ),
			);

			if ( 'overdue_tasks_cleanup' === $rule_key ) {
				$triggered = $enabled && $overdue_count > $threshold;
				$impact    = $overdue_count >= ( $threshold * 2 ) ? 'critical' : ( $triggered ? 'warning' : 'info' );
				if ( $enabled ) {
					if ( $triggered ) {
						$trigger_reason = sprintf(
							/* translators: 1: overdue count, 2: threshold. */
							__( 'Triggered: overdue CRM tasks (%1$d) are above threshold (%2$d).', 'super-mechanic' ),
							$overdue_count,
							$threshold
						);
					} else {
						$trigger_reason = sprintf(
							/* translators: 1: overdue count, 2: threshold. */
							__( 'Not triggered: overdue CRM tasks (%1$d) must be greater than threshold (%2$d).', 'super-mechanic' ),
							$overdue_count,
							$threshold
						);
					}
				}
				$preview   = array(
					'action_type'  => 'bulk_resolve',
					'entity_type'  => 'crm_task',
					'candidate_count' => $overdue_count,
					'executable'   => ! empty( $overdue_group['executable'] ),
					'group_key'    => 'overdue_tasks',
					'execution_mode' => $execution_mode,
					'note'         => __( 'Would resolve pending overdue CRM tasks in a controlled bulk action.', 'super-mechanic' ),
				);
				if ( ! $enabled ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Rule disabled: overdue cleanup is currently inactive.', 'super-mechanic' );
				} elseif ( ! $triggered ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Skipped: overdue threshold is not met yet.', 'super-mechanic' );
				} elseif ( empty( $overdue_group['executable'] ) ) {
					$execution_state  = 'blocked';
					$execution_reason = __( 'Blocked: no executable overdue bulk group is available for current conditions.', 'super-mechanic' );
				} else {
					$execution_state  = 'ready';
					$execution_reason = __( 'Ready: overdue cleanup can run through the controlled execution flow.', 'super-mechanic' );
				}
			} elseif ( 'critical_saturation_rebalance' === $rule_key ) {
				$proposals = isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0;
				$triggered = $enabled && $overloaded_users > $threshold;
				$impact    = $overloaded_users >= 2 ? 'critical' : ( $triggered ? 'warning' : 'info' );
				if ( $enabled ) {
					if ( $triggered ) {
						$trigger_reason = sprintf(
							/* translators: 1: overloaded users, 2: threshold. */
							__( 'Triggered: overloaded users (%1$d) are above threshold (%2$d).', 'super-mechanic' ),
							$overloaded_users,
							$threshold
						);
					} else {
						$trigger_reason = sprintf(
							/* translators: 1: overloaded users, 2: threshold. */
							__( 'Not triggered: overloaded users (%1$d) must be greater than threshold (%2$d).', 'super-mechanic' ),
							$overloaded_users,
							$threshold
						);
					}
				}
				$preview   = array(
					'action_type'    => 'bulk_reassign',
					'entity_type'    => 'crm_task',
					'overloaded_users' => $overloaded_users,
					'proposal_count' => $proposals,
					'executable'     => ! empty( $critical_group['executable'] ) || $proposals > 0,
					'group_key'      => 'critical_pending_tasks',
					'execution_mode' => $execution_mode,
					'note'           => __( 'Would rebalance critical CRM task load using validated proposals.', 'super-mechanic' ),
				);
				if ( ! $enabled ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Rule disabled: saturation rebalance is currently inactive.', 'super-mechanic' );
				} elseif ( ! $triggered ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Skipped: no overload condition has been reached.', 'super-mechanic' );
				} elseif ( empty( $critical_group['executable'] ) && 0 === $proposals ) {
					$execution_state  = 'blocked';
					$execution_reason = __( 'Blocked: no executable reassignment proposal is available yet.', 'super-mechanic' );
				} else {
					$execution_state  = 'ready';
					$execution_reason = __( 'Ready: critical rebalance can run through controlled reassignment.', 'super-mechanic' );
				}
			} elseif ( 'multi_critical_alert' === $rule_key ) {
				$triggered = $enabled && $critical_flags >= $threshold;
				$impact    = $critical_flags >= ( $threshold + 1 ) || 'critical' === $global_level ? 'critical' : ( $triggered ? 'warning' : 'info' );
				if ( $enabled ) {
					if ( $triggered ) {
						$trigger_reason = sprintf(
							/* translators: 1: critical flags, 2: threshold. */
							__( 'Triggered: critical flags (%1$d) reached threshold (%2$d).', 'super-mechanic' ),
							$critical_flags,
							$threshold
						);
					} else {
						$trigger_reason = sprintf(
							/* translators: 1: critical flags, 2: threshold. */
							__( 'Not triggered: critical flags (%1$d) must be at least threshold (%2$d).', 'super-mechanic' ),
							$critical_flags,
							$threshold
						);
					}
				}
				$preview   = array(
					'action_type'    => 'flag',
					'critical_flags' => $critical_flags,
					'global_level'   => $global_level,
					'executable'     => false,
					'execution_mode' => $execution_mode,
					'note'           => __( 'Would elevate operational visibility through dashboard flagging only.', 'super-mechanic' ),
				);
				if ( ! $enabled ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Rule disabled: critical alert visibility is currently inactive.', 'super-mechanic' );
				} elseif ( ! $triggered ) {
					$execution_state  = 'skipped';
					$execution_reason = __( 'Skipped: critical flag threshold has not been reached.', 'super-mechanic' );
				} else {
					$execution_state  = 'informative';
					$execution_reason = __( 'Triggered: this rule increases visibility only and does not mutate data.', 'super-mechanic' );
				}
			}

			$evaluations[] = array(
				'rule_key'       => $rule_key,
				'triggered'      => $triggered,
				'impact_level'   => $impact,
				'trigger_reason' => $trigger_reason,
				'execution_state' => $execution_state,
				'execution_reason' => $execution_reason,
				'action_preview' => $preview,
			);
		}

		$payload = array(
			'rules'       => $rules,
			'evaluations' => $evaluations,
			'meta'        => array(
				'business_id'  => $business_id,
				'user_id'      => $user_id,
				'generated_at' => current_time( 'mysql' ),
				'feature_flags' => $feature_flags,
				'mutations'    => 'none',
			),
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Get default config for one rule key.
	 *
	 * @param string $rule_key Rule key.
	 * @return array<string,mixed>
	 */
	protected function get_default_rule_config( $rule_key ) {
		$rule_key = sanitize_key( (string) $rule_key );
		$defaults = array(
			'overdue_tasks_cleanup' => array(
				'rule_key'       => 'overdue_tasks_cleanup',
				'enabled'        => true,
				'execution_mode' => 'auto',
				'thresholds'     => array(
					'overdue_tasks' => 3,
				),
				'limits'         => array(
					'max_items_auto' => 25,
				),
				'source'         => 'default',
			),
			'critical_saturation_rebalance' => array(
				'rule_key'       => 'critical_saturation_rebalance',
				'enabled'        => true,
				'execution_mode' => 'confirmable',
				'thresholds'     => array(
					'overloaded_users' => 0,
				),
				'limits'         => array(),
				'source'         => 'default',
			),
			'multi_critical_alert' => array(
				'rule_key'       => 'multi_critical_alert',
				'enabled'        => true,
				'execution_mode' => 'manual',
				'thresholds'     => array(
					'critical_flags' => 2,
				),
				'limits'         => array(),
				'source'         => 'default',
			),
		);

		return isset( $defaults[ $rule_key ] ) ? $defaults[ $rule_key ] : array();
	}

	/**
	 * Find a bulk group by key.
	 *
	 * @param array<string,mixed> $bulk_actions Bulk payload.
	 * @param string              $group_key Group key.
	 * @return array<string,mixed>
	 */
	protected function find_bulk_group( array $bulk_actions, $group_key ) {
		$groups = isset( $bulk_actions['groups'] ) && is_array( $bulk_actions['groups'] ) ? $bulk_actions['groups'] : array();
		$group_key = sanitize_key( (string) $group_key );

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$key = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : '';
			if ( $key === $group_key ) {
				return $group;
			}
		}

		return array();
	}

	/**
	 * Supported operational rule keys.
	 *
	 * @return array<int,string>
	 */
	protected function get_supported_rule_keys() {
		return array(
			'overdue_tasks_cleanup',
			'critical_saturation_rebalance',
			'multi_critical_alert',
		);
	}
}
