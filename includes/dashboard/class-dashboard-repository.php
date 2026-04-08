<?php
/**
 * Dashboard repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Logs\Log_Installer;
use Super_Mechanic\Notifications\Notification_Storage_Installer;
use Super_Mechanic\Webhooks\Webhook_Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregated read queries for operational dashboard metrics.
 */
class Dashboard_Repository {
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business context dependency.
	 */
	public function __construct( Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get dashboard metrics for one business.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,int>
	 */
	public function get_dashboard_metrics( $business_id = 0 ) {
		global $wpdb;

		$business_id = $this->resolve_business_id( $business_id );
		$tables      = Schema::get_tables();
		$clients     = isset( $tables['clients'] ) ? (string) $tables['clients'] : '';
		$vehicles    = isset( $tables['vehicles'] ) ? (string) $tables['vehicles'] : '';
		$processes   = isset( $tables['processes'] ) ? (string) $tables['processes'] : '';

		$metrics = array(
			'total_clients'        => 0,
			'total_vehicles'       => 0,
			'active_processes'     => 0,
			'completed_processes'  => 0,
			'pending_processes'    => 0,
			'active_webhooks'      => 0,
			'notifications_today'  => 0,
		);

		if ( '' !== $clients ) {
			$metrics['total_clients'] = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(id) FROM {$clients} WHERE business_id = %d",
						$business_id
					)
				)
			);
		}

		if ( '' !== $vehicles ) {
			$metrics['total_vehicles'] = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(id) FROM {$vehicles} WHERE business_id = %d",
						$business_id
					)
				)
			);
		}

		if ( '' !== $processes ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(CASE WHEN status NOT IN ('completed', 'delivered', 'cancelled') THEN 1 ELSE 0 END) AS active_processes,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_processes,
						SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_processes
					FROM {$processes}
					WHERE business_id = %d",
					$business_id
				),
				ARRAY_A
			);

			if ( is_array( $row ) ) {
				$metrics['active_processes']    = isset( $row['active_processes'] ) ? absint( $row['active_processes'] ) : 0;
				$metrics['completed_processes'] = isset( $row['completed_processes'] ) ? absint( $row['completed_processes'] ) : 0;
				$metrics['pending_processes']   = isset( $row['pending_processes'] ) ? absint( $row['pending_processes'] ) : 0;
			}
		}

		$webhook_table = $this->get_webhook_table_name();
		if ( '' !== $webhook_table ) {
			$metrics['active_webhooks'] = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(id) FROM {$webhook_table}
						WHERE business_id = %d
						AND (is_active = 1 OR status = 'active')",
						$business_id
					)
				)
			);
		}

		$notification_table = $this->get_notifications_table_name();
		if ( '' !== $notification_table ) {
			$metrics['notifications_today'] = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(id) FROM {$notification_table}
						WHERE business_id = %d
						AND DATE(created_at) = %s",
						$business_id,
						current_time( 'Y-m-d' )
					)
				)
			);
		}

		return $metrics;
	}

	/**
	 * Get recent structured activity from logs.
	 *
	 * @param int $business_id Business ID.
	 * @param int $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_activity( $business_id = 0, $limit = 10 ) {
		global $wpdb;

		$business_id = $this->resolve_business_id( $business_id );
		$limit       = max( 1, min( 50, absint( $limit ) ) );
		$log_table   = $this->get_logs_table_name();
		if ( '' === $log_table ) {
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT id, log_type, source, reference_id, status, message, created_at
			FROM {$log_table}
			WHERE business_id = %d
			ORDER BY id DESC
			LIMIT %d",
			$business_id,
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Resolve business scope.
	 *
	 * @param int $business_id Optional business override.
	 * @return int
	 */
	protected function resolve_business_id( $business_id = 0 ) {
		$business_id = absint( $business_id );
		if ( $business_id > 0 ) {
			return $business_id;
		}

		$resolved = absint( $this->business_context_service->resolve_business_id() );
		return $resolved > 0 ? $resolved : 1;
	}

	/**
	 * Resolve webhook table name.
	 *
	 * @return string
	 */
	protected function get_webhook_table_name() {
		$installer = new Webhook_Installer();
		return (string) $installer->get_table_name();
	}

	/**
	 * Resolve notifications table name.
	 *
	 * @return string
	 */
	protected function get_notifications_table_name() {
		$installer = new Notification_Storage_Installer();
		return (string) $installer->get_table_name();
	}

	/**
	 * Resolve logs table name.
	 *
	 * @return string
	 */
	protected function get_logs_table_name() {
		$installer = new Log_Installer();
		return (string) $installer->get_table_name();
	}
}

