<?php
/**
 * Inventory connector service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Inventory_Connectors;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates inbound inventory connector prototypes.
 */
class Inventory_Connector_Service {
	/**
	 * Mock connector dependency.
	 *
	 * @var Mock_Inventory_Connector
	 */
	protected $mock_connector;

	/**
	 * Constructor.
	 *
	 * @param Mock_Inventory_Connector|null $mock_connector Mock connector.
	 */
	public function __construct( Mock_Inventory_Connector $mock_connector = null ) {
		$this->mock_connector = $mock_connector ? $mock_connector : new Mock_Inventory_Connector();
	}

	/**
	 * Get supported connector keys.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_connectors() {
		return array(
			$this->mock_connector->get_connector_key(),
		);
	}

	/**
	 * Run dry-run for one connector.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @return array<string,mixed>
	 */
	public function dry_run( $connector_key, $business_id, array $credentials = array() ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );

		if ( $this->mock_connector->get_connector_key() !== $connector_key ) {
			return $this->unsupported_connector_result( $connector_key, $business_id, 'dry_run' );
		}

		return $this->mock_connector->dry_run( $business_id, $credentials );
	}

	/**
	 * Run sync simulation for one connector.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @return array<string,mixed>
	 */
	public function sync_simulation( $connector_key, $business_id, array $credentials = array() ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );

		if ( $this->mock_connector->get_connector_key() !== $connector_key ) {
			return $this->unsupported_connector_result( $connector_key, $business_id, 'sync_simulation' );
		}

		return $this->mock_connector->sync_simulation( $business_id, $credentials );
	}

	/**
	 * Build unsupported connector result.
	 *
	 * @param string $connector_key Connector key.
	 * @param int    $business_id   Business ID.
	 * @param string $operation     Operation.
	 * @return array<string,mixed>
	 */
	protected function unsupported_connector_result( $connector_key, $business_id, $operation ) {
		return array(
			'connector_key' => sanitize_key( (string) $connector_key ),
			'business_id'   => absint( $business_id ),
			'operation'     => sanitize_key( (string) $operation ),
			'result'        => 'failed',
			'error_code'    => 'invalid_payload',
			'message'       => 'Unsupported inventory connector.',
			'writes'        => 0,
			'simulation'    => true,
		);
	}
}
