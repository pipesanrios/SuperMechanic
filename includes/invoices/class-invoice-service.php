<?php
/**
 * Invoice service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles invoice business rules.
 */
class Invoice_Service {
	/**
	 * Invoice repository.
	 *
	 * @var Invoice_Repository
	 */
	protected $repository;

	/**
	 * Invoice item repository.
	 *
	 * @var Invoice_Item_Repository
	 */
	protected $item_repository;

	/**
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	protected $payment_repository;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Transaction repository.
	 *
	 * @var Invoice_Transaction_Repository
	 */
	protected $transaction_repository;

	/**
	 * Event dispatcher.
	 *
	 * @var Event_Dispatcher
	 */
	protected $event_dispatcher;

	/**
	 * Access control service.
	 *
	 * @var Access_Control_Service
	 */
	protected $access_control_service;

	/**
	 * Constructor.
	 *
	 * @param Invoice_Repository|null             $repository             Repository.
	 * @param Invoice_Item_Repository|null        $item_repository        Item repository.
	 * @param Payment_Repository|null             $payment_repository     Payment repository.
	 * @param Quote_Service|null                  $quote_service          Quote service.
	 * @param Event_Dispatcher|null               $event_dispatcher       Event dispatcher.
	 * @param Invoice_Transaction_Repository|null $transaction_repository Transaction repository.
	 */
	public function __construct( Invoice_Repository $repository = null, Invoice_Item_Repository $item_repository = null, Payment_Repository $payment_repository = null, Quote_Service $quote_service = null, Event_Dispatcher $event_dispatcher = null, Invoice_Transaction_Repository $transaction_repository = null, Access_Control_Service $access_control_service = null ) {
		$this->repository             = $repository ? $repository : new Invoice_Repository();
		$this->item_repository        = $item_repository ? $item_repository : new Invoice_Item_Repository();
		$this->payment_repository     = $payment_repository ? $payment_repository : new Payment_Repository();
		$this->quote_service          = $quote_service ? $quote_service : new Quote_Service();
		$this->event_dispatcher       = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->transaction_repository = $transaction_repository ? $transaction_repository : new Invoice_Transaction_Repository();
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service( null, null, null, null, $this->repository );
	}

	/**
	 * Create invoice.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 * @return int|WP_Error
	 */
	public function create_invoice( array $data ) {
		$data  = $this->prepare_invoice_data( $data );
		$valid = $this->validate_invoice_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_invoice_create_failed', __( 'No fue posible crear la factura.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $inserted );
		$this->recalculate_balance( $inserted );

