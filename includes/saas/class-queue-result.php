<?php
/**
 * Passive queue result.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Standard result object for passive queue dispatching.
 */
class Queue_Result {
	/**
	 * Result data.
	 *
	 * @var array<string,mixed>
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data Result data.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $this->normalize( $data );
	}

	/**
	 * Build accepted result.
	 *
	 * @param array<string,mixed> $job Normalized job.
	 * @return self
	 */
	public static function accepted( array $job, array $meta = array() ) {
		$persisted = ! empty( $meta['persisted'] );

		return new self(
			array(
				'success'  => true,
				'status'   => 'accepted',
				'message'  => $persisted ? 'Queue job normalized and persisted. Passive dispatcher did not execute it.' : 'Queue job normalized. Passive dispatcher did not persist or execute it.',
				'job'      => $job,
				'errors'   => array(),
				'writes'   => $persisted ? 1 : 0,
				'executed' => false,
				'persisted' => $persisted,
				'persisted_id' => isset( $meta['persisted_id'] ) ? absint( $meta['persisted_id'] ) : 0,
			)
		);
	}

	/**
	 * Build invalid result.
	 *
	 * @param string[]            $errors Validation errors.
	 * @param array<string,mixed> $job Normalized job.
	 * @return self
	 */
	public static function invalid( array $errors, array $job = array() ) {
		return new self(
			array(
				'success'  => false,
				'status'   => 'invalid',
				'message'  => 'Queue job validation failed.',
				'job'      => $job,
				'errors'   => $errors,
				'writes'   => 0,
				'executed' => false,
			)
		);
	}

	/**
	 * Build skipped result.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $job Normalized job.
	 * @return self
	 */
	public static function skipped( $message, array $job = array() ) {
		return new self(
			array(
				'success'  => true,
				'status'   => 'skipped',
				'message'  => sanitize_text_field( (string) $message ),
				'job'      => $job,
				'errors'   => array(),
				'writes'   => 0,
				'executed' => false,
			)
		);
	}

	/**
	 * Export result.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return $this->data;
	}

	/**
	 * Normalize result data.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	protected function normalize( array $data ) {
		return array(
			'success'  => ! empty( $data['success'] ),
			'status'   => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'unknown',
			'message'  => isset( $data['message'] ) ? sanitize_text_field( (string) $data['message'] ) : '',
			'job'      => isset( $data['job'] ) && is_array( $data['job'] ) ? $data['job'] : array(),
			'errors'   => isset( $data['errors'] ) && is_array( $data['errors'] ) ? array_values( array_map( 'sanitize_key', $data['errors'] ) ) : array(),
			'writes'   => isset( $data['writes'] ) ? absint( $data['writes'] ) : 0,
			'executed' => ! empty( $data['executed'] ),
			'passive'  => true,
			'persisted' => ! empty( $data['persisted'] ),
			'persisted_id' => isset( $data['persisted_id'] ) ? absint( $data['persisted_id'] ) : 0,
		);
	}
}
