<?php
/**
 * Automation service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

use Super_Mechanic\Appointments\Appointment_Reminder_Scheduler;
use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Executes simple event-based automations using centralized dispatcher hooks.
 */
class Automation_Service {
	/**
	 * Rule engine.
	 *
	 * @var Automation_Rule_Engine
	 */
	protected $rule_engine;

	/**
	 * Appointment reminder scheduler.
	 *
	 * @var Appointment_Reminder_Scheduler
	 */
	protected $appointment_reminder_scheduler;

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Automation_Rule_Engine|null       $rule_engine                   Rule engine.
	 * @param Appointment_Reminder_Scheduler|null $appointment_reminder_scheduler Reminder scheduler.
	 * @param Settings_Service|null             $settings_service               Settings service.
	 */
	public function __construct( Automation_Rule_Engine $rule_engine = null, Appointment_Reminder_Scheduler $appointment_reminder_scheduler = null, Settings_Service $settings_service = null ) {
		$this->settings_service              = $settings_service ? $settings_service : new Settings_Service();
		$this->rule_engine                   = $rule_engine ? $rule_engine : new Automation_Rule_Engine( $this->settings_service );
		$this->appointment_reminder_scheduler = $appointment_reminder_scheduler ? $appointment_reminder_scheduler : new Appointment_Reminder_Scheduler( null, null, null, $this->settings_service );
	}

	/**
	 * Register automation listeners.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'sm_event_appointment_created', array( $this, 'handle_appointment_created' ), 20, 1 );
		add_action( 'sm_event_appointment_updated', array( $this, 'handle_appointment_updated' ), 20, 1 );
		add_action( 'sm_event_appointment_status_changed', array( $this, 'handle_appointment_status_changed' ), 20, 1 );
		add_action( 'sm_event_appointment_cancelled', array( $this, 'handle_appointment_cancelled' ), 20, 1 );
	}

	/**
	 * Handle appointment created event.
	 *
	 * @param mixed $payload Event payload.
	 * @return void
	 */
	public function handle_appointment_created( $payload ) {
		$this->handle_event( 'appointment_created', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle appointment updated event.
	 *
	 * @param mixed $payload Event payload.
	 * @return void
	 */
	public function handle_appointment_updated( $payload ) {
		$this->handle_event( 'appointment_updated', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle appointment status changed event.
	 *
	 * @param mixed $payload Event payload.
	 * @return void
	 */
	public function handle_appointment_status_changed( $payload ) {
		$this->handle_event( 'appointment_status_changed', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle appointment cancelled event.
	 *
	 * @param mixed $payload Event payload.
	 * @return void
	 */
	public function handle_appointment_cancelled( $payload ) {
		$this->handle_event( 'appointment_cancelled', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle normalized event through rule engine.
	 *
	 * @param string               $event_name Event key.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return void
	 */
	protected function handle_event( $event_name, array $payload ) {
		$actions = $this->rule_engine->get_actions_for_event( $event_name, $payload );

		foreach ( $actions as $action ) {
			if ( 'refresh_appointment_reminders' === $action ) {
				$this->appointment_reminder_scheduler->schedule_near_term_scan( 60 );
			}
		}
	}
}
