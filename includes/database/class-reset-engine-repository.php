<?php
/**
 * Reset engine repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized SQL reset repository for Mekvort operational runtime data.
 */
class Reset_Engine_Repository {
	/**
	 * Reset plugin operational/business tables and seed default business baseline.
	 *
	 * @param string $default_business_name Default business display name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reset_operational_data( $default_business_name ) {
		global $wpdb;

		$tables     = Schema::get_tables();
		$delete_map = $this->get_reset_delete_order( $tables );
		$deleted    = array();

		$started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $started ) {
			return new \WP_Error( 'sm_db_reset_transaction_start_failed', __( 'Could not start database transaction.', 'super-mechanic' ) );
		}

		foreach ( $delete_map as $key => $table_name ) {
			$result = $wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names come from trusted schema map.

			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );

				return new \WP_Error(
					'sm_db_reset_failed',
					sprintf(
						/* translators: %s: table key */
						__( 'Could not reset table: %s.', 'super-mechanic' ),
						(string) $key
					)
				);
			}

			$deleted[ $key ] = (int) $result;
		}

		$seeded = $this->seed_default_business( $tables, $default_business_name );
		if ( ! $seeded ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'sm_db_reset_seed_failed', __( 'Could not restore the default business baseline.', 'super-mechanic' ) );
		}

		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'sm_db_reset_commit_failed', __( 'Could not commit database reset.', 'super-mechanic' ) );
		}

		return array(
			'deleted'               => $deleted,
			'default_business_seed' => true,
		);
	}

	/**
	 * Build reset delete order from child-like runtime tables to parent-like tables.
	 *
	 * @param array<string, string> $tables Schema map.
	 * @return array<string, string>
	 */
	protected function get_reset_delete_order( array $tables ) {
		$ordered_keys = array(
			'webhook_deliveries',
			'webhooks',
			'notifications',
			'comments',
			'attachments',
			'payments',
			'invoice_items',
			'invoices',
			'quote_items',
			'quotes',
			'paperwork_items',
			'paperwork',
			'pre_delivery',
			'maintenance_labor',
			'maintenance_parts',
			'maintenance',
			'process_meta',
			'process_parts',
			'process_step_logs',
			'appointment_calendar_sync',
			'appointments',
			'crm_tasks',
			'crm_alerts',
			'crm_pipeline',
			'processes',
			'flow_steps',
			'flows',
			'client_vehicles',
			'vehicles',
			'client_crm_meta',
			'clients',
			'businesses',
		);

		$ordered_tables = array();

		foreach ( $ordered_keys as $key ) {
			if ( isset( $tables[ $key ] ) ) {
				$ordered_tables[ $key ] = $tables[ $key ];
			}
		}

		return $ordered_tables;
	}

	/**
	 * Restore deterministic default business row with id=1.
	 *
	 * @param array<string, string> $tables                Schema map.
	 * @param string                $default_business_name Default name.
	 * @return bool
	 */
	protected function seed_default_business( array $tables, $default_business_name ) {
		global $wpdb;

		if ( empty( $tables['businesses'] ) ) {
			return false;
		}

		$name = sanitize_text_field( (string) $default_business_name );
		if ( '' === trim( $name ) ) {
			$name = 'Mekvort';
		}

		$now = current_time( 'mysql', true );
		$sql = $wpdb->prepare(
			"INSERT INTO {$tables['businesses']} (id, slug, name, status, is_default, timezone, currency, created_at, updated_at)
			VALUES (1, %s, %s, 'active', 1, 'UTC', 'USD', %s, %s)",
			'default',
			$name,
			$now,
			$now
		);

		return false !== $wpdb->query( $sql );
	}
}

