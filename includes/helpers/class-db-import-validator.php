<?php
/**
 * DB import validator.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Validates uploaded JSON backups before any DB transaction is started.
 */
class DB_Import_Validator {
	/**
	 * Validate uploaded backup file payload and return normalized structure.
	 *
	 * @param array<string, mixed> $uploaded_file      File entry from $_FILES.
	 * @param string               $expected_schema    Expected schema version.
	 * @param array<string, string> $allowed_table_map Allowed schema table map.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function validate_uploaded_json_backup( array $uploaded_file, $expected_schema, array $allowed_table_map ) {
		$tmp_name = isset( $uploaded_file['tmp_name'] ) ? (string) $uploaded_file['tmp_name'] : '';
		$error    = isset( $uploaded_file['error'] ) ? (int) $uploaded_file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			return new \WP_Error( 'sm_db_import_upload_error', __( 'Could not upload backup file.', 'super-mechanic' ) );
		}

		if ( '' === $tmp_name || ! is_readable( $tmp_name ) ) {
			return new \WP_Error( 'sm_db_import_file_unreadable', __( 'Backup file is not readable.', 'super-mechanic' ) );
		}

		$raw = file_get_contents( $tmp_name );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new \WP_Error( 'sm_db_import_empty_file', __( 'Backup file is empty.', 'super-mechanic' ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'sm_db_import_invalid_json', __( 'Backup file does not contain valid JSON.', 'super-mechanic' ) );
		}

		return $this->validate_backup_payload( $decoded, (string) $expected_schema, $allowed_table_map );
	}

	/**
	 * Validate parsed JSON payload shape and compatibility.
	 *
	 * @param array<string, mixed>  $payload           Parsed payload.
	 * @param string                $expected_schema   Expected schema version.
	 * @param array<string, string> $allowed_table_map Allowed schema table map.
	 * @return array<string, mixed>|\WP_Error
	 */
	protected function validate_backup_payload( array $payload, $expected_schema, array $allowed_table_map ) {
		$schema_version = isset( $payload['schema_version'] ) ? (string) $payload['schema_version'] : '';
		if ( '' === $schema_version ) {
			return new \WP_Error( 'sm_db_import_missing_schema_version', __( 'Backup file is missing schema_version.', 'super-mechanic' ) );
		}

		if ( $schema_version !== (string) $expected_schema ) {
			return new \WP_Error(
				'sm_db_import_schema_mismatch',
				sprintf(
					/* translators: 1: expected schema version, 2: provided schema version */
					__( 'Backup schema mismatch. Expected %1$s but got %2$s.', 'super-mechanic' ),
					(string) $expected_schema,
					$schema_version
				)
			);
		}

		$tables_payload = isset( $payload['tables'] ) ? $payload['tables'] : null;
		if ( ! is_array( $tables_payload ) ) {
			return new \WP_Error( 'sm_db_import_missing_tables', __( 'Backup file is missing tables payload.', 'super-mechanic' ) );
		}

		$allowed_keys = array_keys( $allowed_table_map );
		sort( $allowed_keys );

		$payload_keys = array_keys( $tables_payload );
		$payload_keys = array_filter(
			$payload_keys,
			static function ( $key ) {
				return is_string( $key );
			}
		);
		sort( $payload_keys );

		if ( $allowed_keys !== $payload_keys ) {
			return new \WP_Error( 'sm_db_import_table_set_invalid', __( 'Backup table set does not match current plugin schema.', 'super-mechanic' ) );
		}

		$normalized_tables = array();
		foreach ( $allowed_keys as $table_key ) {
			$table_entry = isset( $tables_payload[ $table_key ] ) && is_array( $tables_payload[ $table_key ] ) ? $tables_payload[ $table_key ] : null;
			if ( ! is_array( $table_entry ) ) {
				return new \WP_Error(
					'sm_db_import_table_entry_invalid',
					sprintf(
						/* translators: %s: table key */
						__( 'Invalid payload for table %s.', 'super-mechanic' ),
						(string) $table_key
					)
				);
			}

			$rows = isset( $table_entry['rows'] ) && is_array( $table_entry['rows'] ) ? $table_entry['rows'] : null;
			if ( ! is_array( $rows ) ) {
				return new \WP_Error(
					'sm_db_import_rows_invalid',
					sprintf(
						/* translators: %s: table key */
						__( 'Invalid rows payload for table %s.', 'super-mechanic' ),
						(string) $table_key
					)
				);
			}

			$normalized_rows = array();
			foreach ( $rows as $index => $row ) {
				if ( ! is_array( $row ) ) {
					return new \WP_Error(
						'sm_db_import_row_invalid',
						sprintf(
							/* translators: 1: table key, 2: row index */
							__( 'Invalid row structure for table %1$s at index %2$d.', 'super-mechanic' ),
							(string) $table_key,
							(int) $index
						)
					);
				}

				$normalized_row = array();
				foreach ( $row as $column => $value ) {
					if ( ! is_string( $column ) || '' === $column ) {
						return new \WP_Error(
							'sm_db_import_column_invalid',
							sprintf(
								/* translators: %s: table key */
								__( 'Invalid column name in table %s.', 'super-mechanic' ),
								(string) $table_key
							)
						);
					}

					if ( is_array( $value ) || is_object( $value ) ) {
						return new \WP_Error(
							'sm_db_import_value_invalid',
							sprintf(
								/* translators: 1: table key, 2: column name */
								__( 'Invalid value type in table %1$s column %2$s.', 'super-mechanic' ),
								(string) $table_key,
								(string) $column
							)
						);
					}

					$normalized_row[ $column ] = $value;
				}

				$normalized_rows[] = $normalized_row;
			}

			$normalized_tables[ $table_key ] = array(
				'table' => (string) $allowed_table_map[ $table_key ],
				'rows'  => $normalized_rows,
			);
		}

		return array(
			'generated_at'   => isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
			'schema_version' => $schema_version,
			'plugin_version' => isset( $payload['plugin_version'] ) ? (string) $payload['plugin_version'] : '',
			'tables'         => $normalized_tables,
		);
	}
}
