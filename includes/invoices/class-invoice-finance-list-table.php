<?php
/**
 * Invoice finance list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Helpers\PDF_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the dedicated invoices finance admin table.
 */
class Invoice_Finance_List_Table extends \WP_List_Table {
	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $service;

	/**
	 * PDF service.
	 *
	 * @var PDF_Service
	 */
	protected $pdf_service;

	/**
	 * Download service.
	 *
	 * @var Download_Service
	 */
	protected $download_service;

	/**
	 * Constructor.
	 *
	 * @param Invoice_Service  $service          Invoice service.
	 * @param PDF_Service      $pdf_service      PDF service.
	 * @param Download_Service $download_service Download service.
	 */
	public function __construct( Invoice_Service $service, PDF_Service $pdf_service, Download_Service $download_service ) {
		$this->service          = $service;
		$this->pdf_service      = $pdf_service;
		$this->download_service = $download_service;

		parent::__construct(
			array(
				'singular' => 'sm_invoice_finance',
				'plural'   => 'sm_invoices_finance',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'invoice_number'     => __( 'Invoice', 'super-mechanic' ),
			'invoice_status'     => __( 'Estado invoice', 'super-mechanic' ),
			'collection_status'  => __( 'Estado cobro', 'super-mechanic' ),
			'subtotal'           => __( 'Subtotal', 'super-mechanic' ),
			'tax_total'          => __( 'Tax', 'super-mechanic' ),
			'discount_total'     => __( 'Discount', 'super-mechanic' ),
			'grand_total'        => __( 'Grand total', 'super-mechanic' ),
			'amount_paid'        => __( 'Pagado', 'super-mechanic' ),
			'balance_due'        => __( 'Pendiente', 'super-mechanic' ),
			'due_date'           => __( 'Due date', 'super-mechanic' ),
			'actions'            => __( 'Actions', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'invoice_number' => array( 'invoice_number', false ),
			'invoice_status' => array( 'status', false ),
			'grand_total'    => array( 'grand_total', false ),
			'balance_due'    => array( 'balance_due', false ),
			'due_date'       => array( 'due_date', false ),
			'created_at'     => array( 'created_at', true ),
		);
	}

	/**
	 * Render invoice number column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_invoice_number( $item ) {
		$number       = isset( $item['invoice_number'] ) ? (string) $item['invoice_number'] : '';
		$process_id   = isset( $item['process_id'] ) ? absint( $item['process_id'] ) : 0;
		$invoice_id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$client_name  = isset( $item['client_name'] ) ? trim( (string) $item['client_name'] ) : '';
		$process_name = isset( $item['process_title'] ) ? trim( (string) $item['process_title'] ) : '';

		$output  = '<strong>' . esc_html( '' !== $number ? $number : '#' . $invoice_id ) . '</strong>';
		$output .= '<div class="sm-list-meta">#' . esc_html( (string) $invoice_id ) . '</div>';

		if ( '' !== $client_name ) {
			$output .= '<div class="sm-list-meta">' . esc_html( $client_name ) . '</div>';
		}

		if ( '' !== $process_name ) {
			$output .= '<div class="sm-list-meta">' . esc_html( $process_name ) . '</div>';
		}

		$actions = array();

		if ( $process_id > 0 && $invoice_id > 0 ) {
			$actions['open'] = '<a href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id ) ) . '">' . esc_html__( 'Abrir', 'super-mechanic' ) . '</a>';
		}

		if ( $process_id > 0 && $invoice_id > 0 ) {
			$actions['payment'] = '<a href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id, array( 'sm_finance_focus' => 'register_payment' ) ) ) . '">' . esc_html__( 'Register payment', 'super-mechanic' ) . '</a>';
		}

		if ( $invoice_id > 0 && $this->pdf_service->can_generate_pdf() ) {
			$actions['pdf'] = '<a href="' . esc_url( $this->download_service->get_download_url( 'invoice_pdf', $invoice_id ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a>';
		}

		if ( ! empty( $actions ) ) {
			$output .= $this->row_actions( $actions );
		}

		return $output;
	}

	/**
	 * Render invoice status.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_invoice_status( $item ) {
		$status = isset( $item['status'] ) ? (string) $item['status'] : '';

		return $this->render_badge( $this->humanize_key( $status ), $this->get_tone_for_status( $status ) );
	}

	/**
	 * Render collection status.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_collection_status( $item ) {
		$status = isset( $item['collection_status'] ) ? (string) $item['collection_status'] : '';
		$label  = isset( $item['collection_label'] ) ? (string) $item['collection_label'] : $this->humanize_key( $status );

		return $this->render_badge( $label, $this->get_tone_for_collection_status( $status ) );
	}

	/**
	 * Render subtotal column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_subtotal( $item ) {
		return esc_html( $this->format_money( $item, 'subtotal' ) );
	}

	/**
	 * Render tax column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_tax_total( $item ) {
		return esc_html( $this->format_money( $item, 'tax_total' ) );
	}

	/**
	 * Render discount column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_discount_total( $item ) {
		return esc_html( $this->format_money( $item, 'discount_total' ) );
	}

	/**
	 * Render grand total column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_grand_total( $item ) {
		return '<strong>' . esc_html( $this->format_money( $item, 'grand_total' ) ) . '</strong>';
	}

	/**
	 * Render amount paid column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_amount_paid( $item ) {
		return esc_html( $this->format_money( $item, 'amount_paid' ) );
	}

	/**
	 * Render balance due column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_balance_due( $item ) {
		return esc_html( $this->format_money( $item, 'balance_due' ) );
	}

	/**
	 * Render due date.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_due_date( $item ) {
		$due = isset( $item['due_date'] ) ? (string) $item['due_date'] : '';

		if ( '' === $due ) {
			return esc_html__( 'N/A', 'super-mechanic' );
		}

		return esc_html( $due );
	}

	/**
	 * Render actions column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_actions( $item ) {
		$process_id = isset( $item['process_id'] ) ? absint( $item['process_id'] ) : 0;
		$invoice_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

		$actions = array();

		if ( $process_id > 0 && $invoice_id > 0 ) {
			$actions[] = '<a class="button button-small" href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id ) ) . '">' . esc_html__( 'Abrir', 'super-mechanic' ) . '</a>';
			$actions[] = '<a class="button button-secondary button-small" href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id, array( 'sm_finance_focus' => 'register_payment' ) ) ) . '">' . esc_html__( 'Register payment', 'super-mechanic' ) . '</a>';
		}

		if ( $invoice_id > 0 && $this->pdf_service->can_generate_pdf() ) {
			$actions[] = '<a class="button button-secondary button-small" href="' . esc_url( $this->download_service->get_download_url( 'invoice_pdf', $invoice_id ) ) . '">' . esc_html__( 'PDF', 'super-mechanic' ) . '</a>';
		}

		if ( empty( $actions ) ) {
			return esc_html__( 'N/A', 'super-mechanic' );
		}

		return implode( ' ', $actions );
	}

	/**
	 * Render default column.
	 *
	 * @param array<string, mixed> $item        Row item.
	 * @param string               $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';

		return esc_html( (string) $value );
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$paged    = $this->get_pagenum();

		$args = array(
			'search'   => $search,
			'status'   => $status,
			'orderby'  => $orderby,
			'order'    => $order,
			'page'     => $paged,
			'per_page' => $per_page,
		);

		$invoices = $this->service->get_invoices( $args );

		$this->items = array_map(
			array( $this, 'append_collection_state' ),
			$invoices
		);

		$total_items = $this->service->count_invoices(
			array(
				'search' => $search,
				'status' => $status,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Append collection status from service.
	 *
	 * @param array<string, mixed> $invoice Invoice row.
	 * @return array<string, mixed>
	 */
	protected function append_collection_state( array $invoice ) {
		return $this->service->append_collection_state( $invoice );
	}

	/**
	 * Build invoice process URL.
	 *
	 * @param int                  $process_id Process ID.
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $args       Extra args.
	 * @return string
	 */
	protected function get_process_invoice_url( $process_id, $invoice_id, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page'       => 'super-mechanic-processes',
					'action'     => 'edit',
					'id'         => absint( $process_id ),
					'tab'        => 'invoice',
					'invoice_id' => absint( $invoice_id ),
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Format money using invoice currency.
	 *
	 * @param array<string, mixed> $item  Row item.
	 * @param string               $field Amount field.
	 * @return string
	 */
	protected function format_money( array $item, $field ) {
		$currency = isset( $item['currency'] ) ? sanitize_text_field( (string) $item['currency'] ) : 'USD';
		$amount   = isset( $item[ $field ] ) ? (float) $item[ $field ] : 0.0;

		return sprintf( '%s %s', $currency, number_format_i18n( $amount, 2 ) );
	}

	/**
	 * Humanize a machine key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Get visual tone for invoice status.
	 *
	 * @param string $status Invoice status.
	 * @return string
	 */
	protected function get_tone_for_status( $status ) {
		if ( in_array( $status, array( 'paid' ), true ) ) {
			return 'success';
		}

		if ( in_array( $status, array( 'issued', 'partially_paid' ), true ) ) {
			return 'primary';
		}

		if ( in_array( $status, array( 'overdue' ), true ) ) {
			return 'warning';
		}

		if ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
			return 'danger';
		}

		return 'neutral';
	}

	/**
	 * Get visual tone for collection status.
	 *
	 * @param string $status Collection status.
	 * @return string
	 */
	protected function get_tone_for_collection_status( $status ) {
		if ( 'paid' === $status ) {
			return 'success';
		}

		if ( 'partial' === $status ) {
			return 'warning';
		}

		if ( 'pending' === $status ) {
			return 'danger';
		}

		return 'neutral';
	}

	/**
	 * Render shared badge markup.
	 *
	 * @param string $label Badge label.
	 * @param string $tone  Badge tone.
	 * @return string
	 */
	protected function render_badge( $label, $tone ) {
		return '<span class="sm-badge sm-badge-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}
}
