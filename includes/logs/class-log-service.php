<?php
/**
 * Log service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Logs;

defined( 'ABSPATH' ) || exit;

/**
 * Structured logging service.
 */
class Log_Service {
	/**
	 * Allowed log types.
	 *
	 * @var array<int,string>
	 */
	const LOG_TYPES = array( 'queue', 'notification', 'webhook', 'automation' );

	/**
	 * Allowed statuses.
	 *
	 * @var array<int,string>
	 */
	const STATUSES = array( 'info', 'success', 'warning', 'error' );

	/**
	 * Repository dependency.
	 *
	 * @var Log_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Log_Repository|null $repository Repository.
	 */
	public function __construct( Log_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Log_Repository();
	}

	/**
	 * Create log row.
	 *
	 * @param string              $log_type Log type.
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context payload.
	 * @param int                 $reference_id Reference ID.
	 * @return int
	 */
	public function create_log( $log_type, $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		$log_type = sanitize_key( (string) $log_type );
		$source   = sanitize_key( (string) $source );
		$status   = sanitize_key( (string) $status );
		$message  = sanitize_text_field( (string) $message );

		if ( ! in_array( $log_type, self::LOG_TYPES, true ) ) {
			$log_type = 'automation';
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'info';
		}

		$context_json = wp_json_encode( $this->sanitize_context( $context ) );
		if ( false === $context_json ) {
			$context_json = '{}';
		}

		return $this->repository->insert_log(
			$log_type,
			$source,
			absint( $reference_id ),
			$status,
			$message,
			$context_json
		);
	}

	/**
	 * Log queue event.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return int
	 */
	public function log_queue_event( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		return $this->create_log( 'queue', $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Log notification event.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return int
	 */
	public function log_notification_event( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		return $this->create_log( 'notification', $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Log webhook event.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return int
	 */
	public function log_webhook_event( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		return $this->create_log( 'webhook', $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Log automation event.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return int
	 */
	public function log_automation_event( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		return $this->create_log( 'automation', $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Sanitize context recursively with size guardrails.
	 *
	 * @param array<string,mixed> $context Raw context.
	 * @param int                 $depth Recursion depth.
	 * @return array<string,mixed>
	 */
	protected function sanitize_context( array $context, $depth = 0 ) {
		if ( $depth >= 3 ) {
			return array();
		}

		$clean = array();
		$count = 0;
		foreach ( $context as $key => $value ) {
			if ( $count >= 20 ) {
				break;
			}

			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_context( $value, $depth + 1 );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}

			++$count;
		}

		return $clean;
	}
}

