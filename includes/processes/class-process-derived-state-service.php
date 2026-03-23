<?php
/**
 * Process derived state service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Pre_Delivery\Pre_Delivery_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves safe derived process states from existing persisted data.
 */
class Process_Derived_State_Service {
	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Pre-delivery service.
	 *
	 * @var Pre_Delivery_Service
	 */
	protected $pre_delivery_service;

	/**
	 * Constructor.
	 *
	 * @param Process_Service|null      $process_service      Process service.
	 * @param Quote_Service|null        $quote_service        Quote service.
	 * @param Invoice_Service|null      $invoice_service      Invoice service.
	 * @param Pre_Delivery_Service|null $pre_delivery_service Pre-delivery service.
	 */
	public function __construct( Process_Service $process_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Pre_Delivery_Service $pre_delivery_service = null ) {
		$this->process_service      = $process_service ? $process_service : new Process_Service();
		$this->quote_service        = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service      = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->pre_delivery_service = $pre_delivery_service ? $pre_delivery_service : new Pre_Delivery_Service( null, $this->process_service );
	}

	/**
	 * Resolve derived state for a process.
	 *
	 * @param int|array<string, mixed> $process Process ID or row.
	 * @return array<string, string>|WP_Error
	 */
	public function get_process_derived_state( $process ) {
		$process = $this->normalize_process( $process );

		if ( is_wp_error( $process ) ) {
			return $process;
		}

		if ( $this->is_completed_process( $process ) ) {
			return $this->build_state_payload( 'completed', __( 'Completado', 'super-mechanic' ), 'process_status' );
		}

		if ( $this->is_ready_for_delivery( $process ) ) {
			return $this->build_state_payload( 'ready_for_delivery', __( 'Listo para entrega', 'super-mechanic' ), 'pre_delivery' );
		}

		if ( $this->is_waiting_payment( $process ) ) {
			return $this->build_state_payload( 'waiting_payment', __( 'En espera de pago', 'super-mechanic' ), 'invoice_collection' );
		}

		if ( $this->is_waiting_approval( $process ) ) {
			return $this->build_state_payload( 'waiting_approval', __( 'En espera de aprobacion', 'super-mechanic' ), 'quote_or_process' );
		}

		return $this->build_state_payload( '', '', '' );
	}

	/**
	 * Append derived state fields to a process row.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return array<string, mixed>
	 */
	public function append_derived_state( array $process ) {
		$derived = $this->get_process_derived_state( $process );

		if ( is_wp_error( $derived ) ) {
			$derived = $this->build_state_payload( '', '', '' );
		}

		$process['derived_status']        = $derived['key'];
		$process['derived_status_label']  = $derived['label'];
		$process['derived_status_source'] = $derived['source'];

		return $process;
	}

	/**
	 * Append derived state fields to many process rows.
	 *
	 * @param array<int, array<string, mixed>> $processes Process rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function append_derived_states( array $processes ) {
		foreach ( $processes as $index => $process ) {
			if ( is_array( $process ) ) {
				$processes[ $index ] = $this->append_derived_state( $process );
			}
		}

		return $processes;
	}

	/**
	 * Normalize process input.
	 *
	 * @param int|array<string, mixed> $process Process ID or row.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function normalize_process( $process ) {
		if ( is_array( $process ) ) {
			return $process;
		}

		$process_id = absint( $process );
		$process    = $this->process_service->get_process( $process_id );

		if ( ! $process ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		return $process;
	}

	/**
	 * Check for a completed process.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return bool
	 */
	protected function is_completed_process( array $process ) {
		return in_array( sanitize_key( $process['status'] ), array( 'completed', 'delivered' ), true );
	}

	/**
	 * Check whether the process is objectively ready for delivery.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return bool
	 */
	protected function is_ready_for_delivery( array $process ) {
		if ( 'pre_delivery' !== sanitize_key( $process['process_type'] ) ) {
			return false;
		}

		$record = $this->pre_delivery_service->get_by_process( absint( $process['id'] ) );

		return ! is_wp_error( $record ) && ! empty( $record['delivery_ready'] );
	}

	/**
	 * Check whether the process is waiting for payment.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return bool
	 */
	protected function is_waiting_payment( array $process ) {
		$invoices = $this->invoice_service->get_invoices(
			array(
				'process_id' => absint( $process['id'] ),
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);

		foreach ( $invoices as $invoice ) {
			if ( in_array( sanitize_key( $invoice['status'] ), array( 'cancelled', 'refunded' ), true ) ) {
				continue;
			}

			$summary = $this->invoice_service->get_invoice_payment_summary( absint( $invoice['id'] ) );

			if ( is_wp_error( $summary ) ) {
				continue;
			}

			if ( in_array( sanitize_key( $summary['payment_status'] ), array( 'pending', 'partial' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the process is waiting for approval.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return bool
	 */
	protected function is_waiting_approval( array $process ) {
		if ( 'waiting_approval' === sanitize_key( $process['status'] ) ) {
			return true;
		}

		$quotes = $this->quote_service->get_quotes(
			array(
				'process_id' => absint( $process['id'] ),
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);

		foreach ( $quotes as $quote ) {
			if ( 'sent' === sanitize_key( $quote['status'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a normalized derived state payload.
	 *
	 * @param string $key    State key.
	 * @param string $label  State label.
	 * @param string $source State source.
	 * @return array<string, string>
	 */
	protected function build_state_payload( $key, $label, $source ) {
		return array(
			'key'    => sanitize_key( $key ),
			'label'  => (string) $label,
			'source' => sanitize_key( $source ),
		);
	}
}
