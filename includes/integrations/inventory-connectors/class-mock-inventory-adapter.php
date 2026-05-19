<?php
/**
 * Mock inventory adapter.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Inventory_Connectors;

defined( 'ABSPATH' ) || exit;

/**
 * Local mock adapter for first inventory connector prototype.
 */
class Mock_Inventory_Adapter {
	/**
	 * Mapper dependency.
	 *
	 * @var Inventory_Sync_Mapper
	 */
	protected $mapper;

	/**
	 * Constructor.
	 *
	 * @param Inventory_Sync_Mapper|null $mapper Mapper dependency.
	 */
	public function __construct( Inventory_Sync_Mapper $mapper = null ) {
		$this->mapper = $mapper ? $mapper : new Inventory_Sync_Mapper();
	}

	/**
	 * Get connector key.
	 *
	 * @return string
	 */
	public function get_connector_key() {
		return 'mock_inventory';
	}

	/**
	 * Validate mock connector credentials.
	 *
	 * @param array<string,mixed> $credentials Credentials/config.
	 * @param int                 $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function validate_credentials( array $credentials = array(), $business_id = 0 ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array(
				'success'    => false,
				'error_code' => 'business_scope_violation',
				'message'    => 'Business ID is required for mock inventory connector.',
			);
		}

		return array(
			'success'       => true,
			'connector_key' => $this->get_connector_key(),
			'business_id'   => $business_id,
			'message'       => 'Mock connector credentials accepted.',
		);
	}

	/**
	 * Fetch local mock inventory records.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_inventory( $business_id = 0 ) {
		$business_id = absint( $business_id );

		return array(
			array(
				'external_id'  => 'mock-toyota-corolla-2024-hybrid',
				'business_id'  => $business_id,
				'make'         => 'Toyota',
				'model'        => 'Corolla',
				'year'         => 2024,
				'trim_version' => 'Hybrid',
				'body_type'    => 'Sedan',
				'fuel_type'    => 'Hybrid',
				'transmission' => 'CVT',
				'engine'       => '1.8L Hybrid',
				'vin'          => 'MOCKTOYOTA2024HY',
				'plate'        => 'MOCK-001',
				'color'        => 'White',
				'mileage'      => 1250,
				'price'        => 24500,
				'currency'     => 'USD',
				'stock_status' => 'available',
				'media'        => array(),
				'notes'        => 'Mock local inventory item.',
			),
			array(
				'external_id'  => 'mock-honda-civic-2023-sport',
				'business_id'  => $business_id,
				'make'         => 'Honda',
				'model'        => 'Civic',
				'year'         => 2023,
				'trim_version' => 'Sport',
				'body_type'    => 'Sedan',
				'fuel_type'    => 'Gasoline',
				'transmission' => 'Automatic',
				'engine'       => '2.0L I4',
				'vin'          => 'MOCKHONDA2023SP',
				'plate'        => 'MOCK-002',
				'color'        => 'Blue',
				'mileage'      => 8900,
				'price'        => 22900,
				'currency'     => 'USD',
				'stock_status' => 'available',
				'media'        => array(),
				'notes'        => 'Mock local inventory item.',
			),
			array(
				'external_id'  => 'mock-fiat-500-2022-lounge',
				'business_id'  => $business_id,
				'make'         => 'Fiat',
				'model'        => '500',
				'year'         => 2022,
				'trim_version' => 'Lounge',
				'body_type'    => 'Hatchback',
				'fuel_type'    => 'Gasoline',
				'transmission' => 'Manual',
				'engine'       => '1.2L I4',
				'vin'          => 'MOCKFIAT2022LOU',
				'plate'        => 'MOCK-003',
				'color'        => 'Red',
				'mileage'      => 15100,
				'price'        => 16800,
				'currency'     => 'USD',
				'stock_status' => 'available',
				'media'        => array(),
				'notes'        => 'Mock local inventory item.',
			),
		);
	}

	/**
	 * Normalize one mock item.
	 *
	 * @param array<string,mixed> $item        Raw item.
	 * @param int                 $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function normalize_item( array $item, $business_id ) {
		return $this->mapper->normalize_item( $item, $business_id, $this->get_connector_key() );
	}

	/**
	 * Build a dry-run report without writes.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $credentials Credentials/config.
	 * @return array<string,mixed>
	 */
	public function dry_run( $business_id, array $credentials = array() ) {
		$business_id = absint( $business_id );
		$credential  = $this->validate_credentials( $credentials, $business_id );

		if ( empty( $credential['success'] ) ) {
			return $this->empty_report( $business_id, array( $credential ) );
		}

		$rows       = $this->fetch_inventory( $business_id );
		$preview    = array();
		$row_errors = array();
		$valid      = 0;
		$invalid    = 0;

		foreach ( $rows as $index => $row ) {
			$normalized = $this->normalize_item( $row, $business_id );
			$errors     = $this->mapper->validate_payload( $normalized, $business_id );

			if ( empty( $errors ) ) {
				++$valid;
				$preview[] = $normalized;
				continue;
			}

			++$invalid;
			$row_errors[] = array(
				'row'         => $index + 1,
				'external_id' => isset( $normalized['external_id'] ) ? (string) $normalized['external_id'] : '',
				'errors'      => $errors,
			);
		}

		return array(
			'connector_key' => $this->get_connector_key(),
			'business_id'   => $business_id,
			'total_rows'    => count( $rows ),
			'valid_rows'    => $valid,
			'invalid_rows'  => $invalid,
			'would_create'  => $valid,
			'would_update'  => 0,
			'would_skip'    => $invalid,
			'row_errors'    => $row_errors,
			'preview'       => $preview,
			'writes'        => 0,
			'simulation'    => true,
		);
	}

