<?php
/**
 * Workload service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Appointments\Appointment_Service;
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
}
