<?php
/**
 * CRM scheduler service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM scheduler registration and execution.
 */
class Crm_Scheduler_Service {
	/**
	 * Scheduler hook name.
	 *
	 * @var string
	 */
	const TICK_HOOK = 'sm_crm_scheduler_tick';

	/**
	 * Custom schedule key.
	 *
	 * @var string
	 */
	const SCHEDULE_KEY = 'sm_crm_every_ten_minutes';

	/**
	 * Schedule interval in seconds.
	 *
	 * @var int
	 */
	const SCHEDULE_INTERVAL = 600;

	/**
	 * Register scheduler hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( self::TICK_HOOK, array( $this, 'handle_crm_scheduler_tick' ) );
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_cron_schedules( array $schedules ) {
		if ( ! isset( $schedules[ self::SCHEDULE_KEY ] ) ) {
			$schedules[ self::SCHEDULE_KEY ] = array(
				'interval' => self::SCHEDULE_INTERVAL,
				'display'  => __( 'Every 10 Minutes (Super Mechanic CRM)', 'super-mechanic' ),
			);
		}

		return $schedules;
	}

	/**
	 * Ensure scheduler event is registered once.
	 *
	 * @return void
	 */
	public static function ensure_scheduled_event() {
		if ( wp_next_scheduled( self::TICK_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + 60, self::SCHEDULE_KEY, self::TICK_HOOK );
	}

	/**
	 * Clear scheduler hook on deactivation.
	 *
	 * @return void
	 */
	public static function clear_scheduled_event() {
		wp_clear_scheduled_hook( self::TICK_HOOK );
	}

	/**
	 * Handle scheduler tick.
	 *
	 * 39E-1: debug logging + extensible hook.
	 * 39E-2: controlled persisted alerts recalculation.
	 *
	 * @return void
	 */
	public function handle_crm_scheduler_tick() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Super Mechanic][CRM Scheduler] Tick started.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$alert_service = new Crm_Alert_Service();
		$summary       = $alert_service->recalculate_alerts_for_scheduler();

		/**
		 * Fires after CRM scheduler tick has run.
		 *
		 * @param array<string,mixed> $summary Tick summary.
		 */
		do_action( 'sm_crm_scheduler_tick_executed', $summary );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[Super Mechanic][CRM Scheduler] Tick completed. pipelines=%d created=%d updated=%d resolved=%d',
					isset( $summary['processed_pipelines'] ) ? absint( $summary['processed_pipelines'] ) : 0,
					isset( $summary['created'] ) ? absint( $summary['created'] ) : 0,
					isset( $summary['updated'] ) ? absint( $summary['updated'] ) : 0,
					isset( $summary['resolved'] ) ? absint( $summary['resolved'] ) : 0
				)
			);
		}
	}
}

