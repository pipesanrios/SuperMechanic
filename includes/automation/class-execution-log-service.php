<?php
/**
 * Execution log service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Business layer for operational execution logs.
 */
class Execution_Log_Service {
	/**
	 * Repository dependency.
	 *
	 * @var Execution_Log_Repository
	 */
	protected $repository;
	/**
	 * In-request actor label cache.
	 *
	 * @var array<int,string>
	 */
	protected $actor_labels_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Execution_Log_Repository|null $repository Repository.
	 */
	public function __construct( Execution_Log_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Execution_Log_Repository();
	}

	/**
	 * Register one operational execution log row.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $rule_key Rule key.
	 * @param string              $action_type Action type.
	 * @param string              $execution_mode Execution mode.
	 * @param string              $result Result key.
	 * @param int                 $affected_count Affected entities.
	 * @param int                 $actor_user_id Actor user ID.
	 * @param array<string,mixed> $context Context payload.
	 * @return bool
	 */
	public function register_execution( $business_id, $rule_key, $action_type, $execution_mode, $result, $affected_count, $actor_user_id, array $context = array() ) {
		$business_id    = absint( $business_id );
		$rule_key       = sanitize_key( (string) $rule_key );
		$action_type    = sanitize_key( (string) $action_type );
		$execution_mode = sanitize_key( (string) $execution_mode );
		$result         = sanitize_key( (string) $result );
		$affected_count = absint( $affected_count );
		$actor_user_id  = absint( $actor_user_id );

		if ( $business_id <= 0 || '' === $action_type || $actor_user_id <= 0 ) {
			return false;
		}

		if ( ! in_array( $execution_mode, array( 'manual', 'confirmable', 'auto' ), true ) ) {
			$execution_mode = 'manual';
		}

		if ( '' === $result ) {
			$result = 'unknown';
		}

		$inserted = $this->repository->insert_log(
			array(
				'business_id'    => $business_id,
				'rule_key'       => $rule_key,
				'action_type'    => $action_type,
				'execution_mode' => $execution_mode,
				'result'         => $result,
				'affected_count' => $affected_count,
				'actor_user_id'  => $actor_user_id,
				'context_json'   => $context,
			)
		);

		return false !== $inserted;
	}

	/**
	 * Register one rule-update audit log row.
	 *
	 * @param int                 $business_id Business ID.
	 * @param string              $rule_key Rule key.
	 * @param int                 $actor_user_id Actor user ID.
	 * @param string              $execution_mode Resulting execution mode.
	 * @param array<string,mixed> $old_value Previous basic config.
	 * @param array<string,mixed> $new_value New basic config.
	 * @param array<int,string>   $changed_fields Changed field keys.
	 * @return bool
	 */
	public function register_rule_update_audit( $business_id, $rule_key, $actor_user_id, $execution_mode, array $old_value, array $new_value, array $changed_fields = array() ) {
		$context = array(
			'old_value'      => $old_value,
			'new_value'      => $new_value,
			'changed_fields' => array_values( array_filter( array_map( 'sanitize_key', $changed_fields ) ) ),
			'source'         => 'rules_basic_edit',
			'logged_at'      => current_time( 'mysql' ),
		);

		return $this->register_execution(
			$business_id,
			$rule_key,
			'rule_update',
			$execution_mode,
			'success',
			1,
			$actor_user_id,
			$context
		);
	}

