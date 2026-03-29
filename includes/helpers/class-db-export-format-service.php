<?php
/**
 * DB export format service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Builds downloadable DB export files in supported formats.
 */
class DB_Export_Format_Service {
	/**
	 * Build an export file payload by format.
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @param string               $format  Requested format.
	 * @return array<string, string>|\WP_Error
	 */
	public function build_export_file( array $payload, $format ) {
		$format = sanitize_key( (string) $format );

		if ( '' === $format ) {
			$format = 'json';
		}

		switch ( $format ) {
			case 'json':
				return $this->build_json_file( $payload );
			case 'csv':
				return $this->build_csv_zip_file( $payload );
			case 'excel':
				return $this->build_excel_file( $payload );
			default:
				return new \WP_Error( 'sm_db_export_invalid_format', __( 'Invalid DB export format.', 'super-mechanic' ) );
		}
	}

	/**
	 * Build JSON export download.
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @return array<string, string>|\WP_Error
	 */
	protected function build_json_file( array $payload ) {
		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) || '' === $json ) {
			return new \WP_Error( 'sm_db_export_json_encode_failed', __( 'Could not encode JSON export payload.', 'super-mechanic' ) );
		}

		return array(
			'filename' => $this->build_filename( 'json' ),
			'mime'     => 'application/json; charset=utf-8',
			'content'  => $json,
		);
	}

	/**
	 * Build CSV ZIP export download (one CSV per table + manifest).
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @return array<string, string>|\WP_Error
	 */
	protected function build_csv_zip_file( array $payload ) {
		$manifest = $this->build_manifest( $payload, 'csv' );
		$tables = isset( $payload['tables'] ) && is_array( $payload['tables'] ) ? $payload['tables'] : array();
		$files  = array(
			'manifest.json' => (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT ),
		);

		foreach ( $tables as $table_key => $table_payload ) {
			if ( ! is_string( $table_key ) || ! is_array( $table_payload ) ) {
				continue;
			}

			$csv = $this->build_csv_table_content( $table_payload );
			if ( is_wp_error( $csv ) ) {
				return $csv;
			}

			$files[ sanitize_file_name( $table_key ) . '.csv' ] = $csv;
		}

		$content = $this->build_zip_content( $files );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return array(
			'filename' => $this->build_filename( 'zip' ),
			'mime'     => 'application/zip',
			'content'  => $content,
		);
	}

	/**
	 * Build Excel-compatible XML export download.
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @return array<string, string>|\WP_Error
	 */
	protected function build_excel_file( array $payload ) {
		$xml    = array();
		$xml[]  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml[]  = '<?mso-application progid="Excel.Sheet"?>';
		$xml[]  = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
		$xml[]  = '<Styles><Style ss:ID="header"><Font ss:Bold="1"/></Style></Styles>';
		$xml[]  = $this->build_excel_metadata_sheet( $payload );

		$tables = isset( $payload['tables'] ) && is_array( $payload['tables'] ) ? $payload['tables'] : array();
		foreach ( $tables as $table_key => $table_payload ) {
			if ( ! is_string( $table_key ) || ! is_array( $table_payload ) ) {
				continue;
			}

			$xml[] = $this->build_excel_table_sheet( $table_key, $table_payload );
		}

		$xml[] = '</Workbook>';

		return array(
			'filename' => $this->build_filename( 'xml' ),
			'mime'     => 'application/vnd.ms-excel',
			'content'  => implode( '', $xml ),
		);
	}

	/**
	 * Build manifest payload.
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @param string               $format  Export format.
	 * @return array<string, mixed>
	 */
	protected function build_manifest( array $payload, $format ) {
		$tables   = isset( $payload['tables'] ) && is_array( $payload['tables'] ) ? $payload['tables'] : array();
		$manifest = array(
			'generated_at'   => isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '',
			'schema_version' => isset( $payload['schema_version'] ) ? (string) $payload['schema_version'] : '',
			'plugin_version' => isset( $payload['plugin_version'] ) ? (string) $payload['plugin_version'] : '',
			'format'         => (string) $format,
			'tables'         => array(),
		);

		foreach ( $tables as $table_key => $table_payload ) {
			if ( ! is_string( $table_key ) || ! is_array( $table_payload ) ) {
				continue;
			}

			$count = isset( $table_payload['count'] ) ? (int) $table_payload['count'] : 0;
			$table = isset( $table_payload['table'] ) ? (string) $table_payload['table'] : '';

			$manifest['tables'][ $table_key ] = array(
				'table' => $table,
				'count' => $count,
			);
		}

		return $manifest;
	}

	/**
	 * Build a table CSV string from canonical table payload.
	 *
	 * @param array<string, mixed> $table_payload Table payload.
	 * @return string|\WP_Error
	 */
	protected function build_csv_table_content( array $table_payload ) {
		$rows = isset( $table_payload['rows'] ) && is_array( $table_payload['rows'] ) ? $table_payload['rows'] : array();

		$headers = $this->collect_headers_from_rows( $rows );
		$stream  = fopen( 'php://temp', 'w+' );
		if ( false === $stream ) {
			return new \WP_Error( 'sm_db_export_csv_stream_failed', __( 'Could not create CSV output stream.', 'super-mechanic' ) );
		}

		if ( ! empty( $headers ) ) {
			fputcsv( $stream, $headers );
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$csv_row = array();
			foreach ( $headers as $header ) {
				$value     = isset( $row[ $header ] ) ? $row[ $header ] : null;
				$csv_row[] = $this->scalar_to_string( $value );
			}

			fputcsv( $stream, $csv_row );
		}

		rewind( $stream );
		$content = stream_get_contents( $stream );
		fclose( $stream );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Build metadata sheet.
	 *
	 * @param array<string, mixed> $payload Canonical export payload.
	 * @return string
	 */
	protected function build_excel_metadata_sheet( array $payload ) {
		$generated_at   = isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '';
		$schema_version = isset( $payload['schema_version'] ) ? (string) $payload['schema_version'] : '';
		$plugin_version = isset( $payload['plugin_version'] ) ? (string) $payload['plugin_version'] : '';

		return '<Worksheet ss:Name="metadata"><Table>'
			. '<Row><Cell ss:StyleID="header"><Data ss:Type="String">key</Data></Cell><Cell ss:StyleID="header"><Data ss:Type="String">value</Data></Cell></Row>'
			. $this->excel_kv_row( 'generated_at', $generated_at )
			. $this->excel_kv_row( 'schema_version', $schema_version )
			. $this->excel_kv_row( 'plugin_version', $plugin_version )
			. '</Table></Worksheet>';
	}

	/**
	 * Build table worksheet.
	 *
	 * @param string               $table_key     Table key.
	 * @param array<string, mixed> $table_payload Table payload.
	 * @return string
	 */
	protected function build_excel_table_sheet( $table_key, array $table_payload ) {
		$sheet_name = preg_replace( '/[^A-Za-z0-9_\-]/', '_', (string) $table_key );
		if ( ! is_string( $sheet_name ) || '' === $sheet_name ) {
			$sheet_name = 'table';
		}
		$sheet_name = substr( $sheet_name, 0, 31 );

		$rows    = isset( $table_payload['rows'] ) && is_array( $table_payload['rows'] ) ? $table_payload['rows'] : array();
		$headers = $this->collect_headers_from_rows( $rows );
		$xml     = '<Worksheet ss:Name="' . esc_attr( $sheet_name ) . '"><Table>';

		if ( ! empty( $headers ) ) {
			$xml .= '<Row>';
			foreach ( $headers as $header ) {
				$xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . $this->excel_escape( $header ) . '</Data></Cell>';
			}
			$xml .= '</Row>';
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$xml .= '<Row>';
			foreach ( $headers as $header ) {
				$value = isset( $row[ $header ] ) ? $row[ $header ] : null;
				$xml  .= '<Cell><Data ss:Type="String">' . $this->excel_escape( $this->scalar_to_string( $value ) ) . '</Data></Cell>';
			}
			$xml .= '</Row>';
		}

		$xml .= '</Table></Worksheet>';

		return $xml;
	}

	/**
	 * Create KV row for metadata sheet.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 * @return string
	 */
	protected function excel_kv_row( $key, $value ) {
		return '<Row>'
			. '<Cell><Data ss:Type="String">' . $this->excel_escape( (string) $key ) . '</Data></Cell>'
			. '<Cell><Data ss:Type="String">' . $this->excel_escape( (string) $value ) . '</Data></Cell>'
			. '</Row>';
	}

	/**
	 * Escape XML text.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function excel_escape( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Collect ordered headers from row arrays.
	 *
	 * @param array<int, mixed> $rows Rows.
	 * @return array<int, string>
	 */
	protected function collect_headers_from_rows( array $rows ) {
		$headers = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( array_keys( $row ) as $key ) {
				if ( ! is_string( $key ) ) {
					continue;
				}

				if ( ! in_array( $key, $headers, true ) ) {
					$headers[] = $key;
				}
			}
		}

		return $headers;
	}

	/**
	 * Normalize scalar value to string for exports.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function scalar_to_string( $value ) {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return wp_json_encode( $value );
	}

	/**
	 * Build filename by extension.
	 *
	 * @param string $extension File extension.
	 * @return string
	 */
	protected function build_filename( $extension ) {
		return 'super-mechanic-db-export-' . gmdate( 'Ymd-His' ) . '.' . sanitize_key( (string) $extension );
	}

	/**
	 * Build ZIP binary content from file map with ZipArchive or WP PclZip fallback.
	 *
	 * @param array<string, string> $files Files map (name => content).
	 * @return string|\WP_Error
	 */
	protected function build_zip_content( array $files ) {
		$zip_path = wp_tempnam( 'sm-db-export-csv-' );
		if ( ! is_string( $zip_path ) || '' === $zip_path ) {
			return new \WP_Error( 'sm_db_export_zip_temp_failed', __( 'Could not create temporary file for CSV export.', 'super-mechanic' ) );
		}

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new \ZipArchive();
			$ok  = $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
			if ( true !== $ok ) {
				@unlink( $zip_path );
				return new \WP_Error( 'sm_db_export_zip_open_failed', __( 'Could not open ZIP archive for CSV export.', 'super-mechanic' ) );
			}

			foreach ( $files as $name => $content ) {
				$zip->addFromString( (string) $name, (string) $content );
			}
			$zip->close();
		} else {
			if ( ! class_exists( 'PclZip' ) ) {
				$pclzip = ABSPATH . 'wp-admin/includes/class-pclzip.php';
				if ( file_exists( $pclzip ) ) {
					require_once $pclzip;
				}
			}

			if ( ! class_exists( 'PclZip' ) ) {
				@unlink( $zip_path );
				return new \WP_Error( 'sm_db_export_zip_unavailable', __( 'ZIP support is not available in this environment.', 'super-mechanic' ) );
			}

			$temp_dir = trailingslashit( get_temp_dir() ) . 'sm-db-export-' . wp_generate_password( 12, false, false );
			if ( ! wp_mkdir_p( $temp_dir ) ) {
				@unlink( $zip_path );
				return new \WP_Error( 'sm_db_export_zip_tempdir_failed', __( 'Could not create temporary directory for CSV export.', 'super-mechanic' ) );
			}

			$file_paths = array();
			foreach ( $files as $name => $content ) {
				$file_path = trailingslashit( $temp_dir ) . sanitize_file_name( (string) $name );
				file_put_contents( $file_path, (string) $content );
				$file_paths[] = $file_path;
			}

			$archive = new \PclZip( $zip_path );
			$created = $archive->create( $file_paths, PCLZIP_OPT_REMOVE_PATH, $temp_dir );
			$this->cleanup_temp_files( $file_paths, $temp_dir );

			if ( 0 === $created ) {
				@unlink( $zip_path );
				return new \WP_Error(
					'sm_db_export_zip_create_failed',
					sprintf(
						/* translators: %s: ZIP error text */
						__( 'Could not create CSV ZIP export: %s', 'super-mechanic' ),
						(string) $archive->errorInfo( true )
					)
				);
			}
		}

		$content = file_get_contents( $zip_path );
		@unlink( $zip_path );

		if ( ! is_string( $content ) || '' === $content ) {
			return new \WP_Error( 'sm_db_export_zip_read_failed', __( 'Could not read generated CSV ZIP export.', 'super-mechanic' ) );
		}

		return $content;
	}

	/**
	 * Remove temporary export files and folder.
	 *
	 * @param array<int, string> $file_paths Temporary file paths.
	 * @param string             $directory  Temporary directory.
	 * @return void
	 */
	protected function cleanup_temp_files( array $file_paths, $directory ) {
		foreach ( $file_paths as $path ) {
			if ( is_string( $path ) && '' !== $path && file_exists( $path ) ) {
				@unlink( $path );
			}
		}

		if ( is_string( $directory ) && '' !== $directory && is_dir( $directory ) ) {
			@rmdir( $directory );
		}
	}
}