	/**
	 * Simulate sync result without writes or external calls.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $credentials Credentials/config.
	 * @return array<string,mixed>
	 */
	public function sync_simulation( $business_id, array $credentials = array() ) {
		$dry_run = $this->dry_run( $business_id, $credentials );

		return array(
			'connector_key' => $this->get_connector_key(),
			'business_id'   => absint( $business_id ),
			'simulation'    => true,
			'result'        => empty( $dry_run['row_errors'] ) ? 'success' : 'partial',
			'imported'      => isset( $dry_run['would_create'] ) ? absint( $dry_run['would_create'] ) : 0,
			'updated'       => isset( $dry_run['would_update'] ) ? absint( $dry_run['would_update'] ) : 0,
			'skipped'       => isset( $dry_run['would_skip'] ) ? absint( $dry_run['would_skip'] ) : 0,
			'writes'        => 0,
			'operations'    => array(
				'import_new'      => isset( $dry_run['would_create'] ) ? absint( $dry_run['would_create'] ) : 0,
				'update_existing' => isset( $dry_run['would_update'] ) ? absint( $dry_run['would_update'] ) : 0,
				'skip_invalid'    => isset( $dry_run['would_skip'] ) ? absint( $dry_run['would_skip'] ) : 0,
			),
			'dry_run'       => $dry_run,
		);
	}

	/**
	 * Build an empty report when connector cannot run.
	 *
	 * @param int                        $business_id Business ID.
	 * @param array<int,array<string,mixed>> $errors  Errors.
	 * @return array<string,mixed>
	 */
	protected function empty_report( $business_id, array $errors ) {
		return array(
			'connector_key' => $this->get_connector_key(),
			'business_id'   => absint( $business_id ),
			'total_rows'    => 0,
			'valid_rows'    => 0,
			'invalid_rows'  => 0,
			'would_create'  => 0,
			'would_update'  => 0,
			'would_skip'    => 0,
			'row_errors'    => $errors,
			'preview'       => array(),
			'writes'        => 0,
			'simulation'    => true,
		);
	}
}
