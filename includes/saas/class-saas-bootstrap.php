<?php
/**
 * SaaS foundation bootstrap.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Passive SaaS foundation aggregator.
 */
class Saas_Bootstrap {
	/**
	 * Runtime context.
	 *
	 * @var Runtime_Context
	 */
	protected $runtime_context;

	/**
	 * Tenant context.
	 *
	 * @var Tenant_Context
	 */
	protected $tenant_context;

	/**
	 * License context.
	 *
	 * @var License_Context
	 */
	protected $license_context;

	/**
	 * Constructor.
	 *
	 * @param Runtime_Context|null $runtime_context Runtime context.
	 * @param Tenant_Context|null  $tenant_context Tenant context.
	 * @param License_Context|null $license_context License context.
	 */
	public function __construct( Runtime_Context $runtime_context = null, Tenant_Context $tenant_context = null, License_Context $license_context = null ) {
		$this->runtime_context = $runtime_context ? $runtime_context : new Runtime_Context();
		$this->tenant_context  = $tenant_context ? $tenant_context : new Tenant_Context();
		$this->license_context = $license_context ? $license_context : new License_Context();
	}

	/**
	 * Get runtime context.
	 *
	 * @return Runtime_Context
	 */
	public function get_runtime_context() {
		return $this->runtime_context;
	}

	/**
	 * Get tenant context.
	 *
	 * @return Tenant_Context
	 */
	public function get_tenant_context() {
		return $this->tenant_context;
	}

	/**
	 * Get license context.
	 *
	 * @return License_Context
	 */
	public function get_license_context() {
		return $this->license_context;
	}

	/**
	 * Export foundation context for diagnostics or future runtime wiring.
	 *
	 * @param int $business_id Business identifier.
	 * @param int $user_id User identifier.
	 * @return array<string,mixed>
	 */
	public function get_foundation_context( $business_id = 0, $user_id = 0 ) {
		return array(
			'runtime'         => $this->runtime_context->to_array(),
			'tenant'          => $this->tenant_context->resolve( $business_id, $user_id ),
			'license'         => $this->license_context->to_array(),
			'queue_contracts' => $this->get_queue_job_contracts(),
			'runtime_wired'   => false,
		);
	}

	/**
	 * Define async placeholders without registering workers.
	 *
	 * @return array<string,mixed>
	 */
	public function get_queue_job_contracts() {
		$queue_context = new Queue_Context();

		return array(
			'queue_jobs'          => array(
				'status'          => 'passive_contract',
				'workers_enabled' => false,
				'job_types'       => $queue_context->get_supported_job_types(),
				'job_families'    => array(
					'import_jobs',
					'notification_jobs',
					'connector_sync_jobs',
				),
			),
			'retry_jobs'          => array(
				'status'          => 'passive_contract',
				'workers_enabled' => false,
				'backoff'         => $queue_context->get_backoff_strategy(),
				'job_families'    => array(
					'notification_retries',
					'webhook_retries',
					'connector_retries',
				),
			),
			'scheduled_sync_jobs' => array(
				'status'          => 'passive_contract',
				'workers_enabled' => false,
				'job_families'    => array(
					'inventory_connector_sync',
					'catalog_import_review',
				),
			),
			'queue_context'       => $queue_context->to_array(),
		);
	}
}
