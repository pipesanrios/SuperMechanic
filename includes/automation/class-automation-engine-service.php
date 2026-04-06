<?php
/**
 * Advanced automation engine service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

use Super_Mechanic\Notifications\Notification_Service;
use Super_Mechanic\Webhooks\Webhook_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and executes event-driven automation actions.
 */
class Automation_Engine_Service {
	/**
	 * Notification service dependency.
	 *
	 * @var Notification_Service
	 */
	protected $notification_service;

	/**
	 * Webhook service dependency.
	 *
	 * @var Webhook_Service
	 */
	protected $webhook_service;

	/**
	 * Supported initial event keys.
	 *
	 * @var array<int,string>
	 */
	protected $supported_events = array(
		'membership_created',
		'membership_updated',
		'user_transferred',
		'overdue_alert_detected',
		'critical_signal_detected',
	);

	/**
	 * Constructor.
	 *
	 * @param Notification_Service|null $notification_service Notification service.
	 * @param Webhook_Service|null      $webhook_service Webhook service.
	 */
	public function __construct( Notification_Service $notification_service = null, Webhook_Service $webhook_service = null ) {
		$this->notification_service = $notification_service ? $notification_service : new Notification_Service();
		$this->webhook_service      = $webhook_service ? $webhook_service : new Webhook_Service();
	}

	/**
	 * Handle one event through resolve -> execute flow.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Event payload.
	 * @return array<string,mixed>
	 */
	public function handle_event( $event_type, $payload = array() ) {
		$event_type = sanitize_key( (string) $event_type );
		$payload    = is_array( $payload ) ? $payload : array();

		if ( '' === $event_type || ! in_array( $event_type, $this->supported_events, true ) ) {
			return array(
				'event_type' => $event_type,
				'supported'  => false,
				'actions'    => array(),
				'results'    => array(),
			);
		}

		$actions = $this->resolve_automation_actions( $event_type, $payload );
		$payload = array_merge(
			array(
				'event_type' => $event_type,
			),
			$payload
		);
		$results = array();
		foreach ( $actions as $action ) {
			$results[] = $this->execute_automation_action( $action, $payload );
		}

		return array(
			'event_type' => $event_type,
			'supported'  => true,
			'actions'    => $actions,
			'results'    => $results,
		);
	}

	/**
	 * Resolve automation actions for one event.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Event payload.
	 * @return array<int,array<string,mixed>>
	 */
	public function resolve_automation_actions( $event_type, $payload = array() ) {
		$event_type = sanitize_key( (string) $event_type );
		$payload    = is_array( $payload ) ? $payload : array();

		if ( '' === $event_type || ! in_array( $event_type, $this->supported_events, true ) ) {
			return array();
		}

		$actions = array(
			array( 'type' => 'send_notification' ),
			array( 'type' => 'dispatch_webhook' ),
		);

		if ( in_array( $event_type, array( 'overdue_alert_detected', 'critical_signal_detected' ), true ) ) {
			$actions[] = array(
				'type'   => 'create_internal_flag',
				'enabled'=> false,
				'note'   => 'no_clean_integration_point',
			);
			$actions[] = array(
				'type'   => 'add_operational_note',
				'enabled'=> false,
				'note'   => 'no_clean_integration_point',
			);
		}

		return $actions;
	}

	/**
	 * Execute one automation action.
	 *
	 * @param array<string,mixed>|string $action Action descriptor or string.
	 * @param array<string,mixed>        $payload Event payload.
	 * @return array<string,mixed>
	 */
	public function execute_automation_action( $action, $payload = array() ) {
		$payload     = is_array( $payload ) ? $payload : array();
		$event_type  = isset( $payload['event_type'] ) ? sanitize_key( (string) $payload['event_type'] ) : '';
		$action_type = is_array( $action )
			? sanitize_key( isset( $action['type'] ) ? (string) $action['type'] : '' )
			: sanitize_key( (string) $action );

		if ( '' === $action_type ) {
			return array(
				'action'  => '',
				'executed'=> false,
				'reason'  => 'invalid_action',
			);
		}

		if ( is_array( $action ) && array_key_exists( 'enabled', $action ) && ! $action['enabled'] ) {
			return array(
				'action'   => $action_type,
				'executed' => false,
				'reason'   => isset( $action['note'] ) ? sanitize_key( (string) $action['note'] ) : 'disabled',
			);
		}

		if ( 'send_notification' === $action_type ) {
			$user_id = isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0;
			if ( $user_id <= 0 || '' === $event_type ) {
				return array(
					'action'   => $action_type,
					'executed' => false,
					'reason'   => 'missing_user_or_event',
				);
			}

			$result = $this->notification_service->send_notification(
				$event_type,
				$user_id,
				array_merge(
					$payload,
					array(
						'skip_webhook_dispatch' => true,
					)
				)
			);

			return array(
				'action'   => $action_type,
				'executed' => isset( $result['success'] ) ? (bool) $result['success'] : false,
				'result'   => $result,
			);
		}

		if ( 'dispatch_webhook' === $action_type ) {
			if ( '' === $event_type ) {
				return array(
					'action'   => $action_type,
					'executed' => false,
					'reason'   => 'missing_event',
				);
			}

			$result = $this->webhook_service->dispatch_from_engine(
				$event_type,
				array(
					'user_id' => isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : get_current_user_id(),
					'data'    => $payload,
				)
			);

			return array(
				'action'   => $action_type,
				'executed' => true,
				'result'   => $result,
			);
		}

		if ( in_array( $action_type, array( 'create_internal_flag', 'add_operational_note' ), true ) ) {
			return array(
				'action'   => $action_type,
				'executed' => false,
				'reason'   => 'not_integrated',
			);
		}

		return array(
			'action'   => $action_type,
			'executed' => false,
			'reason'   => 'unsupported_action',
		);
	}
}
