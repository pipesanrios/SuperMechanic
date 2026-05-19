<?php
/**
 * Mock inventory connector.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Inventory_Connectors;

defined( 'ABSPATH' ) || exit;

/**
 * Facade for the local mock inventory connector.
 */
class Mock_Inventory_Connector {
	/**
	 * Adapter dependency.
	 *
	 * @var Mock_Inventory_Adapter
	 */
	protected $adapter;

	/**
	 * Constructor.
	 *
	 * @param Mock_Inventory_Adapter|null $adapter Adapter dependency.
	 */
	public function __construct( Mock_Inventory_Adapter $adapter = null ) {
		$this->adapter = $adapter ? $adapter : new Mock_Inventory_Adapter();
	}

	/**
	 * Get connector key.
	 *
	 * @return string
	 */
	public function get_connector_key() {
		return $this->adapter->get_connector_key();
	}

	/**
	 * Get connector identity metadata.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_identity( $business_id = 0 ) {
		return array(
			'connector_key' => $this->get_connector_key(),
			'provider_name' => 'Mock Local Inventory',
			'provider_type' => 'mock',
			'version'       => '1.0.0',
			'business_id'   => absint( $business_id ),
		);
	}

	/**
	 * Run dry-run.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $credentials Credentials/config.
	 * @return array<string,mixed>
	 */
	public function dry_run( $business_id, array $credentials = array() ) {
		$result             = $this->adapter->dry_run( $business_id, $credentials );
		$result['identity'] = $this->get_identity( $business_id );

		return $result;
	}

	/**
	 * Run sync simulation.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $credentials Credentials/config.
	 * @return array<string,mixed>
	 */
	public function sync_simulation( $business_id, array $credentials = array() ) {
		$result             = $this->adapter->sync_simulation( $business_id, $credentials );
		$result['identity'] = $this->get_identity( $business_id );

		return $result;
	}

	/**
	 * Get adapter dependency.
	 *
	 * @return Mock_Inventory_Adapter
	 */
	public function get_adapter() {
		return $this->adapter;
	}
}
