<?php
/**
 * DB security repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates SQL for DB export/reset administrative actions.
 */
class DB_Security_Repository {
	/**
	 * Export all plugin table rows.
	 *
	 * @return array<string, array<string, mixed>>|\WP_Error
	 */
	public function export_plugin_data() {
		global $wpdb;

		$tables = Schema::get_tables();
		$export = array();

		foreach ( $tables as $key => $table_name ) {
			$rows = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names come from trusted schema map.

			if ( null === $rows ) {
				return new \WP_Error(
					'sm_db_export_failed',
					sprintf(
						/* translators: %s: table key */
						__( 'Could not export table: %s.', 'super-mechanic' ),
						(string) $key
					)
				);
			}

			$export[ $key ] = array(
				'table' => $table_name,
				'count' => count( $rows ),
				'rows'  => is_array( $rows ) ? $rows : array(),
			);
		}

		return $export;
	}

	/**
	 * Reset plugin table data and restore default business baseline.
	 *
	 * @param string $default_business_name Default business display name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reset_plugin_data( $default_business_name ) {
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
	 * Validate import rows against current DB table columns before any transaction.
	 *
	 * @param array<string, array<string, mixed>> $tables_payload Validated table payload.
	 * @return true|\WP_Error
	 */
	public function validate_import_table_rows( array $tables_payload ) {
		$tables = Schema::get_tables();

		foreach ( $tables as $table_key => $table_name ) {
			if ( ! isset( $tables_payload[ $table_key ] ) || ! is_array( $tables_payload[ $table_key ] ) ) {
				return new \WP_Error(
					'sm_db_import_table_payload_missing',
					sprintf(
						/* translators: %s: table key */
						__( 'Missing import payload for table %s.', 'super-mechanic' ),
						(string) $table_key
					)
				);
			}

			$rows    = isset( $tables_payload[ $table_key ]['rows'] ) && is_array( $tables_payload[ $table_key ]['rows'] ) ? $tables_payload[ $table_key ]['rows'] : array();
			$columns = $this->get_table_columns( $table_name );
			if ( is_wp_error( $columns ) ) {
				return $columns;
			}

			foreach ( $rows as $index => $row ) {
				if ( ! is_array( $row ) ) {
					return new \WP_Error(
						'sm_db_import_row_payload_invalid',
						sprintf(
							/* translators: 1: table key, 2: row index */
							__( 'Invalid row payload in table %1$s at index %2$d.', 'super-mechanic' ),
							(string) $table_key,
							(int) $index
						)
					);
				}

				$row_columns = array_keys( $row );
				sort( $row_columns );
				$expected_columns = $columns;
				sort( $expected_columns );

				if ( $row_columns !== $expected_columns ) {
					return new \WP_Error(
						'sm_db_import_row_columns_mismatch',
						sprintf(
							/* translators: 1: table key, 2: row index */
							__( 'Import row columns do not match current schema for table %1$s at index %2$d.', 'super-mechanic' ),
							(string) $table_key,
							(int) $index
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Import plugin data from canonical JSON payload tables.
	 *
	 * @param array<string, array<string, mixed>> $tables_payload         Validated table payload.
	 * @param string                              $default_business_name   Default business display name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import_plugin_data( array $tables_payload, $default_business_name ) {
		global $wpdb;

		$tables       = Schema::get_tables();
		$delete_map   = $this->get_reset_delete_order( $tables );
		$insert_map   = $this->get_import_insert_order( $tables );
		$deleted      = array();
		$inserted     = array();

		$started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $started ) {
			return new \WP_Error( 'sm_db_import_transaction_start_failed', __( 'Could not start database transaction for import.', 'super-mechanic' ) );
		}

		foreach ( $delete_map as $table_key => $table_name ) {
			$result = $wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names come from trusted schema map.
			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error(
					'sm_db_import_delete_failed',
					sprintf(
						/* translators: %s: table key */
						__( 'Could not clear table %s during import.', 'super-mechanic' ),
						(string) $table_key
					)
				);
			}

			$deleted[ $table_key ] = (int) $result;
		}

		foreach ( $insert_map as $table_key => $table_name ) {
			$rows = isset( $tables_payload[ $table_key ]['rows'] ) && is_array( $tables_payload[ $table_key ]['rows'] ) ? $tables_payload[ $table_key ]['rows'] : array();

			$inserted_count = 0;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					$wpdb->query( 'ROLLBACK' );
					return new \WP_Error(
						'sm_db_import_row_invalid',
						sprintf(
							/* translators: %s: table key */
							__( 'Invalid row while importing table %s.', 'super-mechanic' ),
							(string) $table_key
						)
					);
				}

				$normalized_row = $this->normalize_import_row( $row );
				$ok             = $wpdb->insert( $table_name, $normalized_row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Import write path in repository.

				if ( false === $ok ) {
					$wpdb->query( 'ROLLBACK' );
					return new \WP_Error(
						'sm_db_import_insert_failed',
						sprintf(
							/* translators: %s: table key */
							__( 'Could not import row into table %s.', 'super-mechanic' ),
							(string) $table_key
						)
					);
				}

				++$inserted_count;
			}

			$inserted[ $table_key ] = $inserted_count;
		}

		$baseline_ok = $this->ensure_default_business_baseline( $tables, $default_business_name );
		if ( ! $baseline_ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'sm_db_import_baseline_failed', __( 'Could not preserve default business baseline after import.', 'super-mechanic' ) );
		}

		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'sm_db_import_commit_failed', __( 'Could not commit database import.', 'super-mechanic' ) );
		}

		return array(
			'deleted'               => $deleted,
			'inserted'              => $inserted,
			'default_business_seed' => true,
		);
	}

