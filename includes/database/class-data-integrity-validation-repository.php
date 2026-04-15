<?php
/**
 * Data integrity validation repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates SQL integrity/orphan checks for core runtime entities.
 */
class Data_Integrity_Validation_Repository {
	/**
	 * Run all configured integrity checks.
	 *
	 * @param int $sample_limit Max sample IDs per check.
	 * @return array<int,array<string,mixed>>
	 */
	public function run_integrity_checks( $sample_limit = 20 ) {
		$sample_limit = max( 1, min( 100, absint( $sample_limit ) ) );
		$tables       = Schema::get_tables();
		global $wpdb;

		$checks = array(
			array(
				'key'         => 'vehicles_without_client',
				'description' => 'Vehicles reference missing clients in same business.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['vehicles']} v
					LEFT JOIN {$tables['clients']} c
						ON c.id = v.client_id AND c.business_id = v.business_id
					WHERE v.client_id > 0 AND c.id IS NULL",
				'sample_sql'  => "SELECT v.id FROM {$tables['vehicles']} v
					LEFT JOIN {$tables['clients']} c
						ON c.id = v.client_id AND c.business_id = v.business_id
					WHERE v.client_id > 0 AND c.id IS NULL
					ORDER BY v.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'client_vehicle_links_missing_core_entities',
				'description' => 'Ownership links point to missing client or vehicle.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['client_vehicles']} cv
					LEFT JOIN {$tables['clients']} c
						ON c.id = cv.client_id AND c.business_id = cv.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = cv.vehicle_id AND v.business_id = cv.business_id
					WHERE c.id IS NULL OR v.id IS NULL",
				'sample_sql'  => "SELECT cv.id FROM {$tables['client_vehicles']} cv
					LEFT JOIN {$tables['clients']} c
						ON c.id = cv.client_id AND c.business_id = cv.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = cv.vehicle_id AND v.business_id = cv.business_id
					WHERE c.id IS NULL OR v.id IS NULL
					ORDER BY cv.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'processes_with_invalid_core_relations',
				'description' => 'Processes reference missing client/vehicle or invalid maintenance vehicle relation.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['processes']} p
					LEFT JOIN {$tables['clients']} c
						ON c.id = p.client_id AND c.business_id = p.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = p.vehicle_id AND v.business_id = p.business_id
					WHERE (p.client_id > 0 AND c.id IS NULL)
						OR (p.vehicle_id > 0 AND v.id IS NULL)
						OR (p.process_type = 'maintenance' AND p.vehicle_id <= 0)",
				'sample_sql'  => "SELECT p.id FROM {$tables['processes']} p
					LEFT JOIN {$tables['clients']} c
						ON c.id = p.client_id AND c.business_id = p.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = p.vehicle_id AND v.business_id = p.business_id
					WHERE (p.client_id > 0 AND c.id IS NULL)
						OR (p.vehicle_id > 0 AND v.id IS NULL)
						OR (p.process_type = 'maintenance' AND p.vehicle_id <= 0)
					ORDER BY p.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'process_logs_without_process',
				'description' => 'Process logs reference missing process or cross-business process mismatch.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['process_step_logs']} l
					LEFT JOIN {$tables['processes']} p
						ON p.id = l.process_id
					WHERE p.id IS NULL OR p.business_id <> l.business_id",
				'sample_sql'  => "SELECT l.id FROM {$tables['process_step_logs']} l
					LEFT JOIN {$tables['processes']} p
						ON p.id = l.process_id
					WHERE p.id IS NULL OR p.business_id <> l.business_id
					ORDER BY l.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'crm_meta_without_client',
				'description' => 'Client CRM meta references missing client.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['client_crm_meta']} m
					LEFT JOIN {$tables['clients']} c
						ON c.id = m.client_id AND c.business_id = m.business_id
					WHERE c.id IS NULL",
				'sample_sql'  => "SELECT m.id FROM {$tables['client_crm_meta']} m
					LEFT JOIN {$tables['clients']} c
						ON c.id = m.client_id AND c.business_id = m.business_id
					WHERE c.id IS NULL
					ORDER BY m.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'crm_pipeline_with_invalid_relations',
				'description' => 'CRM pipeline records reference missing client/vehicle/process relations.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['crm_pipeline']} cp
					LEFT JOIN {$tables['clients']} c
						ON c.id = cp.client_id AND c.business_id = cp.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = cp.vehicle_id AND v.business_id = cp.business_id
					LEFT JOIN {$tables['processes']} p
						ON p.id = cp.process_id AND p.business_id = cp.business_id
					WHERE c.id IS NULL
						OR (cp.vehicle_id > 0 AND v.id IS NULL)
						OR (cp.process_id > 0 AND p.id IS NULL)",
				'sample_sql'  => "SELECT cp.id FROM {$tables['crm_pipeline']} cp
					LEFT JOIN {$tables['clients']} c
						ON c.id = cp.client_id AND c.business_id = cp.business_id
					LEFT JOIN {$tables['vehicles']} v
						ON v.id = cp.vehicle_id AND v.business_id = cp.business_id
					LEFT JOIN {$tables['processes']} p
						ON p.id = cp.process_id AND p.business_id = cp.business_id
					WHERE c.id IS NULL
						OR (cp.vehicle_id > 0 AND v.id IS NULL)
						OR (cp.process_id > 0 AND p.id IS NULL)
					ORDER BY cp.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'crm_tasks_without_pipeline',
				'description' => 'CRM tasks reference missing pipeline.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['crm_tasks']} ct
					LEFT JOIN {$tables['crm_pipeline']} cp
						ON cp.id = ct.crm_pipeline_id AND cp.business_id = ct.business_id
					WHERE cp.id IS NULL",
				'sample_sql'  => "SELECT ct.id FROM {$tables['crm_tasks']} ct
					LEFT JOIN {$tables['crm_pipeline']} cp
						ON cp.id = ct.crm_pipeline_id AND cp.business_id = ct.business_id
					WHERE cp.id IS NULL
					ORDER BY ct.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'crm_alerts_without_pipeline',
				'description' => 'CRM alerts reference missing pipeline.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['crm_alerts']} ca
					LEFT JOIN {$tables['crm_pipeline']} cp
						ON cp.id = ca.crm_pipeline_id AND cp.business_id = ca.business_id
					WHERE cp.id IS NULL",
				'sample_sql'  => "SELECT ca.id FROM {$tables['crm_alerts']} ca
					LEFT JOIN {$tables['crm_pipeline']} cp
						ON cp.id = ca.crm_pipeline_id AND cp.business_id = ca.business_id
					WHERE cp.id IS NULL
					ORDER BY ca.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'payments_without_invoice',
				'description' => 'Payments reference missing invoice or cross-business mismatch.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['payments']} p
					LEFT JOIN {$tables['invoices']} i
						ON i.id = p.invoice_id
					WHERE i.id IS NULL OR i.business_id <> p.business_id",
				'sample_sql'  => "SELECT p.id FROM {$tables['payments']} p
					LEFT JOIN {$tables['invoices']} i
						ON i.id = p.invoice_id
					WHERE i.id IS NULL OR i.business_id <> p.business_id
					ORDER BY p.id ASC
					LIMIT {$sample_limit}",
			),
			array(
				'key'         => 'invoice_items_without_invoice',
				'description' => 'Invoice items reference missing invoice or cross-business mismatch.',
				'count_sql'   => "SELECT COUNT(*) FROM {$tables['invoice_items']} ii
					LEFT JOIN {$tables['invoices']} i
						ON i.id = ii.invoice_id
					WHERE i.id IS NULL OR i.business_id <> ii.business_id",
				'sample_sql'  => "SELECT ii.id FROM {$tables['invoice_items']} ii
					LEFT JOIN {$tables['invoices']} i
						ON i.id = ii.invoice_id
					WHERE i.id IS NULL OR i.business_id <> ii.business_id
					ORDER BY ii.id ASC
					LIMIT {$sample_limit}",
			),
		);

		$results = array();
		foreach ( $checks as $check ) {
			$key         = isset( $check['key'] ) ? sanitize_key( (string) $check['key'] ) : '';
			$description = isset( $check['description'] ) ? sanitize_text_field( (string) $check['description'] ) : '';
			$count_sql   = isset( $check['count_sql'] ) ? (string) $check['count_sql'] : '';
			$sample_sql  = isset( $check['sample_sql'] ) ? (string) $check['sample_sql'] : '';

			if ( '' === $key || '' === $count_sql || '' === $sample_sql ) {
				continue;
			}

			$count_raw = $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static integrity checks over trusted table names.
			$count     = is_numeric( $count_raw ) ? max( 0, (int) $count_raw ) : 0;

			$sample_rows = $wpdb->get_col( $sample_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static integrity checks over trusted table names.
			$sample_ids  = is_array( $sample_rows ) ? array_values( array_unique( array_map( 'absint', $sample_rows ) ) ) : array();

			$results[] = array(
				'key'         => $key,
				'description' => $description,
				'count'       => $count,
				'sample_ids'  => $sample_ids,
			);
		}

		return $results;
	}
}