		return $inserted;
	}

	/**
	 * Update invoice.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Invoice data.
	 * @return bool|WP_Error
	 */
	public function update_invoice( $invoice_id, array $data ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_invoice_data( array_merge( $invoice, $data ) );
		$valid = $this->validate_invoice_data( $data, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update( $invoice_id, $data ) ) {
			return new WP_Error( 'sm_invoice_update_failed', __( 'No fue posible actualizar la factura.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $invoice_id );
		$this->recalculate_balance( $invoice_id );

		return true;
	}

	/**
	 * Delete invoice.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return bool|WP_Error
	 */
	public function delete_invoice( $invoice_id ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$this->item_repository->delete_by_invoice_id( $invoice_id );
		$this->payment_repository->delete_by_invoice_id( $invoice_id );

		if ( ! $this->repository->delete( $invoice_id ) ) {
			return new WP_Error( 'sm_invoice_delete_failed', __( 'No fue posible eliminar la factura.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get invoice.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<string, mixed>|null
	 */
	public function get_invoice( $invoice_id ) {
		return $this->repository->get_by_id( $invoice_id );
	}

	/**
	 * Get invoices.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoices( array $args = array() ) {
		return $this->repository->get_all( $args );
	}

	/**
	 * Get invoices filtered by the current access policy.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoices_for_user( $user_id, array $args = array() ) {
		$user_id = absint( $user_id );

		if ( $this->access_control_service->user_has_full_access( $user_id ) ) {
			return $this->get_invoices( $args );
		}

		$invoices = $this->get_invoices( $args );

		return array_values(
			array_filter(
				$invoices,
				function ( $invoice ) use ( $user_id ) {
					return ! empty( $invoice['id'] ) && $this->access_control_service->user_can_access_invoice( $user_id, absint( $invoice['id'] ) );
				}
			)
		);
	}

	/**
	 * Get approved quotes available for invoice generation.
	 *
	 * We keep one invoice per quote to avoid duplicate billing from the same
	 * approved quote until a split-invoice flow is explicitly implemented.
	 *
	 * @param int $process_id Process ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_approved_quotes_for_process( $process_id ) {
		$quotes    = $this->quote_service->get_approved_quotes_for_process( $process_id );
		$available = array();

		foreach ( $quotes as $quote ) {
			if ( ! $this->quote_has_invoice( absint( $quote['id'] ) ) ) {
				$available[] = $quote;
			}
		}

		return $available;
	}

	/**
	 * Count invoices.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_invoices( array $args = array() ) {
		return $this->repository->count_all( $args );
	}

	/**
	 * Get invoice items.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_items( $invoice_id ) {
		return $this->item_repository->get_by_invoice_id( $invoice_id );
	}

	/**
	 * Add invoice item.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Item data.
	 * @return int|WP_Error
	 */
	public function add_invoice_item( $invoice_id, array $data ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$data['invoice_id'] = $invoice_id;
		$data               = $this->prepare_invoice_item_data( $data );
		$valid              = $this->validate_invoice_item_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->item_repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_invoice_item_create_failed', __( 'No fue posible agregar el item de la factura.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $invoice_id );
		$this->recalculate_balance( $invoice_id );

		return $inserted;
	}

	/**
	 * Update invoice item.
	 *
	 * @param int                  $item_id Item ID.
	 * @param array<string, mixed> $data    Item data.
	 * @return bool|WP_Error
	 */
	public function update_invoice_item( $item_id, array $data ) {
		$item_id = absint( $item_id );
		$item    = $this->item_repository->get_by_id( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'sm_invoice_item_not_found', __( 'El item de la factura no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_invoice_item_data( array_merge( $item, $data ) );
		$valid = $this->validate_invoice_item_data( $data, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->item_repository->update( $item_id, $data ) ) {
			return new WP_Error( 'sm_invoice_item_update_failed', __( 'No fue posible actualizar el item de la factura.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( absint( $item['invoice_id'] ) );
		$this->recalculate_balance( absint( $item['invoice_id'] ) );

		return true;
	}

	/**
	 * Delete invoice item.
	 *
	 * @param int $item_id Item ID.
	 * @return bool|WP_Error
	 */
	public function delete_invoice_item( $item_id ) {
		$item_id = absint( $item_id );
		$item    = $this->item_repository->get_by_id( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'sm_invoice_item_not_found', __( 'El item de la factura no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->item_repository->delete( $item_id ) ) {
			return new WP_Error( 'sm_invoice_item_delete_failed', __( 'No fue posible eliminar el item de la factura.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( absint( $item['invoice_id'] ) );
		$this->recalculate_balance( absint( $item['invoice_id'] ) );

		return true;
	}

	/**
	 * Get payments.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_payments( $invoice_id ) {
		return $this->payment_repository->get_by_invoice_id( $invoice_id );
	}

	/**
	 * Add payment.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Payment data.
	 * @return int|WP_Error
	 */
	public function add_payment( $invoice_id, array $data ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$data['invoice_id'] = $invoice_id;
		$data               = $this->prepare_payment_data( $data );
		$valid              = $this->validate_payment_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->can_invoice_receive_payments( $invoice ) ) {
			return new WP_Error( 'sm_invoice_payment_not_allowed', __( 'La factura no permite registrar pagos en su estado actual.', 'super-mechanic' ) );
		}

		$previous_summary = $this->get_invoice_payment_summary( $invoice_id );

		if ( is_wp_error( $previous_summary ) ) {
			return $previous_summary;
		}

		$payment_validation = $this->validate_payment_amount_against_balance( $invoice, $data['amount'] );

		if ( is_wp_error( $payment_validation ) ) {
			return $payment_validation;
		}

		$inserted = $this->payment_repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_payment_create_failed', __( 'No fue posible registrar el pago.', 'super-mechanic' ) );
		}

		$this->recalculate_balance( $invoice_id );

		$this->event_dispatcher->dispatch(
			'payment_registered',
			array(
				'invoice_id'   => absint( $invoice_id ),
				'process_id'   => ! empty( $invoice['process_id'] ) ? absint( $invoice['process_id'] ) : 0,
				'client_id'    => ! empty( $invoice['client_id'] ) ? absint( $invoice['client_id'] ) : 0,
				'amount'       => $data['amount'],
				'triggered_by' => get_current_user_id(),
			)
		);

		$this->dispatch_invoice_paid_if_transitioned( $invoice_id, $previous_summary, get_current_user_id() );

		return $inserted;
	}

	public function update_payment( $payment_id, array $data ) {
		$payment_id = absint( $payment_id );
		$payment    = $this->payment_repository->get_by_id( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'sm_payment_not_found', __( 'El pago no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_payment_data( array_merge( $payment, $data ) );
		$valid = $this->validate_payment_data( $data, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$invoice = $this->repository->get_by_id( absint( $payment['invoice_id'] ) );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->can_invoice_receive_payments( $invoice ) ) {
			return new WP_Error( 'sm_invoice_payment_not_allowed', __( 'La factura no permite registrar pagos en su estado actual.', 'super-mechanic' ) );
		}

		$previous_summary = $this->get_invoice_payment_summary( absint( $payment['invoice_id'] ) );

		if ( is_wp_error( $previous_summary ) ) {
			return $previous_summary;
		}

		$payment_validation = $this->validate_payment_amount_against_balance( $invoice, $data['amount'], $payment_id );

		if ( is_wp_error( $payment_validation ) ) {
			return $payment_validation;
		}

		if ( ! $this->payment_repository->update( $payment_id, $data ) ) {
			return new WP_Error( 'sm_payment_update_failed', __( 'No fue posible actualizar el pago.', 'super-mechanic' ) );
		}

		$this->recalculate_balance( absint( $payment['invoice_id'] ) );

		$this->dispatch_invoice_paid_if_transitioned( absint( $payment['invoice_id'] ), $previous_summary, get_current_user_id() );

		return true;
	}

	/**
	 * Delete payment.
	 *
	 * @param int $payment_id Payment ID.
	 * @return bool|WP_Error
	 */
	public function delete_payment( $payment_id ) {
		$payment_id = absint( $payment_id );
		$payment    = $this->payment_repository->get_by_id( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'sm_payment_not_found', __( 'El pago no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->payment_repository->delete( $payment_id ) ) {
			return new WP_Error( 'sm_payment_delete_failed', __( 'No fue posible eliminar el pago.', 'super-mechanic' ) );
		}

		$this->recalculate_balance( absint( $payment['invoice_id'] ) );

		return true;
	}

	/**
	 * Recalculate invoice totals.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return bool|WP_Error
	 */
	public function recalculate_totals( $invoice_id ) {
		$invoice = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$items     = $this->get_invoice_items( $invoice_id );
		$subtotal  = 0.0;
		$tax_total = isset( $invoice['tax_total'] ) ? (float) $invoice['tax_total'] : 0.0;
		$discount  = isset( $invoice['discount_total'] ) ? (float) $invoice['discount_total'] : 0.0;

		foreach ( $items as $item ) {
			$subtotal += (float) $item['line_total'];
		}

		$grand_total = max( 0, $subtotal + $tax_total - $discount );

		return $this->repository->update(
			$invoice_id,
			array(
				'subtotal'   => round( $subtotal, 2 ),
				'grand_total' => round( $grand_total, 2 ),
			)
		);
	}

	/**
	 * Recalculate invoice balance.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return bool|WP_Error
	 */
	public function recalculate_balance( $invoice_id ) {
		$invoice = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$payment_summary = $this->get_invoice_payment_summary( $invoice_id );

		if ( is_wp_error( $payment_summary ) ) {
			return $payment_summary;
		}

		$amount_paid = $payment_summary['total_paid'];
		$balance_due = $payment_summary['remaining_balance'];
		$status      = $invoice['status'];
		$paid_at     = $invoice['paid_at'];

		if ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
			$paid_at = null;
		} elseif ( $balance_due <= 0 && (float) $invoice['grand_total'] > 0 ) {
			$status = 'paid';
			$paid_at = $paid_at ? $paid_at : current_time( 'mysql' );
		} elseif ( $amount_paid > 0 && $balance_due > 0 ) {
			$status = 'partially_paid';
			$paid_at = null;
		} elseif ( in_array( $status, array( 'paid', 'partially_paid' ), true ) ) {
			$status = ! empty( $invoice['issued_at'] ) ? 'issued' : 'draft';
			$paid_at = null;
		} elseif ( 'issued' === $status && ! empty( $invoice['due_date'] ) ) {
			$due_timestamp = strtotime( $invoice['due_date'] . ' 23:59:59' );

			if ( false !== $due_timestamp && time() > $due_timestamp ) {
				$status = 'overdue';
			}
		} elseif ( 'overdue' === $status && ! empty( $invoice['issued_at'] ) ) {
			$due_timestamp = ! empty( $invoice['due_date'] ) ? strtotime( $invoice['due_date'] . ' 23:59:59' ) : false;

			if ( empty( $invoice['due_date'] ) || ( false !== $due_timestamp && time() <= $due_timestamp ) ) {
				$status = 'issued';
			}
		}

		return $this->repository->update(
			$invoice_id,
			array(
				'amount_paid' => round( $amount_paid, 2 ),
				'balance_due' => $balance_due,
				'status'      => $status,
				'paid_at'     => $paid_at,
			)
		);
	}

	/**
	 * Generate invoice number.
	 *
	 * @return string
	 */
	public function generate_invoice_number() {
		$prefix = 'SMI-' . gmdate( 'Ymd' ) . '-';
		$index  = 1;

		do {
			$invoice_number = $prefix . str_pad( (string) $index, 4, '0', STR_PAD_LEFT );
			$exists         = $this->repository->get_by_invoice_number( $invoice_number );
			++$index;
		} while ( $exists );

		return $invoice_number;
	}

	/**
	 * Create invoice from approved quote.
	 *
	 * @param int                  $quote_id Quote ID.
	 * @param array<string, mixed> $args     Extra args.
	 * @return int|WP_Error
	 */
	public function create_invoice_from_quote( $quote_id, $args = array() ) {
		$quote_id = absint( $quote_id );
		$quote    = $this->quote_service->get_quote( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		if ( 'approved' !== $quote['status'] ) {
			return new WP_Error( 'sm_quote_not_approved', __( 'Solo se puede facturar una cotizacion aprobada.', 'super-mechanic' ) );
		}

		if ( $this->quote_has_invoice( $quote_id ) ) {
			return new WP_Error( 'sm_quote_already_invoiced', __( 'La cotizacion aprobada ya fue convertida en factura.', 'super-mechanic' ) );
		}

		$items = $this->quote_service->get_quote_items( $quote_id );

		if ( empty( $items ) ) {
			return new WP_Error( 'sm_quote_without_items', __( 'La cotizacion aprobada no tiene items para facturar.', 'super-mechanic' ) );
		}

		$result = $this->transaction_repository->run_in_transaction(
			function () use ( $quote, $quote_id, $args, $items ) {
				return $this->create_invoice_from_quote_transactional( $quote, $quote_id, $args, $items );
			}
		);

		if ( ! is_wp_error( $result ) ) {
			$this->event_dispatcher->dispatch(
				'invoice_created_from_quote',
				array(
					'invoice_id'   => absint( $result ),
					'quote_id'     => $quote_id,
					'process_id'   => absint( $quote['process_id'] ),
					'client_id'    => absint( $quote['client_id'] ),
					'triggered_by' => get_current_user_id(),
				)
			);
		}

		return $result;
	}

	/**
	 * Get normalized payment summary for an invoice.
	 *
	 * This keeps the internal invoice statuses intact while exposing the
	 * cobranzas state required by the payment flow.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_invoice_payment_summary( $invoice_id, $exclude_payment_id = null ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$total_paid         = 0.0;
		$invoice_total      = isset( $invoice['grand_total'] ) ? round( (float) $invoice['grand_total'], 2 ) : 0.0;
		$exclude_payment_id = null === $exclude_payment_id ? 0 : absint( $exclude_payment_id );
		$payments           = $this->payment_repository->get_by_invoice_id( $invoice_id );

		foreach ( $payments as $payment ) {
			if ( $exclude_payment_id > 0 && absint( $payment['id'] ) === $exclude_payment_id ) {
				continue;
			}

			$total_paid += isset( $payment['amount'] ) ? (float) $payment['amount'] : 0.0;
		}

		$total_paid        = round( max( 0, $total_paid ), 2 );
		$remaining_balance = round( max( 0, $invoice_total - $total_paid ), 2 );
		$payment_status    = 'pending';

		if ( $total_paid > 0 && $remaining_balance > 0 ) {
			$payment_status = 'partial';
		} elseif ( $invoice_total > 0 && $total_paid >= $invoice_total ) {
			$payment_status = 'paid';
		}

		return array(
			'total_paid'        => $total_paid,
			'remaining_balance' => $remaining_balance,
			'balance_due'       => $remaining_balance,
			'payment_status'    => $payment_status,
			'collection_status' => $payment_status,
			'payment_label'     => $this->get_invoice_collection_status_label( $payment_status ),
			'collection_label'  => $this->get_invoice_collection_status_label( $payment_status ),
		);
	}

	/**
	 * Append derived collection state fields to an invoice row.
	 *
	 * @param array<string, mixed> $invoice Invoice row.
	 * @return array<string, mixed>
	 */
	public function append_collection_state( array $invoice ) {
		if ( empty( $invoice['id'] ) ) {
			$invoice['collection_status'] = '';
			$invoice['collection_label']  = '';
			return $invoice;
		}

		$summary = $this->get_invoice_payment_summary( absint( $invoice['id'] ) );

		if ( is_wp_error( $summary ) ) {
			$invoice['collection_status'] = '';
			$invoice['collection_label']  = '';
			return $invoice;
		}

		$invoice['collection_status'] = $summary['collection_status'];
		$invoice['collection_label']  = $summary['collection_label'];
		$invoice['payment_status']    = $summary['payment_status'];
		$invoice['payment_label']     = $summary['payment_label'];
		$invoice['amount_paid']       = $summary['total_paid'];
		$invoice['balance_due']       = $summary['remaining_balance'];

		return $invoice;
	}

	/**
	 * Get the public-facing collection status label.
	 *
	 * @param string $status Collection status key.
	 * @return string
	 */
	public function get_invoice_collection_status_label( $status ) {
		$labels = array(
			'pending' => __( 'Pendiente', 'super-mechanic' ),
			'partial' => __( 'Parcial', 'super-mechanic' ),
			'paid'    => __( 'Pagado', 'super-mechanic' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $this->humanize_key( $status );
	}

	/**
	 * Issue invoice.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return bool|WP_Error
	 */
	public function issue_invoice( $invoice_id ) {
		$invoice = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		if ( 'draft' !== $invoice['status'] ) {
			return new WP_Error( 'sm_invalid_invoice_status', __( 'Solo se puede emitir una factura en borrador.', 'super-mechanic' ) );
		}

		$result = $this->repository->update(
			$invoice_id,
			array(
				'status'    => 'issued',
				'issued_at' => current_time( 'mysql' ),
			)
		);

		if ( $result ) {
			$this->event_dispatcher->dispatch(
				'invoice_issued',
				array(
					'invoice_id'   => absint( $invoice_id ),
					'process_id'   => ! empty( $invoice['process_id'] ) ? absint( $invoice['process_id'] ) : 0,
					'client_id'    => ! empty( $invoice['client_id'] ) ? absint( $invoice['client_id'] ) : 0,
					'triggered_by' => get_current_user_id(),
				)
			);
		}

		return $result;
	}

	public function mark_invoice_paid_if_applicable( $invoice_id ) {
		return $this->recalculate_balance( $invoice_id );
	}

	/**
	 * Check whether user can access invoice.
	 *
	 * @param int $user_id    User ID.
	 * @param int $invoice_id Invoice ID.
	 * @return bool
	 */
	public function user_can_access_invoice( $user_id, $invoice_id ) {
		$user_id = absint( $user_id );
		$invoice = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return false;
		}

		if ( user_can( $user_id, 'sm_manage_processes' ) || user_can( $user_id, 'sm_manage_plugin' ) ) {
			return true;
		}

		$client_id = absint( get_user_meta( $user_id, 'sm_client_id', true ) );

		if ( ! $client_id ) {
			return false;
		}

		if ( absint( $invoice['client_id'] ) === $client_id ) {
			return true;
		}

		if ( ! empty( $invoice['process_client_id'] ) && absint( $invoice['process_client_id'] ) === $client_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Build printable invoice context.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_invoice_print_context( $invoice_id ) {
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		$settings = get_option( Settings::OPTION_NAME, array() );

		return array(
			'invoice'     => $invoice,
			'items'       => $this->get_invoice_items( $invoice_id ),
			'payments'    => $this->get_payments( $invoice_id ),
			'company'     => ! empty( $settings['company_name'] ) ? sanitize_text_field( $settings['company_name'] ) : __( 'Super Mechanic', 'super-mechanic' ),
			'client_name' => ! empty( $invoice['client_name'] ) ? $invoice['client_name'] : __( 'Cliente no asignado', 'super-mechanic' ),
		);
	}

	/**
	 * Render printable invoice HTML from context.
	 *
	 * @param array<string, mixed> $context Invoice printable context.
	 * @return string
	 */
	public function render_invoice_printable_html( array $context ) {
		$invoice  = $context['invoice'];
		$items    = ! empty( $context['items'] ) && is_array( $context['items'] ) ? $context['items'] : array();
		$payments = ! empty( $context['payments'] ) && is_array( $context['payments'] ) ? $context['payments'] : array();
		$company  = isset( $context['company'] ) ? $context['company'] : __( 'Super Mechanic', 'super-mechanic' );
		$client   = isset( $context['client_name'] ) ? $context['client_name'] : __( 'Cliente no asignado', 'super-mechanic' );

		ob_start();
		echo '<div class="sm-invoice-print">';
		echo '<h1>' . esc_html( $company ) . '</h1>';
		echo '<h2>' . esc_html( sprintf( __( 'Factura %s', 'super-mechanic' ), $invoice['invoice_number'] ) ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Cliente:', 'super-mechanic' ) . '</strong> ' . esc_html( $client ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Proceso:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['process_title'] ) ? $invoice['process_title'] : '#' . $invoice['process_id'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Estado:', 'super-mechanic' ) . '</strong> ' . esc_html( ucwords( str_replace( '_', ' ', $invoice['status'] ) ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Emitida:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['issued_at'] ) ? $invoice['issued_at'] : '-' ) . ' <strong>' . esc_html__( 'Vence:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['due_date'] ) ? $invoice['due_date'] : '-' ) . '</p>';
		echo '<table border="1" cellpadding="8" cellspacing="0" width="100%"><thead><tr><th>' . esc_html__( 'Item', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Descripcion', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay items.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr>';
				echo '<td>' . esc_html( $item['label'] ) . '</td>';
				echo '<td>' . esc_html( $item['description'] ) . '</td>';
				echo '<td>' . esc_html( $item['quantity'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['unit_price'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['line_total'], $invoice['currency'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Subtotal:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $invoice['subtotal'], $invoice['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Impuestos:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $invoice['tax_total'], $invoice['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Descuento:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $invoice['discount_total'], $invoice['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Total:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $invoice['grand_total'], $invoice['currency'] ) ) . '</p>';
		$payment_summary = $this->get_invoice_payment_summary( absint( $invoice['id'] ) );
		$payment_total   = is_wp_error( $payment_summary ) ? 0 : $payment_summary['total_paid'];
		$balance_due     = is_wp_error( $payment_summary ) ? ( isset( $invoice['grand_total'] ) ? (float) $invoice['grand_total'] : 0 ) : $payment_summary['remaining_balance'];
		echo '<p><strong>' . esc_html__( 'Pagado:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $payment_total, $invoice['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Saldo pendiente:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( $balance_due, $invoice['currency'] ) ) . '</p>';
		if ( ! empty( $invoice['notes'] ) ) {
			echo '<p><strong>' . esc_html__( 'Notas:', 'super-mechanic' ) . '</strong> ' . esc_html( $invoice['notes'] ) . '</p>';
		}
		if ( ! empty( $payments ) ) {
			echo '<h3>' . esc_html__( 'Pagos', 'super-mechanic' ) . '</h3><ul>';
			foreach ( $payments as $payment ) {
				echo '<li>' . esc_html( $payment['payment_date'] . ' - ' . $payment['payment_method'] . ' - ' . $this->format_money( $payment['amount'], $invoice['currency'] ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Get printable HTML.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return string
	 */
	public function get_printable_invoice_html( $invoice_id ) {
		$context = $this->get_invoice_print_context( $invoice_id );

		if ( is_wp_error( $context ) ) {
			return '<p>' . esc_html( $context->get_error_message() ) . '</p>';
		}

		return $this->render_invoice_printable_html( $context );
	}

	/**
	 * Build a predictable invoice PDF file name.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return string
	 */
	public function get_invoice_pdf_filename( $invoice_id ) {
		$invoice = $this->repository->get_by_id( $invoice_id );

		if ( ! $invoice ) {
			return 'invoice-' . absint( $invoice_id ) . '.pdf';
		}

		$number = ! empty( $invoice['invoice_number'] ) ? sanitize_file_name( strtolower( $invoice['invoice_number'] ) ) : 'invoice-' . absint( $invoice_id );

		return $number . '.pdf';
	}

	/**
	 * Validate invoice data.
	 *
	 * @param array<string, mixed> $data      Data.
	 * @param bool                 $is_update Updating.
	 * @return true|WP_Error
	 */
	public function validate_invoice_data( array $data, $is_update = false ) {
		$errors           = new WP_Error();
		$allowed_statuses = array( 'draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'refunded' );

		if ( empty( $data['invoice_number'] ) ) {
			$errors->add( 'invoice_number_required', __( 'El numero de factura es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
			$errors->add( 'invalid_status', __( 'El estado de la factura no es valido.', 'super-mechanic' ) );
		}

		if ( ! $is_update && ! empty( $data['quote_id'] ) && $this->quote_has_invoice( $data['quote_id'] ) ) {
			$errors->add( 'duplicate_quote_invoice', __( 'La cotizacion ya tiene una factura asociada.', 'super-mechanic' ) );
		}

		if ( $data['subtotal'] < 0 || $data['tax_total'] < 0 || $data['discount_total'] < 0 || $data['grand_total'] < 0 ) {
			$errors->add( 'invalid_amounts', __( 'Los montos de la factura no son validos.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Validate invoice item data.
	 *
	 * @param array<string, mixed> $data      Data.
	 * @param bool                 $is_update Updating.
	 * @return true|WP_Error
	 */
	public function validate_invoice_item_data( array $data, $is_update = false ) {
		$errors             = new WP_Error();
		$allowed_item_types = array( 'part', 'labor', 'custom', 'quote_item' );

		if ( empty( $data['invoice_id'] ) ) {
			$errors->add( 'invoice_required', __( 'La factura es obligatoria.', 'super-mechanic' ) );
		} elseif ( ! $this->repository->get_by_id( absint( $data['invoice_id'] ) ) ) {
			$errors->add( 'invoice_not_found', __( 'La factura indicada no existe.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['item_type'], $allowed_item_types, true ) ) {
			$errors->add( 'invalid_item_type', __( 'El tipo de item no es valido.', 'super-mechanic' ) );
		}

		if ( '' === $data['label'] ) {
			$errors->add( 'label_required', __( 'La etiqueta del item es obligatoria.', 'super-mechanic' ) );
		}

		if ( $data['quantity'] <= 0 ) {
			$errors->add( 'invalid_quantity', __( 'La cantidad debe ser mayor que cero.', 'super-mechanic' ) );
		}

		if ( $data['unit_price'] < 0 ) {
			$errors->add( 'invalid_unit_price', __( 'El precio unitario no puede ser negativo.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Validate payment data.
	 *
	 * @param array<string, mixed> $data      Data.
	 * @param bool                 $is_update Updating.
	 * @return true|WP_Error
	 */
	public function validate_payment_data( array $data, $is_update = false ) {
		$errors                  = new WP_Error();
		$allowed_payment_methods = array( 'cash', 'transfer', 'card', 'check', 'other' );

		if ( empty( $data['invoice_id'] ) ) {
			$errors->add( 'invoice_required', __( 'La factura es obligatoria.', 'super-mechanic' ) );
		}

		if ( $data['amount'] <= 0 ) {
			$errors->add( 'invalid_amount', __( 'El monto del pago debe ser mayor que cero.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['payment_method'], $allowed_payment_methods, true ) ) {
			$errors->add( 'invalid_payment_method', __( 'El metodo de pago no es valido.', 'super-mechanic' ) );
		}

		if ( empty( $data['payment_date'] ) ) {
			$errors->add( 'invalid_payment_date', __( 'La fecha del pago no es valida.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Prepare invoice data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_invoice_data( array $data ) {
		$quote_id = isset( $data['quote_id'] ) ? absint( $data['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		return array(
			'process_id'     => isset( $data['process_id'] ) ? absint( $data['process_id'] ) : ( $quote ? absint( $quote['process_id'] ) : 0 ),
			'quote_id'       => $quote_id,
			'client_id'      => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : ( $quote ? absint( $quote['client_id'] ) : 0 ),
			'invoice_number' => ! empty( $data['invoice_number'] ) ? sanitize_text_field( $data['invoice_number'] ) : $this->generate_invoice_number(),
			'status'         => ! empty( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
			'currency'       => ! empty( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : $this->get_default_currency(),
			'subtotal'       => isset( $data['subtotal'] ) ? $this->normalize_decimal( $data['subtotal'] ) : 0,
			'tax_total'      => isset( $data['tax_total'] ) ? $this->normalize_decimal( $data['tax_total'] ) : 0,
			'discount_total' => isset( $data['discount_total'] ) ? $this->normalize_decimal( $data['discount_total'] ) : 0,
			'grand_total'    => isset( $data['grand_total'] ) ? $this->normalize_decimal( $data['grand_total'] ) : 0,
			'amount_paid'    => isset( $data['amount_paid'] ) ? $this->normalize_decimal( $data['amount_paid'] ) : 0,
			'balance_due'    => isset( $data['balance_due'] ) ? $this->normalize_decimal( $data['balance_due'] ) : 0,
			'issued_at'      => isset( $data['issued_at'] ) ? $this->normalize_datetime_value( $data['issued_at'] ) : null,
			'due_date'       => isset( $data['due_date'] ) ? $this->normalize_date_value( $data['due_date'] ) : null,
			'paid_at'        => isset( $data['paid_at'] ) ? $this->normalize_datetime_value( $data['paid_at'] ) : null,
			'notes'          => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'created_by'     => isset( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id(),
		);
	}

	/**
	 * Prepare invoice item data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_invoice_item_data( array $data ) {
		$quantity   = isset( $data['quantity'] ) ? $this->normalize_decimal( $data['quantity'] ) : 1;
		$unit_price = isset( $data['unit_price'] ) ? $this->normalize_decimal( $data['unit_price'] ) : 0;

		return array(
			'invoice_id'   => isset( $data['invoice_id'] ) ? absint( $data['invoice_id'] ) : 0,
			'item_type'    => isset( $data['item_type'] ) ? sanitize_key( $data['item_type'] ) : 'custom',
			'reference_id' => isset( $data['reference_id'] ) ? absint( $data['reference_id'] ) : 0,
			'label'        => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '',
			'description'  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'quantity'     => $quantity,
			'unit_price'   => $unit_price,
			'line_total'   => round( $quantity * $unit_price, 2 ),
			'sort_order'   => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
		);
	}

	/**
	 * Prepare payment data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_payment_data( array $data ) {
		return array(
			'invoice_id'     => isset( $data['invoice_id'] ) ? absint( $data['invoice_id'] ) : 0,
			'payment_date'   => isset( $data['payment_date'] ) ? $this->normalize_datetime_value( $data['payment_date'] ) : current_time( 'mysql' ),
			'amount'         => isset( $data['amount'] ) ? $this->normalize_decimal( $data['amount'] ) : 0,
			'payment_method' => isset( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'cash',
			'reference'      => isset( $data['reference'] ) ? sanitize_text_field( $data['reference'] ) : '',
			'notes'          => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'received_by'    => isset( $data['received_by'] ) ? absint( $data['received_by'] ) : get_current_user_id(),
		);
	}

	/**
	 * Get default currency.
	 *
	 * @return string
	 */
	protected function get_default_currency() {
		$settings = get_option( Settings::OPTION_NAME, array() );

		return ! empty( $settings['default_currency'] ) ? sanitize_text_field( $settings['default_currency'] ) : 'USD';
	}

	/**
	 * Normalize decimal.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	protected function normalize_decimal( $value ) {
		return round( (float) str_replace( ',', '.', (string) $value ), 2 );
	}

	/**
	 * Normalize datetime.
	 *
	 * @param string $value Raw value.
	 * @return string|null
	 */
	protected function normalize_datetime_value( $value ) {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Normalize date.
	 *
	 * @param string $value Raw value.
	 * @return string|null
	 */
	protected function normalize_date_value( $value ) {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Format money.
	 *
	 * @param mixed  $amount   Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	protected function format_money( $amount, $currency ) {
		return sprintf( '%s %s', esc_html( $currency ), esc_html( number_format_i18n( (float) $amount, 2 ) ) );
	}

	/**
	 * Check if a quote already has an invoice.
	 *
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	protected function quote_has_invoice( $quote_id ) {
		$quote_id = absint( $quote_id );

		if ( ! $quote_id ) {
			return false;
		}

		return ! empty( $this->repository->get_by_quote_id( $quote_id ) );
	}

	/**
	 * Check if the invoice can receive payments.
	 *
	 * @param array<string, mixed> $invoice Invoice data.
	 * @return bool
	 */
	protected function can_invoice_receive_payments( array $invoice ) {
		return ! in_array( $invoice['status'], array( 'cancelled', 'refunded' ), true );
	}

	/**
	 * Validate that a payment does not exceed the remaining balance.
	 *
	 * @param array<string, mixed> $invoice     Invoice data.
	 * @param float                $amount      New payment amount.
	 * @param int                  $payment_id  Optional payment ID being updated.
	 * @return true|WP_Error
	 */
	protected function validate_payment_amount_against_balance( array $invoice, $amount, $payment_id = 0 ) {
		$amount          = round( (float) $amount, 2 );
		$payment_summary = $this->get_invoice_payment_summary( absint( $invoice['id'] ), $payment_id );

		if ( is_wp_error( $payment_summary ) ) {
			return $payment_summary;
		}

		$available_balance = isset( $payment_summary['remaining_balance'] ) ? round( (float) $payment_summary['remaining_balance'], 2 ) : 0.0;

		if ( $amount > $available_balance ) {
			return new WP_Error(
				'sm_payment_exceeds_balance',
				sprintf(
					/* translators: %s remaining balance amount */
					__( 'El pago no puede ser mayor al saldo pendiente disponible (%s).', 'super-mechanic' ),
					$this->format_money( $available_balance, $invoice['currency'] )
				)
			);
		}

		return true;
	}

	/**
	 * Humanize a machine key.
	 *
	 * @param string $value Raw key.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Execute invoice creation from quote inside a transaction boundary.
	 *
	 * @param array<string, mixed>       $quote    Quote data.
	 * @param int                        $quote_id Quote ID.
	 * @param array<string, mixed>       $args     Extra args.
	 * @param array<int, array<string,mixed>> $items Quote items.
	 * @return int|WP_Error
	 */
	protected function create_invoice_from_quote_transactional( array $quote, $quote_id, array $args, array $items ) {
		$invoice_id = $this->create_invoice(
			array(
				'process_id'     => absint( $quote['process_id'] ),
				'quote_id'       => $quote_id,
				'client_id'      => absint( $quote['client_id'] ),
				'status'         => ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft',
				'currency'       => ! empty( $args['currency'] ) ? sanitize_text_field( $args['currency'] ) : $quote['currency'],
				'tax_total'      => isset( $args['tax_total'] ) ? $this->normalize_decimal( $args['tax_total'] ) : $quote['tax_total'],
				'discount_total' => isset( $args['discount_total'] ) ? $this->normalize_decimal( $args['discount_total'] ) : $quote['discount_total'],
				'notes'          => ! empty( $args['notes'] ) ? sanitize_textarea_field( $args['notes'] ) : $quote['notes'],
				'created_by'     => get_current_user_id(),
			)
		);

		if ( is_wp_error( $invoice_id ) ) {
			return $invoice_id;
		}

		foreach ( $items as $item ) {
			$result = $this->add_invoice_item(
				$invoice_id,
				array(
					'item_type'    => ! empty( $item['item_type'] ) ? $item['item_type'] : 'quote_item',
					'reference_id' => absint( $item['id'] ),
					'label'        => $item['label'],
					'description'  => $item['description'],
					'quantity'     => $item['quantity'],
					'unit_price'   => $item['unit_price'],
					'sort_order'   => $item['sort_order'],
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$result = $this->recalculate_totals( $invoice_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = $this->recalculate_balance( $invoice_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $invoice_id;
	}

	/**
	 * Dispatch invoice paid only on a real transition to collection status paid.
	 *
	 * @param int                 $invoice_id        Invoice ID.
	 * @param array<string,mixed> $previous_summary  Summary before the mutation.
	 * @param int                 $triggered_by      User ID.
	 * @return void
	 */
	protected function dispatch_invoice_paid_if_transitioned( $invoice_id, array $previous_summary, $triggered_by ) {
		$current_summary = $this->get_invoice_payment_summary( $invoice_id );

		if ( is_wp_error( $current_summary ) ) {
			return;
		}

		$previous_status = isset( $previous_summary['payment_status'] ) ? sanitize_key( $previous_summary['payment_status'] ) : '';
		$current_status  = isset( $current_summary['payment_status'] ) ? sanitize_key( $current_summary['payment_status'] ) : '';

		if ( 'paid' === $current_status && 'paid' !== $previous_status ) {
			$this->event_dispatcher->dispatch(
				'invoice_paid',
				array(
					'invoice_id'   => absint( $invoice_id ),
					'triggered_by' => absint( $triggered_by ),
				)
			);
		}
	}
}







