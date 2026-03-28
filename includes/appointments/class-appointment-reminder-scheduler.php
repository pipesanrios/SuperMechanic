<?php
/**
 * Appointment reminder scheduler.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Runs controlled reminder jobs via wp_cron and dispatches appointment_reminder events.
 */
class Appointment_Reminder_Scheduler {
	const CRON_HOOK        = 'sm_appointment_reminder_cron';
	const ON_DEMAND_HOOK   = 'sm_appointment_reminder_run_once';
	const LOCK_TRANSIENT   = 'sm_appointment_reminder_lock';
	const SENT_TRANSIENT_PREFIX = 'sm_appointment_reminder_sent_';

	/**
	 * Appointment service.
	 *
	 * @var Appointment_Service
	 */
	protected $appointment_service;

	/**
	 * Notification service.
	 *
	 * @var Notification_Service
	 */
	protected $notification_service;

	/**
	 * Event dispatcher.
	 *
	 * @var Event_Dispatcher
	 */
	protected $event_dispatcher;

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Service|null  $appointment_service  Appointment service.
	 * @param Notification_Service|null $notification_service Notification service.
	 * @param Event_Dispatcher|null     $event_dispatcher     Event dispatcher.
	 * @param Settings_Service|null     $settings_service     Settings service.
	 */
	public function __construct( Appointment_Service $appointment_service = null, Notification_Service $notification_service = null, Event_Dispatcher $event_dispatcher = null, Settings_Service $settings_service = null ) {
		$this->appointment_service  = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->notification_service = $notification_service ? $notification_service : new Notification_Service();
		$this->event_dispatcher     = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance( $this->notification_service );
		$this->settings_service     = $settings_service ? $settings_service : new Settings_Service();
	}

	/**
	 * Register cron hooks and schedules.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_scan' ) );
		add_action( self::ON_DEMAND_HOOK, array( $this, 'run_scheduled_scan' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'sm_every_fifteen_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Add custom cron interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_custom_schedules( $schedules ) {
		if ( ! isset( $schedules['sm_every_fifteen_minutes'] ) ) {
			$schedules['sm_every_fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (Super Mechanic)', 'super-mechanic' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule a near-term one-time reminder scan.
	 *
	 * @param int $delay_seconds Delay before run.
	 * @return void
	 */
	public function schedule_near_term_scan( $delay_seconds = 60 ) {
		if ( ! $this->is_runtime_enabled() || ! $this->is_reminders_enabled() ) {
			return;
		}

		$next = wp_next_scheduled( self::ON_DEMAND_HOOK );
		if ( $next && ( $next - time() ) <= 120 ) {
			return;
		}

		wp_schedule_single_event( time() + max( 10, absint( $delay_seconds ) ), self::ON_DEMAND_HOOK );
	}

	/**
	 * Execute reminder scan.
	 *
	 * @return void
	 */
	public function run_scheduled_scan() {
		if ( ! $this->is_runtime_enabled() || ! $this->is_reminders_enabled() ) {
			return;
		}

		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::LOCK_TRANSIENT, 1, 2 * MINUTE_IN_SECONDS );

		$appointments = $this->get_candidate_appointments();
		$now_ts       = time();
		$minutes      = absint( $this->settings_service->get_setting( 'automation', 'appointment_reminder_minutes_before', 120 ) );
		$window       = absint( $this->settings_service->get_setting( 'automation', 'appointment_reminder_window_minutes', 15 ) );

		if ( $window < 1 ) {
			$window = 15;
		}

		foreach ( $appointments as $appointment ) {
			$start_ts = ! empty( $appointment['start_at'] ) ? strtotime( (string) $appointment['start_at'] . ' UTC' ) : false;

			if ( false === $start_ts || $start_ts <= $now_ts ) {
				continue;
			}

			$target_ts = $start_ts - ( $minutes * MINUTE_IN_SECONDS );
			if ( $now_ts < $target_ts || $now_ts > ( $target_ts + ( $window * MINUTE_IN_SECONDS ) ) ) {
				continue;
			}

			$dedupe_key = self::SENT_TRANSIENT_PREFIX . absint( $appointment['id'] ) . '_' . gmdate( 'YmdHi', $target_ts );
			if ( get_transient( $dedupe_key ) ) {
				continue;
			}

			$this->event_dispatcher->dispatch(
				'appointment_reminder',
				array(
					'appointment_id' => absint( $appointment['id'] ),
					'appointment'    => $appointment,
					'source'         => 'cron',
					'triggered_by'   => 0,
				)
			);

			$ttl = max( HOUR_IN_SECONDS, ( $start_ts - $now_ts ) + HOUR_IN_SECONDS );
			set_transient( $dedupe_key, 1, $ttl );
		}

		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Get upcoming appointments that can receive reminders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_candidate_appointments() {
		$today   = gmdate( 'Y-m-d' );
		$max_day = gmdate( 'Y-m-d', strtotime( '+2 days' ) );
		$result  = array();
		$seen    = array();

		foreach ( array( 'scheduled', 'confirmed' ) as $status ) {
			$rows = $this->appointment_service->get_appointments(
				array(
					'appointment_status' => $status,
					'date_from'          => $today,
					'date_to'            => $max_day,
					'per_page'           => 300,
					'orderby'            => 'start_at',
					'order'              => 'ASC',
				)
			);

			foreach ( $rows as $row ) {
				$id = ! empty( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( ! $id || isset( $seen[ $id ] ) ) {
					continue;
				}

				$seen[ $id ] = true;
				$result[]    = $row;
			}
		}

		return $result;
	}

	/**
	 * Whether automation runtime is globally enabled.
	 *
	 * @return bool
	 */
	protected function is_runtime_enabled() {
		return ! empty( $this->settings_service->get_setting( 'automation', 'enable_automation_runtime', true ) );
	}

	/**
	 * Whether appointment reminders are enabled.
	 *
	 * @return bool
	 */
	protected function is_reminders_enabled() {
		return ! empty( $this->settings_service->get_setting( 'automation', 'enable_appointment_reminders', true ) );
	}
}