	/**
	 * Build reset delete order from child-like tables to parent-like tables.
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
			'processes',
			'flow_steps',
			'flows',
			'client_vehicles',
			'vehicles',
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
	 * Build deterministic import insert order from parent-like tables to child-like tables.
	 *
	 * @param array<string, string> $tables Schema map.
	 * @return array<string, string>
	 */
	protected function get_import_insert_order( array $tables ) {
		$ordered_keys = array(
			'businesses',
			'clients',
			'vehicles',
			'client_vehicles',
			'flows',
			'flow_steps',
			'processes',
			'appointments',
			'appointment_calendar_sync',
			'process_step_logs',
			'process_parts',
			'process_meta',
			'maintenance',
			'maintenance_parts',
			'maintenance_labor',
			'pre_delivery',
			'paperwork',
			'paperwork_items',
			'quotes',
			'quote_items',
			'invoices',
			'invoice_items',
			'payments',
			'attachments',
			'comments',
			'notifications',
			'webhooks',
			'webhook_deliveries',
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
			$name = 'Super Mechanic';
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

	/**
	 * Ensure default business baseline exists after import.
	 *
	 * @param array<string, string> $tables                Schema map.
	 * @param string                $default_business_name Default name.
	 * @return bool
	 */
	protected function ensure_default_business_baseline( array $tables, $default_business_name ) {
		global $wpdb;

		if ( empty( $tables['businesses'] ) ) {
			return false;
		}

		$businesses_table = $tables['businesses'];
		$default_id       = (int) $wpdb->get_var( "SELECT id FROM {$businesses_table} WHERE is_default = 1 LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query over trusted table name.
		if ( $default_id > 0 ) {
			return true;
		}

		return $this->seed_default_business( $tables, $default_business_name );
	}

	/**
	 * Get table column names.
	 *
	 * @param string $table_name Physical table name.
	 * @return array<int, string>|\WP_Error
	 */
	protected function get_table_columns( $table_name ) {
		global $wpdb;

		$columns_raw = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names come from trusted schema map.
		if ( ! is_array( $columns_raw ) ) {
			return new \WP_Error(
				'sm_db_import_columns_failed',
				sprintf(
					/* translators: %s: table name */
					__( 'Could not read DB columns for table %s.', 'super-mechanic' ),
					(string) $table_name
				)
			);
		}

		$columns = array();
		foreach ( $columns_raw as $column_data ) {
			if ( isset( $column_data['Field'] ) && is_string( $column_data['Field'] ) ) {
				$columns[] = $column_data['Field'];
			}
		}

		if ( empty( $columns ) ) {
			return new \WP_Error(
				'sm_db_import_columns_empty',
				sprintf(
					/* translators: %s: table name */
					__( 'No columns found for table %s.', 'super-mechanic' ),
					(string) $table_name
				)
			);
		}

		return $columns;
	}

	/**
	 * Normalize imported row values.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	protected function normalize_import_row( array $row ) {
		$normalized = array();

		foreach ( $row as $column => $value ) {
			if ( null === $value ) {
				$normalized[ $column ] = null;
				continue;
			}

			if ( is_bool( $value ) ) {
				$normalized[ $column ] = $value ? '1' : '0';
				continue;
			}

			if ( is_scalar( $value ) ) {
				$normalized[ $column ] = (string) $value;
				continue;
			}

			$normalized[ $column ] = wp_json_encode( $value );
		}

		return $normalized;
	}
}
