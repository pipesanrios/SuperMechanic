<?php
/**
 * Workload service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Automation\Operational_Rules_Service;
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
	 * Constructor.
	 *
	 * @param Crm_Task_Service|null        $task_service CRM task service.
	 * @param Crm_Pipeline_Service|null    $crm_pipeline_service CRM pipeline service.
	 * @param Process_Service|null         $process_service Process service.
	 * @param Appointment_Service|null     $appointment_service Appointment service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Crm_Task_Service $task_service = null, Crm_Pipeline_Service $crm_pipeline_service = null, Process_Service $process_service = null, Appointment_Service $appointment_service = null, Business_Context_Service $business_context_service = null ) {
		$this->task_service             = $task_service ? $task_service : new Crm_Task_Service();
		$this->crm_pipeline_service     = $crm_pipeline_service ? $crm_pipeline_service : new Crm_Pipeline_Service();
		$this->process_service          = $process_service ? $process_service : new Process_Service();
		$this->appointment_service      = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get user workload grouped by priority.
	 *
	 * @param int                 $assigned_user_id User ID.
	 * @param array<string,mixed> $args Optional filters.
	 * @return array<string,mixed>
	 */
	public function get_user_workload( $assigned_user_id, array $args = array() ) {
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

		return $workload;
	}

	/**
	 * Get global operational summary for active business context.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,int>
	 */
	public function get_global_operational_summary( $business_id ) {
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

		return $summary;
	}

	/**
	 * Get operational SLA metrics for active business context.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_metrics( $business_id ) {
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

		return $metrics;
	}

	/**
	 * Get internal automation flags from existing operational signals.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID for user-scoped saturation flag.
	 * @return array<string,mixed>
	 */
	public function get_operational_automation_flags( $business_id, $user_id = null ) {
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

		$flags = array(
			array(
				'code'      => 'overdue_open_tasks',
				'active'    => $tasks_overdue_total > 0,
				'level'     => $tasks_overdue_total > 0 ? 'critical' : 'normal',
				'message'   => __( 'There are overdue CRM tasks still open.', 'super-mechanic' ),
				'value'     => $tasks_overdue_total,
				'threshold' => 1,
			),
			array(
				'code'      => 'delayed_active_processes',
				'active'    => $processes_delayed > 0,
				'level'     => $processes_delayed > 0 ? 'warning' : 'normal',
				'message'   => __( 'Active processes with operational delay detected.', 'super-mechanic' ),
				'value'     => $processes_delayed,
				'threshold' => 1,
			),
			array(
				'code'      => 'user_critical_saturation',
				'active'    => $user_critical_load >= 3,
				'level'     => $user_critical_load >= 3 ? 'warning' : 'normal',
				'message'   => __( 'User operational saturation by critical workload.', 'super-mechanic' ),
				'value'     => $user_critical_load,
				'threshold' => 3,
			),
			array(
				'code'      => 'global_critical_escalation',
				'active'    => $alerts_critical >= 2,
				'level'     => $alerts_critical >= 2 ? 'critical' : 'normal',
				'message'   => __( 'Multiple critical business signals require elevated operational state.', 'super-mechanic' ),
				'value'     => $alerts_critical,
				'threshold' => 2,
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

		return $payload;
	}

	/**
	 * Build operational escalation state from existing aggregated signals.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_escalation_state( $business_id, $user_id = null ) {
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
			'threshold'       => 3,
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

		if ( $has_critical_flag || $payload['critical_workload_count'] > 0 || ( isset( $summary['tasks_overdue_total'] ) && absint( $summary['tasks_overdue_total'] ) > 0 ) ) {
			$payload['global_level'] = 'critical';
		} elseif ( $has_warning_flag || $payload['warning_workload_count'] > 0 || ( isset( $metrics['processes']['delayed'] ) && absint( $metrics['processes']['delayed'] ) > 0 ) ) {
			$payload['global_level'] = 'warning';
		}

		$payload['meta'] = array(
			'business_id'    => $target_business_id,
			'user_id'        => $target_user_id,
			'generated_at'   => current_time( 'mysql' ),
			'source'         => 'automation_flags_workload_summary_metrics',
		);

		return $payload;
	}

	/**
	 * Build intelligent operational recommendations from existing aggregates.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_recommendations( $business_id, $user_id = null ) {
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

		$tasks_overdue = isset( $global_summary['tasks_overdue_total'] ) ? absint( $global_summary['tasks_overdue_total'] ) : 0;
		if ( $tasks_overdue > 0 ) {
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
		if ( $processes_delayed > 0 ) {
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
		if ( $critical_flags >= 2 || ( isset( $escalation['global_level'] ) && 'critical' === $escalation['global_level'] ) ) {
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
		$payload['summary']['total'] = count( $recommendations );
		foreach ( $recommendations as $recommendation ) {
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

		return $payload;
	}

	/**
	 * Build operational assignment suggestions without mutating real assignments.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_assignments( $business_id ) {
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
			$is_saturated = ! empty( $escalation['user_saturation']['is_saturated'] ) || $critical >= 3 || $total >= 10;

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

			if ( isset( $global_metrics['processes']['delayed'] ) && absint( $global_metrics['processes']['delayed'] ) > 0 && 'warning' === $level ) {
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
		$payload['assignments']      = $assignments;
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

		return $payload;
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

		return $payload;
	}

	/**
	 * Build assisted operational actions as safe navigation targets.
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_assisted_actions( $business_id, $user_id = null ) {
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
			'url'     => $this->build_admin_page_url( 'super-mechanic', array( 'workload_user_id' => $target_user_id ) ),
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
			} elseif ( in_array( $key, array( 'immediate_critical_intervention', 'redistribute_user_load' ), true ) ) {
				$url = $this->build_admin_page_url( 'super-mechanic', array( 'workload_user_id' => $target_user_id ) );
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

		$payload['actions'] = array_values( $unique );
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

		return $payload;
	}

	/**
	 * Build grouped operational bulk actions (read-only).
	 *
	 * @param int      $business_id Business ID.
	 * @param int|null $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_bulk_actions( $business_id, $user_id = null ) {
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
		$overdue_ids       = $this->extract_overdue_task_ids_from_workload( $workload );
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

		$payload['groups'] = $groups;
		$payload['summary'] = array(
			'total_groups'      => count( $groups ),
			'executable_groups' => count(
				array_filter(
					$groups,
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

		return $payload;
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
	public function execute_operational_bulk_action( $business_id, $action, $entity_type, $ids, $target_user_id = null ) {
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
			}
		}

		$status = empty( $failed_ids ) ? 'success' : ( empty( $success_ids ) ? 'failed' : 'partial' );

		return array(
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
		);
	}

	/**
	 * Get read-only operational rules overview.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_operational_rules_overview( $business_id ) {
		$rules_service = new Operational_Rules_Service( $this );

		return $rules_service->evaluate_operational_rules( $business_id );
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
}
