<?php
/**
 * Passive queue dispatcher.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Builds normalized jobs without persistence or execution.
 */
class Queue_Dispatcher {
	/**
	 * Queue context.
	 *
	 * @var Queue_Context
	 */
	protected $context;

	/**
	 * Queue job repository.
	 *
	 * @var Queue_Job_Repository|null
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Queue_Context|null $context Queue context.
	 */
	public function __construct( Queue_Context $context = null, Queue_Job_Repository $repository = null ) {
		$this->context    = $context ? $context : new Queue_Context();
		$this->repository = $repository;
	}

	/**
	 * Build and validate one normalized job.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Job payload.
	 * @param int                 $business_id Business ID.
	 * @param string|null         $tenant_id Future tenant ID.
	 * @param array<string,mixed> $options Job options.
	 * @return Queue_Result
	 */
	public function build_job( $job_type, array $payload = array(), $business_id = 0, $tenant_id = null, array $options = array() ) {
		$job_type    = sanitize_key( (string) $job_type );
		$business_id = absint( $business_id );
		$max_attempts = isset( $options['max_attempts'] ) ? absint( $options['max_attempts'] ) : $this->context->get_default_max_attempts( $job_type );

		$job = new Queue_Job_Contract(
			array(
				'job_type'     => $job_type,
				'business_id'  => $business_id,
				'tenant_id'    => $tenant_id,
				'payload'      => $payload,
				'status'       => isset( $options['status'] ) ? $options['status'] : Queue_Job_Contract::STATUS_PENDING,
				'attempts'     => isset( $options['attempts'] ) ? $options['attempts'] : 0,
				'max_attempts' => $max_attempts,
				'scheduled_at' => isset( $options['scheduled_at'] ) ? $options['scheduled_at'] : gmdate( 'c' ),
				'created_at'   => isset( $options['created_at'] ) ? $options['created_at'] : gmdate( 'c' ),
				'last_error'   => isset( $options['last_error'] ) ? $options['last_error'] : '',
			)
		);

		if ( ! $job->is_valid() ) {
			return Queue_Result::invalid( $job->get_validation_errors(), $job->to_array() );
		}

		$normalized_job = $job->to_array();

		if ( $this->context->is_persistence_enabled() ) {
			$repository = $this->repository ? $this->repository : new Queue_Job_Repository();
			$insert_id  = $repository->create_job( $normalized_job );

			if ( $insert_id <= 0 ) {
				return Queue_Result::invalid( array( 'queue_job_persistence_failed' ), $normalized_job );
			}

			return Queue_Result::accepted(
				$normalized_job,
				array(
					'persisted'    => true,
					'persisted_id' => $insert_id,
				)
			);
		}

		return Queue_Result::accepted( $normalized_job );
	}

	/**
	 * Passive dispatch alias.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Job payload.
	 * @param int                 $business_id Business ID.
	 * @param string|null         $tenant_id Future tenant ID.
	 * @param array<string,mixed> $options Job options.
	 * @return array<string,mixed>
	 */
	public function dispatch( $job_type, array $payload = array(), $business_id = 0, $tenant_id = null, array $options = array() ) {
		return $this->build_job( $job_type, $payload, $business_id, $tenant_id, $options )->to_array();
	}

	/**
	 * Passive dry-run alias for planned job creation.
	 *
	 * @param string              $job_type Job type.
	 * @param array<string,mixed> $payload Job payload.
	 * @param int                 $business_id Business ID.
	 * @param string|null         $tenant_id Future tenant ID.
	 * @param array<string,mixed> $options Job options.
	 * @return array<string,mixed>
	 */
	public function dry_run( $job_type, array $payload = array(), $business_id = 0, $tenant_id = null, array $options = array() ) {
		$result = $this->build_job( $job_type, $payload, $business_id, $tenant_id, $options )->to_array();
		$result['dry_run'] = true;

		return $result;
	}

	/**
	 * Get dispatcher context.
	 *
	 * @return Queue_Context
	 */
	public function get_context() {
		return $this->context;
	}
}
