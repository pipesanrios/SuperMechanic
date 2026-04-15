<?php
/**
 * CRM alerts service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM alerts recalculation and persistence.
 */
class Crm_Alert_Service {
	/**
	 * Alert types.
	 *
	 * @var array<int,string>
	 */
	const ALERT_TYPES = array(
		'overdue_task',
		'inactive_opportunity',
		'follow_up_needed',
		'conversion_pending',
	);

	/**
	 * Max pipelines to process per scheduler tick.
	 *
	 * @var int
	 */
	const MAX_PIPELINES_PER_TICK = 300;

	/**
	 * Batch size for pipeline recalculation.
	 *
	 * @var int
	 */
	const PIPELINE_BATCH_SIZE = 100;

	/**
	 * Max businesses to process per scheduler tick.
	 *
	 * @var int
	 */
	const MAX_BUSINESSES_PER_TICK = 50;

	/**
	 * Pipeline repository.
	 *
	 * @var Crm_Pipeline_Repository
	 */
	protected $pipeline_repository;

	/**
	 * Task repository.
	 *
	 * @var Crm_Task_Repository
	 */
	protected $task_repository;

	/**
	 * Alert repository.
	 *
	 * @var Crm_Alert_Repository
	 */
	protected $alert_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->pipeline_repository = new Crm_Pipeline_Repository();
		$this->task_repository     = new Crm_Task_Repository();
		$this->alert_repository    = new Crm_Alert_Repository();
	}

	/**
	 * Get active alerts grouped by pipeline IDs for active business.
	 *
	 * @param array<int,int> $pipeline_ids Pipeline IDs.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public function get_active_alerts_by_pipeline_ids( array $pipeline_ids ) {
		return $this->alert_repository->get_active_alerts_by_pipeline_ids( $pipeline_ids );
	}

	/**
	 * Resolve active alerts for one pipeline in active business.
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return bool
	 */
	public function resolve_active_alerts_by_pipeline_id( $pipeline_id ) {
		$pipeline_id = absint( $pipeline_id );
		if ( $pipeline_id <= 0 ) {
			return false;
		}

		return $this->alert_repository->resolve_active_alerts_by_pipeline_id( $pipeline_id );
	}

	/**
	 * Recalculate alerts with controlled batch limits.
	 *
	 * @return array<string,int>
	 */
	public function recalculate_alerts_for_scheduler() {
		$summary = array(
			'processed_pipelines' => 0,
			'created'             => 0,
			'updated'             => 0,
			'resolved'            => 0,
		);

		$business_ids = $this->pipeline_repository->get_business_ids_with_opportunities( self::MAX_BUSINESSES_PER_TICK );
		if ( empty( $business_ids ) ) {
			return $summary;
		}

		foreach ( $business_ids as $business_id ) {
			if ( $summary['processed_pipelines'] >= self::MAX_PIPELINES_PER_TICK ) {
				break;
			}

			$offset = 0;
			while ( $summary['processed_pipelines'] < self::MAX_PIPELINES_PER_TICK ) {
				$remaining = self::MAX_PIPELINES_PER_TICK - $summary['processed_pipelines'];
				$limit     = min( self::PIPELINE_BATCH_SIZE, $remaining );
				$ids       = $this->pipeline_repository->get_pipeline_ids_by_business( $business_id, $limit, $offset );

				if ( empty( $ids ) ) {
					break;
				}

				$offset += count( $ids );
				$rows    = $this->pipeline_repository->get_rows_by_ids_and_business( $business_id, $ids );
				$batch   = $this->recalculate_batch( $business_id, $rows );

				$summary['processed_pipelines'] += count( $ids );
				$summary['created']             += $batch['created'];
				$summary['updated']             += $batch['updated'];
				$summary['resolved']            += $batch['resolved'];
			}
		}

		return $summary;
	}

	/**
	 * Recalculate alerts for one business/pipeline batch.
	 *
	 * @param int                           $business_id Business ID.
	 * @param array<int,array<string,mixed>> $rows Opportunity rows.
	 * @return array<string,int>
	 */
	protected function recalculate_batch( $business_id, array $rows ) {
		$result = array(
			'created'  => 0,
			'updated'  => 0,
			'resolved' => 0,
		);

		if ( empty( $rows ) ) {
			return $result;
		}

		$pipeline_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $row ) {
							return isset( $row['id'] ) ? absint( $row['id'] ) : 0;
						},
						$rows
					)
				)
			)
		);
		if ( empty( $pipeline_ids ) ) {
			return $result;
		}

		$desired_by_pipeline = $this->build_desired_alert_types_by_pipeline( $business_id, $rows, $pipeline_ids );
		$active_map          = $this->alert_repository->get_active_alerts_map_by_pipeline_ids( $business_id, $pipeline_ids );

		foreach ( $pipeline_ids as $pipeline_id ) {
			$desired_types = isset( $desired_by_pipeline[ $pipeline_id ] ) ? $desired_by_pipeline[ $pipeline_id ] : array();
			$active_types  = isset( $active_map[ $pipeline_id ] ) ? $active_map[ $pipeline_id ] : array();

			foreach ( $desired_types as $alert_type ) {
				$message = $this->get_alert_message_by_type( $alert_type );

				if ( isset( $active_types[ $alert_type ] ) ) {
					$updated = $this->alert_repository->update_active_alert_message_if_changed(
						(int) $active_types[ $alert_type ]['id'],
						$message
					);
					if ( $updated ) {
						++$result['updated'];
					}
					continue;
				}

				$created_id = $this->alert_repository->create_active_alert( $business_id, $pipeline_id, $alert_type, $message );
				if ( false !== $created_id ) {
					++$result['created'];
				}
			}

			foreach ( $active_types as $active_type => $active_row ) {
				if ( in_array( $active_type, $desired_types, true ) ) {
					continue;
				}

				if ( $this->alert_repository->resolve_alert_by_id( (int) $active_row['id'] ) ) {
					++$result['resolved'];
				}
			}
		}

		return $result;
	}

	/**
	 * Build desired alert types by pipeline from runtime signals.
	 *
	 * @param int                           $business_id Business ID.
	 * @param array<int,array<string,mixed>> $rows Opportunity rows.
	 * @param array<int,int>                $pipeline_ids Pipeline IDs.
	 * @return array<int,array<int,string>>
	 */
	protected function build_desired_alert_types_by_pipeline( $business_id, array $rows, array $pipeline_ids ) {
		$desired_map        = array();
		$now_mysql          = current_time( 'mysql' );
		$now_ts             = strtotime( $now_mysql );
		$pending_count_map  = $this->task_repository->count_pending_by_pipeline_ids_for_business( $business_id, $pipeline_ids );
		$overdue_count_map  = $this->task_repository->count_overdue_by_pipeline_ids_for_business( $business_id, $pipeline_ids, $now_mysql );
		$task_activity_map  = $this->task_repository->get_last_activity_by_pipeline_ids_for_business( $business_id, $pipeline_ids );

		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}

			$stage         = isset( $row['stage'] ) ? sanitize_key( (string) $row['stage'] ) : '';
			$has_process   = ! empty( $row['process_id'] );
			$pending_count = isset( $pending_count_map[ $id ] ) ? absint( $pending_count_map[ $id ] ) : 0;
			$overdue_count = isset( $overdue_count_map[ $id ] ) ? absint( $overdue_count_map[ $id ] ) : 0;

			$base_activity_at = '';
			if ( ! empty( $row['updated_at'] ) ) {
				$base_activity_at = sanitize_text_field( (string) $row['updated_at'] );
			} elseif ( ! empty( $row['created_at'] ) ) {
				$base_activity_at = sanitize_text_field( (string) $row['created_at'] );
			}

			$last_activity_at = $base_activity_at;
			if ( isset( $task_activity_map[ $id ] ) && $this->is_datetime_more_recent( $task_activity_map[ $id ], $last_activity_at ) ) {
				$last_activity_at = $task_activity_map[ $id ];
			}

			$inactive_attention = false;
			if ( ! empty( $last_activity_at ) && false !== $now_ts ) {
				$last_activity_ts = strtotime( (string) $last_activity_at );
				if ( false !== $last_activity_ts ) {
					$inactive_attention = ( $now_ts - $last_activity_ts ) > ( Crm_Pipeline_Service::INACTIVITY_DAYS * DAY_IN_SECONDS );
				}
			}

			$desired_types = array();
			if ( $overdue_count > 0 ) {
				$desired_types[] = 'overdue_task';
			}

			if ( in_array( $stage, Crm_Pipeline_Service::FOLLOW_UP_SUGGESTION_STAGES, true ) && 0 === $pending_count ) {
				$desired_types[] = 'follow_up_needed';
			}

			if ( 'won' === $stage && ! $has_process ) {
				$desired_types[] = 'conversion_pending';
			}

			if ( $inactive_attention ) {
				$desired_types[] = 'inactive_opportunity';
			}

			$desired_map[ $id ] = array_values( array_unique( array_filter( $desired_types ) ) );
		}

		return $desired_map;
	}

	/**
	 * Get deterministic message by alert type.
	 *
	 * @param string $alert_type Alert type.
	 * @return string
	 */
	protected function get_alert_message_by_type( $alert_type ) {
		$messages = array(
			'overdue_task'        => __( 'There are overdue CRM tasks.', 'super-mechanic' ),
			'inactive_opportunity' => __( 'Opportunity is inactive and needs attention.', 'super-mechanic' ),
			'follow_up_needed'    => __( 'Follow-up task is needed.', 'super-mechanic' ),
			'conversion_pending'  => __( 'Opportunity won without linked process.', 'super-mechanic' ),
		);

		return isset( $messages[ $alert_type ] ) ? $messages[ $alert_type ] : __( 'CRM alert active.', 'super-mechanic' );
	}

	/**
	 * Compare two mysql datetime values and report if A is more recent than B.
	 *
	 * @param string $candidate Candidate datetime.
	 * @param string $baseline Baseline datetime.
	 * @return bool
	 */
	protected function is_datetime_more_recent( $candidate, $baseline ) {
		$candidate_ts = strtotime( (string) $candidate );
		$baseline_ts  = strtotime( (string) $baseline );

		if ( false === $candidate_ts ) {
			return false;
		}

		if ( false === $baseline_ts ) {
			return true;
		}

		return $candidate_ts > $baseline_ts;
	}
}
