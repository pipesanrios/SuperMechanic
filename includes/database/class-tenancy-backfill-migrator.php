<?php
/**
 * Tenancy backfill migrator for FASE 35A.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Applies idempotent business_id backfill for core tenancy tables.
 */
class Tenancy_Backfill_Migrator {
	/**
	 * Legacy-safe default business identifier.
	 */
	const DEFAULT_BUSINESS_ID = 1;

	/**
	 * Run the tenancy backfill in a deterministic order.
	 *
	 * @return array<string, mixed>
	 */
	public static function run() {
		global $wpdb;

		$tables           = Schema::get_tables();
		$default          = self::DEFAULT_BUSINESS_ID;
		$settings_service = new Settings_Service();
		$default_name     = (string) $settings_service->get_setting( 'business', 'business_name', 'Super Mechanic' );
		$default_name     = '' !== trim( $default_name ) ? sanitize_text_field( $default_name ) : 'Super Mechanic';
		$now              = current_time( 'mysql', true );
		$results = array(
			'updates' => array(),
			'errors'  => array(),
		);

		$queries = array(
			'businesses_default_upsert' => $wpdb->prepare(
				"INSERT INTO {$tables['businesses']} (id, slug, name, status, is_default, timezone, currency, created_at, updated_at)
				VALUES (1, %s, %s, 'active', 1, 'UTC', 'USD', %s, %s)
				ON DUPLICATE KEY UPDATE
					slug = VALUES(slug),
					name = CASE WHEN name IS NULL OR name = '' THEN VALUES(name) ELSE name END,
					status = 'active',
					is_default = 1,
					updated_at = VALUES(updated_at)",
				'default',
				$default_name,
				$now,
				$now
			),
			'clients_default' => "UPDATE {$tables['clients']} c
				SET c.business_id = {$default}
				WHERE c.business_id IS NULL OR c.business_id < 1",
			'vehicles_from_client' => "UPDATE {$tables['vehicles']} v
				LEFT JOIN {$tables['clients']} c ON c.id = v.client_id
				SET v.business_id = COALESCE(NULLIF(c.business_id, 0), {$default})
				WHERE v.business_id IS NULL
					OR v.business_id < 1
					OR (c.id IS NOT NULL AND v.business_id <> c.business_id)",
			'client_vehicles_from_roots' => "UPDATE {$tables['client_vehicles']} cv
				LEFT JOIN {$tables['clients']} c ON c.id = cv.client_id
				LEFT JOIN {$tables['vehicles']} v ON v.id = cv.vehicle_id
				SET cv.business_id = COALESCE(NULLIF(c.business_id, 0), NULLIF(v.business_id, 0), {$default})
				WHERE cv.business_id IS NULL
					OR cv.business_id < 1
					OR (c.id IS NOT NULL AND cv.business_id <> c.business_id)
					OR (v.id IS NOT NULL AND cv.business_id <> v.business_id)",
			'processes_from_roots' => "UPDATE {$tables['processes']} p
				LEFT JOIN {$tables['vehicles']} v ON v.id = p.vehicle_id
				LEFT JOIN {$tables['clients']} c ON c.id = p.client_id
				SET p.business_id = COALESCE(NULLIF(v.business_id, 0), NULLIF(c.business_id, 0), {$default})
				WHERE p.business_id IS NULL
					OR p.business_id < 1
					OR (v.id IS NOT NULL AND p.business_id <> v.business_id)
					OR (c.id IS NOT NULL AND p.business_id <> c.business_id)",
			'quotes_from_parents' => "UPDATE {$tables['quotes']} q
				LEFT JOIN {$tables['processes']} p ON p.id = q.process_id
				LEFT JOIN {$tables['clients']} c ON c.id = q.client_id
				SET q.business_id = COALESCE(NULLIF(p.business_id, 0), NULLIF(c.business_id, 0), {$default})
				WHERE q.business_id IS NULL
					OR q.business_id < 1
					OR (p.id IS NOT NULL AND q.business_id <> p.business_id)
					OR (c.id IS NOT NULL AND q.business_id <> c.business_id)",
			'invoices_from_parents' => "UPDATE {$tables['invoices']} i
				LEFT JOIN {$tables['quotes']} q ON q.id = i.quote_id
				LEFT JOIN {$tables['processes']} p ON p.id = i.process_id
				LEFT JOIN {$tables['clients']} c ON c.id = i.client_id
				SET i.business_id = COALESCE(NULLIF(q.business_id, 0), NULLIF(p.business_id, 0), NULLIF(c.business_id, 0), {$default})
				WHERE i.business_id IS NULL
					OR i.business_id < 1
					OR (q.id IS NOT NULL AND i.business_id <> q.business_id)
					OR (p.id IS NOT NULL AND i.business_id <> p.business_id)
					OR (c.id IS NOT NULL AND i.business_id <> c.business_id)",
			'payments_from_invoice' => "UPDATE {$tables['payments']} pay
				LEFT JOIN {$tables['invoices']} i ON i.id = pay.invoice_id
				SET pay.business_id = COALESCE(NULLIF(i.business_id, 0), {$default})
				WHERE pay.business_id IS NULL
					OR pay.business_id < 1
					OR (i.id IS NOT NULL AND pay.business_id <> i.business_id)",

			// FASE 35B - Bloque 1 (estructurales simples).
			'quote_items_from_quote' => "UPDATE {$tables['quote_items']} qi
				LEFT JOIN {$tables['quotes']} q ON q.id = qi.quote_id
				SET qi.business_id = COALESCE(NULLIF(q.business_id, 0), {$default})
				WHERE qi.business_id IS NULL
					OR qi.business_id < 1
					OR (q.id IS NOT NULL AND qi.business_id <> q.business_id)",
			'invoice_items_from_invoice' => "UPDATE {$tables['invoice_items']} ii
				LEFT JOIN {$tables['invoices']} i ON i.id = ii.invoice_id
				SET ii.business_id = COALESCE(NULLIF(i.business_id, 0), {$default})
				WHERE ii.business_id IS NULL
					OR ii.business_id < 1
					OR (i.id IS NOT NULL AND ii.business_id <> i.business_id)",
			'process_step_logs_from_process' => "UPDATE {$tables['process_step_logs']} l
				LEFT JOIN {$tables['processes']} p ON p.id = l.process_id
				SET l.business_id = COALESCE(NULLIF(p.business_id, 0), {$default})
				WHERE l.business_id IS NULL
					OR l.business_id < 1
					OR (p.id IS NOT NULL AND l.business_id <> p.business_id)",

			// FASE 35B - Bloque 2 (agenda / integración).
			'appointments_from_parents' => "UPDATE {$tables['appointments']} a
				LEFT JOIN {$tables['processes']} p ON p.id = a.process_id
				LEFT JOIN {$tables['clients']} c ON c.id = a.client_id
				LEFT JOIN {$tables['vehicles']} v ON v.id = a.vehicle_id
				SET a.business_id = COALESCE(NULLIF(p.business_id, 0), NULLIF(c.business_id, 0), NULLIF(v.business_id, 0), {$default})
				WHERE a.business_id IS NULL
					OR a.business_id < 1
					OR (p.id IS NOT NULL AND a.business_id <> p.business_id)
					OR (c.id IS NOT NULL AND a.business_id <> c.business_id)
					OR (v.id IS NOT NULL AND a.business_id <> v.business_id)",
			'appointment_sync_from_appointment' => "UPDATE {$tables['appointment_calendar_sync']} s
				LEFT JOIN {$tables['appointments']} a ON a.id = s.appointment_id
				SET s.business_id = COALESCE(NULLIF(a.business_id, 0), {$default})
				WHERE s.business_id IS NULL
					OR s.business_id < 1
					OR (a.id IS NOT NULL AND s.business_id <> a.business_id)",

			// FASE 35B - Bloque 3 (polimórficas / mayor riesgo).
			'attachments_from_structural_parent' => "UPDATE {$tables['attachments']} a
				LEFT JOIN {$tables['processes']} p_rel ON p_rel.id = a.process_id
				LEFT JOIN {$tables['processes']} p_obj ON a.object_type = 'process' AND p_obj.id = a.object_id
				LEFT JOIN {$tables['quotes']} q_obj ON a.object_type = 'quote' AND q_obj.id = a.object_id
				LEFT JOIN {$tables['invoices']} i_obj ON a.object_type = 'invoice' AND i_obj.id = a.object_id
				LEFT JOIN {$tables['appointments']} ap_obj ON a.object_type = 'appointment' AND ap_obj.id = a.object_id
				LEFT JOIN {$tables['payments']} pay_obj ON a.object_type = 'payment' AND pay_obj.id = a.object_id
				LEFT JOIN {$tables['clients']} c_rel ON c_rel.id = a.client_id
				LEFT JOIN {$tables['vehicles']} v_rel ON v_rel.id = a.vehicle_id
				SET a.business_id = COALESCE(
					NULLIF(p_rel.business_id, 0),
					NULLIF(p_obj.business_id, 0),
					NULLIF(q_obj.business_id, 0),
					NULLIF(i_obj.business_id, 0),
					NULLIF(ap_obj.business_id, 0),
					NULLIF(pay_obj.business_id, 0),
					NULLIF(c_rel.business_id, 0),
					NULLIF(v_rel.business_id, 0),
					{$default}
				)
				WHERE a.business_id IS NULL
					OR a.business_id < 1
					OR (p_rel.id IS NOT NULL AND a.business_id <> p_rel.business_id)
					OR (p_obj.id IS NOT NULL AND a.business_id <> p_obj.business_id)
					OR (q_obj.id IS NOT NULL AND a.business_id <> q_obj.business_id)
					OR (i_obj.id IS NOT NULL AND a.business_id <> i_obj.business_id)
					OR (ap_obj.id IS NOT NULL AND a.business_id <> ap_obj.business_id)
					OR (pay_obj.id IS NOT NULL AND a.business_id <> pay_obj.business_id)
					OR (c_rel.id IS NOT NULL AND a.business_id <> c_rel.business_id)
					OR (v_rel.id IS NOT NULL AND a.business_id <> v_rel.business_id)",
			'comments_from_structural_parent' => "UPDATE {$tables['comments']} cm
				LEFT JOIN {$tables['processes']} p_rel ON p_rel.id = cm.process_id
				LEFT JOIN {$tables['processes']} p_obj ON cm.object_type = 'process' AND p_obj.id = cm.object_id
				LEFT JOIN {$tables['quotes']} q_obj ON cm.object_type = 'quote' AND q_obj.id = cm.object_id
				LEFT JOIN {$tables['invoices']} i_obj ON cm.object_type = 'invoice' AND i_obj.id = cm.object_id
				LEFT JOIN {$tables['attachments']} at_obj ON cm.object_type = 'attachment' AND at_obj.id = cm.object_id
				LEFT JOIN {$tables['clients']} c_rel ON c_rel.id = cm.client_id
				LEFT JOIN {$tables['vehicles']} v_rel ON v_rel.id = cm.vehicle_id
				SET cm.business_id = COALESCE(
					NULLIF(p_rel.business_id, 0),
					NULLIF(p_obj.business_id, 0),
					NULLIF(q_obj.business_id, 0),
					NULLIF(i_obj.business_id, 0),
					NULLIF(at_obj.business_id, 0),
					NULLIF(c_rel.business_id, 0),
					NULLIF(v_rel.business_id, 0),
					{$default}
				)
				WHERE cm.business_id IS NULL
					OR cm.business_id < 1
					OR (p_rel.id IS NOT NULL AND cm.business_id <> p_rel.business_id)
					OR (p_obj.id IS NOT NULL AND cm.business_id <> p_obj.business_id)
					OR (q_obj.id IS NOT NULL AND cm.business_id <> q_obj.business_id)
					OR (i_obj.id IS NOT NULL AND cm.business_id <> i_obj.business_id)
					OR (at_obj.id IS NOT NULL AND cm.business_id <> at_obj.business_id)
					OR (c_rel.id IS NOT NULL AND cm.business_id <> c_rel.business_id)
					OR (v_rel.id IS NOT NULL AND cm.business_id <> v_rel.business_id)",
			'notifications_from_structural_parent' => "UPDATE {$tables['notifications']} n
				LEFT JOIN {$tables['processes']} p_rel ON p_rel.id = n.process_id
				LEFT JOIN {$tables['processes']} p_obj ON n.object_type = 'process' AND p_obj.id = n.object_id
				LEFT JOIN {$tables['quotes']} q_obj ON n.object_type = 'quote' AND q_obj.id = n.object_id
				LEFT JOIN {$tables['invoices']} i_obj ON n.object_type = 'invoice' AND i_obj.id = n.object_id
				LEFT JOIN {$tables['attachments']} at_obj ON n.object_type = 'attachment' AND at_obj.id = n.object_id
				LEFT JOIN {$tables['appointments']} ap_obj ON n.object_type = 'appointment' AND ap_obj.id = n.object_id
				LEFT JOIN {$tables['comments']} cm_obj ON n.object_type = 'comment' AND cm_obj.id = n.object_id
				LEFT JOIN {$tables['clients']} rc ON n.recipient_type = 'client' AND rc.id = n.recipient_id
				SET n.business_id = COALESCE(
					NULLIF(p_rel.business_id, 0),
					NULLIF(p_obj.business_id, 0),
					NULLIF(q_obj.business_id, 0),
					NULLIF(i_obj.business_id, 0),
					NULLIF(at_obj.business_id, 0),
					NULLIF(ap_obj.business_id, 0),
					NULLIF(cm_obj.business_id, 0),
					NULLIF(rc.business_id, 0),
					{$default}
				)
				WHERE n.business_id IS NULL
					OR n.business_id < 1
					OR (p_rel.id IS NOT NULL AND n.business_id <> p_rel.business_id)
					OR (p_obj.id IS NOT NULL AND n.business_id <> p_obj.business_id)
					OR (q_obj.id IS NOT NULL AND n.business_id <> q_obj.business_id)
					OR (i_obj.id IS NOT NULL AND n.business_id <> i_obj.business_id)
					OR (at_obj.id IS NOT NULL AND n.business_id <> at_obj.business_id)
					OR (ap_obj.id IS NOT NULL AND n.business_id <> ap_obj.business_id)
					OR (cm_obj.id IS NOT NULL AND n.business_id <> cm_obj.business_id)
					OR (rc.id IS NOT NULL AND n.business_id <> rc.business_id)",
		);

		$tenant_tables_for_orphan_repair = array(
			'clients',
			'vehicles',
			'client_vehicles',
			'processes',
			'quotes',
			'quote_items',
			'invoices',
			'invoice_items',
			'payments',
			'appointments',
			'appointment_calendar_sync',
			'process_step_logs',
			'attachments',
			'comments',
			'notifications',
		);

		foreach ( $tenant_tables_for_orphan_repair as $table_key ) {
			if ( empty( $tables[ $table_key ] ) ) {
				continue;
			}

			$table_name                              = $tables[ $table_key ];
			$queries[ 'repair_orphan_' . $table_key ] = "UPDATE {$table_name} t
				LEFT JOIN {$tables['businesses']} b ON b.id = t.business_id
				SET t.business_id = {$default}
				WHERE t.business_id IS NULL
					OR t.business_id < 1
					OR b.id IS NULL";
		}

		foreach ( $queries as $key => $sql ) {
			$executed = $wpdb->query( $sql );

			if ( false === $executed ) {
				$results['errors'][ $key ] = $wpdb->last_error;
				continue;
			}

			$results['updates'][ $key ] = (int) $executed;
		}

		return $results;
	}
}
