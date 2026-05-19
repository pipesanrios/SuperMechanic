<?php
/**
 * Inventory connector service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Inventory_Connectors;

use Super_Mechanic\Saas\Queue_Dispatcher;
use Super_Mechanic\Saas\Queue_Job_Contract;

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
	 * Passive queue dispatcher dependency.
	 *
	 * @var Queue_Dispatcher
	 */
	protected $queue_dispatcher;

	/**
	 * Constructor.
	 *
	 * @param Mock_Inventory_Connector|null $mock_connector   Mock connector.
	 * @param Queue_Dispatcher|null         $queue_dispatcher Passive queue dispatcher.
	 */
	public function __construct( Mock_Inventory_Connector $mock_connector = null, Queue_Dispatcher $queue_dispatcher = null ) {
		$this->mock_connector   = $mock_connector ? $mock_connector : new Mock_Inventory_Connector();
		$this->queue_dispatcher = $queue_dispatcher ? $queue_dispatcher : new Queue_Dispatcher();
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
	 * Build a passive queue job for connector dry-run intent.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @param string|null         $tenant_id     Future tenant ID.
	 * @return array<string,mixed>
	 */
	public function build_dry_run_job( $connector_key, $business_id, array $credentials = array(), $tenant_id = null ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );
		$report        = $this->dry_run( $connector_key, $business_id, $credentials );

		return $this->build_passive_queue_job(
			$connector_key,
			$business_id,
			'dry_run',
			true,
			$report,
			$tenant_id
		);
	}

	/**
	 * Build a passive queue job for connector sync simulation intent.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @param string|null         $tenant_id     Future tenant ID.
	 * @return array<string,mixed>
	 */
	public function build_sync_job( $connector_key, $business_id, array $credentials = array(), $tenant_id = null ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );
		$report        = $this->sync_simulation( $connector_key, $business_id, $credentials );

		return $this->build_passive_queue_job(
			$connector_key,
			$business_id,
			'sync_simulation',
			false,
			$report,
			$tenant_id
		);
	}

	/**
	 * Dispatch a dry-run connector intent to the passive queue dispatcher.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @param string|null         $tenant_id     Future tenant ID.
	 * @return array<string,mixed>
	 */
	public function dispatch_dry_run_intent( $connector_key, $business_id, array $credentials = array(), $tenant_id = null ) {
		return $this->build_dry_run_job( $connector_key, $business_id, $credentials, $tenant_id );
	}

	/**
	 * Dispatch a sync connector intent to the passive queue dispatcher.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param array<string,mixed> $credentials   Credentials/config.
	 * @param string|null         $tenant_id     Future tenant ID.
	 * @return array<string,mixed>
	 */
	public function dispatch_sync_intent( $connector_key, $business_id, array $credentials = array(), $tenant_id = null ) {
		return $this->build_sync_job( $connector_key, $business_id, $credentials, $tenant_id );
	}

	/**
	 * Build passive connector queue job from a connector report.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param string              $operation     Operation.
	 * @param bool                $dry_run       Dry-run flag.
	 * @param array<string,mixed> $report        Connector report.
	 * @param string|null         $tenant_id     Future tenant ID.
	 * @return array<string,mixed>
	 */
	protected function build_passive_queue_job( $connector_key, $business_id, $operation, $dry_run, array $report, $tenant_id = null ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );
		$operation     = sanitize_key( (string) $operation );
		$identity      = isset( $report['identity'] ) && is_array( $report['identity'] ) ? $report['identity'] : $this->resolve_connector_identity( $connector_key, $business_id );
		$payload       = $this->build_connector_queue_payload( $connector_key, $business_id, $operation, (bool) $dry_run, $identity, $report );

		return $this->queue_dispatcher->dispatch(
			Queue_Job_Contract::JOB_INVENTORY_CONNECTOR_SYNC,
			$payload,
			$business_id,
			$tenant_id
		);
	}

	/**
	 * Build connector queue payload.
	 *
	 * @param string              $connector_key Connector key.
	 * @param int                 $business_id   Business ID.
	 * @param string              $operation     Operation.
	 * @param bool                $dry_run       Dry-run flag.
	 * @param array<string,mixed> $identity      Connector identity.
	 * @param array<string,mixed> $report        Connector report.
	 * @return array<string,mixed>
	 */
	protected function build_connector_queue_payload( $connector_key, $business_id, $operation, $dry_run, array $identity, array $report ) {
		$preview = $this->extract_preview_items( $report );

		return array(
			'connector_key'    => sanitize_key( (string) $connector_key ),
			'operation'        => sanitize_key( (string) $operation ),
			'dry_run'          => (bool) $dry_run,
			'provider_type'    => isset( $identity['provider_type'] ) ? sanitize_key( (string) $identity['provider_type'] ) : 'unknown',
			'business_id'      => absint( $business_id ),
			'identity'         => $this->sanitize_identity( $identity, $business_id ),
			'normalized_items' => array(
				'count'   => count( $preview ),
				'preview' => $preview,
			),
			'validation'       => $this->build_validation_summary( $report ),
			'writes'           => 0,
			'execution'        => array(
				'worker_enabled'      => false,
				'job_persisted'       => false,
				'connector_executed'  => false,
				'import_executed'     => false,
				'external_api_called' => false,
			),
		);
	}

	/**
	 * Extract normalized preview items from connector report.
	 *
	 * @param array<string,mixed> $report Connector report.
	 * @return array<int,array<string,mixed>>
	 */
	protected function extract_preview_items( array $report ) {
		$preview = array();

		if ( isset( $report['preview'] ) && is_array( $report['preview'] ) ) {
			$preview = $report['preview'];
		} elseif ( isset( $report['dry_run']['preview'] ) && is_array( $report['dry_run']['preview'] ) ) {
			$preview = $report['dry_run']['preview'];
		}

		return array_slice( $preview, 0, 5 );
	}

	/**
	 * Build validation summary from connector report.
	 *
	 * @param array<string,mixed> $report Connector report.
	 * @return array<string,mixed>
	 */
	protected function build_validation_summary( array $report ) {
		$source = isset( $report['dry_run'] ) && is_array( $report['dry_run'] ) ? $report['dry_run'] : $report;

		return array(
			'total_rows'   => isset( $source['total_rows'] ) ? absint( $source['total_rows'] ) : 0,
			'valid_rows'   => isset( $source['valid_rows'] ) ? absint( $source['valid_rows'] ) : 0,
			'invalid_rows' => isset( $source['invalid_rows'] ) ? absint( $source['invalid_rows'] ) : 0,
			'would_create' => isset( $source['would_create'] ) ? absint( $source['would_create'] ) : ( isset( $report['imported'] ) ? absint( $report['imported'] ) : 0 ),
			'would_update' => isset( $source['would_update'] ) ? absint( $source['would_update'] ) : ( isset( $report['updated'] ) ? absint( $report['updated'] ) : 0 ),
			'would_skip'   => isset( $source['would_skip'] ) ? absint( $source['would_skip'] ) : ( isset( $report['skipped'] ) ? absint( $report['skipped'] ) : 0 ),
			'row_errors'   => isset( $source['row_errors'] ) && is_array( $source['row_errors'] ) ? $source['row_errors'] : array(),
			'result'       => isset( $report['result'] ) ? sanitize_key( (string) $report['result'] ) : ( empty( $source['row_errors'] ) ? 'success' : 'partial' ),
		);
	}

	/**
	 * Resolve connector identity for supported connectors.
	 *
	 * @param string $connector_key Connector key.
	 * @param int    $business_id   Business ID.
	 * @return array<string,mixed>
	 */
	protected function resolve_connector_identity( $connector_key, $business_id ) {
		$connector_key = sanitize_key( (string) $connector_key );
		$business_id   = absint( $business_id );

		if ( $this->mock_connector->get_connector_key() === $connector_key ) {
			return $this->mock_connector->get_identity( $business_id );
		}

		return array(
			'connector_key' => $connector_key,
			'provider_name' => 'Unsupported connector',
			'provider_type' => 'unknown',
			'version'       => '',
			'business_id'   => $business_id,
		);
	}

	/**
	 * Sanitize connector identity.
	 *
	 * @param array<string,mixed> $identity    Identity.
	 * @param int                 $business_id Business ID.
	 * @return array<string,mixed>
	 */
	protected function sanitize_identity( array $identity, $business_id ) {
		return array(
			'connector_key' => isset( $identity['connector_key'] ) ? sanitize_key( (string) $identity['connector_key'] ) : '',
			'provider_name' => isset( $identity['provider_name'] ) ? sanitize_text_field( (string) $identity['provider_name'] ) : '',
			'provider_type' => isset( $identity['provider_type'] ) ? sanitize_key( (string) $identity['provider_type'] ) : '',
			'version'       => isset( $identity['version'] ) ? sanitize_text_field( (string) $identity['version'] ) : '',
			'business_id'   => isset( $identity['business_id'] ) ? absint( $identity['business_id'] ) : absint( $business_id ),
		);
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