	/**
	 * Get paginated logs list for admin UI.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @param int                 $page Page number.
	 * @param int                 $per_page Rows per page.
	 * @return array<string,mixed>
	 */
	public function get_logs_list( array $filters = array(), $page = 1, $per_page = 20 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 100, absint( $per_page ) ) );

		$total = $this->repository->count_logs( $filters );
		$rows  = $this->repository->get_logs( $filters, $page, $per_page );
		$items = array();
		$actor_labels = $this->resolve_actor_labels(
			array_values(
				array_filter(
					array_map(
						function ( $row ) {
							return is_array( $row ) && isset( $row['actor_user_id'] ) ? absint( $row['actor_user_id'] ) : 0;
						},
						$rows
					)
				)
			)
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$context_raw = isset( $row['context_json'] ) ? (string) $row['context_json'] : '';
			$context     = json_decode( $context_raw, true );
			if ( ! is_array( $context ) ) {
				$context = array();
			}

			$actor_user_id = isset( $row['actor_user_id'] ) ? absint( $row['actor_user_id'] ) : 0;
			$actor_label   = isset( $actor_labels[ $actor_user_id ] ) ? (string) $actor_labels[ $actor_user_id ] : '';

			$items[] = array(
				'id'              => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'date'            => isset( $row['created_at'] ) ? sanitize_text_field( (string) $row['created_at'] ) : '',
				'business_id'     => isset( $row['business_id'] ) ? absint( $row['business_id'] ) : 0,
				'rule_key'        => isset( $row['rule_key'] ) ? sanitize_key( (string) $row['rule_key'] ) : '',
				'action_type'     => isset( $row['action_type'] ) ? sanitize_key( (string) $row['action_type'] ) : '',
				'execution_mode'  => isset( $row['execution_mode'] ) ? sanitize_key( (string) $row['execution_mode'] ) : 'manual',
				'result'          => isset( $row['result'] ) ? sanitize_key( (string) $row['result'] ) : 'unknown',
				'affected_count'  => isset( $row['affected_count'] ) ? absint( $row['affected_count'] ) : 0,
				'actor_user_id'   => $actor_user_id,
				'actor_label'     => $actor_label,
				'context_summary' => $this->summarize_context( $context ),
				'debug_reason'    => $this->build_debug_reason(
					isset( $row['result'] ) ? sanitize_key( (string) $row['result'] ) : 'unknown',
					isset( $row['action_type'] ) ? sanitize_key( (string) $row['action_type'] ) : '',
					$context
				),
			);
		}

		$total_pages = (int) ceil( $total / $per_page );
		return array(
			'items'       => $items,
			'filters'     => array(
				'rule_key'    => isset( $filters['rule_key'] ) ? sanitize_key( (string) $filters['rule_key'] ) : '',
				'result'      => isset( $filters['result'] ) ? sanitize_key( (string) $filters['result'] ) : '',
				'date'        => isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '',
				'business_id' => isset( $filters['business_id'] ) ? absint( $filters['business_id'] ) : 0,
			),
			'pagination'  => array(
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => max( 1, $total_pages ),
			),
		);
	}

	/**
	 * Resolve actor labels in one batch to avoid one query per log row.
	 *
	 * @param array<int,int> $actor_ids Actor IDs.
	 * @return array<int,string>
	 */
	protected function resolve_actor_labels( array $actor_ids ) {
		$actor_ids = array_values( array_unique( array_filter( array_map( 'absint', $actor_ids ) ) ) );
		if ( empty( $actor_ids ) ) {
			return array();
		}

		$resolved = array();
		$missing  = array();
		foreach ( $actor_ids as $actor_id ) {
			if ( isset( $this->actor_labels_cache[ $actor_id ] ) ) {
				$resolved[ $actor_id ] = $this->actor_labels_cache[ $actor_id ];
				continue;
			}
			$missing[] = $actor_id;
		}

		if ( ! empty( $missing ) ) {
			$users = get_users(
				array(
					'include' => $missing,
					'fields'  => array( 'ID', 'display_name' ),
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			if ( is_array( $users ) ) {
				foreach ( $users as $user ) {
					if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
						continue;
					}
					$user_id = absint( $user->ID );
					if ( $user_id <= 0 ) {
						continue;
					}
					$label = isset( $user->display_name ) ? sanitize_text_field( (string) $user->display_name ) : '';
					$this->actor_labels_cache[ $user_id ] = $label;
					$resolved[ $user_id ]                 = $label;
				}
			}

			// Keep misses memoized to avoid repeated lookups in the same request.
			foreach ( $missing as $actor_id ) {
				if ( ! array_key_exists( $actor_id, $this->actor_labels_cache ) ) {
					$this->actor_labels_cache[ $actor_id ] = '';
				}
				if ( ! array_key_exists( $actor_id, $resolved ) ) {
					$resolved[ $actor_id ] = $this->actor_labels_cache[ $actor_id ];
				}
			}
		}

		return $resolved;
	}

	/**
	 * Summarize context JSON into short human-readable text.
	 *
	 * @param array<string,mixed> $context Context payload.
	 * @return string
	 */
	protected function summarize_context( array $context ) {
		if ( empty( $context ) ) {
			return '—';
		}

		$parts = array();

		if ( isset( $context['source'] ) ) {
			$parts[] = 'source: ' . sanitize_key( (string) $context['source'] );
		}
		if ( isset( $context['entity_type'] ) ) {
			$parts[] = 'entity: ' . sanitize_key( (string) $context['entity_type'] );
		}
		if ( isset( $context['target_user_id'] ) ) {
			$parts[] = 'target_user: ' . absint( $context['target_user_id'] );
		}
		if ( isset( $context['changed_fields'] ) && is_array( $context['changed_fields'] ) ) {
			$fields = array_values( array_filter( array_map( 'sanitize_key', $context['changed_fields'] ) ) );
			if ( ! empty( $fields ) ) {
				$parts[] = 'changed: ' . implode( ',', $fields );
			}
		}

		if ( empty( $parts ) ) {
			return 'context available';
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Build readable operational debug reason from result and context.
	 *
	 * @param string              $result Result key.
	 * @param string              $action_type Action type.
	 * @param array<string,mixed> $context Context payload.
	 * @return string
	 */
	protected function build_debug_reason( $result, $action_type, array $context ) {
		$result      = sanitize_key( (string) $result );
		$action_type = sanitize_key( (string) $action_type );

		if ( 'rule_update' === $action_type ) {
			$fields = isset( $context['changed_fields'] ) && is_array( $context['changed_fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', $context['changed_fields'] ) ) ) : array();
			if ( ! empty( $fields ) ) {
				return sprintf(
					/* translators: %s changed field list. */
					__( 'Rule updated. Changed fields: %s.', 'super-mechanic' ),
					implode( ', ', $fields )
				);
			}
			return __( 'Rule update audit entry registered.', 'super-mechanic' );
		}

		if ( isset( $context['reason'] ) ) {
			$reason = sanitize_text_field( (string) $context['reason'] );
			if ( '' !== $reason ) {
				return $reason;
			}
		}
		if ( isset( $context['trigger_reason'] ) ) {
			$trigger_reason = sanitize_text_field( (string) $context['trigger_reason'] );
			if ( '' !== $trigger_reason ) {
				return $trigger_reason;
			}
		}
		if ( isset( $context['execution_reason'] ) ) {
			$execution_reason = sanitize_text_field( (string) $context['execution_reason'] );
			if ( '' !== $execution_reason ) {
				return $execution_reason;
			}
		}

		if ( 'blocked' === $result ) {
			return __( 'Blocked by guardrails or missing preconditions.', 'super-mechanic' );
		}
		if ( 'skipped' === $result ) {
			return __( 'Skipped because trigger conditions were not met.', 'super-mechanic' );
		}
		if ( 'partial' === $result ) {
			return __( 'Executed partially: some items completed and some failed.', 'super-mechanic' );
		}
		if ( 'failed' === $result ) {
			return __( 'Execution failed for this action.', 'super-mechanic' );
		}
		if ( 'success' === $result ) {
			return __( 'Execution completed successfully.', 'super-mechanic' );
		}

		return __( 'Operational result recorded without additional detail.', 'super-mechanic' );
	}
}
