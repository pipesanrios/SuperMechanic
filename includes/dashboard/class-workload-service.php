<?php
/**
 * Workload service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Automation\Execution_Log_Service;
use Super_Mechanic\Automation\Operational_Rules_Service;
use Super_Mechanic\Config\Operational_Config_Service;
use Super_Mechanic\CRM\Crm_Pipeline_Service;
use Super_Mechanic\CRM\Crm_Task_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Processes\Process_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds operational workload buckets for one user.
 */
class Workload_Service {
	/**
	 * CRM task service.
	 *
	 * @var Crm_Task_Service
	 */
	protected $task_service;

	/**
	 * CRM pipeline service.
	 *
	 * @var Crm_Pipeline_Service
	 */
	protected $crm_pipeline_service;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Appointment service.
	 *
	 * @var Appointment_Service
	 */
	protected $appointment_service;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;
	/**
	 * Execution log service.
	 *
	 * @var Execution_Log_Service
	 */
	protected $execution_log_service;
	/**
	 * Operational config service.
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
	 * @param Crm_Task_Service|null        $task_service CRM task service.
	 * @param Crm_Pipeline_Service|null    $crm_pipeline_service CRM pipeline service.
	 * @param Process_Service|null         $process_service Process service.
	 * @param Appointment_Service|null     $appointment_service Appointment service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Crm_Task_Service $task_service = null, Crm_Pipeline_Service $crm_pipeline_service = null, Process_Service $process_service = null, Appointment_Service $appointment_service = null, Business_Context_Service $business_context_service = null, Execution_Log_Service $execution_log_service = null, Operational_Config_Service $operational_config_service = null ) {
		$this->task_service             = $task_service ? $task_service : new Crm_Task_Service();
		$this->crm_pipeline_service     = $crm_pipeline_service ? $crm_pipeline_service : new Crm_Pipeline_Service();
		$this->process_service          = $process_service ? $process_service : new Process_Service();
		$this->appointment_service      = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->execution_log_service    = $execution_log_service ? $execution_log_service : new Execution_Log_Service();
		$this->operational_config_service = $operational_config_service ? $operational_config_service : new Operational_Config_Service();
	}

	/**
	 * Build request cache key.
	 *
	 * @param string              $method Method name.
	 * @param array<int,mixed>    $args Arguments.
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
	 * Clear request-level memoization cache.
	 *
	 * @return void
	 */
	protected function clear_request_cache() {
		$this->request_cache = array();
	}

	/**
	 * Read one operational threshold with safe fallback.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $key Threshold key.
	 * @param int    $fallback Fallback value.
	 * @return int
	 */
	protected function get_operational_threshold( $business_id, $key, $fallback ) {
		$value = $this->operational_config_service->get_threshold( $business_id, $key );
		if ( null === $value ) {
			$value = absint( $fallback );
		}

		return absint( $value );
	}

	/**
	 * Read one operational feature flag with safe fallback.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $flag Flag key.
	 * @param bool   $fallback Fallback value.
	 * @return bool
	 */
	protected function is_operational_feature_enabled( $business_id, $flag, $fallback = true ) {
		$value = $this->operational_config_service->get( $business_id, $flag, $fallback );
		if ( is_bool( $value ) ) {
			return $value;
		}

		return (bool) $fallback;
	}

	/**
	 * Get user workload grouped by priority.
	 *
	 * @param int                 $assigned_user_id User ID.
	 * @param array<string,mixed> $args Optional filters.
	 * @return array<string,mixed>
	 */
	public function get_user_workload( $assigned_user_id, array $args = array() ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $assigned_user_id ), $args ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$args = wp_parse_args(
			$args,
			array(
				'upcoming_days'     => 7,
				'max_scan'          => 250,
				'limit_per_bucket'  => 20,
			)
		);

		$user_id = absint( $assigned_user_id );
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		$workload = $this->get_empty_workload();

		if ( $user_id <= 0 ) {
			return $workload;
		}

		$max_scan         = max( 50, absint( $args['max_scan'] ) );
		$upcoming_days    = max( 1, absint( $args['upcoming_days'] ) );
		$limit_per_bucket = max( 1, absint( $args['limit_per_bucket'] ) );
		$all_items        = array_merge(
			$this->collect_task_items( $user_id, $max_scan ),
			$this->collect_alert_items( $user_id, $max_scan ),
			$this->collect_process_items( $user_id, $max_scan ),
			$this->collect_appointment_items( $user_id, $upcoming_days, $max_scan )
		);

		foreach ( $all_items as $item ) {
			$priority = isset( $item['priority'] ) ? strtolower( sanitize_key( (string) $item['priority'] ) ) : 'normal';
			if ( ! isset( $workload[ $priority ] ) ) {
				$priority = 'normal';
			}

			$workload[ $priority ][] = $item;
		}

		foreach ( array( 'critical', 'warning', 'normal' ) as $bucket ) {
			$workload[ $bucket ] = $this->sort_items_by_date( $workload[ $bucket ] );
			$workload[ $bucket ] = array_slice( $workload[ $bucket ], 0, $limit_per_bucket );
		}

		$workload['meta'] = array(
			'assigned_user_id' => $user_id,
			'business_id'      => absint( $this->business_context_service->resolve_business_id_for_user( $user_id ) ),
			'generated_at'     => current_time( 'mysql' ),
		);

