<?php
/**
 * Vehicle catalog CSV import service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Helpers\Business_Context_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Parses, validates, previews, and imports CSV rows into the vehicle catalog.
 */
class Vehicle_Catalog_Import_Service {
	const PREVIEW_LIMIT = 10;

	/**
	 * Required CSV headers.
	 *
	 * @var string[]
	 */
	protected $required_headers = array( 'make', 'model', 'year' );

	/**
	 * Optional CSV headers.
	 *
	 * @var string[]
	 */
	protected $optional_headers = array( 'trim_version', 'body_type', 'fuel_type', 'transmission', 'engine', 'notes', 'status' );

	/**
	 * Vehicle catalog service.
	 *
	 * @var Vehicle_Catalog_Service
	 */
	protected $catalog_service;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Catalog_Service|null  $catalog_service          Catalog service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Vehicle_Catalog_Service $catalog_service = null, Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->catalog_service          = $catalog_service ? $catalog_service : new Vehicle_Catalog_Service( null, $this->business_context_service );
	}

	/**
	 * Parse a CSV file into normalized row arrays.
	 *
	 * @param string $file_path CSV file path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function parse_csv_file( $file_path ) {
		$file_path = (string) $file_path;
		if ( '' === $file_path || ! is_readable( $file_path ) ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_unreadable', __( 'No se pudo leer el archivo CSV.', 'super-mechanic' ) );
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_open_failed', __( 'No se pudo abrir el archivo CSV.', 'super-mechanic' ) );
		}

		$raw_headers = fgetcsv( $handle );
		if ( false === $raw_headers || empty( $raw_headers ) ) {
			fclose( $handle );
			return new WP_Error( 'sm_vehicle_catalog_csv_empty', __( 'El CSV no contiene encabezados.', 'super-mechanic' ) );
		}

		$headers = array();
		foreach ( $raw_headers as $header ) {
			$headers[] = $this->normalize_header( (string) $header );
		}

		$duplicate_headers = array_unique( array_diff_assoc( $headers, array_unique( $headers ) ) );
		if ( ! empty( $duplicate_headers ) ) {
			fclose( $handle );
			return new WP_Error( 'sm_vehicle_catalog_csv_duplicate_headers', __( 'El CSV contiene encabezados duplicados.', 'super-mechanic' ) );
		}

		$rows       = array();
		$row_number = 1;
		while ( false !== ( $raw_row = fgetcsv( $handle ) ) ) {
			$row_number++;
			if ( $this->is_empty_csv_row( $raw_row ) ) {
				continue;
			}

			$data = array();
			foreach ( $headers as $index => $header ) {
				if ( '' === $header ) {
					continue;
				}

				$data[ $header ] = isset( $raw_row[ $index ] ) ? (string) $raw_row[ $index ] : '';
			}

			$rows[] = array(
				'row_number' => $row_number,
				'data'       => $data,
			);
		}

		fclose( $handle );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Validate parsed rows for one business scope.
	 *
	 * @param array<int,array<string,mixed>> $rows        Parsed rows.
	 * @param int                            $business_id Business ID.
	 * @param string[]                       $headers     CSV headers.
	 * @return array<string,mixed>
	 */
	public function validate_rows( array $rows, $business_id = 0, array $headers = array() ) {
		$business_id = $this->normalize_business_id( $business_id );
		$report      = $this->build_empty_report( $business_id, count( $rows ) );

		$header_errors = $this->validate_headers( $headers );
		if ( $business_id <= 0 ) {
			$header_errors[] = __( 'El negocio es obligatorio para importar catálogo vehicular.', 'super-mechanic' );
		}

		if ( ! empty( $header_errors ) ) {
			$report['header_errors'] = $header_errors;
			$report['invalid_rows']  = count( $rows );
			$report['row_errors']    = $this->rows_to_header_errors( $rows, $header_errors );
			return $report;
		}

		foreach ( $rows as $row ) {
			$row_number = isset( $row['row_number'] ) ? absint( $row['row_number'] ) : 0;
			$row_data   = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : $row;
			$record     = $this->sanitize_row( $row_data, $business_id );
			$errors     = $this->validate_record( $record );

			if ( ! empty( $errors ) ) {
				$report['invalid_rows']++;
				$report['row_errors'][] = array(
					'row_number' => $row_number,
					'errors'     => $errors,
				);
				continue;
			}

			$report['valid_rows']++;
			$report['valid_records'][] = $record;
			if ( count( $report['preview_rows'] ) < self::PREVIEW_LIMIT ) {
				$report['preview_rows'][] = $record;
			}
		}

		$report['can_import'] = $report['valid_rows'] > 0;

		return $report;
	}

	/**
	 * Run a dry-run CSV validation without writing to the database.
	 *
	 * @param string $file_path   CSV file path.
	 * @param int    $business_id Business ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function dry_run( $file_path, $business_id ) {
		$parsed = $this->parse_csv_file( $file_path );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return $this->validate_rows(
			isset( $parsed['rows'] ) && is_array( $parsed['rows'] ) ? $parsed['rows'] : array(),
			$business_id,
			isset( $parsed['headers'] ) && is_array( $parsed['headers'] ) ? $parsed['headers'] : array()
		);
	}

	/**
	 * Import valid rows into the vehicle catalog.
	 *
	 * @param array<int,array<string,mixed>> $rows        Parsed or validated rows.
	 * @param int                            $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function import_rows( array $rows, $business_id ) {
		$business_id = $this->normalize_business_id( $business_id );
		$headers     = array_merge( $this->required_headers, $this->optional_headers );
		$validation  = $this->validate_rows( $rows, $business_id, $headers );
		$result      = $validation;

		$result['imported_rows'] = 0;
		$result['imported_ids']  = array();
		$result['import_errors'] = array();

		if ( empty( $validation['valid_records'] ) ) {
			$result['can_import'] = false;
			return $result;
		}

		foreach ( $validation['valid_records'] as $index => $record ) {
			$catalog_id = $this->catalog_service->create_catalog_vehicle( $record );
			if ( is_wp_error( $catalog_id ) ) {
				$result['import_errors'][] = array(
					'row_number' => isset( $rows[ $index ]['row_number'] ) ? absint( $rows[ $index ]['row_number'] ) : 0,
					'errors'     => $catalog_id->get_error_messages(),
				);
				continue;
			}

			$result['imported_rows']++;
			$result['imported_ids'][] = absint( $catalog_id );
		}

		return $result;
	}

	/**
	 * Build an empty import report.
	 *
	 * @param int $business_id Business ID.
	 * @param int $total_rows  Total rows.
	 * @return array<string,mixed>
	 */
	protected function build_empty_report( $business_id, $total_rows ) {
		return array(
			'business_id'    => absint( $business_id ),
			'total_rows'     => absint( $total_rows ),
			'valid_rows'     => 0,
			'invalid_rows'   => 0,
			'header_errors'  => array(),
			'row_errors'     => array(),
			'preview_rows'   => array(),
			'valid_records'  => array(),
			'can_import'     => false,
			'imported_rows'  => 0,
			'imported_ids'   => array(),
			'import_errors'  => array(),
		);
	}

	/**
	 * Validate CSV headers.
	 *
	 * @param string[] $headers Headers.
	 * @return string[]
	 */
	protected function validate_headers( array $headers ) {
		$headers = array_filter( array_map( array( $this, 'normalize_header' ), $headers ) );
		$allowed = array_merge( $this->required_headers, $this->optional_headers );
		$errors  = array();

		foreach ( $this->required_headers as $required ) {
			if ( ! in_array( $required, $headers, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: CSV column name. */
					__( 'Falta la columna obligatoria: %s.', 'super-mechanic' ),
					$required
				);
			}
		}

		foreach ( $headers as $header ) {
			if ( ! in_array( $header, $allowed, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: CSV column name. */
					__( 'Columna CSV no soportada: %s.', 'super-mechanic' ),
					$header
				);
			}
		}

		return $errors;
	}

	/**
	 * Sanitize one CSV row.
	 *
	 * @param array<string,mixed> $row         Row.
	 * @param int                 $business_id Business ID.
	 * @return array<string,mixed>
	 */
	protected function sanitize_row( array $row, $business_id ) {
		$status = isset( $row['status'] ) && '' !== trim( (string) $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'active';

		return array(
			'business_id'  => absint( $business_id ),
			'make'         => isset( $row['make'] ) ? sanitize_text_field( (string) $row['make'] ) : '',
			'model'        => isset( $row['model'] ) ? sanitize_text_field( (string) $row['model'] ) : '',
			'year'         => isset( $row['year'] ) ? absint( $row['year'] ) : 0,
			'trim_version' => isset( $row['trim_version'] ) ? sanitize_text_field( (string) $row['trim_version'] ) : '',
			'body_type'    => isset( $row['body_type'] ) ? sanitize_text_field( (string) $row['body_type'] ) : '',
			'fuel_type'    => isset( $row['fuel_type'] ) ? sanitize_text_field( (string) $row['fuel_type'] ) : '',
			'transmission' => isset( $row['transmission'] ) ? sanitize_text_field( (string) $row['transmission'] ) : '',
			'engine'       => isset( $row['engine'] ) ? sanitize_text_field( (string) $row['engine'] ) : '',
			'notes'        => isset( $row['notes'] ) ? sanitize_textarea_field( (string) $row['notes'] ) : '',
			'status'       => $status,
		);
	}

	/**
	 * Validate one sanitized catalog record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return string[]
	 */
	protected function validate_record( array $record ) {
		$errors = array();
		$year   = isset( $record['year'] ) ? absint( $record['year'] ) : 0;

		if ( empty( $record['make'] ) ) {
			$errors[] = __( 'La marca es obligatoria.', 'super-mechanic' );
		}

		if ( empty( $record['model'] ) ) {
			$errors[] = __( 'El modelo es obligatorio.', 'super-mechanic' );
		}

		if ( $year <= 0 ) {
			$errors[] = __( 'El año es obligatorio.', 'super-mechanic' );
		} elseif ( $year < 1900 || $year > ( (int) gmdate( 'Y' ) + 1 ) ) {
			$errors[] = __( 'El año del vehículo no es válido.', 'super-mechanic' );
		}

		if ( ! in_array( (string) $record['status'], array( 'active', 'inactive' ), true ) ) {
			$errors[] = __( 'El estado debe ser active o inactive.', 'super-mechanic' );
		}

		return $errors;
	}

	/**
	 * Normalize a CSV header.
	 *
	 * @param string $header Header.
	 * @return string
	 */
	protected function normalize_header( $header ) {
		$header = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header );
		$header = strtolower( trim( (string) $header ) );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );

		return trim( (string) $header, '_' );
	}

	/**
	 * Whether a raw CSV row is empty.
	 *
	 * @param array<int,mixed> $row Row.
	 * @return bool
	 */
	protected function is_empty_csv_row( array $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert header errors into row-level errors for report visibility.
	 *
	 * @param array<int,array<string,mixed>> $rows          Rows.
	 * @param string[]                       $header_errors Header errors.
	 * @return array<int,array<string,mixed>>
	 */
	protected function rows_to_header_errors( array $rows, array $header_errors ) {
		if ( empty( $rows ) ) {
			return array();
		}

		$errors = array();
		foreach ( $rows as $row ) {
			$errors[] = array(
				'row_number' => isset( $row['row_number'] ) ? absint( $row['row_number'] ) : 0,
				'errors'     => $header_errors,
			);
		}

		return $errors;
	}

	/**
	 * Normalize and enforce a business ID.
	 *
	 * @param int $business_id Business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return 0;
		}

		$normalized = absint( $this->business_context_service->normalize_business_id( $business_id ) );

		return $normalized === $business_id ? $business_id : 0;
	}
}
