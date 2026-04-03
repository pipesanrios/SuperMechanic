<?php
/**
 * Operational rules service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

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
	 * Constructor.
	 *
	 * @param Workload_Service|null $workload_service Workload service.
	 */
	public function __construct( Workload_Service $workload_service = null ) {
		$this->workload_service = $workload_service ? $workload_service : new Workload_Service();
	}

	/**
	 * Return operational rules definition.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_operational_rules( $business_id ) {
		$business_id = absint( $business_id );

		return array(
			array(
				'rule_key'     => 'overdue_tasks_cleanup',
				'name'         => __( 'Overdue tasks cleanup', 'super-mechanic' ),
				'description'  => __( 'Detects overdue CRM task pressure and previews a safe bulk resolve action.', 'super-mechanic' ),
				'enabled'      => true,
				'conditions'   => array(
					array(
						'metric'    => 'overdue_tasks',
						'operator'  => '>',
						'threshold' => 3,
					),
				),
				'action_type'  => 'bulk_resolve',
				'action_config' => array(
					'entity_type' => 'crm_task',
					'source_group' => 'overdue_tasks',
				),
				'business_id'  => $business_id,
			),
			array(
				'rule_key'     => 'critical_saturation_rebalance',
				'name'         => __( 'Critical saturation rebalance', 'super-mechanic' ),
				'description'  => __( 'Detects overloaded users and previews controlled workload rebalancing.', 'super-mechanic' ),
				'enabled'      => true,
				'conditions'   => array(
					array(
						'metric'    => 'overloaded_users',
						'operator'  => '>',
						'threshold' => 0,
					),
				),
				'action_type'  => 'bulk_reassign',
				'action_config' => array(
					'entity_type' => 'crm_task',
					'source'      => 'assignment_proposals',
				),
				'business_id'  => $business_id,
			),
			array(
				'rule_key'     => 'multi_critical_alert',
				'name'         => __( 'Multiple critical alert pattern', 'super-mechanic' ),
				'description'  => __( 'Detects accumulation of critical operational flags to elevate visibility.', 'super-mechanic' ),
				'enabled'      => true,
				'conditions'   => array(
					array(
						'metric'    => 'critical_flags',
						'operator'  => '>=',
						'threshold' => 2,
					),
				),
				'action_type'  => 'flag',
				'action_config' => array(
					'level'  => 'critical',
					'source' => 'automation_console',
				),
				'business_id'  => $business_id,
			),
		);
	}

	/**
	 * Evaluate operational rules with action previews only.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function evaluate_operational_rules( $business_id ) {
		$business_id = absint( $business_id );
		$user_id     = get_current_user_id();
		$rules       = $this->get_operational_rules( $business_id );

		$console      = $this->workload_service->get_operational_automation_console( $business_id, $user_id );
		$bulk_actions = $this->workload_service->get_operational_bulk_actions( $business_id, $user_id );
		$assignments  = $this->workload_service->get_operational_assignments( $business_id );

		$overdue_group = $this->find_bulk_group( $bulk_actions, 'overdue_tasks' );
		$critical_group = $this->find_bulk_group( $bulk_actions, 'critical_pending_tasks' );
		$overdue_count = isset( $overdue_group['count'] ) ? absint( $overdue_group['count'] ) : 0;
		$overloaded_users = isset( $assignments['summary']['overloaded_users'] ) ? absint( $assignments['summary']['overloaded_users'] ) : 0;
		$critical_flags = isset( $console['flags']['summary']['critical_flags'] ) ? absint( $console['flags']['summary']['critical_flags'] ) : 0;
		$global_level   = isset( $console['system_status']['global_level'] ) ? sanitize_key( (string) $console['system_status']['global_level'] ) : 'normal';

		$evaluations = array();
		foreach ( $rules as $rule ) {
			$rule_key   = isset( $rule['rule_key'] ) ? sanitize_key( (string) $rule['rule_key'] ) : '';
			$enabled    = ! empty( $rule['enabled'] );
			$threshold  = isset( $rule['conditions'][0]['threshold'] ) ? absint( $rule['conditions'][0]['threshold'] ) : 0;
			$triggered  = false;
			$impact     = 'info';
			$preview    = array(
				'action_type' => isset( $rule['action_type'] ) ? sanitize_key( (string) $rule['action_type'] ) : 'flag',
				'executable'  => false,
				'note'        => __( 'Preview only. No automatic execution.', 'super-mechanic' ),
			);

			if ( 'overdue_tasks_cleanup' === $rule_key ) {
				$triggered = $enabled && $overdue_count > $threshold;
				$impact    = $overdue_count >= ( $threshold * 2 ) ? 'critical' : ( $triggered ? 'warning' : 'info' );
				$preview   = array(
					'action_type'  => 'bulk_resolve',
					'entity_type'  => 'crm_task',
					'candidate_count' => $overdue_count,
					'executable'   => ! empty( $overdue_group['executable'] ),
					'group_key'    => 'overdue_tasks',
					'note'         => __( 'Would resolve pending overdue CRM tasks in a controlled bulk action.', 'super-mechanic' ),
				);
			} elseif ( 'critical_saturation_rebalance' === $rule_key ) {
				$proposals = isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0;
				$triggered = $enabled && $overloaded_users > $threshold;
				$impact    = $overloaded_users >= 2 ? 'critical' : ( $triggered ? 'warning' : 'info' );
				$preview   = array(
					'action_type'    => 'bulk_reassign',
					'entity_type'    => 'crm_task',
					'overloaded_users' => $overloaded_users,
					'proposal_count' => $proposals,
					'executable'     => ! empty( $critical_group['executable'] ) || $proposals > 0,
					'group_key'      => 'critical_pending_tasks',
					'note'           => __( 'Would rebalance critical CRM task load using validated proposals.', 'super-mechanic' ),
				);
			} elseif ( 'multi_critical_alert' === $rule_key ) {
				$triggered = $enabled && $critical_flags >= $threshold;
				$impact    = $critical_flags >= ( $threshold + 1 ) || 'critical' === $global_level ? 'critical' : ( $triggered ? 'warning' : 'info' );
				$preview   = array(
					'action_type'    => 'flag',
					'critical_flags' => $critical_flags,
					'global_level'   => $global_level,
					'executable'     => false,
					'note'           => __( 'Would elevate operational visibility through dashboard flagging only.', 'super-mechanic' ),
				);
			}

			$evaluations[] = array(
				'rule_key'       => $rule_key,
				'triggered'      => $triggered,
				'impact_level'   => $impact,
				'action_preview' => $preview,
			);
		}

		return array(
			'rules'       => $rules,
			'evaluations' => $evaluations,
			'meta'        => array(
				'business_id'  => $business_id,
				'user_id'      => $user_id,
				'generated_at' => current_time( 'mysql' ),
				'mutations'    => 'none',
			),
		);
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
}
