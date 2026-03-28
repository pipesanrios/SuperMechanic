<?php
/**
 * Simple automation rule engine.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves simple enabled/disabled automation actions per event.
 */
class Automation_Rule_Engine {
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 */
	public function __construct( Settings_Service $settings_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
	}

	/**
	 * Get rule actions for an event.
	 *
	 * @param string               $event_name Event key.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return array<int, string>
	 */
	public function get_actions_for_event( $event_name, array $payload = array() ) {
		if ( ! $this->is_automation_enabled() ) {
			return array();
		}

		$event_name = sanitize_key( (string) $event_name );
		$map        = array(
			'appointment_created'        => array( 'refresh_appointment_reminders' ),
			'appointment_updated'        => array( 'refresh_appointment_reminders' ),
			'appointment_status_changed' => array( 'refresh_appointment_reminders' ),
			'appointment_cancelled'      => array( 'refresh_appointment_reminders' ),
		);

		if ( empty( $map[ $event_name ] ) ) {
			return array();
		}

		$actions = array();
		foreach ( $map[ $event_name ] as $action ) {
			if ( $this->is_action_enabled( $action, $payload ) ) {
				$actions[] = $action;
			}
		}

		return $actions;
	}

	/**
	 * Whether global automation runtime is enabled.
	 *
	 * @return bool
	 */
	protected function is_automation_enabled() {
		return ! empty( $this->settings_service->get_setting( 'automation', 'enable_automation_runtime', true ) );
	}

	/**
	 * Whether specific action is enabled.
	 *
	 * @param string               $action  Action key.
	 * @param array<string, mixed> $payload Event payload.
	 * @return bool
	 */
	protected function is_action_enabled( $action, array $payload = array() ) {
		$action = sanitize_key( (string) $action );

		if ( 'refresh_appointment_reminders' === $action ) {
			return ! empty( $this->settings_service->get_setting( 'automation', 'enable_appointment_reminders', true ) );
		}

		return false;
	}
}