		return $this->set_request_cache( $cache_key, $workload );
	}

	/**
	 * Get global operational summary for active business context.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,int>
	 */
	public function get_global_operational_summary( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$summary             = $this->get_empty_global_summary();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		// Guard against cross-tenant reads when a foreign business_id is requested.
		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $summary;
		}

		$task_buckets = $this->task_service->get_operational_buckets( 7, 200 );

		$summary['tasks_pending_total']       = isset( $task_buckets['pending']['count'] ) ? absint( $task_buckets['pending']['count'] ) : 0;
		$summary['tasks_overdue_total']       = isset( $task_buckets['overdue']['count'] ) ? absint( $task_buckets['overdue']['count'] ) : 0;
		$summary['alerts_active_total']       = $this->count_active_operational_signals_total();
		$summary['processes_active_total']    = absint( $this->process_service->count_open_processes() );
		$summary['appointments_upcoming_total'] = $this->count_upcoming_appointments_total( $target_business_id, 7 );

		return $this->set_request_cache( $cache_key, $summary );
	}

	/**
	 * Get operational SLA metrics for active business context.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_metrics( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$metrics             = $this->get_empty_operational_metrics();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $metrics;
		}

		$metrics['tasks']        = $this->build_task_metrics();
		$metrics['processes']    = $this->build_process_metrics();
		$metrics['alerts']       = $this->build_alert_metrics();
		$metrics['appointments'] = $this->build_appointment_metrics( $target_business_id );

		return $this->set_request_cache( $cache_key, $metrics );
	}

	/**
	 * Get internal automation flags from existing operational signals.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID for user-scoped saturation flag.
	 * @return array<string,mixed>
	 */
	public function get_operational_automation_flags( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_automation_flags();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}
		if ( ! $this->is_operational_feature_enabled( $target_business_id, 'enable_internal_flags', true ) ) {
			$payload['meta'] = array(
				'business_id'    => $target_business_id,
				'user_id'        => $target_user_id,
				'generated_at'   => current_time( 'mysql' ),
				'signals_policy' => 'disabled_by_operational_config',
			);
			return $this->set_request_cache( $cache_key, $payload );
		}

		$summary  = $this->get_global_operational_summary( $target_business_id );
		$metrics  = $this->get_operational_metrics( $target_business_id );
		$workload = $this->get_user_workload(
			$target_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 50,
			)
		);

		$tasks_overdue_total = isset( $summary['tasks_overdue_total'] ) ? absint( $summary['tasks_overdue_total'] ) : 0;
		$processes_delayed   = isset( $metrics['processes']['delayed'] ) ? absint( $metrics['processes']['delayed'] ) : 0;
		$alerts_critical     = isset( $metrics['alerts']['critical'] ) ? absint( $metrics['alerts']['critical'] ) : 0;
		$user_critical_load  = isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0;
		$overdue_threshold   = max( 1, $this->get_operational_threshold( $target_business_id, 'overdue_tasks_threshold', 1 ) );
		$delayed_threshold   = max( 1, $this->get_operational_threshold( $target_business_id, 'delayed_process_threshold', 1 ) );
		$critical_threshold  = max( 1, $this->get_operational_threshold( $target_business_id, 'critical_signal_threshold', 2 ) );
		$saturation_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'user_saturation_threshold', 3 ) );

		$flags = array(
			array(
				'code'      => 'overdue_open_tasks',
				'active'    => $tasks_overdue_total >= $overdue_threshold,
				'level'     => $tasks_overdue_total >= $overdue_threshold ? 'critical' : 'normal',
				'message'   => __( 'There are overdue CRM tasks still open.', 'super-mechanic' ),
				'value'     => $tasks_overdue_total,
				'threshold' => $overdue_threshold,
			),
			array(
				'code'      => 'delayed_active_processes',
				'active'    => $processes_delayed >= $delayed_threshold,
				'level'     => $processes_delayed >= $delayed_threshold ? 'warning' : 'normal',
				'message'   => __( 'Active processes with operational delay detected.', 'super-mechanic' ),
				'value'     => $processes_delayed,
				'threshold' => $delayed_threshold,
			),
			array(
				'code'      => 'user_critical_saturation',
				'active'    => $user_critical_load >= $saturation_threshold,
				'level'     => $user_critical_load >= $saturation_threshold ? 'warning' : 'normal',
				'message'   => __( 'User operational saturation by critical workload.', 'super-mechanic' ),
				'value'     => $user_critical_load,
				'threshold' => $saturation_threshold,
			),
			array(
				'code'      => 'global_critical_escalation',
				'active'    => $alerts_critical >= $critical_threshold,
				'level'     => $alerts_critical >= $critical_threshold ? 'critical' : 'normal',
				'message'   => __( 'Multiple critical business signals require elevated operational state.', 'super-mechanic' ),
				'value'     => $alerts_critical,
				'threshold' => $critical_threshold,
			),
		);

		$payload['flags'] = $flags;
		$payload['meta']  = array(
			'business_id'      => $target_business_id,
			'user_id'          => $target_user_id,
			'generated_at'     => current_time( 'mysql' ),
			'signals_policy'   => 'crm_pipeline_aligned',
		);

		foreach ( $flags as $flag ) {
			if ( empty( $flag['active'] ) ) {
				continue;
			}

			$level = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'normal';
			if ( 'critical' === $level ) {
				++$payload['summary']['critical_flags'];
			} elseif ( 'warning' === $level ) {
				++$payload['summary']['warning_flags'];
			} else {
				++$payload['summary']['normal_flags'];
			}
		}

		$payload['summary']['active_flags'] = $payload['summary']['critical_flags'] + $payload['summary']['warning_flags'] + $payload['summary']['normal_flags'];
		$payload['summary']['global_state'] = $payload['summary']['critical_flags'] > 0 ? 'elevated' : ( $payload['summary']['warning_flags'] > 0 ? 'attention' : 'stable' );

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build operational escalation state from existing aggregated signals.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_escalation_state( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_escalation_state();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}
		if ( ! $this->is_operational_feature_enabled( $target_business_id, 'enable_escalation', true ) ) {
			$payload['meta'] = array(
				'business_id'  => $target_business_id,
				'user_id'      => $target_user_id,
				'generated_at' => current_time( 'mysql' ),
				'source'       => 'disabled_by_operational_config',
			);
			return $this->set_request_cache( $cache_key, $payload );
		}

		$automation_flags = $this->get_operational_automation_flags( $target_business_id, $target_user_id );
		$workload         = $this->get_user_workload(
			$target_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 50,
			)
		);
		$summary          = $this->get_global_operational_summary( $target_business_id );
		$metrics          = $this->get_operational_metrics( $target_business_id );
		$flags            = isset( $automation_flags['flags'] ) && is_array( $automation_flags['flags'] ) ? $automation_flags['flags'] : array();
		$overdue_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'overdue_tasks_threshold', 1 ) );
		$delayed_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'delayed_process_threshold', 1 ) );
		$saturation_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'user_saturation_threshold', 3 ) );
		$active_flags     = array_values(
			array_filter(
				$flags,
				function ( $flag ) {
					return is_array( $flag ) && ! empty( $flag['active'] );
				}
			)
		);

		$payload['critical_workload_count'] = isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0;
		$payload['warning_workload_count']  = isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? count( $workload['warning'] ) : 0;
		$payload['blocking_flags']          = array_values(
			array_filter(
				$active_flags,
				function ( $flag ) {
					$level = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'normal';
					return in_array( $level, array( 'critical', 'warning' ), true );
				}
			)
		);

		$payload['user_saturation'] = array(
			'user_id'         => $target_user_id,
			'is_saturated'    => false,
			'critical_load'   => $payload['critical_workload_count'],
			'threshold'       => $saturation_threshold,
			'active_flag'     => '',
			'suggested_level' => 'normal',
		);
		foreach ( $active_flags as $flag ) {
			if ( isset( $flag['code'] ) && 'user_critical_saturation' === $flag['code'] ) {
				$payload['user_saturation']['is_saturated']    = true;
				$payload['user_saturation']['threshold']       = isset( $flag['threshold'] ) ? absint( $flag['threshold'] ) : 3;
				$payload['user_saturation']['active_flag']     = 'user_critical_saturation';
				$payload['user_saturation']['suggested_level'] = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'warning';
				break;
			}
		}

		$has_critical_flag = false;
		$has_warning_flag  = false;
		foreach ( $active_flags as $flag ) {
			$level = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'normal';
			if ( 'critical' === $level ) {
				$has_critical_flag = true;
			} elseif ( 'warning' === $level ) {
				$has_warning_flag = true;
			}
		}

		if ( $has_critical_flag || $payload['critical_workload_count'] > 0 || ( isset( $summary['tasks_overdue_total'] ) && absint( $summary['tasks_overdue_total'] ) >= $overdue_threshold ) ) {
			$payload['global_level'] = 'critical';
		} elseif ( $has_warning_flag || $payload['warning_workload_count'] > 0 || ( isset( $metrics['processes']['delayed'] ) && absint( $metrics['processes']['delayed'] ) >= $delayed_threshold ) ) {
			$payload['global_level'] = 'warning';
		}

		$payload['meta'] = array(
			'business_id'    => $target_business_id,
			'user_id'        => $target_user_id,
			'generated_at'   => current_time( 'mysql' ),
			'source'         => 'automation_flags_workload_summary_metrics',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build intelligent operational recommendations from existing aggregates.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_recommendations( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_recommendations();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}
		if ( ! $this->is_operational_feature_enabled( $target_business_id, 'enable_recommendations', true ) ) {
			$payload['meta'] = array(
				'business_id'  => $target_business_id,
				'user_id'      => $target_user_id,
				'generated_at' => current_time( 'mysql' ),
				'source'       => 'disabled_by_operational_config',
			);
			return $this->set_request_cache( $cache_key, $payload );
		}

		$automation      = $this->get_operational_automation_flags( $target_business_id, $target_user_id );
		$escalation      = $this->get_operational_escalation_state( $target_business_id, $target_user_id );
		$workload        = $this->get_user_workload(
			$target_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 50,
			)
		);
		$global_summary  = $this->get_global_operational_summary( $target_business_id );
		$metrics         = $this->get_operational_metrics( $target_business_id );
		$recommendations = array();
		$overdue_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'overdue_tasks_threshold', 1 ) );
		$delayed_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'delayed_process_threshold', 1 ) );
		$critical_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'critical_signal_threshold', 2 ) );

		$tasks_overdue = isset( $global_summary['tasks_overdue_total'] ) ? absint( $global_summary['tasks_overdue_total'] ) : 0;
		if ( $tasks_overdue >= $overdue_threshold ) {
			$recommendations[] = array(
				'key'         => 'resolve_overdue_backlog',
				'level'       => 'critical',
				'title'       => __( 'Resolve overdue backlog', 'super-mechanic' ),
				'message'     => sprintf(
					/* translators: %d number of overdue tasks. */
					__( '%d overdue CRM tasks are still open and should be resolved first.', 'super-mechanic' ),
					$tasks_overdue
				),
				'action_hint' => __( 'Prioritize overdue tasks in CRM and clear oldest items first.', 'super-mechanic' ),
			);
		}

		$processes_delayed = isset( $metrics['processes']['delayed'] ) ? absint( $metrics['processes']['delayed'] ) : 0;
		if ( $processes_delayed >= $delayed_threshold ) {
			$recommendations[] = array(
				'key'         => 'review_delayed_processes',
				'level'       => 'warning',
				'title'       => __( 'Review delayed processes', 'super-mechanic' ),
				'message'     => sprintf(
					/* translators: %d number of delayed processes. */
					__( '%d active processes show operational delay.', 'super-mechanic' ),
					$processes_delayed
				),
				'action_hint' => __( 'Inspect blockers and unblock delayed processes before adding new load.', 'super-mechanic' ),
			);
		}

		$is_user_saturated = ! empty( $escalation['user_saturation']['is_saturated'] );
		if ( $is_user_saturated ) {
			$critical_load = isset( $escalation['user_saturation']['critical_load'] ) ? absint( $escalation['user_saturation']['critical_load'] ) : 0;
			$recommendations[] = array(
				'key'         => 'redistribute_user_load',
				'level'       => 'warning',
				'title'       => __( 'Redistribute user load', 'super-mechanic' ),
				'message'     => sprintf(
					/* translators: %d critical workload count. */
					__( 'User has %d critical workload items and is operationally saturated.', 'super-mechanic' ),
					$critical_load
				),
				'action_hint' => __( 'Reassign non-critical items to rebalance daily operational capacity.', 'super-mechanic' ),
			);
		}

		$critical_flags = isset( $automation['summary']['critical_flags'] ) ? absint( $automation['summary']['critical_flags'] ) : 0;
		if ( $critical_flags >= $critical_threshold || ( isset( $escalation['global_level'] ) && 'critical' === $escalation['global_level'] ) ) {
			$recommendations[] = array(
				'key'         => 'immediate_critical_intervention',
				'level'       => 'critical',
				'title'       => __( 'Immediate critical intervention', 'super-mechanic' ),
				'message'     => __( 'Multiple critical operational signals are active across the business.', 'super-mechanic' ),
				'action_hint' => __( 'Run a short priority triage and focus team effort on top critical blockers.', 'super-mechanic' ),
			);
		}

		$critical_count   = isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0;
		$warning_count    = isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? count( $workload['warning'] ) : 0;
		$normal_count     = isset( $workload['normal'] ) && is_array( $workload['normal'] ) ? count( $workload['normal'] ) : 0;
		$workload_total   = $critical_count + $warning_count + $normal_count;
		$appointments_upcoming = isset( $global_summary['appointments_upcoming_total'] ) ? absint( $global_summary['appointments_upcoming_total'] ) : 0;
		if ( 0 === $workload_total && $appointments_upcoming > 0 ) {
			$recommendations[] = array(
				'key'         => 'prepare_upcoming_appointments',
				'level'       => 'warning',
				'title'       => __( 'Prepare scheduled work', 'super-mechanic' ),
				'message'     => sprintf(
					/* translators: %d number of upcoming appointments. */
					__( 'No current workload items, but %d appointments are coming soon.', 'super-mechanic' ),
					$appointments_upcoming
				),
				'action_hint' => __( 'Pre-assign resources and prepare required documents and parts in advance.', 'super-mechanic' ),
			);
		}

		$payload['recommendations'] = $recommendations;
		$payload['recommendations'] = $this->prioritize_operational_recommendations( $payload['recommendations'] );
		$payload['summary']['total'] = count( $recommendations );
		foreach ( $payload['recommendations'] as $recommendation ) {
			$level = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';
			if ( 'critical' === $level ) {
				++$payload['summary']['critical'];
			} else {
				++$payload['summary']['warning'];
			}
		}

		$payload['meta'] = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'source'       => 'automation_escalation_workload_metrics',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build operational assignment suggestions without mutating real assignments.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_assignments( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_assignments();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		$user_ids = $this->get_operational_candidate_user_ids( $target_business_id );
		if ( empty( $user_ids ) ) {
			return $payload;
		}

		$global_metrics = $this->get_operational_metrics( $target_business_id );
		$saturation_threshold = max( 1, $this->get_operational_threshold( $target_business_id, 'user_saturation_threshold', 3 ) );
		$delayed_threshold    = max( 1, $this->get_operational_threshold( $target_business_id, 'delayed_process_threshold', 1 ) );
		$overloaded     = array();
		$available      = array();
		$task_candidates_by_user = array();

		foreach ( $user_ids as $user_id ) {
			$workload = $this->get_user_workload(
				$user_id,
				array(
					'upcoming_days'    => 7,
					'max_scan'         => 250,
					'limit_per_bucket' => 50,
				)
			);
			$escalation = $this->get_operational_escalation_state( $target_business_id, $user_id );
			$critical   = isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0;
			$warning    = isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? count( $workload['warning'] ) : 0;
			$normal     = isset( $workload['normal'] ) && is_array( $workload['normal'] ) ? count( $workload['normal'] ) : 0;
			$total      = $critical + $warning + $normal;
			$user       = get_userdata( $user_id );
			$name       = $user ? sanitize_text_field( $user->display_name ) : sprintf( __( 'User #%d', 'super-mechanic' ), $user_id );
			$row        = array(
				'user_id'       => $user_id,
				'display_name'  => $name,
				'critical'      => $critical,
				'warning'       => $warning,
				'total'         => $total,
			);
			$task_candidates_by_user[ $user_id ] = $this->extract_reassignable_task_ids_from_workload( $workload );
			$is_saturated = ! empty( $escalation['user_saturation']['is_saturated'] ) || $critical >= $saturation_threshold || $total >= 10;

			if ( $is_saturated ) {
				$overloaded[] = $row;
			} elseif ( 0 === $critical && $total <= 2 ) {
				$available[] = $row;
			}
		}

		usort(
			$overloaded,
			function ( $left, $right ) {
				$left_score  = ( absint( $left['critical'] ) * 10 ) + absint( $left['warning'] );
				$right_score = ( absint( $right['critical'] ) * 10 ) + absint( $right['warning'] );
				return $right_score <=> $left_score;
			}
		);
		usort(
			$available,
			function ( $left, $right ) {
				return absint( $left['total'] ) <=> absint( $right['total'] );
			}
		);

		$executable_task_candidates = 0;
		foreach ( $overloaded as $source ) {
			$source_user_id = isset( $source['user_id'] ) ? absint( $source['user_id'] ) : 0;
			if ( $source_user_id <= 0 || empty( $task_candidates_by_user[ $source_user_id ] ) || ! is_array( $task_candidates_by_user[ $source_user_id ] ) ) {
				continue;
			}
			$executable_task_candidates += count( $task_candidates_by_user[ $source_user_id ] );
		}

		$assignments       = array();
		$available_pointer = 0;
		foreach ( $overloaded as $source ) {
			if ( ! isset( $available[ $available_pointer ] ) ) {
				break;
			}

			$target   = $available[ $available_pointer ];
			$capacity = max( 1, 3 - absint( $target['total'] ) );
			$delta    = min( max( 1, absint( $source['critical'] ) - 2 ), $capacity );
			$level    = absint( $source['critical'] ) >= 5 ? 'critical' : 'warning';
			$source_user_id = absint( $source['user_id'] );
			$entity_id = 0;

			if ( ! empty( $task_candidates_by_user[ $source_user_id ] ) && is_array( $task_candidates_by_user[ $source_user_id ] ) ) {
				$entity_id = absint( array_shift( $task_candidates_by_user[ $source_user_id ] ) );
			}

			if ( isset( $global_metrics['processes']['delayed'] ) && absint( $global_metrics['processes']['delayed'] ) >= $delayed_threshold && 'warning' === $level ) {
				$level = 'critical';
			}

			$assignments[] = array(
				'from_user'      => $source_user_id,
				'to_user'        => absint( $target['user_id'] ),
				'reason'         => 'saturation_balance',
				'workload_delta' => absint( $delta ),
				'level'          => $level,
				'from_name'      => isset( $source['display_name'] ) ? sanitize_text_field( (string) $source['display_name'] ) : '',
				'to_name'        => isset( $target['display_name'] ) ? sanitize_text_field( (string) $target['display_name'] ) : '',
				'entity_type'    => $entity_id > 0 ? 'crm_task' : '',
				'entity_id'      => $entity_id,
				'executable'     => $entity_id > 0,
			);

			$available[ $available_pointer ]['total'] += $delta;
			if ( absint( $available[ $available_pointer ]['total'] ) >= 3 ) {
				++$available_pointer;
			}
		}

		$payload['overloaded_users'] = $overloaded;
		$payload['available_users']  = $available;
		$payload['assignments']      = $this->prioritize_operational_assignments( $assignments );
		$payload['summary']          = array(
			'overloaded_users' => count( $overloaded ),
			'available_users'  => count( $available ),
			'proposals'        => count( $assignments ),
			'executable_task_candidates' => $executable_task_candidates,
		);
		$payload['meta']             = array(
			'business_id'  => $target_business_id,
			'generated_at' => current_time( 'mysql' ),
			'mutations'    => 'none',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Execute one controlled operational reassignment.
	 *
	 * @param int    $business_id Business ID.
	 * @param int    $from_user_id Source user ID.
	 * @param int    $to_user_id Destination user ID.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function execute_operational_reassignment( $business_id, $from_user_id, $to_user_id, $entity_type, $entity_id ) {
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$from_user_id        = absint( $from_user_id );
		$to_user_id          = absint( $to_user_id );
		$entity_id           = absint( $entity_id );
		$entity_type         = sanitize_key( (string) $entity_type );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return new \WP_Error( 'sm_operational_reassign_business', __( 'Business scope is not valid for reassignment.', 'super-mechanic' ) );
		}

		if ( $from_user_id <= 0 || $to_user_id <= 0 || $from_user_id === $to_user_id ) {
			return new \WP_Error( 'sm_operational_reassign_users', __( 'Reassignment users are not valid.', 'super-mechanic' ) );
		}

		if ( ! get_userdata( $from_user_id ) || ! get_userdata( $to_user_id ) ) {
			return new \WP_Error( 'sm_operational_reassign_unknown_user', __( 'Users must exist to execute reassignment.', 'super-mechanic' ) );
		}

		if ( 'crm_task' !== $entity_type ) {
			return new \WP_Error( 'sm_operational_reassign_unsupported', __( 'Entity type is not supported in this phase.', 'super-mechanic' ) );
		}

		if ( $entity_id <= 0 ) {
			return new \WP_Error( 'sm_operational_reassign_entity', __( 'Entity is not valid for reassignment.', 'super-mechanic' ) );
		}

		$proposal_valid = $this->has_matching_assignment_proposal( $target_business_id, $from_user_id, $to_user_id, $entity_type, $entity_id );
		if ( ! $proposal_valid ) {
			return new \WP_Error( 'sm_operational_reassign_proposal', __( 'No valid reassignment proposal matches this action.', 'super-mechanic' ) );
		}

			$reassigned = $this->task_service->reassign_task( $entity_id, $from_user_id, $to_user_id );
			if ( is_wp_error( $reassigned ) ) {
				return $reassigned;
			}

			$this->clear_request_cache();

			return array(
			'status'      => 'success',
			'entity_type' => 'crm_task',
			'entity_id'   => $entity_id,
			'from_user'   => $from_user_id,
			'to_user'     => $to_user_id,
			'business_id' => $target_business_id,
		);
	}

	/**
	 * Consolidated automation console payload.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_automation_console( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_automation_console();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$flags           = $this->get_operational_automation_flags( $target_business_id, $target_user_id );
		$escalation      = $this->get_operational_escalation_state( $target_business_id, $target_user_id );
		$recommendations = $this->get_operational_recommendations( $target_business_id, $target_user_id );
		$assignments     = $this->get_operational_assignments( $target_business_id );

		$payload['flags']           = $flags;
		$payload['escalation']      = $escalation;
		$payload['recommendations'] = $recommendations;
		$payload['assignments']     = $assignments;
		$payload['system_status']   = array(
			'global_level'   => isset( $escalation['global_level'] ) ? sanitize_key( (string) $escalation['global_level'] ) : 'normal',
			'active_flags'   => isset( $flags['summary']['active_flags'] ) ? absint( $flags['summary']['active_flags'] ) : 0,
			'blocking_flags' => isset( $escalation['blocking_flags'] ) && is_array( $escalation['blocking_flags'] ) ? count( $escalation['blocking_flags'] ) : 0,
		);
		$payload['meta']            = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'source'       => 'flags_escalation_recommendations_assignments',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build assisted operational actions as safe navigation targets.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_assisted_actions( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_assisted_actions();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$recommendations = $this->get_operational_recommendations( $target_business_id, $target_user_id );
		$escalation      = $this->get_operational_escalation_state( $target_business_id, $target_user_id );
		$workload        = $this->get_user_workload(
			$target_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 50,
			)
		);
		$console         = $this->get_operational_automation_console( $target_business_id, $target_user_id );
		$actions         = array();

		$actions[] = array(
			'key'     => 'open_overdue_tasks',
			'label'   => __( 'Open overdue CRM tasks', 'super-mechanic' ),
			'url'     => $this->build_admin_page_url( 'super-mechanic-crm-pipeline' ),
			'level'   => 'warning',
			'context' => __( 'Review overdue backlog from CRM pipeline.', 'super-mechanic' ),
		);
		$actions[] = array(
			'key'     => 'open_delayed_processes',
			'label'   => __( 'Open delayed processes', 'super-mechanic' ),
			'url'     => $this->build_admin_page_url( 'super-mechanic-processes', array( 'filter_status' => 'overdue' ) ),
			'level'   => 'warning',
			'context' => __( 'Inspect process delays and unblock pending work.', 'super-mechanic' ),
		);
		$actions[] = array(
			'key'     => 'open_upcoming_appointments',
			'label'   => __( 'Open upcoming appointments', 'super-mechanic' ),
			'url'     => $this->build_admin_page_url( 'super-mechanic-appointments' ),
			'level'   => 'warning',
			'context' => __( 'Prepare near-term scheduled work.', 'super-mechanic' ),
		);
		$actions[] = array(
			'key'     => 'open_critical_workload',
			'label'   => __( 'Open critical workload', 'super-mechanic' ),
			'url'     => $this->build_admin_page_url(
				'super-mechanic',
				array(
					'section' => 'action_center',
					'filter'  => 'critical',
				)
			),
			'level'   => 'critical',
			'context' => __( 'Focus on critical operational items first.', 'super-mechanic' ),
		);

		$recommendation_rows = isset( $recommendations['recommendations'] ) && is_array( $recommendations['recommendations'] ) ? $recommendations['recommendations'] : array();
		foreach ( $recommendation_rows as $row ) {
			$key    = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
			$hint   = isset( $row['action_hint'] ) ? sanitize_text_field( (string) $row['action_hint'] ) : '';
			$level  = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : 'warning';
			$title  = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : __( 'Open related module', 'super-mechanic' );
			$url    = '';

			if ( false !== stripos( $hint, 'CRM' ) || false !== stripos( $hint, 'task' ) || false !== stripos( $key, 'backlog' ) ) {
				$url = $this->build_admin_page_url( 'super-mechanic-crm-pipeline' );
			} elseif ( false !== stripos( $hint, 'process' ) || false !== stripos( $key, 'process' ) ) {
				$url = $this->build_admin_page_url( 'super-mechanic-processes' );
			} elseif ( false !== stripos( $hint, 'appoint' ) || false !== stripos( $key, 'appointment' ) ) {
				$url = $this->build_admin_page_url( 'super-mechanic-appointments' );
			} elseif ( 'immediate_critical_intervention' === $key ) {
				$url = $this->build_admin_page_url( 'super-mechanic-crm-pipeline' );
			} elseif ( 'redistribute_user_load' === $key ) {
				$url = '';
			}

			$actions[] = array(
				'key'     => 'open_recommendation_' . ( '' !== $key ? $key : substr( md5( $title . '|' . $hint ), 0, 10 ) ),
				'label'   => $title,
				'url'     => $url,
				'level'   => in_array( $level, array( 'critical', 'warning' ), true ) ? $level : 'warning',
				'context' => '' !== $hint ? $hint : __( 'Open the module related to this recommendation.', 'super-mechanic' ),
			);
		}

		$is_critical_console = isset( $console['system_status']['global_level'] ) && 'critical' === $console['system_status']['global_level'];
		$has_blocking        = isset( $escalation['blocking_flags'] ) && is_array( $escalation['blocking_flags'] ) && ! empty( $escalation['blocking_flags'] );
		$critical_count      = isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0;
		foreach ( $actions as $index => $action ) {
			if ( ! isset( $actions[ $index ]['level'] ) ) {
				$actions[ $index ]['level'] = 'warning';
			}
			if ( ( $is_critical_console || $has_blocking || $critical_count > 0 ) && 'open_critical_workload' === $action['key'] ) {
				$actions[ $index ]['level'] = 'critical';
			}
		}

		$unique = array();
		foreach ( $actions as $action ) {
			$key = isset( $action['key'] ) ? sanitize_key( (string) $action['key'] ) : '';
			if ( '' === $key || isset( $unique[ $key ] ) ) {
				continue;
			}
			$unique[ $key ] = array(
				'key'     => $key,
				'label'   => isset( $action['label'] ) ? sanitize_text_field( (string) $action['label'] ) : __( 'Open module', 'super-mechanic' ),
				'url'     => isset( $action['url'] ) ? esc_url_raw( (string) $action['url'] ) : '',
				'level'   => isset( $action['level'] ) ? sanitize_key( (string) $action['level'] ) : 'warning',
				'context' => isset( $action['context'] ) ? sanitize_text_field( (string) $action['context'] ) : '',
			);
		}

		$payload['actions'] = $this->prioritize_operational_assisted_actions( array_values( $unique ) );
		$payload['summary'] = array(
			'total'    => count( $payload['actions'] ),
			'critical' => 0,
			'warning'  => 0,
		);

		foreach ( $payload['actions'] as $action ) {
			$level = isset( $action['level'] ) ? sanitize_key( (string) $action['level'] ) : 'warning';
			if ( 'critical' === $level ) {
				++$payload['summary']['critical'];
			} else {
				++$payload['summary']['warning'];
			}
		}

		$payload['meta'] = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'mutations'    => 'none',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build grouped operational bulk actions (read-only).
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_bulk_actions( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_operational_bulk_actions();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$assisted_actions = $this->get_operational_assisted_actions( $target_business_id, $target_user_id );
		$assignments      = $this->get_operational_assignments( $target_business_id );
		$console          = $this->get_operational_automation_console( $target_business_id, $target_user_id );
		$workload         = $this->get_user_workload(
			$target_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 50,
			)
		);

		$task_ids_by_level = $this->extract_task_ids_by_priority( $workload );
		$overdue_ids       = $this->get_global_overdue_task_ids( 250 );
		$critical_ids      = isset( $task_ids_by_level['critical'] ) ? $task_ids_by_level['critical'] : array();
		$warning_ids       = isset( $task_ids_by_level['warning'] ) ? $task_ids_by_level['warning'] : array();
		$reassign_target   = $this->resolve_bulk_reassign_target_user( $assignments, $target_user_id );
		$groups            = array();
		$is_critical_state = isset( $console['system_status']['global_level'] ) && 'critical' === $console['system_status']['global_level'];

		if ( ! empty( $overdue_ids ) ) {
			$groups[] = array(
				'group_key'   => 'overdue_tasks',
				'entity_type' => 'crm_task',
				'count'       => count( $overdue_ids ),
				'level'       => 'critical',
				'action'      => 'bulk_resolve',
				'executable'  => true,
				'items'       => array_values( $overdue_ids ),
			);
		}

		if ( ! empty( $critical_ids ) ) {
			$groups[] = array(
				'group_key'       => 'critical_pending_tasks',
				'entity_type'     => 'crm_task',
				'count'           => count( $critical_ids ),
				'level'           => $is_critical_state ? 'critical' : 'warning',
				'action'          => 'bulk_reassign',
				'executable'      => $reassign_target > 0,
				'target_user_id'  => $reassign_target,
				'items'           => array_values( $critical_ids ),
			);
		}

		if ( empty( $critical_ids ) && ! empty( $warning_ids ) ) {
			$groups[] = array(
				'group_key'   => 'warning_pending_tasks',
				'entity_type' => 'crm_task',
				'count'       => count( $warning_ids ),
				'level'       => 'warning',
				'action'      => 'bulk_resolve',
				'executable'  => true,
				'items'       => array_values( $warning_ids ),
			);
		}

		$payload['groups'] = $this->prioritize_operational_bulk_groups( $groups );
		$payload['summary'] = array(
			'total_groups'      => count( $payload['groups'] ),
			'executable_groups' => count(
				array_filter(
					$payload['groups'],
					function ( $group ) {
						return is_array( $group ) && ! empty( $group['executable'] );
					}
				)
			),
		);
		$payload['meta'] = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'source'       => 'assisted_actions_assignments_console',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Execute one operational bulk action with strict validations.
	 *
	 * @param int                   $business_id Business ID.
	 * @param string                $action Action key.
	 * @param string                $entity_type Entity type.
	 * @param array<int|string|int> $ids Entity IDs.
	 * @param int|null              $target_user_id Optional target user ID for reassignment.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function execute_operational_bulk_action( $business_id, $action, $entity_type, $ids, $target_user_id = null, array $execution_context = array() ) {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return new \WP_Error( 'sm_bulk_action_capability', __( 'You are not allowed to execute bulk actions.', 'super-mechanic' ) );
		}

		$nonce = isset( $_POST['sm_operational_bulk_action_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_operational_bulk_action_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_operational_bulk_action' ) ) {
			return new \WP_Error( 'sm_bulk_action_nonce', __( 'Security validation failed for bulk action.', 'super-mechanic' ) );
		}

		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$action              = sanitize_key( (string) $action );
		$entity_type         = sanitize_key( (string) $entity_type );
		$ids                 = $this->sanitize_bulk_ids( $ids, 50 );
		$target_user_id      = null !== $target_user_id ? absint( $target_user_id ) : 0;

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return new \WP_Error( 'sm_bulk_action_business', __( 'Business scope is not valid for bulk action.', 'super-mechanic' ) );
		}

		if ( ! in_array( $action, array( 'bulk_resolve', 'bulk_reassign' ), true ) ) {
			return new \WP_Error( 'sm_bulk_action_invalid_action', __( 'Bulk action is not supported.', 'super-mechanic' ) );
		}

		if ( 'crm_task' !== $entity_type ) {
			return new \WP_Error( 'sm_bulk_action_entity', __( 'Entity type is not supported in this phase.', 'super-mechanic' ) );
		}

		if ( empty( $ids ) ) {
			return new \WP_Error( 'sm_bulk_action_ids', __( 'No valid IDs were provided for bulk action.', 'super-mechanic' ) );
		}

		if ( 'bulk_reassign' === $action && $target_user_id <= 0 ) {
			return new \WP_Error( 'sm_bulk_action_target', __( 'Target user is required for bulk reassignment.', 'super-mechanic' ) );
		}

		$execution_guard = $this->get_execution_guardrails( $target_business_id, $action, $entity_type, $ids );
		if ( empty( $execution_guard['allowed'] ) ) {
			$reason = isset( $execution_guard['reason'] ) ? sanitize_text_field( (string) $execution_guard['reason'] ) : __( 'Execution blocked by guardrails.', 'super-mechanic' );
			return new \WP_Error( 'sm_bulk_action_guardrail', $reason );
		}

		$validated_rows = array();
		foreach ( $ids as $task_id ) {
			$task = $this->task_service->get_task( $task_id );
			if ( empty( $task ) || ! is_array( $task ) ) {
				return new \WP_Error( 'sm_bulk_action_missing_task', __( 'One or more CRM tasks are not valid for this business.', 'super-mechanic' ) );
			}

			$status = isset( $task['status'] ) ? sanitize_key( (string) $task['status'] ) : '';
			if ( 'pending' !== $status ) {
				return new \WP_Error( 'sm_bulk_action_status', __( 'All CRM tasks must be pending to execute this bulk action.', 'super-mechanic' ) );
			}

			$validated_rows[] = array(
				'id'               => absint( $task_id ),
				'assigned_user_id' => isset( $task['assigned_user_id'] ) ? absint( $task['assigned_user_id'] ) : 0,
			);
		}

		$success_ids = array();
		$failed_ids  = array();
		$rollback_items = array();
		foreach ( $validated_rows as $row ) {
			$task_id = absint( $row['id'] );
			if ( 'bulk_resolve' === $action ) {
				$result = $this->task_service->complete_task( $task_id );
			} else {
				$from_user = absint( $row['assigned_user_id'] );
				$result    = $this->task_service->reassign_task( $task_id, $from_user, $target_user_id );
			}

			if ( is_wp_error( $result ) || false === $result ) {
				$failed_ids[] = $task_id;
			} else {
				$success_ids[] = $task_id;
				if ( 'bulk_resolve' === $action ) {
					$rollback_items[] = array(
						'task_id'          => $task_id,
						'previous_status'  => 'pending',
					);
				} else {
					$rollback_items[] = array(
						'task_id'                   => $task_id,
						'previous_assigned_user_id' => absint( $row['assigned_user_id'] ),
						'current_assigned_user_id'  => $target_user_id,
					);
				}
			}
		}

		$status   = empty( $failed_ids ) ? 'success' : ( empty( $success_ids ) ? 'failed' : 'partial' );
		$rule_key = isset( $execution_context['rule_key'] ) ? sanitize_key( (string) $execution_context['rule_key'] ) : '';
		if ( '' === $rule_key && isset( $_POST['sm_rule_key'] ) ) {
			$rule_key = sanitize_key( (string) wp_unslash( $_POST['sm_rule_key'] ) );
		}
		$rollback = array(
			'supported'    => in_array( $action, array( 'bulk_resolve', 'bulk_reassign' ), true ),
			'action_type'  => $action,
			'items'        => $rollback_items,
			'available'    => ! empty( $rollback_items ),
			'snapshot_key' => '',
		);

		$response = array(
			'status'         => $status,
			'action'         => $action,
			'entity_type'    => $entity_type,
			'total'          => count( $validated_rows ),
			'success_count'  => count( $success_ids ),
			'failed_count'   => count( $failed_ids ),
			'success_ids'    => $success_ids,
			'failed_ids'     => $failed_ids,
			'business_id'    => $target_business_id,
			'target_user_id' => $target_user_id,
			'rule_key'       => $rule_key,
			'execution_guard' => $execution_guard,
			'rollback'       => $rollback,
		);

			if ( $this->is_controlled_execution_source() && ! empty( $rollback['available'] ) ) {
			$snapshot_key = $this->store_controlled_execution_snapshot( $target_business_id, $response );
			if ( '' !== $snapshot_key ) {
				$response['rollback']['snapshot_key'] = $snapshot_key;
			}
			}

		$this->register_execution_log( $target_business_id, $action, $response, $execution_context );
		$this->clear_request_cache();

		return $response;
	}

	/**
	 * Evaluate execution guardrails before mutating controlled actions.
	 *
	 * @param int                   $business_id Business ID.
	 * @param string                $action_type Action type.
	 * @param string                $entity_type Entity type.
	 * @param array<int|string|int> $ids Entity IDs.
	 * @return array<string,mixed>
	 */
	public function get_execution_guardrails( $business_id, $action_type, $entity_type, $ids ) {
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$action_type         = sanitize_key( (string) $action_type );
		$entity_type         = sanitize_key( (string) $entity_type );
		$ids                 = $this->sanitize_bulk_ids( $ids, 50 );
		$count               = count( $ids );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return array(
				'allowed'    => false,
				'risk_level' => 'high',
				'reason'     => __( 'Business scope is not valid for execution.', 'super-mechanic' ),
			);
		}

		if ( ! in_array( $action_type, array( 'bulk_resolve', 'bulk_reassign' ), true ) ) {
			return array(
				'allowed'    => false,
				'risk_level' => 'high',
				'reason'     => __( 'Action type is not supported by execution guardrails.', 'super-mechanic' ),
			);
		}

		if ( 'crm_task' !== $entity_type ) {
			return array(
				'allowed'    => false,
				'risk_level' => 'high',
				'reason'     => __( 'Only crm_task entity type is allowed.', 'super-mechanic' ),
			);
		}

		if ( $count <= 0 ) {
			return array(
				'allowed'    => false,
				'risk_level' => 'medium',
				'reason'     => __( 'No valid execution items were provided.', 'super-mechanic' ),
			);
		}

		if ( $count > 25 ) {
			return array(
				'allowed'    => false,
				'risk_level' => 'high',
				'reason'     => sprintf(
					/* translators: %d number of items. */
					__( 'Execution blocked: %d items exceed safe limit (25).', 'super-mechanic' ),
					$count
				),
			);
		}

		if ( $count > 10 ) {
			return array(
				'allowed'    => true,
				'risk_level' => 'medium',
				'reason'     => __( 'Execution allowed with medium risk under controlled limit.', 'super-mechanic' ),
			);
		}

		return array(
			'allowed'    => true,
			'risk_level' => 'low',
			'reason'     => __( 'Execution allowed with low operational risk.', 'super-mechanic' ),
		);
	}

	/**
	 * Rollback one controlled execution for supported actions.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $action_type Action type.
	 * @param array<string,mixed> $payload Rollback payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function rollback_controlled_execution( $business_id, $action_type, array $payload ) {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return new \WP_Error( 'sm_rollback_capability', __( 'You are not allowed to rollback controlled execution.', 'super-mechanic' ) );
		}

		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$action_type         = sanitize_key( (string) $action_type );
		$snapshot_key        = isset( $payload['snapshot_key'] ) ? sanitize_text_field( (string) $payload['snapshot_key'] ) : '';

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return new \WP_Error( 'sm_rollback_business', __( 'Business scope is not valid for rollback.', 'super-mechanic' ) );
		}

		if ( ! in_array( $action_type, array( 'bulk_resolve', 'bulk_reassign' ), true ) ) {
			return new \WP_Error( 'sm_rollback_action', __( 'Rollback is not supported for this action.', 'super-mechanic' ) );
		}

		$snapshot = $this->get_controlled_execution_snapshot( $target_business_id );
		if ( empty( $snapshot ) ) {
			return new \WP_Error( 'sm_rollback_snapshot_missing', __( 'No controlled execution snapshot available for rollback.', 'super-mechanic' ) );
		}

		$stored_action = isset( $snapshot['action_type'] ) ? sanitize_key( (string) $snapshot['action_type'] ) : '';
		$stored_key    = isset( $snapshot['snapshot_key'] ) ? sanitize_text_field( (string) $snapshot['snapshot_key'] ) : '';
		$is_available  = ! empty( $snapshot['available'] );
		$source        = isset( $snapshot['execution_source'] ) ? sanitize_key( (string) $snapshot['execution_source'] ) : '';

		if ( 'controlled' !== $source || ! $is_available ) {
			return new \WP_Error( 'sm_rollback_not_available', __( 'Rollback is not available for this snapshot.', 'super-mechanic' ) );
		}

		if ( '' !== $snapshot_key && $stored_key !== $snapshot_key ) {
			return new \WP_Error( 'sm_rollback_snapshot_key', __( 'Rollback snapshot key is not valid.', 'super-mechanic' ) );
		}

		if ( $stored_action !== $action_type ) {
			return new \WP_Error( 'sm_rollback_action_mismatch', __( 'Rollback action does not match latest controlled execution.', 'super-mechanic' ) );
		}

		$items = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array();
		if ( empty( $items ) ) {
			return new \WP_Error( 'sm_rollback_items', __( 'Rollback snapshot has no valid items.', 'super-mechanic' ) );
		}

		$success_ids = array();
		$failed_ids  = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$task_id = isset( $item['task_id'] ) ? absint( $item['task_id'] ) : 0;
			if ( $task_id <= 0 ) {
				continue;
			}

			if ( 'bulk_resolve' === $action_type ) {
				$result = $this->task_service->reopen_task( $task_id );
			} else {
				$current_assigned  = isset( $item['current_assigned_user_id'] ) ? absint( $item['current_assigned_user_id'] ) : 0;
				$previous_assigned = isset( $item['previous_assigned_user_id'] ) ? absint( $item['previous_assigned_user_id'] ) : 0;
				$result            = $this->task_service->reassign_task( $task_id, $current_assigned, $previous_assigned );
			}

			if ( is_wp_error( $result ) || false === $result ) {
				$failed_ids[] = $task_id;
			} else {
				$success_ids[] = $task_id;
			}
		}

		if ( empty( $success_ids ) && empty( $failed_ids ) ) {
			return new \WP_Error( 'sm_rollback_empty_result', __( 'Rollback did not process any valid items.', 'super-mechanic' ) );
		}

		$status = empty( $failed_ids ) ? 'success' : ( empty( $success_ids ) ? 'failed' : 'partial' );

		$snapshot['available']       = false;
		$snapshot['rollback_status'] = $status;
		$snapshot['rolled_back_at']  = current_time( 'mysql' );
		$snapshot['rollback_result'] = array(
			'success_ids' => $success_ids,
			'failed_ids'  => $failed_ids,
		);
		$this->update_controlled_execution_snapshot( $target_business_id, $snapshot );
		$this->clear_request_cache();

		if ( ! empty( $success_ids ) ) {
			$this->register_execution_log(
				$target_business_id,
				'rollback_' . $action_type,
				array(
					'status'        => $status,
					'entity_type'   => 'crm_task',
					'total'         => count( $items ),
					'success_count' => count( $success_ids ),
					'failed_count'  => count( $failed_ids ),
					'target_user_id' => 0,
				),
				array(
					'rule_key'       => isset( $snapshot['rule_key'] ) ? sanitize_key( (string) $snapshot['rule_key'] ) : '',
					'execution_mode' => isset( $snapshot['execution_mode'] ) ? sanitize_key( (string) $snapshot['execution_mode'] ) : 'confirmable',
					'source'         => 'controlled_rollback',
				)
			);
		}

		return array(
			'status'        => $status,
			'action_type'   => $action_type,
			'business_id'   => $target_business_id,
			'success_count' => count( $success_ids ),
			'failed_count'  => count( $failed_ids ),
			'success_ids'   => $success_ids,
			'failed_ids'    => $failed_ids,
		);
	}

	/**
	 * Get execution safety overview with guardrails and rollback availability.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_execution_safety_overview( $business_id, $user_id = null ) {
		$payload             = $this->get_empty_execution_safety_payload();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}
		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}
		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$bulk_payload = $this->get_operational_bulk_actions( $target_business_id, $target_user_id );
		$groups       = isset( $bulk_payload['groups'] ) && is_array( $bulk_payload['groups'] ) ? $bulk_payload['groups'] : array();
		$selected     = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['executable'] ) ) {
				continue;
			}
			$selected = $group;
			break;
		}

		if ( ! empty( $selected ) ) {
			$action     = isset( $selected['action'] ) ? sanitize_key( (string) $selected['action'] ) : '';
			$entity     = isset( $selected['entity_type'] ) ? sanitize_key( (string) $selected['entity_type'] ) : '';
			$ids        = isset( $selected['items'] ) && is_array( $selected['items'] ) ? $selected['items'] : array();
			$payload['execution_guard'] = $this->get_execution_guardrails( $target_business_id, $action, $entity, $ids );
		} else {
			$payload['execution_guard'] = array(
				'allowed'    => false,
				'risk_level' => 'medium',
				'reason'     => __( 'No executable controlled group available right now.', 'super-mechanic' ),
			);
		}

		$snapshot = $this->get_controlled_execution_snapshot( $target_business_id );
		if ( ! empty( $snapshot ) ) {
			$payload['rollback'] = array(
				'supported'    => ! empty( $snapshot['supported'] ),
				'action_type'  => isset( $snapshot['action_type'] ) ? sanitize_key( (string) $snapshot['action_type'] ) : '',
				'items'        => isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array(),
				'available'    => ! empty( $snapshot['available'] ),
				'snapshot_key' => isset( $snapshot['snapshot_key'] ) ? sanitize_text_field( (string) $snapshot['snapshot_key'] ) : '',
				'result'       => isset( $snapshot['result'] ) ? sanitize_key( (string) $snapshot['result'] ) : '',
			);
		}

		return $payload;
	}

	/**
	 * Get read-only operational rules overview.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_rules_overview( $business_id ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ) ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$rules_service = new Operational_Rules_Service( $this );
		$payload       = $rules_service->evaluate_operational_rules( $business_id );

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build manual guided actions from triggered operational rules.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_guided_rule_actions( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_guided_rule_actions();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$rules_overview     = $this->get_operational_rules_overview( $target_business_id );
		$bulk_actions       = $this->get_operational_bulk_actions( $target_business_id, $target_user_id );
		$assisted_actions   = $this->get_operational_assisted_actions( $target_business_id, $target_user_id );
		$automation_console = $this->get_operational_automation_console( $target_business_id, $target_user_id );

		$evaluations = isset( $rules_overview['evaluations'] ) && is_array( $rules_overview['evaluations'] ) ? $rules_overview['evaluations'] : array();
		$by_rule     = array();
		foreach ( $evaluations as $evaluation ) {
			if ( ! is_array( $evaluation ) ) {
				continue;
			}
			$rule_key = isset( $evaluation['rule_key'] ) ? sanitize_key( (string) $evaluation['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$by_rule[ $rule_key ] = $evaluation;
		}

		$bulk_groups = isset( $bulk_actions['groups'] ) && is_array( $bulk_actions['groups'] ) ? $bulk_actions['groups'] : array();
		$overdue_group  = array();
		$reassign_group = array();
		foreach ( $bulk_groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$group_key = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : '';
			$action    = isset( $group['action'] ) ? sanitize_key( (string) $group['action'] ) : '';
			if ( 'overdue_tasks' === $group_key && 'bulk_resolve' === $action ) {
				$overdue_group = $group;
			}
			if ( 'critical_pending_tasks' === $group_key && 'bulk_reassign' === $action ) {
				$reassign_group = $group;
			}
		}

		$open_center_url = $this->build_admin_page_url( 'super-mechanic-crm-pipeline' );
		$assisted_rows = isset( $assisted_actions['actions'] ) && is_array( $assisted_actions['actions'] ) ? $assisted_actions['actions'] : array();
		foreach ( $assisted_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
			$url = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';
			if ( 'open_overdue_tasks' === $key && '' !== $url ) {
				$open_center_url = $url;
				break;
			}
		}

		$critical_flags = isset( $automation_console['flags']['summary']['critical_flags'] ) ? absint( $automation_console['flags']['summary']['critical_flags'] ) : 0;

		$guided = array();

		$overdue_eval     = isset( $by_rule['overdue_tasks_cleanup'] ) ? $by_rule['overdue_tasks_cleanup'] : array();
		$overdue_triggered = ! empty( $overdue_eval['triggered'] );
		$overdue_impact    = isset( $overdue_eval['impact_level'] ) ? sanitize_key( (string) $overdue_eval['impact_level'] ) : 'info';
		$overdue_preview   = isset( $overdue_eval['action_preview'] ) && is_array( $overdue_eval['action_preview'] ) ? $overdue_eval['action_preview'] : array();
		$overdue_ids       = isset( $overdue_group['items'] ) && is_array( $overdue_group['items'] ) ? array_values( array_filter( array_map( 'absint', $overdue_group['items'] ) ) ) : array();
		$overdue_executable = $overdue_triggered && ! empty( $overdue_group['executable'] ) && ! empty( $overdue_ids );
		$guided[] = array(
			'rule_key'          => 'overdue_tasks_cleanup',
			'triggered'         => $overdue_triggered,
			'impact_level'      => $overdue_impact,
			'label'             => __( 'Resolve overdue tasks now', 'super-mechanic' ),
			'action_type'       => 'bulk_resolve',
			'executable'        => $overdue_executable,
			'execution_payload' => $overdue_executable ? array(
				'action_key'  => 'bulk_resolve',
				'business_id' => $target_business_id,
				'entity_type' => 'crm_task',
				'ids'         => implode( ',', $overdue_ids ),
			) : array(),
			'reason'            => isset( $overdue_preview['note'] ) ? sanitize_text_field( (string) $overdue_preview['note'] ) : __( 'Rule is not currently executable for overdue tasks.', 'super-mechanic' ),
		);

		$rebalance_eval      = isset( $by_rule['critical_saturation_rebalance'] ) ? $by_rule['critical_saturation_rebalance'] : array();
		$rebalance_triggered = ! empty( $rebalance_eval['triggered'] );
		$rebalance_impact    = isset( $rebalance_eval['impact_level'] ) ? sanitize_key( (string) $rebalance_eval['impact_level'] ) : 'info';
		$rebalance_preview   = isset( $rebalance_eval['action_preview'] ) && is_array( $rebalance_eval['action_preview'] ) ? $rebalance_eval['action_preview'] : array();
		$rebalance_ids       = isset( $reassign_group['items'] ) && is_array( $reassign_group['items'] ) ? array_values( array_filter( array_map( 'absint', $reassign_group['items'] ) ) ) : array();
		$rebalance_target    = isset( $reassign_group['target_user_id'] ) ? absint( $reassign_group['target_user_id'] ) : 0;
		$rebalance_executable = $rebalance_triggered && ! empty( $reassign_group['executable'] ) && ! empty( $rebalance_ids ) && $rebalance_target > 0;
		$guided[] = array(
			'rule_key'          => 'critical_saturation_rebalance',
			'triggered'         => $rebalance_triggered,
			'impact_level'      => $rebalance_impact,
			'label'             => __( 'Reassign critical workload now', 'super-mechanic' ),
			'action_type'       => 'bulk_reassign',
			'executable'        => $rebalance_executable,
			'execution_payload' => $rebalance_executable ? array(
				'action_key'      => 'bulk_reassign',
				'business_id'     => $target_business_id,
				'entity_type'     => 'crm_task',
				'ids'             => implode( ',', $rebalance_ids ),
				'target_user_id'  => $rebalance_target,
			) : array(),
			'reason'            => isset( $rebalance_preview['note'] ) ? sanitize_text_field( (string) $rebalance_preview['note'] ) : __( 'Rule is not currently executable for reassignment.', 'super-mechanic' ),
		);

		$critical_eval      = isset( $by_rule['multi_critical_alert'] ) ? $by_rule['multi_critical_alert'] : array();
		$critical_triggered = ! empty( $critical_eval['triggered'] );
		$critical_impact    = isset( $critical_eval['impact_level'] ) ? sanitize_key( (string) $critical_eval['impact_level'] ) : 'info';
		$critical_preview   = isset( $critical_eval['action_preview'] ) && is_array( $critical_eval['action_preview'] ) ? $critical_eval['action_preview'] : array();
		$open_center_executable = $critical_triggered && '' !== $open_center_url;
		$guided[] = array(
			'rule_key'          => 'multi_critical_alert',
			'triggered'         => $critical_triggered,
			'impact_level'      => $critical_impact,
			'label'             => __( 'Open action center for intervention', 'super-mechanic' ),
			'action_type'       => 'open_center',
			'executable'        => $open_center_executable,
			'execution_payload' => $open_center_executable ? array(
				'url'            => $open_center_url,
				'critical_flags' => $critical_flags,
			) : array(),
			'reason'            => isset( $critical_preview['note'] ) ? sanitize_text_field( (string) $critical_preview['note'] ) : __( 'Open intervention center when critical signal accumulation is active.', 'super-mechanic' ),
		);

		$payload['guided_actions'] = $guided;
		$payload['summary']        = array(
			'total'          => count( $guided ),
			'executable'     => count(
				array_filter(
					$guided,
					function ( $item ) {
						return is_array( $item ) && ! empty( $item['executable'] );
					}
				)
			),
			'non_executable' => count( $guided ) - count(
				array_filter(
					$guided,
					function ( $item ) {
						return is_array( $item ) && ! empty( $item['executable'] );
					}
				)
			),
		);
		$payload['meta']           = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'source'       => 'rules_bulk_assisted_console',
			'mutations'    => 'none',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Build semi-automatic confirmable actions from triggered operational rules.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_confirmable_rule_actions( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload             = $this->get_empty_confirmable_rule_actions();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

		$rules_overview      = $this->get_operational_rules_overview( $target_business_id );
		$guided_actions      = $this->get_guided_rule_actions( $target_business_id, $target_user_id );
		$bulk_actions        = $this->get_operational_bulk_actions( $target_business_id, $target_user_id );
		$assignments         = $this->get_operational_assignments( $target_business_id );
		$automation_console  = $this->get_operational_automation_console( $target_business_id, $target_user_id );

		$evaluations = isset( $rules_overview['evaluations'] ) && is_array( $rules_overview['evaluations'] ) ? $rules_overview['evaluations'] : array();
		$rules_rows  = isset( $rules_overview['rules'] ) && is_array( $rules_overview['rules'] ) ? $rules_overview['rules'] : array();
		$guided_rows = isset( $guided_actions['guided_actions'] ) && is_array( $guided_actions['guided_actions'] ) ? $guided_actions['guided_actions'] : array();
		$bulk_groups = isset( $bulk_actions['groups'] ) && is_array( $bulk_actions['groups'] ) ? $bulk_actions['groups'] : array();
		$assignment_rows = isset( $assignments['assignments'] ) && is_array( $assignments['assignments'] ) ? $assignments['assignments'] : array();

		$evaluation_by_rule = array();
		foreach ( $evaluations as $evaluation ) {
			if ( ! is_array( $evaluation ) ) {
				continue;
			}
			$rule_key = isset( $evaluation['rule_key'] ) ? sanitize_key( (string) $evaluation['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$evaluation_by_rule[ $rule_key ] = $evaluation;
		}

		$guided_by_rule = array();
		$rules_by_key   = array();
		foreach ( $guided_rows as $guided_row ) {
			if ( ! is_array( $guided_row ) ) {
				continue;
			}
			$rule_key = isset( $guided_row['rule_key'] ) ? sanitize_key( (string) $guided_row['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$guided_by_rule[ $rule_key ] = $guided_row;
		}
		foreach ( $rules_rows as $rule_row ) {
			if ( ! is_array( $rule_row ) ) {
				continue;
			}
			$rule_key = isset( $rule_row['rule_key'] ) ? sanitize_key( (string) $rule_row['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$rules_by_key[ $rule_key ] = $rule_row;
		}

		$overdue_group      = array();
		$reassign_group     = array();
		foreach ( $bulk_groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$group_key = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : '';
			$action    = isset( $group['action'] ) ? sanitize_key( (string) $group['action'] ) : '';
			if ( 'overdue_tasks' === $group_key && 'bulk_resolve' === $action ) {
				$overdue_group = $group;
			}
			if ( 'critical_pending_tasks' === $group_key && 'bulk_reassign' === $action ) {
				$reassign_group = $group;
			}
		}

		$executable_assignments = array_values(
			array_filter(
				$assignment_rows,
				function ( $proposal ) {
					if ( ! is_array( $proposal ) ) {
						return false;
					}
					$entity_type = isset( $proposal['entity_type'] ) ? sanitize_key( (string) $proposal['entity_type'] ) : '';
					return ! empty( $proposal['executable'] ) && 'crm_task' === $entity_type;
				}
			)
		);

		$critical_center_url = $this->build_admin_page_url(
			'super-mechanic',
			array(
				'section' => 'action_center',
				'filter'  => 'critical',
			)
		);
		if ( isset( $guided_by_rule['multi_critical_alert']['execution_payload']['url'] ) ) {
			$candidate_url = esc_url_raw( (string) $guided_by_rule['multi_critical_alert']['execution_payload']['url'] );
			if ( '' !== $candidate_url ) {
				$critical_center_url = $candidate_url;
			}
		}

		$critical_flags = isset( $automation_console['flags']['summary']['critical_flags'] ) ? absint( $automation_console['flags']['summary']['critical_flags'] ) : 0;

		$confirmable_actions = array();

		$overdue_eval      = isset( $evaluation_by_rule['overdue_tasks_cleanup'] ) ? $evaluation_by_rule['overdue_tasks_cleanup'] : array();
		$overdue_guided    = isset( $guided_by_rule['overdue_tasks_cleanup'] ) ? $guided_by_rule['overdue_tasks_cleanup'] : array();
		$overdue_triggered = ! empty( $overdue_eval['triggered'] );
		$overdue_impact    = isset( $overdue_eval['impact_level'] ) ? sanitize_key( (string) $overdue_eval['impact_level'] ) : 'info';
		$overdue_mode      = isset( $rules_by_key['overdue_tasks_cleanup']['execution_mode'] ) ? sanitize_key( (string) $rules_by_key['overdue_tasks_cleanup']['execution_mode'] ) : 'confirmable';
		$overdue_ids       = isset( $overdue_group['items'] ) && is_array( $overdue_group['items'] ) ? array_values( array_filter( array_map( 'absint', $overdue_group['items'] ) ) ) : array();
		$overdue_confirmable = 'confirmable' === $overdue_mode;
		$overdue_executable  = $overdue_confirmable && $overdue_triggered && ! empty( $overdue_guided['executable'] ) && ! empty( $overdue_ids );
		$confirmable_actions[] = array(
			'rule_key'          => 'overdue_tasks_cleanup',
			'triggered'         => $overdue_triggered,
			'impact_level'      => $overdue_impact,
			'label'             => __( 'Confirm resolving overdue tasks', 'super-mechanic' ),
			'action_type'       => 'bulk_resolve',
			'confirm_required'  => $overdue_confirmable,
			'executable'        => $overdue_executable,
			'execution_payload' => $overdue_executable ? array(
				'action_key'  => 'bulk_resolve',
				'business_id' => $target_business_id,
				'entity_type' => 'crm_task',
				'ids'         => implode( ',', $overdue_ids ),
			) : array(),
			'affected_count'    => count( $overdue_ids ),
			'reason'            => $overdue_confirmable
				? ( isset( $overdue_guided['reason'] ) ? sanitize_text_field( (string) $overdue_guided['reason'] ) : __( 'Overdue cleanup is not executable with current runtime conditions.', 'super-mechanic' ) )
				: __( 'Rule execution mode is not confirmable for this layer.', 'super-mechanic' ),
		);

		$rebalance_eval      = isset( $evaluation_by_rule['critical_saturation_rebalance'] ) ? $evaluation_by_rule['critical_saturation_rebalance'] : array();
		$rebalance_guided    = isset( $guided_by_rule['critical_saturation_rebalance'] ) ? $guided_by_rule['critical_saturation_rebalance'] : array();
		$rebalance_triggered = ! empty( $rebalance_eval['triggered'] );
		$rebalance_impact    = isset( $rebalance_eval['impact_level'] ) ? sanitize_key( (string) $rebalance_eval['impact_level'] ) : 'info';
		$rebalance_mode      = isset( $rules_by_key['critical_saturation_rebalance']['execution_mode'] ) ? sanitize_key( (string) $rules_by_key['critical_saturation_rebalance']['execution_mode'] ) : 'confirmable';
		$rebalance_ids       = isset( $reassign_group['items'] ) && is_array( $reassign_group['items'] ) ? array_values( array_filter( array_map( 'absint', $reassign_group['items'] ) ) ) : array();
		$rebalance_target    = isset( $reassign_group['target_user_id'] ) ? absint( $reassign_group['target_user_id'] ) : 0;
		$has_real_proposal   = ! empty( $executable_assignments );
		$rebalance_confirmable = 'confirmable' === $rebalance_mode;
		$rebalance_executable  = $rebalance_confirmable && $rebalance_triggered && ! empty( $rebalance_guided['executable'] ) && ! empty( $rebalance_ids ) && $rebalance_target > 0 && $has_real_proposal;
		$confirmable_actions[] = array(
			'rule_key'          => 'critical_saturation_rebalance',
			'triggered'         => $rebalance_triggered,
			'impact_level'      => $rebalance_impact,
			'label'             => __( 'Confirm reassigning critical workload', 'super-mechanic' ),
			'action_type'       => 'bulk_reassign',
			'confirm_required'  => $rebalance_confirmable,
			'executable'        => $rebalance_executable,
			'execution_payload' => $rebalance_executable ? array(
				'action_key'      => 'bulk_reassign',
				'business_id'     => $target_business_id,
				'entity_type'     => 'crm_task',
				'ids'             => implode( ',', $rebalance_ids ),
				'target_user_id'  => $rebalance_target,
			) : array(),
			'affected_count'    => count( $rebalance_ids ),
			'reason'            => $rebalance_confirmable
				? ( isset( $rebalance_guided['reason'] ) ? sanitize_text_field( (string) $rebalance_guided['reason'] ) : __( 'Critical saturation rebalance requires executable reassignment proposals.', 'super-mechanic' ) )
				: __( 'Rule execution mode is not confirmable for this layer.', 'super-mechanic' ),
		);

		$critical_eval      = isset( $evaluation_by_rule['multi_critical_alert'] ) ? $evaluation_by_rule['multi_critical_alert'] : array();
		$critical_guided    = isset( $guided_by_rule['multi_critical_alert'] ) ? $guided_by_rule['multi_critical_alert'] : array();
		$critical_triggered = ! empty( $critical_eval['triggered'] );
		$critical_impact    = isset( $critical_eval['impact_level'] ) ? sanitize_key( (string) $critical_eval['impact_level'] ) : 'info';
		$open_center_executable = $critical_triggered && '' !== $critical_center_url;
		$confirmable_actions[] = array(
			'rule_key'          => 'multi_critical_alert',
			'triggered'         => $critical_triggered,
			'impact_level'      => $critical_impact,
			'label'             => __( 'Open action center for intervention', 'super-mechanic' ),
			'action_type'       => 'open_center',
			'confirm_required'  => false,
			'executable'        => $open_center_executable,
			'execution_payload' => $open_center_executable ? array(
				'url' => $critical_center_url,
			) : array(),
			'affected_count'    => $critical_flags,
			'reason'            => isset( $critical_guided['reason'] ) ? sanitize_text_field( (string) $critical_guided['reason'] ) : __( 'Critical alert pattern requires intervention visibility only.', 'super-mechanic' ),
		);

		$payload['confirmable_actions'] = $confirmable_actions;
		$payload['summary']             = array(
			'total'          => count( $confirmable_actions ),
			'confirmable'    => count(
				array_filter(
					$confirmable_actions,
					function ( $action ) {
						return is_array( $action ) && ! empty( $action['confirm_required'] ) && ! empty( $action['executable'] );
					}
				)
			),
			'non_executable' => count(
				array_filter(
					$confirmable_actions,
					function ( $action ) {
						return is_array( $action ) && empty( $action['executable'] );
					}
				)
			),
		);
		$payload['meta']                = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'source'       => 'rules_guided_bulk_assignments_console',
			'mutations'    => 'manual_confirmation_required',
		);

		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Return controlled auto-execution overview without mutating data.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_controlled_auto_execution_overview( $business_id, $user_id = null ) {
		$cache_key = $this->build_request_cache_key( __FUNCTION__, array( absint( $business_id ), null !== $user_id ? absint( $user_id ) : null ) );
		$cached    = $this->get_request_cache( $cache_key, $cache_hit );
		if ( $cache_hit ) {
			return $cached;
		}

		$payload = $this->build_controlled_auto_execution_payload( $business_id, $user_id, false );
		return $this->set_request_cache( $cache_key, $payload );
	}

	/**
	 * Run controlled auto-execution for eligible low-risk rules.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function run_controlled_auto_execution( $business_id, $user_id = null ) {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return new \WP_Error( 'sm_auto_exec_capability', __( 'You are not allowed to run controlled auto execution.', 'super-mechanic' ) );
		}

		$result = $this->build_controlled_auto_execution_payload( $business_id, $user_id, true );
		if ( ! is_wp_error( $result ) ) {
			$this->clear_request_cache();
		}

		return $result;
	}

	/**
	 * Build controlled auto-execution payload and optionally execute allowed rules.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @param bool     $execute Whether execution should run.
	 * @return array<string,mixed>
	 */
	protected function build_controlled_auto_execution_payload( $business_id, $user_id, $execute ) {
		$payload             = $this->get_empty_controlled_auto_execution_payload();
		$current_user_id     = get_current_user_id();
		$current_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_user_id ) );
		$target_business_id  = absint( $this->business_context_service->normalize_business_id( absint( $business_id ), $current_user_id ) );
		$target_user_id      = null !== $user_id ? absint( $user_id ) : absint( $current_user_id );

		if ( $target_business_id <= 0 ) {
			$target_business_id = $current_business_id;
		}

		if ( $target_business_id <= 0 || $target_business_id !== $current_business_id ) {
			return $payload;
		}

		if ( $target_user_id <= 0 ) {
			$target_user_id = $current_user_id;
		}

			$rules_overview    = $this->get_operational_rules_overview( $target_business_id );
		$bulk_actions      = $this->get_operational_bulk_actions( $target_business_id, $target_user_id );
		$rules             = isset( $rules_overview['rules'] ) && is_array( $rules_overview['rules'] ) ? $rules_overview['rules'] : array();
		$evaluations       = isset( $rules_overview['evaluations'] ) && is_array( $rules_overview['evaluations'] ) ? $rules_overview['evaluations'] : array();
		$groups            = isset( $bulk_actions['groups'] ) && is_array( $bulk_actions['groups'] ) ? $bulk_actions['groups'] : array();
		$rules_by_key      = array();
		$evaluations_by_key = array();
		$overdue_group     = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rule_key = isset( $rule['rule_key'] ) ? sanitize_key( (string) $rule['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$rules_by_key[ $rule_key ] = $rule;
		}
		$overdue_rule = isset( $rules_by_key['overdue_tasks_cleanup'] ) && is_array( $rules_by_key['overdue_tasks_cleanup'] ) ? $rules_by_key['overdue_tasks_cleanup'] : array();
		$safe_limit   = isset( $overdue_rule['action_config']['limit'] ) ? absint( $overdue_rule['action_config']['limit'] ) : 25;
		if ( $safe_limit <= 0 ) {
			$safe_limit = 25;
		}

		foreach ( $evaluations as $evaluation ) {
			if ( ! is_array( $evaluation ) ) {
				continue;
			}
			$rule_key = isset( $evaluation['rule_key'] ) ? sanitize_key( (string) $evaluation['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$evaluations_by_key[ $rule_key ] = $evaluation;
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$group_key = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : '';
			$action    = isset( $group['action'] ) ? sanitize_key( (string) $group['action'] ) : '';
			if ( 'overdue_tasks' === $group_key && 'bulk_resolve' === $action ) {
				$overdue_group = $group;
				break;
			}
		}

		$rows = array();
		foreach ( array( 'overdue_tasks_cleanup', 'critical_saturation_rebalance', 'multi_critical_alert' ) as $rule_key ) {
			$rule           = isset( $rules_by_key[ $rule_key ] ) ? $rules_by_key[ $rule_key ] : array();
			$evaluation     = isset( $evaluations_by_key[ $rule_key ] ) ? $evaluations_by_key[ $rule_key ] : array();
			$triggered      = ! empty( $evaluation['triggered'] );
			$impact_level   = isset( $evaluation['impact_level'] ) ? sanitize_key( (string) $evaluation['impact_level'] ) : 'info';
			$auto_executable = ! empty( $rule['auto_executable'] );
			$row            = array(
				'rule_key'        => $rule_key,
				'auto_executable' => $auto_executable,
				'executed'        => false,
				'result'          => 'skipped',
				'affected_count'  => 0,
				'reason'          => __( 'Rule not triggered.', 'super-mechanic' ),
				'impact_level'    => $impact_level,
			);

			if ( ! $triggered ) {
				$rows[] = $row;
				continue;
			}

			if ( ! $auto_executable ) {
				$row['result'] = 'blocked';
				$row['reason'] = __( 'Rule is not auto-executable in this phase.', 'super-mechanic' );
				$rows[] = $row;
				continue;
			}

			if ( 'overdue_tasks_cleanup' !== $rule_key ) {
				$row['result'] = 'blocked';
				$row['reason'] = __( 'Only overdue_tasks_cleanup is allowed for controlled auto execution.', 'super-mechanic' );
				$rows[] = $row;
				continue;
			}

			$items = isset( $overdue_group['items'] ) && is_array( $overdue_group['items'] ) ? array_values( array_filter( array_map( 'absint', $overdue_group['items'] ) ) ) : array();
			$count = count( $items );
			$row['affected_count'] = $count;

			if ( empty( $overdue_group ) || empty( $overdue_group['executable'] ) || 0 === $count ) {
				$row['result'] = 'blocked';
				$row['reason'] = __( 'No executable overdue group found for controlled auto execution.', 'super-mechanic' );
				$rows[] = $row;
				continue;
			}

			if ( $count > $safe_limit ) {
				$row['result'] = 'blocked';
				$row['reason'] = sprintf(
					/* translators: 1: candidate count, 2: safe limit. */
					__( 'Blocked by safety limit: %1$d candidates exceed max %2$d.', 'super-mechanic' ),
					$count,
					$safe_limit
				);
				$rows[] = $row;
				continue;
			}

			if ( ! $execute ) {
				$row['result'] = 'skipped';
				$row['reason'] = __( 'Eligible and ready. Requires explicit controlled run.', 'super-mechanic' );
				$rows[] = $row;
				continue;
			}

			$exec_result = $this->execute_operational_bulk_action(
				$target_business_id,
				'bulk_resolve',
				'crm_task',
				$items,
				null,
				array(
					'rule_key'       => $rule_key,
					'execution_mode' => 'auto',
					'source'         => 'controlled_auto_execution',
				)
			);

			if ( is_wp_error( $exec_result ) ) {
				$row['result'] = 'blocked';
				$row['reason'] = $exec_result->get_error_message();
				$rows[] = $row;
				continue;
			}

			$status               = isset( $exec_result['status'] ) ? sanitize_key( (string) $exec_result['status'] ) : 'failed';
			$row['executed']      = true;
			$row['affected_count'] = isset( $exec_result['success_count'] ) ? absint( $exec_result['success_count'] ) : $count;
			if ( 'success' === $status ) {
				$row['result'] = 'success';
				$row['reason'] = __( 'Controlled auto execution completed successfully.', 'super-mechanic' );
			} elseif ( 'partial' === $status ) {
				$row['result'] = 'partial';
				$row['reason'] = __( 'Controlled auto execution completed partially.', 'super-mechanic' );
			} else {
				$row['result'] = 'blocked';
				$row['reason'] = __( 'Controlled auto execution failed.', 'super-mechanic' );
			}

			$rows[] = $row;
		}

		$payload['auto_execution'] = $rows;
		$payload['summary']        = array(
			'eligible_rules' => count(
				array_filter(
					$rows,
					function ( $row ) {
						return is_array( $row ) && ! empty( $row['auto_executable'] ) && 'skipped' === ( $row['result'] ?? '' );
					}
				)
			),
			'executed_rules' => count(
				array_filter(
					$rows,
					function ( $row ) {
						return is_array( $row ) && ! empty( $row['executed'] );
					}
				)
			),
			'blocked_rules'  => count(
				array_filter(
					$rows,
					function ( $row ) {
						return is_array( $row ) && 'blocked' === ( $row['result'] ?? '' );
					}
				)
			),
		);
		$payload['meta']           = array(
			'business_id'  => $target_business_id,
			'user_id'      => $target_user_id,
			'generated_at' => current_time( 'mysql' ),
			'safe_limit'   => $safe_limit,
			'mode'         => $execute ? 'run' : 'preview',
		);

		return $payload;
	}

	/**
	 * Collect CRM task items.
	 *
	 * @param int $user_id User ID.
	 * @param int $max_scan Max rows to scan.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_task_items( $user_id, $max_scan ) {
		$items    = array();
		$seen_ids = array();
		$now_ts   = current_time( 'timestamp', true );
		$buckets  = $this->task_service->get_operational_buckets( 14, $max_scan );

		$sources = array(
			'overdue' => isset( $buckets['overdue']['items'] ) && is_array( $buckets['overdue']['items'] ) ? $buckets['overdue']['items'] : array(),
			'pending' => isset( $buckets['pending']['items'] ) && is_array( $buckets['pending']['items'] ) ? $buckets['pending']['items'] : array(),
		);

		foreach ( $sources as $source_key => $rows ) {
			foreach ( $rows as $row ) {
				$task_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( $task_id <= 0 || isset( $seen_ids[ $task_id ] ) ) {
					continue;
				}

				$assigned_user_id = isset( $row['assigned_user_id'] ) ? absint( $row['assigned_user_id'] ) : 0;
				if ( $assigned_user_id !== absint( $user_id ) ) {
					continue;
				}

				$due_at     = isset( $row['due_at'] ) ? sanitize_text_field( (string) $row['due_at'] ) : '';
				$due_ts     = '' !== $due_at ? strtotime( $due_at ) : false;
				$is_overdue = 'overdue' === $source_key || ( false !== $due_ts && $due_ts < $now_ts );
				$priority   = $is_overdue ? 'critical' : 'warning';
				$title      = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
				if ( '' === $title ) {
					$title = sprintf(
						/* translators: %d task ID. */
						__( 'CRM task #%d', 'super-mechanic' ),
						$task_id
					);
				}

				$items[] = array(
					'type'      => 'task',
					'title'     => $title,
					'url'       => $this->get_crm_task_url(
						isset( $row['crm_pipeline_id'] ) ? absint( $row['crm_pipeline_id'] ) : 0,
						$task_id
					),
					'date'      => $due_at,
					'priority'  => $priority,
					'source_id' => 'task:' . $task_id,
				);

				$seen_ids[ $task_id ] = true;
			}
		}

		return $items;
	}

	/**
	 * Collect persisted CRM alert items.
	 *
	 * @param int $user_id User ID.
	 * @param int $max_scan Max rows to scan.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_alert_items( $user_id, $max_scan ) {
		$items         = array();
		$opportunities = $this->crm_pipeline_service->get_opportunities(
			array(
				'assigned_user_id' => absint( $user_id ),
				'page'             => 1,
				'per_page'         => $max_scan,
				'orderby'          => 'updated_at',
				'order'            => 'DESC',
			)
		);

		if ( empty( $opportunities ) ) {
			return $items;
		}

		$signals_by_id = $this->crm_pipeline_service->get_automation_signals_for_opportunities( $opportunities );

		foreach ( $opportunities as $row ) {
			$opportunity_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $opportunity_id <= 0 ) {
				continue;
			}

			if ( ! isset( $signals_by_id[ $opportunity_id ] ) || ! is_array( $signals_by_id[ $opportunity_id ] ) ) {
				continue;
			}

			$signal = $signals_by_id[ $opportunity_id ];
			$level  = $this->resolve_operational_signal_level( $signal );

			if ( 'none' === $level ) {
				continue;
			}

			$items[] = array(
				'type'      => 'task',
				'title'     => sprintf(
					/* translators: 1: priority label, 2: opportunity title. */
					__( 'CRM signal: %1$s - %2$s', 'super-mechanic' ),
					'critical' === $level ? __( 'Critical', 'super-mechanic' ) : __( 'Attention', 'super-mechanic' ),
					isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : __( 'Opportunity', 'super-mechanic' )
				),
				'url'       => $this->get_crm_opportunity_url( $opportunity_id ),
				'date'      => isset( $signal['last_activity_at'] ) ? sanitize_text_field( (string) $signal['last_activity_at'] ) : '',
				'priority'  => $level,
				'source_id' => 'signal:' . $opportunity_id,
			);
		}

		return $items;
	}

	/**
	 * Collect active process items.
	 *
	 * @param int $user_id User ID.
	 * @param int $max_scan Max rows to scan.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_process_items( $user_id, $max_scan ) {
		$items     = array();
		$processes = $this->process_service->get_mechanic_processes(
			$user_id,
			array(
				'exclude_statuses' => array( 'completed', 'delivered', 'cancelled' ),
			),
			$max_scan
		);

		foreach ( $processes as $row ) {
			$process_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $process_id <= 0 ) {
				continue;
			}

			$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$title  = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %d process ID. */
					__( 'Process #%d', 'super-mechanic' ),
					$process_id
				);
			}

			$items[] = array(
				'type'      => 'process',
				'title'     => $title,
				'url'       => $this->get_process_url( $process_id ),
				'date'      => isset( $row['updated_at'] ) ? sanitize_text_field( (string) $row['updated_at'] ) : '',
				'priority'  => $this->map_process_priority( $status ),
				'source_id' => 'process:' . $process_id,
			);
		}

		return $items;
	}

	/**
	 * Collect upcoming appointment items.
	 *
	 * @param int $user_id User ID.
	 * @param int $upcoming_days Upcoming window in days.
	 * @param int $max_scan Max rows to scan.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_appointment_items( $user_id, $upcoming_days, $max_scan ) {
		$items      = array();
		$today      = wp_date( 'Y-m-d' );
		$range_end  = wp_date( 'Y-m-d', strtotime( '+' . max( 1, absint( $upcoming_days ) ) . ' days' ) );
		$rows       = $this->appointment_service->get_appointments(
			array(
				'assigned_to' => absint( $user_id ),
				'date_from'   => $today,
				'date_to'     => $range_end,
				'per_page'    => $max_scan,
				'page'        => 1,
				'orderby'     => 'start_at',
				'order'       => 'ASC',
			)
		);
		$allowed    = array( 'scheduled', 'confirmed', 'in_progress' );
		$now_ts     = current_time( 'timestamp', true );

		foreach ( $rows as $row ) {
			$appointment_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $appointment_id <= 0 ) {
				continue;
			}

			$status = isset( $row['appointment_status'] ) ? sanitize_key( (string) $row['appointment_status'] ) : '';
			if ( ! in_array( $status, $allowed, true ) ) {
				continue;
			}

			$start_at = isset( $row['start_at'] ) ? sanitize_text_field( (string) $row['start_at'] ) : '';
			$start_ts = '' !== $start_at ? strtotime( $start_at ) : false;
			$priority = 'normal';

			if ( 'in_progress' === $status ) {
				$priority = 'critical';
			} elseif ( false !== $start_ts && ( $start_ts - $now_ts ) <= DAY_IN_SECONDS ) {
				$priority = 'warning';
			}

			$items[] = array(
				'type'      => 'appointment',
				'title'     => $this->build_appointment_title( $row, $appointment_id ),
				'url'       => $this->get_appointment_url( $appointment_id ),
				'date'      => $start_at,
				'priority'  => $priority,
				'source_id' => 'appointment:' . $appointment_id,
			);
		}

		return $items;
	}

	/**
	 * Sort items by date ascending with empty dates at the end.
	 *
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return array<int,array<string,mixed>>
	 */
	protected function sort_items_by_date( array $items ) {
		usort(
			$items,
			function ( $left, $right ) {
				$left_ts  = ! empty( $left['date'] ) ? strtotime( (string) $left['date'] ) : false;
				$right_ts = ! empty( $right['date'] ) ? strtotime( (string) $right['date'] ) : false;

				if ( false === $left_ts && false === $right_ts ) {
					return 0;
				}

				if ( false === $left_ts ) {
					return 1;
				}

				if ( false === $right_ts ) {
					return -1;
				}

				return $left_ts <=> $right_ts;
			}
		);

		return $items;
	}

	/**
	 * Build CRM task URL.
	 *
	 * @param int $opportunity_id Opportunity ID.
	 * @param int $task_id Task ID.
	 * @return string
	 */
	protected function get_crm_task_url( $opportunity_id, $task_id ) {
		return add_query_arg(
			array(
				'page'          => 'super-mechanic-crm-pipeline',
				'action'        => 'edit',
				'id'            => absint( $opportunity_id ),
				'task_action'   => 'edit_task',
				'task_id'       => absint( $task_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build CRM opportunity URL.
	 *
	 * @param int $opportunity_id Opportunity ID.
	 * @return string
	 */
	protected function get_crm_opportunity_url( $opportunity_id ) {
		return add_query_arg(
			array(
				'page'   => 'super-mechanic-crm-pipeline',
				'action' => 'edit',
				'id'     => absint( $opportunity_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build process URL.
	 *
	 * @param int $process_id Process ID.
	 * @return string
	 */
	protected function get_process_url( $process_id ) {
		return add_query_arg(
			array(
				'page'   => 'super-mechanic-processes',
				'action' => 'edit',
				'id'     => absint( $process_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build appointment URL.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return string
	 */
	protected function get_appointment_url( $appointment_id ) {
		return add_query_arg(
			array(
				'page'   => 'super-mechanic-appointments',
				'action' => 'edit',
				'id'     => absint( $appointment_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build appointment title.
	 *
	 * @param array<string,mixed> $row Appointment row.
	 * @param int                 $appointment_id Appointment ID.
	 * @return string
	 */
	protected function build_appointment_title( array $row, $appointment_id ) {
		$client_name = isset( $row['client_name'] ) ? trim( sanitize_text_field( (string) $row['client_name'] ) ) : '';
		$vehicle     = trim(
			implode(
				' ',
				array_filter(
					array(
						isset( $row['brand'] ) ? sanitize_text_field( (string) $row['brand'] ) : '',
						isset( $row['model'] ) ? sanitize_text_field( (string) $row['model'] ) : '',
					)
				)
			)
		);

		if ( '' !== $client_name && '' !== $vehicle ) {
			return sprintf(
				/* translators: 1: client name, 2: vehicle label. */
				__( 'Appointment: %1$s - %2$s', 'super-mechanic' ),
				$client_name,
				$vehicle
			);
		}

		if ( '' !== $client_name ) {
			return sprintf(
				/* translators: %s client name. */
				__( 'Appointment: %s', 'super-mechanic' ),
				$client_name
			);
		}

		return sprintf(
			/* translators: %d appointment ID. */
			__( 'Appointment #%d', 'super-mechanic' ),
			absint( $appointment_id )
		);
	}

	/**
	 * Map persisted alert type to workload priority.
	 *
	 * @param string $alert_type Alert type.
	 * @return string
	 */
	protected function resolve_operational_signal_level( array $signal ) {
		if ( ! empty( $signal['overdue_task_count'] ) ) {
			return 'critical';
		}

		if ( ! empty( $signal['requires_attention'] ) ) {
			return 'warning';
		}

		return 'none';
	}

	/**
	 * Map process status to workload priority.
	 *
	 * @param string $status Process status.
	 * @return string
	 */
	protected function map_process_priority( $status ) {
		$critical = array( 'overdue', 'waiting_approval' );
		$warning  = array( 'open', 'in_progress', 'pending' );

		if ( in_array( $status, $critical, true ) ) {
			return 'critical';
		}

		if ( in_array( $status, $warning, true ) ) {
			return 'warning';
		}

		return 'normal';
	}

	/**
	 * Humanize a key value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Empty workload payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_workload() {
		return array(
			'critical' => array(),
			'warning'  => array(),
			'normal'   => array(),
			'meta'     => array(
				'assigned_user_id' => 0,
				'business_id'      => 0,
				'generated_at'     => '',
			),
		);
	}

	/**
	 * Empty global summary payload.
	 *
	 * @return array<string,int>
	 */
	protected function get_empty_global_summary() {
		return array(
			'tasks_pending_total'        => 0,
			'tasks_overdue_total'        => 0,
			'alerts_active_total'        => 0,
			'processes_active_total'     => 0,
			'appointments_upcoming_total' => 0,
		);
	}

	/**
	 * Count persisted active alerts across all opportunities in active business.
	 *
	 * @return int
	 */
	protected function count_active_operational_signals_total() {
		$counts = $this->count_operational_signals_by_level();

		return absint( $counts['critical'] ) + absint( $counts['warning'] );
	}

	/**
	 * Count upcoming appointments for active business and bounded future window.
	 *
	 * @param int $business_id Business ID.
	 * @param int $days Days ahead.
	 * @return int
	 */
	protected function count_upcoming_appointments_total( $business_id, $days ) {
		$total    = 0;
		$page     = 1;
		$per_page = 200;
		$max_page = 100;
		$allowed  = array( 'scheduled', 'confirmed', 'in_progress' );
		$today    = wp_date( 'Y-m-d' );
		$range_end = wp_date( 'Y-m-d', strtotime( '+' . max( 1, absint( $days ) ) . ' days' ) );

		do {
			$rows = $this->appointment_service->get_appointments(
				array(
					'business_id' => absint( $business_id ),
					'date_from'   => $today,
					'date_to'     => $range_end,
					'per_page'    => $per_page,
					'page'        => $page,
					'orderby'     => 'start_at',
					'order'       => 'ASC',
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$status = isset( $row['appointment_status'] ) ? sanitize_key( (string) $row['appointment_status'] ) : '';
				if ( in_array( $status, $allowed, true ) ) {
					++$total;
				}
			}

			++$page;
		} while ( $page <= $max_page && count( $rows ) === $per_page );

		return $total;
	}

	/**
	 * Build tasks SLA metrics.
	 *
	 * @return array<string,mixed>
	 */
	protected function build_task_metrics() {
		$metrics         = array(
			'avg_resolution_time' => 0.0,
			'overdue_ratio'       => 0.0,
			'open_vs_closed'      => array(
				'open'   => 0,
				'closed' => 0,
			),
		);
		$task_buckets     = $this->task_service->get_operational_buckets( 7, 500 );
		$pending_count    = isset( $task_buckets['pending']['count'] ) ? absint( $task_buckets['pending']['count'] ) : 0;
		$overdue_count    = isset( $task_buckets['overdue']['count'] ) ? absint( $task_buckets['overdue']['count'] ) : 0;
		$completed_tasks  = $this->task_service->get_tasks_for_calendar(
			wp_date( 'Y-m-d', strtotime( '-365 days' ) ),
			wp_date( 'Y-m-d', strtotime( '+1 day' ) ),
			array( 'completed' ),
			1000
		);
		$closed_count     = is_array( $completed_tasks ) ? count( $completed_tasks ) : 0;
		$average_seconds  = $this->calculate_average_resolution_seconds( is_array( $completed_tasks ) ? $completed_tasks : array() );
		$total_reference  = $pending_count + $closed_count;

		$metrics['avg_resolution_time'] = round( (float) ( $average_seconds / HOUR_IN_SECONDS ), 2 );
		$metrics['overdue_ratio']       = $total_reference > 0 ? round( (float) ( $overdue_count / $total_reference ), 4 ) : 0.0;
		$metrics['open_vs_closed']      = array(
			'open'   => $pending_count,
			'closed' => $closed_count,
		);

		return $metrics;
	}

	/**
	 * Build process SLA metrics.
	 *
	 * @return array<string,mixed>
	 */
	protected function build_process_metrics() {
		$metrics   = array(
			'avg_duration'         => 0.0,
			'delayed'              => 0,
			'completed_vs_active'  => array(
				'completed' => 0,
				'active'    => 0,
			),
		);
		$page      = 1;
		$per_page  = 200;
		$max_page  = 100;
		$rows      = array();

		do {
			$batch = $this->process_service->get_processes(
				array(
					'per_page' => $per_page,
					'page'     => $page,
					'orderby'  => 'created_at',
					'order'    => 'DESC',
				)
			);

			if ( empty( $batch ) ) {
				break;
			}

			$rows = array_merge( $rows, $batch );
			++$page;
		} while ( $page <= $max_page && count( $batch ) === $per_page );

		$completed_statuses = array( 'completed', 'delivered', 'cancelled' );
		$completed_count    = 0;
		$active_count       = 0;
		$delayed_count      = 0;
		$duration_total     = 0;
		$duration_samples   = 0;
		$now_ts             = current_time( 'timestamp', true );

		foreach ( $rows as $row ) {
			$status     = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$created_ts = ! empty( $row['created_at'] ) ? strtotime( (string) $row['created_at'] ) : false;
			$updated_ts = ! empty( $row['updated_at'] ) ? strtotime( (string) $row['updated_at'] ) : false;
			$is_final   = in_array( $status, $completed_statuses, true );

			if ( $is_final ) {
				++$completed_count;
			} else {
				++$active_count;
				if ( false !== $created_ts && false !== $now_ts && ( $now_ts - $created_ts ) > ( 7 * DAY_IN_SECONDS ) ) {
					++$delayed_count;
				}
			}

			if ( $is_final && false !== $created_ts && false !== $updated_ts && $updated_ts > $created_ts ) {
				$duration_total += ( $updated_ts - $created_ts );
				++$duration_samples;
			}
		}

		$metrics['avg_duration'] = $duration_samples > 0 ? round( (float) ( $duration_total / $duration_samples / HOUR_IN_SECONDS ), 2 ) : 0.0;
		$metrics['delayed']      = $delayed_count;
		$metrics['completed_vs_active'] = array(
			'completed' => $completed_count,
			'active'    => $active_count,
		);

		return $metrics;
	}

	/**
	 * Build alert metrics from CRM Pipeline aligned signals.
	 *
	 * @return array<string,int>
	 */
	protected function build_alert_metrics() {
		return $this->count_operational_signals_by_level();
	}

	/**
	 * Build appointment SLA metrics.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	protected function build_appointment_metrics( $business_id ) {
		$completed_count = absint(
			$this->appointment_service->count_appointments(
				array(
					'business_id'        => $business_id,
					'appointment_status' => 'completed',
				)
			)
		);
		$scheduled_statuses = array( 'scheduled', 'confirmed', 'in_progress' );
		$scheduled_count    = 0;

		foreach ( $scheduled_statuses as $status ) {
			$scheduled_count += absint(
				$this->appointment_service->count_appointments(
					array(
						'business_id'        => $business_id,
						'appointment_status' => $status,
					)
				)
			);
		}

		$yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
		$overdue   = 0;
		foreach ( $scheduled_statuses as $status ) {
			$overdue += absint(
				$this->appointment_service->count_appointments(
					array(
						'business_id'        => $business_id,
						'appointment_status' => $status,
						'date_to'            => $yesterday,
					)
				)
			);
		}

		return array(
			'completed_vs_scheduled' => array(
				'completed' => $completed_count,
				'scheduled' => $scheduled_count,
			),
			'overdue'                => $overdue,
		);
	}

	/**
	 * Count operational signal levels from CRM Pipeline aligned source.
	 *
	 * @return array<string,int>
	 */
	protected function count_operational_signals_by_level() {
		$counts   = array(
			'critical' => 0,
			'warning'  => 0,
		);
		$page     = 1;
		$per_page = 200;
		$max_page = 100;

		do {
			$opportunities = $this->crm_pipeline_service->get_opportunities(
				array(
					'page'     => $page,
					'per_page' => $per_page,
					'orderby'  => 'updated_at',
					'order'    => 'DESC',
				)
			);

			if ( empty( $opportunities ) ) {
				break;
			}

			$signals = $this->crm_pipeline_service->get_automation_signals_for_opportunities( $opportunities );
			foreach ( $opportunities as $opportunity ) {
				$opportunity_id = isset( $opportunity['id'] ) ? absint( $opportunity['id'] ) : 0;
				if ( $opportunity_id <= 0 || empty( $signals[ $opportunity_id ] ) || ! is_array( $signals[ $opportunity_id ] ) ) {
					continue;
				}

				$level = $this->resolve_operational_signal_level( $signals[ $opportunity_id ] );
				if ( isset( $counts[ $level ] ) ) {
					++$counts[ $level ];
				}
			}

			++$page;
		} while ( $page <= $max_page && count( $opportunities ) === $per_page );

		return $counts;
	}

	/**
	 * Compute average completed task resolution time in seconds.
	 *
	 * @param array<int,array<string,mixed>> $tasks Completed task rows.
	 * @return int
	 */
	protected function calculate_average_resolution_seconds( array $tasks ) {
		$total_seconds = 0;
		$samples       = 0;

		foreach ( $tasks as $task ) {
			$created_ts = ! empty( $task['created_at'] ) ? strtotime( (string) $task['created_at'] ) : false;
			$updated_ts = ! empty( $task['updated_at'] ) ? strtotime( (string) $task['updated_at'] ) : false;

			if ( false === $created_ts || false === $updated_ts || $updated_ts <= $created_ts ) {
				continue;
			}

			$total_seconds += ( $updated_ts - $created_ts );
			++$samples;
		}

		if ( 0 === $samples ) {
			return 0;
		}

		return (int) floor( $total_seconds / $samples );
	}

	/**
	 * Empty operational metrics payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_metrics() {
		return array(
			'tasks'        => array(
				'avg_resolution_time' => 0.0,
				'overdue_ratio'       => 0.0,
				'open_vs_closed'      => array(
					'open'   => 0,
					'closed' => 0,
				),
			),
			'processes'    => array(
				'avg_duration'        => 0.0,
				'delayed'             => 0,
				'completed_vs_active' => array(
					'completed' => 0,
					'active'    => 0,
				),
			),
			'alerts'       => array(
				'critical' => 0,
				'warning'  => 0,
			),
			'appointments' => array(
				'completed_vs_scheduled' => array(
					'completed' => 0,
					'scheduled' => 0,
				),
				'overdue'                => 0,
			),
		);
	}

	/**
	 * Empty internal automation flags payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_automation_flags() {
		return array(
			'flags'   => array(),
			'summary' => array(
				'active_flags'   => 0,
				'critical_flags' => 0,
				'warning_flags'  => 0,
				'normal_flags'   => 0,
				'global_state'   => 'stable',
			),
			'meta'    => array(
				'business_id'    => 0,
				'user_id'        => 0,
				'generated_at'   => '',
				'signals_policy' => 'crm_pipeline_aligned',
			),
		);
	}

	/**
	 * Empty operational escalation payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_escalation_state() {
		return array(
			'global_level'            => 'normal',
			'blocking_flags'          => array(),
			'user_saturation'         => array(
				'user_id'         => 0,
				'is_saturated'    => false,
				'critical_load'   => 0,
				'threshold'       => 3,
				'active_flag'     => '',
				'suggested_level' => 'normal',
			),
			'critical_workload_count' => 0,
			'warning_workload_count'  => 0,
			'meta'                    => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'automation_flags_workload_summary_metrics',
			),
		);
	}

	/**
	 * Empty operational recommendations payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_recommendations() {
		return array(
			'recommendations' => array(),
			'summary'         => array(
				'total'    => 0,
				'critical' => 0,
				'warning'  => 0,
			),
			'meta'            => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'automation_escalation_workload_metrics',
			),
		);
	}

	/**
	 * Resolve candidate user IDs for operational assignment analysis.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,int>
	 */
	protected function get_operational_candidate_user_ids( $business_id ) {
		$users = get_users(
			array(
				'role__in' => array( 'sm_admin', 'sm_mechanic', 'administrator' ),
				'fields'   => array( 'ID' ),
				'number'   => 200,
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);
		$ids   = array();

		foreach ( $users as $user ) {
			$user_id = isset( $user->ID ) ? absint( $user->ID ) : 0;
			if ( $user_id <= 0 ) {
				continue;
			}

			$user_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $user_id, $business_id ) );
			if ( $user_business_id !== absint( $business_id ) ) {
				continue;
			}

			$ids[ $user_id ] = $user_id;
		}

		return array_values( $ids );
	}

	/**
	 * Extract candidate CRM task IDs from one workload payload.
	 *
	 * @param array<string,mixed> $workload Workload payload.
	 * @return array<int,int>
	 */
	protected function extract_reassignable_task_ids_from_workload( array $workload ) {
		$task_ids = array();
		$buckets  = array( 'critical', 'warning' );

		foreach ( $buckets as $bucket ) {
			$items = isset( $workload[ $bucket ] ) && is_array( $workload[ $bucket ] ) ? $workload[ $bucket ] : array();
			foreach ( $items as $item ) {
				$type      = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
				$source_id = isset( $item['source_id'] ) ? sanitize_text_field( (string) $item['source_id'] ) : '';
				if ( 'task' !== $type || 0 !== strpos( $source_id, 'task:' ) ) {
					continue;
				}

				$task_id = absint( substr( $source_id, 5 ) );
				if ( $task_id > 0 ) {
					$task_ids[ $task_id ] = $task_id;
				}
			}
		}

		return array_values( $task_ids );
	}

	/**
	 * Check if one reassignment request matches a currently executable proposal.
	 *
	 * @param int    $business_id Business ID.
	 * @param int    $from_user_id Source user ID.
	 * @param int    $to_user_id Destination user ID.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return bool
	 */
	protected function has_matching_assignment_proposal( $business_id, $from_user_id, $to_user_id, $entity_type, $entity_id ) {
		$payload     = $this->get_operational_assignments( $business_id );
		$assignments = isset( $payload['assignments'] ) && is_array( $payload['assignments'] ) ? $payload['assignments'] : array();

		foreach ( $assignments as $proposal ) {
			$is_executable = ! empty( $proposal['executable'] );
			if ( ! $is_executable ) {
				continue;
			}

			if (
				absint( isset( $proposal['from_user'] ) ? $proposal['from_user'] : 0 ) === absint( $from_user_id ) &&
				absint( isset( $proposal['to_user'] ) ? $proposal['to_user'] : 0 ) === absint( $to_user_id ) &&
				sanitize_key( isset( $proposal['entity_type'] ) ? (string) $proposal['entity_type'] : '' ) === sanitize_key( (string) $entity_type ) &&
				absint( isset( $proposal['entity_id'] ) ? $proposal['entity_id'] : 0 ) === absint( $entity_id )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract task IDs grouped by workload priority.
	 *
	 * @param array<string,mixed> $workload Workload payload.
	 * @return array<string,array<int,int>>
	 */
	protected function extract_task_ids_by_priority( array $workload ) {
		$grouped = array(
			'critical' => array(),
			'warning'  => array(),
		);

		foreach ( array( 'critical', 'warning' ) as $bucket ) {
			$items = isset( $workload[ $bucket ] ) && is_array( $workload[ $bucket ] ) ? $workload[ $bucket ] : array();
			foreach ( $items as $item ) {
				$type      = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
				$source_id = isset( $item['source_id'] ) ? sanitize_text_field( (string) $item['source_id'] ) : '';
				if ( 'task' !== $type || 0 !== strpos( $source_id, 'task:' ) ) {
					continue;
				}

				$task_id = absint( substr( $source_id, 5 ) );
				if ( $task_id > 0 ) {
					$grouped[ $bucket ][ $task_id ] = $task_id;
				}
			}
		}

		return array(
			'critical' => array_values( $grouped['critical'] ),
			'warning'  => array_values( $grouped['warning'] ),
		);
	}

	/**
	 * Extract overdue task IDs from workload items.
	 *
	 * @param array<string,mixed> $workload Workload payload.
	 * @return array<int,int>
	 */
	protected function extract_overdue_task_ids_from_workload( array $workload ) {
		$overdue = array();
		$now_ts  = current_time( 'timestamp', true );

		foreach ( array( 'critical', 'warning' ) as $bucket ) {
			$items = isset( $workload[ $bucket ] ) && is_array( $workload[ $bucket ] ) ? $workload[ $bucket ] : array();
			foreach ( $items as $item ) {
				$type      = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
				$source_id = isset( $item['source_id'] ) ? sanitize_text_field( (string) $item['source_id'] ) : '';
				$date      = isset( $item['date'] ) ? sanitize_text_field( (string) $item['date'] ) : '';
				if ( 'task' !== $type || 0 !== strpos( $source_id, 'task:' ) ) {
					continue;
				}

				$date_ts = '' !== $date ? strtotime( $date ) : false;
				if ( false === $date_ts || $date_ts >= $now_ts ) {
					continue;
				}

				$task_id = absint( substr( $source_id, 5 ) );
				if ( $task_id > 0 ) {
					$overdue[ $task_id ] = $task_id;
				}
			}
		}

		return array_values( $overdue );
	}

	/**
	 * Get global overdue CRM task IDs for active business context.
	 *
	 * @param int $max_scan Max rows to scan.
	 * @return array<int,int>
	 */
	protected function get_global_overdue_task_ids( $max_scan = 250 ) {
		$max_scan = max( 1, absint( $max_scan ) );
		$buckets  = $this->task_service->get_operational_buckets( 14, $max_scan );
		$rows     = isset( $buckets['overdue']['items'] ) && is_array( $buckets['overdue']['items'] ) ? $buckets['overdue']['items'] : array();
		$ids      = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$task_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $task_id > 0 ) {
				$ids[ $task_id ] = $task_id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Resolve target user for bulk reassignment from assignment proposals.
	 *
	 * @param array<string,mixed> $assignments Assignments payload.
	 * @param int                 $fallback_user_id Fallback user ID.
	 * @return int
	 */
	protected function resolve_bulk_reassign_target_user( array $assignments, $fallback_user_id ) {
		$rows = isset( $assignments['assignments'] ) && is_array( $assignments['assignments'] ) ? $assignments['assignments'] : array();
		foreach ( $rows as $row ) {
			if ( empty( $row['executable'] ) ) {
				continue;
			}
			$to_user = isset( $row['to_user'] ) ? absint( $row['to_user'] ) : 0;
			if ( $to_user > 0 ) {
				return $to_user;
			}
		}

		return absint( $fallback_user_id );
	}

	/**
	 * Sanitize bulk action IDs with hard safety cap.
	 *
	 * @param mixed $ids Raw IDs payload.
	 * @param int   $max_items Max allowed IDs.
	 * @return array<int,int>
	 */
	protected function sanitize_bulk_ids( $ids, $max_items ) {
		$max_items = max( 1, absint( $max_items ) );
		if ( is_string( $ids ) ) {
			$ids = array_filter( array_map( 'trim', explode( ',', $ids ) ) );
		}

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$clean = array();
		foreach ( $ids as $candidate ) {
			$id = absint( $candidate );
			if ( $id > 0 ) {
				$clean[ $id ] = $id;
			}
			if ( count( $clean ) >= $max_items ) {
				break;
			}
		}

		return array_values( $clean );
	}

	/**
	 * Check if current request explicitly comes from controlled execution layer.
	 *
	 * @return bool
	 */
	protected function is_controlled_execution_source() {
		$source = isset( $_POST['sm_execution_source'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_execution_source'] ) ) : '';
		return 'controlled' === $source;
	}

	/**
	 * Resolve execution mode for execution log row.
	 *
	 * @param array<string,mixed> $execution_context Optional execution context.
	 * @return string
	 */
	protected function resolve_execution_log_mode( array $execution_context = array() ) {
		$mode = isset( $execution_context['execution_mode'] ) ? sanitize_key( (string) $execution_context['execution_mode'] ) : '';
		if ( in_array( $mode, array( 'manual', 'confirmable', 'auto' ), true ) ) {
			return $mode;
		}

		if ( isset( $_POST['sm_execution_mode'] ) ) {
			$post_mode = sanitize_key( (string) wp_unslash( $_POST['sm_execution_mode'] ) );
			if ( in_array( $post_mode, array( 'manual', 'confirmable', 'auto' ), true ) ) {
				return $post_mode;
			}
		}

		if ( $this->is_controlled_execution_source() ) {
			return 'confirmable';
		}

		return 'manual';
	}

	/**
	 * Register one execution log row for bulk actions.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $action Action key.
	 * @param array<string,mixed> $response Execution response.
	 * @param array<string,mixed> $execution_context Execution context.
	 * @return void
	 */
	protected function register_execution_log( $business_id, $action, array $response, array $execution_context = array() ) {
		$business_id = absint( $business_id );
		$action      = sanitize_key( (string) $action );
		$status      = isset( $response['status'] ) ? sanitize_key( (string) $response['status'] ) : 'unknown';
		$actor_id    = get_current_user_id();
		if ( $business_id <= 0 || '' === $action || $actor_id <= 0 ) {
			return;
		}

		$rule_key       = isset( $execution_context['rule_key'] ) ? sanitize_key( (string) $execution_context['rule_key'] ) : '';
		if ( '' === $rule_key && isset( $_POST['sm_rule_key'] ) ) {
			$rule_key = sanitize_key( (string) wp_unslash( $_POST['sm_rule_key'] ) );
		}
		$execution_mode = $this->resolve_execution_log_mode( $execution_context );
		$affected_count = isset( $response['success_count'] ) ? absint( $response['success_count'] ) : 0;
		$context        = array(
			'entity_type'    => isset( $response['entity_type'] ) ? sanitize_key( (string) $response['entity_type'] ) : 'crm_task',
			'total'          => isset( $response['total'] ) ? absint( $response['total'] ) : 0,
			'failed_count'   => isset( $response['failed_count'] ) ? absint( $response['failed_count'] ) : 0,
			'target_user_id' => isset( $response['target_user_id'] ) ? absint( $response['target_user_id'] ) : 0,
			'source'         => isset( $execution_context['source'] ) ? sanitize_key( (string) $execution_context['source'] ) : ( $this->is_controlled_execution_source() ? 'controlled' : 'dashboard' ),
		);

		$this->execution_log_service->register_execution(
			$business_id,
			$rule_key,
			$action,
			$execution_mode,
			$status,
			$affected_count,
			$actor_id,
			$context
		);
	}

	/**
	 * Build per-user snapshot meta key for controlled execution.
	 *
	 * @param int $business_id Business ID.
	 * @return string
	 */
	protected function get_controlled_execution_snapshot_meta_key( $business_id ) {
		return 'sm_controlled_execution_snapshot_' . absint( $business_id );
	}

	/**
	 * Store one controlled execution snapshot for rollback.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $execution_result Execution result payload.
	 * @return string Snapshot key.
	 */
	protected function store_controlled_execution_snapshot( $business_id, array $execution_result ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}

		$rollback = isset( $execution_result['rollback'] ) && is_array( $execution_result['rollback'] ) ? $execution_result['rollback'] : array();
		$items    = isset( $rollback['items'] ) && is_array( $rollback['items'] ) ? $rollback['items'] : array();
		if ( empty( $items ) ) {
			return '';
		}

		$snapshot_key = wp_generate_uuid4();
		$snapshot     = array(
			'snapshot_key'      => $snapshot_key,
			'execution_source'  => 'controlled',
			'business_id'       => absint( $business_id ),
			'action_type'       => isset( $rollback['action_type'] ) ? sanitize_key( (string) $rollback['action_type'] ) : '',
			'entity_type'       => isset( $execution_result['entity_type'] ) ? sanitize_key( (string) $execution_result['entity_type'] ) : '',
			'supported'         => ! empty( $rollback['supported'] ),
			'available'         => ! empty( $rollback['available'] ),
			'items'             => $items,
			'result'            => isset( $execution_result['status'] ) ? sanitize_key( (string) $execution_result['status'] ) : 'failed',
			'success_count'     => isset( $execution_result['success_count'] ) ? absint( $execution_result['success_count'] ) : 0,
			'failed_count'      => isset( $execution_result['failed_count'] ) ? absint( $execution_result['failed_count'] ) : 0,
			'rule_key'          => isset( $execution_result['rule_key'] ) ? sanitize_key( (string) $execution_result['rule_key'] ) : '',
			'execution_mode'    => $this->resolve_execution_log_mode(),
			'created_at'        => current_time( 'mysql' ),
		);
		update_user_meta( $user_id, $this->get_controlled_execution_snapshot_meta_key( $business_id ), $snapshot );

		return $snapshot_key;
	}

	/**
	 * Retrieve latest controlled execution snapshot.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	protected function get_controlled_execution_snapshot( $business_id ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return array();
		}

		$snapshot = get_user_meta( $user_id, $this->get_controlled_execution_snapshot_meta_key( $business_id ), true );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * Update controlled execution snapshot.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $snapshot Snapshot payload.
	 * @return void
	 */
	protected function update_controlled_execution_snapshot( $business_id, array $snapshot ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, $this->get_controlled_execution_snapshot_meta_key( $business_id ), $snapshot );
	}

	/**
	 * Prioritize operational recommendations with explicit scoring.
	 *
	 * Scoring model:
	 * - severity: critical > warning > normal
	 * - impact: numeric signals in message/context
	 * - urgency: overdue/delay/critical/upcoming keywords
	 * - readiness: recommendations are informative (no readiness bonus by default)
	 *
	 * @param array<int,array<string,mixed>> $rows Recommendation rows.
	 * @return array<int,array<string,mixed>>
	 */
	protected function prioritize_operational_recommendations( array $rows ) {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key_text = implode(
				' ',
				array(
					isset( $row['key'] ) ? (string) $row['key'] : '',
					isset( $row['title'] ) ? (string) $row['title'] : '',
					isset( $row['message'] ) ? (string) $row['message'] : '',
					isset( $row['action_hint'] ) ? (string) $row['action_hint'] : '',
				)
			);
			$level   = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : 'normal';
			$impact  = $this->extract_numeric_impact_from_text( $key_text );
			$urgency = $this->resolve_urgency_points_from_text( $key_text );
			$score   = $this->build_operational_priority_score( $level, $impact, $urgency, false );

			$rows[ $index ]['_priority_score'] = $score;
		}

		return $this->sort_operational_items_by_priority( $rows );
	}

	/**
	 * Prioritize assisted actions with explicit scoring.
	 *
	 * @param array<int,array<string,mixed>> $rows Action rows.
	 * @return array<int,array<string,mixed>>
	 */
	protected function prioritize_operational_assisted_actions( array $rows ) {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$text   = implode(
				' ',
				array(
					isset( $row['key'] ) ? (string) $row['key'] : '',
					isset( $row['label'] ) ? (string) $row['label'] : '',
					isset( $row['context'] ) ? (string) $row['context'] : '',
				)
			);
			$level  = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : 'normal';
			$impact = $this->extract_numeric_impact_from_text( $text );
			$urgency = $this->resolve_urgency_points_from_text( $text );
			$ready   = ! empty( $row['url'] );
			$score   = $this->build_operational_priority_score( $level, $impact, $urgency, $ready );

			$rows[ $index ]['_priority_score'] = $score;
		}

		return $this->sort_operational_items_by_priority( $rows );
	}

	/**
	 * Prioritize assignment proposals with explicit scoring.
	 *
	 * @param array<int,array<string,mixed>> $rows Assignment proposals.
	 * @return array<int,array<string,mixed>>
	 */
	protected function prioritize_operational_assignments( array $rows ) {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$level   = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : 'normal';
			$impact  = isset( $row['workload_delta'] ) ? absint( $row['workload_delta'] ) : 0;
			$urgency = $this->resolve_urgency_points_from_text( isset( $row['reason'] ) ? (string) $row['reason'] : '' );
			$ready   = ! empty( $row['executable'] );
			$score   = $this->build_operational_priority_score( $level, $impact, $urgency, $ready );

			$rows[ $index ]['_priority_score'] = $score;
		}

		return $this->sort_operational_items_by_priority( $rows );
	}

	/**
	 * Prioritize bulk groups with explicit scoring.
	 *
	 * @param array<int,array<string,mixed>> $rows Bulk groups.
	 * @return array<int,array<string,mixed>>
	 */
	protected function prioritize_operational_bulk_groups( array $rows ) {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$text   = implode(
				' ',
				array(
					isset( $row['group_key'] ) ? (string) $row['group_key'] : '',
					isset( $row['action'] ) ? (string) $row['action'] : '',
				)
			);
			$level  = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : 'normal';
			$impact = isset( $row['count'] ) ? absint( $row['count'] ) : 0;
			$urgency = $this->resolve_urgency_points_from_text( $text );
			$ready   = ! empty( $row['executable'] );
			$score   = $this->build_operational_priority_score( $level, $impact, $urgency, $ready );

			$rows[ $index ]['_priority_score'] = $score;
		}

		return $this->sort_operational_items_by_priority( $rows );
	}

	/**
	 * Build one explicit operational priority score.
	 *
	 * @param string $level Severity level.
	 * @param int    $impact Impact value.
	 * @param int    $urgency Urgency points.
	 * @param bool   $executable Executable/readiness flag.
	 * @return int
	 */
	protected function build_operational_priority_score( $level, $impact, $urgency, $executable ) {
		$severity_points = $this->resolve_severity_points( $level );
		$impact_points   = min( 80, max( 0, absint( $impact ) * 5 ) );
		$urgency_points  = min( 60, max( 0, absint( $urgency ) ) );
		$readiness_bonus = $executable ? 40 : 0;

		return $severity_points + $impact_points + $urgency_points + $readiness_bonus;
	}

	/**
	 * Convert severity level to priority points.
	 *
	 * @param string $level Severity level.
	 * @return int
	 */
	protected function resolve_severity_points( $level ) {
		$level = sanitize_key( (string) $level );
		if ( 'critical' === $level ) {
			return 300;
		}
		if ( 'warning' === $level ) {
			return 200;
		}

		return 100;
	}

	/**
	 * Resolve urgency points from text keywords.
	 *
	 * @param string $text Source text.
	 * @return int
	 */
	protected function resolve_urgency_points_from_text( $text ) {
		$text  = strtolower( sanitize_text_field( (string) $text ) );
		$score = 0;

		if ( false !== strpos( $text, 'overdue' ) || false !== strpos( $text, 'vencid' ) ) {
			$score += 30;
		}
		if ( false !== strpos( $text, 'delay' ) || false !== strpos( $text, 'delayed' ) || false !== strpos( $text, 'retras' ) ) {
			$score += 22;
		}
		if ( false !== strpos( $text, 'critical' ) || false !== strpos( $text, 'critic' ) || false !== strpos( $text, 'blocking' ) ) {
			$score += 18;
		}
		if ( false !== strpos( $text, 'upcoming' ) || false !== strpos( $text, 'next' ) || false !== strpos( $text, 'appoint' ) ) {
			$score += 10;
		}

		return $score;
	}

	/**
	 * Extract impact signal from first numeric token in text.
	 *
	 * @param string $text Source text.
	 * @return int
	 */
	protected function extract_numeric_impact_from_text( $text ) {
		$text = sanitize_text_field( (string) $text );
		if ( preg_match( '/\b(\d{1,4})\b/', $text, $matches ) ) {
			return absint( $matches[1] );
		}

		return 0;
	}

	/**
	 * Sort items by explicit priority score descending.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows to sort.
	 * @return array<int,array<string,mixed>>
	 */
	protected function sort_operational_items_by_priority( array $rows ) {
		usort(
			$rows,
			function ( $left, $right ) {
				$left_score  = isset( $left['_priority_score'] ) ? intval( $left['_priority_score'] ) : 0;
				$right_score = isset( $right['_priority_score'] ) ? intval( $right['_priority_score'] ) : 0;
				if ( $right_score !== $left_score ) {
					return $right_score <=> $left_score;
				}

				$left_key  = isset( $left['key'] ) ? sanitize_key( (string) $left['key'] ) : '';
				$right_key = isset( $right['key'] ) ? sanitize_key( (string) $right['key'] ) : '';
				if ( '' !== $left_key || '' !== $right_key ) {
					return strcmp( $left_key, $right_key );
				}

				$left_title  = isset( $left['title'] ) ? sanitize_text_field( (string) $left['title'] ) : '';
				$right_title = isset( $right['title'] ) ? sanitize_text_field( (string) $right['title'] ) : '';

				return strcmp( $left_title, $right_title );
			}
		);

		return array_values( $rows );
	}

	/**
	 * Empty operational assignments payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_assignments() {
		return array(
			'overloaded_users' => array(),
			'available_users'  => array(),
			'assignments'      => array(),
			'summary'          => array(
				'overloaded_users' => 0,
				'available_users'  => 0,
				'proposals'        => 0,
				'executable_task_candidates' => 0,
			),
			'meta'             => array(
				'business_id'  => 0,
				'generated_at' => '',
				'mutations'    => 'none',
			),
		);
	}

	/**
	 * Empty automation console payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_automation_console() {
		return array(
			'system_status'   => array(
				'global_level'   => 'normal',
				'active_flags'   => 0,
				'blocking_flags' => 0,
			),
			'flags'           => $this->get_empty_operational_automation_flags(),
			'escalation'      => $this->get_empty_operational_escalation_state(),
			'recommendations' => $this->get_empty_operational_recommendations(),
			'assignments'     => $this->get_empty_operational_assignments(),
			'meta'            => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'flags_escalation_recommendations_assignments',
			),
		);
	}

	/**
	 * Build a safe admin URL to navigate assisted actions.
	 *
	 * @param string               $page_slug Page slug.
	 * @param array<string,mixed>  $args Extra args.
	 * @return string
	 */
	protected function build_admin_page_url( $page_slug, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => sanitize_key( $page_slug ),
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Empty assisted actions payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_assisted_actions() {
		return array(
			'actions' => array(),
			'summary' => array(
				'total'    => 0,
				'critical' => 0,
				'warning'  => 0,
			),
			'meta'    => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'mutations'    => 'none',
			),
		);
	}

	/**
	 * Empty operational bulk actions payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_operational_bulk_actions() {
		return array(
			'groups'  => array(),
			'summary' => array(
				'total_groups'      => 0,
				'executable_groups' => 0,
			),
			'meta'    => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'assisted_actions_assignments_console',
			),
		);
	}

	/**
	 * Empty guided actions payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_guided_rule_actions() {
		return array(
			'guided_actions' => array(),
			'summary'        => array(
				'total'          => 0,
				'executable'     => 0,
				'non_executable' => 0,
			),
			'meta'           => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'rules_bulk_assisted_console',
				'mutations'    => 'none',
			),
		);
	}

	/**
	 * Empty confirmable actions payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_confirmable_rule_actions() {
		return array(
			'confirmable_actions' => array(),
			'summary'             => array(
				'total'          => 0,
				'confirmable'    => 0,
				'non_executable' => 0,
			),
			'meta'                => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'source'       => 'rules_guided_bulk_assignments_console',
				'mutations'    => 'manual_confirmation_required',
			),
		);
	}

	/**
	 * Empty controlled auto-execution payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_controlled_auto_execution_payload() {
		return array(
			'auto_execution' => array(),
			'summary'        => array(
				'eligible_rules' => 0,
				'executed_rules' => 0,
				'blocked_rules'  => 0,
			),
			'meta'           => array(
				'business_id'  => 0,
				'user_id'      => 0,
				'generated_at' => '',
				'safe_limit'   => 25,
				'mode'         => 'preview',
			),
		);
	}

	/**
	 * Empty execution safety payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_empty_execution_safety_payload() {
		return array(
			'execution_guard' => array(
				'allowed'    => false,
				'risk_level' => 'medium',
				'reason'     => __( 'No guardrail context available.', 'super-mechanic' ),
			),
			'rollback'       => array(
				'supported'    => false,
				'action_type'  => '',
				'items'        => array(),
				'available'    => false,
				'snapshot_key' => '',
				'result'       => '',
			),
		);
	}
}
