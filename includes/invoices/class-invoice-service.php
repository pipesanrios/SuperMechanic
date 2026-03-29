<?php
/**
 * Invoice service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Integrations\WooCommerce\Woo_Product_Service;
use Super_Mechanic\Quotes\Quote_Service;
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
	protected $settings_service;
	protected $business_context_service;
	protected $woo_product_service;

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
	public function __construct( Invoice_Repository $repository = null, Invoice_Item_Repository $item_repository = null, Payment_Repository $payment_repository = null, Quote_Service $quote_service = null, Event_Dispatcher $event_dispatcher = null, Invoice_Transaction_Repository $transaction_repository = null, Access_Control_Service $access_control_service = null, Settings_Service $settings_service = null, Business_Context_Service $business_context_service = null, Woo_Product_Service $woo_product_service = null ) {
		$this->repository             = $repository ? $repository : new Invoice_Repository();
		$this->item_repository        = $item_repository ? $item_repository : new Invoice_Item_Repository();
		$this->payment_repository     = $payment_repository ? $payment_repository : new Payment_Repository();
		$this->quote_service          = $quote_service ? $quote_service : new Quote_Service();
		$this->event_dispatcher       = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->transaction_repository = $transaction_repository ? $transaction_repository : new Invoice_Transaction_Repository();
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service( null, null, null, null, $this->repository );
		$this->settings_service       = $settings_service ? $settings_service : new Settings_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->woo_product_service      = $woo_product_service ? $woo_product_service : new Woo_Product_Service();
	}

	/**
	 * Whether Woo product catalog is available.
	 *
	 * @return bool
	 */
	public function is_woo_available() {
		return $this->woo_product_service->is_available();
	}

	/**
	 * Get Woo product options for admin selectors.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_woo_product_options( $limit = 100 ) {
		return $this->woo_product_service->get_product_options( $limit );
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
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

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

		$raw_data             = $data;
		$data['invoice_id']   = $invoice_id;
		$data['business_id']  = ! empty( $invoice['business_id'] ) ? absint( $invoice['business_id'] ) : $this->resolve_business_id();
		$data                 = $this->prepare_invoice_item_data( $data );
		$valid                = $this->validate_invoice_item_data(
			$data,
			false,
			array(
				'raw' => $raw_data,
			)
		);

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

		$raw_data = $data;
		$merged   = array_merge( $item, $data );
		$merged   = $this->apply_legacy_woo_sanitization( $merged, $raw_data, $item );
		if ( ! empty( $item['invoice_id'] ) ) {
			$parent_invoice = $this->repository->get_by_id( absint( $item['invoice_id'] ) );
			if ( is_array( $parent_invoice ) && ! empty( $parent_invoice['business_id'] ) ) {
				$merged['business_id'] = absint( $parent_invoice['business_id'] );
			}
		}

		$data  = $this->prepare_invoice_item_data( $merged );
		$valid = $this->validate_invoice_item_data(
			$data,
			true,
			array(
				'raw'           => $raw_data,
				'existing_item' => $item,
			)
		);

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
	 * Get payment by ID.
	 *
	 * @param int $payment_id Payment ID.
	 * @return array<string, mixed>|null
	 */
	public function get_payment( $payment_id ) {
		return $this->payment_repository->get_by_id( $payment_id );
	}

	/**
	 * Check whether a user can access a payment receipt through its invoice.
	 *
	 * @param int $user_id    User ID.
	 * @param int $payment_id Payment ID.
	 * @return bool
	 */
	public function user_can_access_payment( $user_id, $payment_id ) {
		$user_id    = absint( $user_id );
		$payment_id = absint( $payment_id );
		$payment    = $this->get_payment( $payment_id );

		if ( ! $user_id || ! $payment || empty( $payment['invoice_id'] ) ) {
			return false;
		}

		return $this->user_can_access_invoice( $user_id, absint( $payment['invoice_id'] ) );
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
				'payment_id'    => absint( $inserted ),
				'invoice_id'    => absint( $invoice_id ),
				'process_id'    => ! empty( $invoice['process_id'] ) ? absint( $invoice['process_id'] ) : 0,
				'client_id'     => ! empty( $invoice['client_id'] ) ? absint( $invoice['client_id'] ) : 0,
				'amount'        => $data['amount'],
				'payment_date'  => $data['payment_date'],
				'payment_method'=> $data['payment_method'],
				'triggered_by'  => get_current_user_id(),
			)
		);

		$this->dispatch_invoice_paid_if_transitioned( $invoice_id, $previous_summary, get_current_user_id(), $inserted );

		return $inserted;
	}

	/**
	 * Normalize tax and discount inputs into persisted totals.
	 *
	 * This keeps schema compatibility: percentage-based inputs are converted
	 * into absolute totals before saving the invoice row.
	 *
	 * @param float                $subtotal Current invoice subtotal.
	 * @param array<string, mixed> $data     Raw request-like data.
	 * @return array<string, float>
	 */
	public function normalize_adjustment_totals( $subtotal, array $data ) {
		$subtotal = round( max( 0, (float) $subtotal ), 2 );

		return array(
			'tax_total'      => $this->normalize_adjustment_total(
				$subtotal,
				isset( $data['tax_mode'] ) ? $data['tax_mode'] : 'fixed',
				isset( $data['tax_value'] ) ? $data['tax_value'] : ( isset( $data['tax_total'] ) ? $data['tax_total'] : 0 )
			),
			'discount_total' => $this->normalize_adjustment_total(
				$subtotal,
				isset( $data['discount_mode'] ) ? $data['discount_mode'] : 'fixed',
				isset( $data['discount_value'] ) ? $data['discount_value'] : ( isset( $data['discount_total'] ) ? $data['discount_total'] : 0 )
			),
		);
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

		$this->dispatch_invoice_paid_if_transitioned( absint( $payment['invoice_id'] ), $previous_summary, get_current_user_id(), $payment_id );

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
			$item_id             = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$quantity            = isset( $item['quantity'] ) ? $this->normalize_decimal( $item['quantity'] ) : 0;
			$unit_price          = isset( $item['unit_price'] ) ? $this->normalize_decimal( $item['unit_price'] ) : 0;
			$computed_line_total = round( $quantity * $unit_price, 2 );
			$stored_line_total   = isset( $item['line_total'] ) ? $this->normalize_decimal( $item['line_total'] ) : 0;
			$item_type           = isset( $item['item_type'] ) ? $this->normalize_item_type( $item['item_type'] ) : 'custom';
			$needs_sync          = abs( $computed_line_total - $stored_line_total ) >= 0.01;
			$needs_type_sync     = isset( $item['item_type'] ) && $item_type !== sanitize_key( (string) $item['item_type'] );

			if ( $item_id > 0 && ( $needs_sync || $needs_type_sync ) ) {
				$item_update = array();
				if ( $needs_sync ) {
					$item_update['line_total'] = $computed_line_total;
				}
				if ( $needs_type_sync ) {
					$item_update['item_type'] = $item_type;
				}
				$this->item_repository->update( $item_id, $item_update );
			}

			$subtotal += $computed_line_total;
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
	 * Build consolidated payment receipt context.
	 *
	 * @param int $payment_id Payment ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_payment_receipt_context( $payment_id ) {
		$payment_id = absint( $payment_id );
		$payment    = $this->get_payment( $payment_id );

		if ( ! $payment ) {
			return new WP_Error( 'sm_payment_not_found', __( 'El pago no existe.', 'super-mechanic' ) );
		}

		$invoice_id = ! empty( $payment['invoice_id'] ) ? absint( $payment['invoice_id'] ) : 0;
		$context    = $this->get_invoice_print_context( $invoice_id );

		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$payment_summary = $this->get_invoice_payment_summary( $invoice_id );

		if ( is_wp_error( $payment_summary ) ) {
			return $payment_summary;
		}

		$invoice = isset( $context['invoice'] ) && is_array( $context['invoice'] ) ? $context['invoice'] : array();

		return array(
			'payment_id'      => $payment_id,
			'payment'         => $payment,
			'invoice'         => $invoice,
			'company'         => isset( $context['company'] ) ? $context['company'] : sanitize_text_field( $this->settings_service->get_setting( 'business', 'business_name', __( 'Super Mechanic', 'super-mechanic' ) ) ),
			'client_name'     => isset( $context['client_name'] ) ? $context['client_name'] : __( 'Cliente no asignado', 'super-mechanic' ),
			'process_title'   => ! empty( $invoice['process_title'] ) ? $invoice['process_title'] : '',
			'payment_status'  => ! empty( $payment_summary['payment_status'] ) ? sanitize_key( $payment_summary['payment_status'] ) : 'pending',
			'remaining_balance' => isset( $payment_summary['remaining_balance'] ) ? (float) $payment_summary['remaining_balance'] : 0.0,
		);
	}

	/**
	 * Render printable HTML for a payment receipt.
	 *
	 * @param array<string, mixed> $context Receipt context.
	 * @return string
	 */
	public function render_payment_receipt_html( array $context ) {
		$payment     = isset( $context['payment'] ) && is_array( $context['payment'] ) ? $context['payment'] : array();
		$invoice     = isset( $context['invoice'] ) && is_array( $context['invoice'] ) ? $context['invoice'] : array();
		$company     = isset( $context['company'] ) ? $context['company'] : __( 'Super Mechanic', 'super-mechanic' );
		$client_name = isset( $context['client_name'] ) ? $context['client_name'] : __( 'Cliente no asignado', 'super-mechanic' );
		$currency    = ! empty( $invoice['currency'] ) ? $invoice['currency'] : $this->get_default_currency();
		$status      = ! empty( $context['payment_status'] ) ? sanitize_key( $context['payment_status'] ) : 'pending';
		$process_ref = ! empty( $context['process_title'] ) ? $context['process_title'] : ( ! empty( $invoice['process_id'] ) ? '#' . absint( $invoice['process_id'] ) : '-' );

		ob_start();
		echo '<div class="sm-payment-receipt-print">';
		echo '<h1>' . esc_html( $company ) . '</h1>';
		echo '<h2>' . esc_html( sprintf( __( 'Comprobante de pago #%d', 'super-mechanic' ), absint( $context['payment_id'] ) ) ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Factura:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['invoice_number'] ) ? $invoice['invoice_number'] : '#' . absint( $invoice['id'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Cliente:', 'super-mechanic' ) . '</strong> ' . esc_html( $client_name ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Proceso:', 'super-mechanic' ) . '</strong> ' . esc_html( $process_ref ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Fecha de pago:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $payment['payment_date'] ) ? $payment['payment_date'] : '-' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Metodo de pago:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $payment['payment_method'] ) ? $this->humanize_key( $payment['payment_method'] ) : '-' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Monto pagado:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( ! empty( $payment['amount'] ) ? $payment['amount'] : 0, $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Estado de cobro luego del pago:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->humanize_key( $status ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Saldo pendiente:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_money( isset( $context['remaining_balance'] ) ? $context['remaining_balance'] : 0, $currency ) ) . '</p>';

		if ( ! empty( $payment['reference'] ) ) {
			echo '<p><strong>' . esc_html__( 'Referencia:', 'super-mechanic' ) . '</strong> ' . esc_html( $payment['reference'] ) . '</p>';
		}

		if ( ! empty( $payment['notes'] ) ) {
			echo '<p><strong>' . esc_html__( 'Notas:', 'super-mechanic' ) . '</strong> ' . esc_html( $payment['notes'] ) . '</p>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Build a predictable payment receipt file name.
	 *
	 * @param int $payment_id Payment ID.
	 * @return string
	 */
	public function get_payment_receipt_pdf_filename( $payment_id ) {
		$payment = $this->get_payment( $payment_id );

		if ( ! $payment ) {
			return 'payment-receipt-' . absint( $payment_id ) . '.pdf';
		}

		$invoice = ! empty( $payment['invoice_id'] ) ? $this->get_invoice( absint( $payment['invoice_id'] ) ) : null;
		$base    = ! empty( $invoice['invoice_number'] ) ? sanitize_file_name( strtolower( $invoice['invoice_number'] ) ) : 'invoice-' . ( ! empty( $payment['invoice_id'] ) ? absint( $payment['invoice_id'] ) : '0' );

		return $base . '-payment-' . absint( $payment_id ) . '.pdf';
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
	public function validate_invoice_item_data( array $data, $is_update = false, array $context = array() ) {
		$errors             = new WP_Error();
		$allowed_item_types = array( 'part', 'labor', 'custom', 'quote_item', 'woo_product' );
		$context            = wp_parse_args(
			$context,
			array(
				'raw'           => array(),
				'existing_item' => null,
			)
		);
		$raw_data            = is_array( $context['raw'] ) ? $context['raw'] : array();
		$existing_item       = is_array( $context['existing_item'] ) ? $context['existing_item'] : null;
		$raw_item_type       = sanitize_key( isset( $raw_data['item_type'] ) ? (string) $raw_data['item_type'] : '' );
		$raw_woo_product_id  = isset( $raw_data['woo_product_id'] ) ? absint( $raw_data['woo_product_id'] ) : 0;
		$existing_is_inconsistent_woo = $this->is_inconsistent_woo_snapshot_payload( $existing_item );
		$explicit_woo_intent          = $this->has_explicit_woo_intent( $raw_item_type, $raw_woo_product_id, $is_update, $existing_is_inconsistent_woo );

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
		if ( 'woo_product' === $data['item_type'] && ! $this->is_valid_woo_snapshot_payload( $data ) ) {
			$errors->add( 'invalid_woo_snapshot', __( 'Invalid Woo product snapshot. Use manual/custom or reselect the Woo product.', 'super-mechanic' ) );
		}

		if ( $explicit_woo_intent ) {
			if ( ! $this->is_woo_catalog_available() ) {
				$errors->add( 'woo_not_available', __( 'WooCommerce not available. Use manual/custom item.', 'super-mechanic' ) );
			} elseif ( $raw_woo_product_id > 0 && 'woo_product' !== $data['item_type'] ) {
				$errors->add( 'woo_product_not_found', __( 'Woo product not found. Select a valid Woo product.', 'super-mechanic' ) );
			} elseif ( 0 === $raw_woo_product_id ) {
				$existing_is_valid_woo        = $this->is_valid_woo_snapshot_payload( $existing_item );
				$sanitized_to_custom          = ( 'custom' === $data['item_type'] );

				if ( ! ( $is_update && $existing_is_valid_woo && 'woo_product' === $data['item_type'] ) && ! ( $is_update && $existing_is_inconsistent_woo && $sanitized_to_custom ) ) {
					$errors->add( 'invalid_woo_snapshot', __( 'Invalid Woo product snapshot. Use manual/custom or reselect the Woo product.', 'super-mechanic' ) );
				}
			}
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
		$process  = null;

		if ( ! empty( $data['process_id'] ) ) {
			$process_service = new \Super_Mechanic\Processes\Process_Service();
			$process         = $process_service->get_process( absint( $data['process_id'] ) );
		}

		$client = null;
		if ( ! empty( $data['client_id'] ) ) {
			$client_service = new \Super_Mechanic\Clients\Client_Service();
			$client         = $client_service->get_client( absint( $data['client_id'] ) );
		}

		return array(
			'business_id'    => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_business_id_from_parents( $quote, $process, $client ),
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
		$data['item_type'] = $this->normalize_item_type( isset( $data['item_type'] ) ? $data['item_type'] : 'custom' );

		$woo_product_id = isset( $data['woo_product_id'] ) ? absint( $data['woo_product_id'] ) : 0;
		if ( $woo_product_id > 0 ) {
			$snapshot = $this->woo_product_service->get_product_snapshot( $woo_product_id );
			if ( is_array( $snapshot ) ) {
				$data['reference_id'] = absint( $snapshot['id'] );
				$data['label']        = $snapshot['name'];
				$data['unit_price']   = $snapshot['unit_price'];
				$data['item_type']    = 'woo_product';
			} else {
				$data['reference_id'] = 0;
				if ( 'woo_product' === ( isset( $data['item_type'] ) ? sanitize_key( $data['item_type'] ) : '' ) ) {
					$data['item_type'] = 'custom';
				}
			}
		}

		$quantity   = isset( $data['quantity'] ) ? $this->normalize_decimal( $data['quantity'] ) : 1;
		$unit_price = isset( $data['unit_price'] ) ? $this->normalize_decimal( $data['unit_price'] ) : 0;

		// If legacy/incomplete Woo payload arrives without a valid snapshot shape,
		// fallback to custom to preserve integrity without dynamic Woo recalculation.
		if ( 'woo_product' === $data['item_type'] ) {
			$reference_id = isset( $data['reference_id'] ) ? absint( $data['reference_id'] ) : 0;
			$label        = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
			if ( $reference_id <= 0 || '' === $label ) {
				$data['item_type']    = 'custom';
				$data['reference_id'] = 0;
			}
		}

		return array(
			'business_id' => isset( $data['business_id'] ) ? absint( $data['business_id'] ) : $this->resolve_business_id(),
			'invoice_id'   => isset( $data['invoice_id'] ) ? absint( $data['invoice_id'] ) : 0,
			'item_type'    => $this->normalize_item_type( isset( $data['item_type'] ) ? $data['item_type'] : 'custom' ),
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
		$invoice_id = isset( $data['invoice_id'] ) ? absint( $data['invoice_id'] ) : 0;
		$invoice    = $invoice_id > 0 ? $this->repository->get_by_id( $invoice_id ) : null;

		return array(
			'business_id'    => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: ( is_array( $invoice ) && ! empty( $invoice['business_id'] ) ? absint( $invoice['business_id'] ) : $this->resolve_business_id() ),
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
		return sanitize_text_field( $this->settings_service->get_setting( 'business', 'currency', 'USD' ) );
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
	 * Normalize item type aliases to active canonical types.
	 *
	 * @param mixed $item_type Raw item type.
	 * @return string
	 */
	protected function normalize_item_type( $item_type ) {
		$item_type = sanitize_key( (string) $item_type );

		if ( 'manual' === $item_type ) {
			return 'custom';
		}

		return $item_type;
	}

	/**
	 * Apply controlled sanitization for legacy inconsistent Woo payloads.
	 *
	 * @param array<string,mixed>      $candidate     Candidate payload.
	 * @param array<string,mixed>      $raw_data      Raw incoming payload.
	 * @param array<string,mixed>|null $existing_item Existing item payload.
	 * @return array<string,mixed>
	 */
	protected function apply_legacy_woo_sanitization( array $candidate, array $raw_data, $existing_item = null ) {
		$existing_is_inconsistent_woo = $this->is_inconsistent_woo_snapshot_payload( $existing_item );
		if ( ! $existing_is_inconsistent_woo ) {
			return $candidate;
		}

		$raw_item_type      = sanitize_key( isset( $raw_data['item_type'] ) ? (string) $raw_data['item_type'] : '' );
		$raw_woo_product_id = isset( $raw_data['woo_product_id'] ) ? absint( $raw_data['woo_product_id'] ) : 0;
		$explicit_woo_intent = $this->has_explicit_woo_intent( $raw_item_type, $raw_woo_product_id, true, $existing_is_inconsistent_woo );

		if ( $explicit_woo_intent ) {
			return $candidate;
		}

		$candidate['item_type']    = 'custom';
		$candidate['reference_id'] = 0;

		$label = isset( $candidate['label'] ) ? sanitize_text_field( (string) $candidate['label'] ) : '';
		if ( '' === $label ) {
			$candidate['label'] = __( 'Legacy custom item', 'super-mechanic' );
		}

		return $candidate;
	}

	/**
	 * Determine whether request has explicit Woo intent.
	 *
	 * @param string $raw_item_type              Raw item type.
	 * @param int    $raw_woo_product_id         Raw Woo product ID.
	 * @param bool   $is_update                  Updating item.
	 * @param bool   $existing_is_inconsistent_woo Existing legacy inconsistency.
	 * @return bool
	 */
	protected function has_explicit_woo_intent( $raw_item_type, $raw_woo_product_id, $is_update, $existing_is_inconsistent_woo ) {
		if ( $raw_woo_product_id > 0 ) {
			return true;
		}

		if ( 'woo_product' !== $raw_item_type ) {
			return false;
		}

		if ( $is_update && $existing_is_inconsistent_woo ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether Woo product catalog is available.
	 *
	 * @return bool
	 */
	protected function is_woo_catalog_available() {
		return $this->woo_product_service->is_available();
	}

	/**
	 * Validate whether an item payload has a consistent Woo snapshot shape.
	 *
	 * @param array<string,mixed>|null $item Item payload.
	 * @return bool
	 */
	protected function is_valid_woo_snapshot_payload( $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}

		$item_type = $this->normalize_item_type( isset( $item['item_type'] ) ? $item['item_type'] : '' );
		if ( 'woo_product' !== $item_type ) {
			return false;
		}

		$reference_id = isset( $item['reference_id'] ) ? absint( $item['reference_id'] ) : 0;
		$label        = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
		$quantity     = isset( $item['quantity'] ) ? $this->normalize_decimal( $item['quantity'] ) : 0;
		$unit_price   = isset( $item['unit_price'] ) ? $this->normalize_decimal( $item['unit_price'] ) : -1;

		return $reference_id > 0 && '' !== $label && $quantity > 0 && $unit_price >= 0;
	}

	/**
	 * Detect legacy inconsistent Woo payloads that can be safely sanitized to custom.
	 *
	 * @param array<string,mixed>|null $item Item payload.
	 * @return bool
	 */
	protected function is_inconsistent_woo_snapshot_payload( $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}

		$item_type = $this->normalize_item_type( isset( $item['item_type'] ) ? $item['item_type'] : '' );

		return 'woo_product' === $item_type && ! $this->is_valid_woo_snapshot_payload( $item );
	}

	/**
	 * Convert a fixed/percent adjustment value into a stored absolute total.
	 *
	 * @param float  $subtotal Current subtotal.
	 * @param string $mode     Adjustment mode.
	 * @param mixed  $value    Raw value.
	 * @return float
	 */
	protected function normalize_adjustment_total( $subtotal, $mode, $value ) {
		$subtotal = round( max( 0, (float) $subtotal ), 2 );
		$mode     = 'percent' === sanitize_key( (string) $mode ) ? 'percent' : 'fixed';
		$value    = max( 0, $this->normalize_decimal( $value ) );

		if ( 'percent' === $mode ) {
			return round( ( $subtotal * $value ) / 100, 2 );
		}

		return round( $value, 2 );
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

		if ( ! $this->allows_partial_payments() && $amount < $available_balance ) {
			return new WP_Error(
				'sm_partial_payments_disabled',
				__( 'La configuracion actual del taller no permite pagos parciales.', 'super-mechanic' )
			);
		}

		return true;
	}

	/**
	 * Check whether partial payments are enabled.
	 *
	 * @return bool
	 */
	protected function allows_partial_payments() {
		return (bool) $this->settings_service->get_setting( 'financial', 'allow_partial_payments', true );
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
	 * Resolve business ID from parent entities.
	 *
	 * @param array<string,mixed>|null $quote   Quote row.
	 * @param array<string,mixed>|null $process Process row.
	 * @param array<string,mixed>|null $client  Client row.
	 * @return int
	 */
	protected function resolve_business_id_from_parents( $quote, $process, $client ) {
		if ( is_array( $quote ) && ! empty( $quote['business_id'] ) ) {
			return max( 1, absint( $quote['business_id'] ) );
		}

		if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
			return max( 1, absint( $process['business_id'] ) );
		}

		if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
			return max( 1, absint( $client['business_id'] ) );
		}

		return $this->resolve_business_id();
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
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
	protected function dispatch_invoice_paid_if_transitioned( $invoice_id, array $previous_summary, $triggered_by, $payment_id = 0 ) {
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
					'payment_id'   => absint( $payment_id ),
					'invoice_id'   => absint( $invoice_id ),
					'triggered_by' => absint( $triggered_by ),
				)
			);
		}
	}
}







