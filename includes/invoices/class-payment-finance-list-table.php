<?php
/**
 * Payment finance list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Helpers\Download_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the dedicated payments finance admin table.
 */
class Payment_Finance_List_Table extends \WP_List_Table {
	/**
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	protected $payment_repository;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Download service.
	 *
	 * @var Download_Service
	 */
	protected $download_service;

	/**
	 * Constructor.
	 *
	 * @param Payment_Repository $payment_repository Payment repository.
	 * @param Invoice_Service    $invoice_service    Invoice service.
	 * @param Download_Service   $download_service   Download service.
	 */
	public function __construct( Payment_Repository $payment_repository, Invoice_Service $invoice_service, Download_Service $download_service ) {
		$this->payment_repository = $payment_repository;
		$this->invoice_service    = $invoice_service;
		$this->download_service   = $download_service;

		parent::__construct(
			array(
				'singular' => 'sm_payment_finance',
				'plural'   => 'sm_payments_finance',
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
			'id'                => __( 'Payment', 'super-mechanic' ),
			'payment_date'      => __( 'Fecha', 'super-mechanic' ),
			'invoice'           => __( 'Invoice', 'super-mechanic' ),
			'payment_method'    => __( 'Metodo', 'super-mechanic' ),
			'amount'            => __( 'Monto', 'super-mechanic' ),
			'collection_status' => __( 'Estado cobro invoice', 'super-mechanic' ),
			'reference'         => __( 'Referencia', 'super-mechanic' ),
			'actions'           => __( 'Acciones', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'             => array( 'id', false ),
			'payment_date'   => array( 'payment_date', true ),
			'invoice'        => array( 'invoice_number', false ),
			'payment_method' => array( 'payment_method', false ),
			'amount'         => array( 'amount', false ),
			'created_at'     => array( 'created_at', false ),
		);
	}

	/**
	 * Render payment id column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_id( $item ) {
		$payment_id    = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$invoice_id    = isset( $item['invoice_id'] ) ? absint( $item['invoice_id'] ) : 0;
		$invoice_num   = isset( $item['invoice_number'] ) ? (string) $item['invoice_number'] : '';
		$process_title = isset( $item['process_title'] ) ? trim( (string) $item['process_title'] ) : '';
		$client_name   = isset( $item['client_name'] ) ? trim( (string) $item['client_name'] ) : '';

		$output  = '<strong>#' . esc_html( (string) $payment_id ) . '</strong>';
		$output .= '<div class="sm-list-meta">' . esc_html( '' !== $invoice_num ? $invoice_num : '#' . $invoice_id ) . '</div>';

		if ( '' !== $client_name ) {
			$output .= '<div class="sm-list-meta">' . esc_html( $client_name ) . '</div>';
		}

		if ( '' !== $process_title ) {
			$output .= '<div class="sm-list-meta">' . esc_html( $process_title ) . '</div>';
		}

		return $output;
	}

	/**
	 * Render invoice relation column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_invoice( $item ) {
		$invoice_id  = isset( $item['invoice_id'] ) ? absint( $item['invoice_id'] ) : 0;
		$invoice_num = isset( $item['invoice_number'] ) ? (string) $item['invoice_number'] : '';
		$process_id  = isset( $item['process_id'] ) ? absint( $item['process_id'] ) : 0;

		if ( $invoice_id <= 0 ) {
			return esc_html__( 'N/A', 'super-mechanic' );
		}

		$label = '' !== $invoice_num ? $invoice_num : '#' . $invoice_id;

		if ( $process_id <= 0 ) {
			return esc_html( $label );
		}

		return '<a href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id ) ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Render payment method column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_payment_method( $item ) {
		$method = isset( $item['payment_method'] ) ? (string) $item['payment_method'] : '';

		return $this->render_badge( $this->humanize_key( $method ), 'primary' );
	}

	/**
	 * Render amount column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_amount( $item ) {
		$currency = isset( $item['currency'] ) ? sanitize_text_field( (string) $item['currency'] ) : 'USD';
		$amount   = isset( $item['amount'] ) ? (float) $item['amount'] : 0;

		return '<strong>' . esc_html( sprintf( '%s %s', $currency, number_format_i18n( $amount, 2 ) ) ) . '</strong>';
	}

	/**
	 * Render collection status column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_collection_status( $item ) {
		$status = isset( $item['collection_status'] ) ? (string) $item['collection_status'] : '';
		$label  = isset( $item['collection_label'] ) ? (string) $item['collection_label'] : $this->humanize_key( $status );

		if ( '' === $status ) {
			return esc_html__( 'N/A', 'super-mechanic' );
		}

		return $this->render_badge( $label, $this->get_tone_for_collection_status( $status ) );
	}

	/**
	 * Render reference column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_reference( $item ) {
		$reference = isset( $item['reference'] ) ? trim( (string) $item['reference'] ) : '';

		return esc_html( '' !== $reference ? $reference : 'N/A' );
	}

	/**
	 * Render actions column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_actions( $item ) {
		$payment_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$invoice_id = isset( $item['invoice_id'] ) ? absint( $item['invoice_id'] ) : 0;
		$process_id = isset( $item['process_id'] ) ? absint( $item['process_id'] ) : 0;

		$actions = array();

		if ( $process_id > 0 && $invoice_id > 0 ) {
			$actions[] = '<a class="button button-small" href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id ) ) . '">' . esc_html__( 'Abrir invoice', 'super-mechanic' ) . '</a>';
			$actions[] = '<a class="button button-secondary button-small" href="' . esc_url( $this->get_process_invoice_url( $process_id, $invoice_id, array( 'sm_finance_focus' => 'register_payment' ) ) ) . '">' . esc_html__( 'Registrar pago', 'super-mechanic' ) . '</a>';
		}

		if ( $payment_id > 0 && $this->download_service->can_generate_pdf() ) {
			$actions[] = '<a class="button button-secondary button-small" href="' . esc_url( $this->download_service->get_download_url( 'payment_receipt', $payment_id ) ) . '">' . esc_html__( 'Ver comprobante', 'super-mechanic' ) . '</a>';
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
		$per_page       = 20;
		$search         = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$payment_method = isset( $_REQUEST['filter_payment_method'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_payment_method'] ) ) : '';
		$date_from      = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		$date_to        = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';
		$orderby        = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'payment_date';
		$order          = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$paged          = $this->get_pagenum();

		$args = array(
			'search'         => $search,
			'payment_method' => $payment_method,
			'date_from'      => $date_from,
			'date_to'        => $date_to,
			'orderby'        => $orderby,
			'order'          => $order,
			'page'           => $paged,
			'per_page'       => $per_page,
		);

		$payments = $this->payment_repository->get_all( $args );
		$this->items = array_map(
			array( $this, 'append_invoice_collection_state' ),
			$payments
		);

		$total_items = $this->payment_repository->count_all(
			array(
				'search'         => $search,
				'payment_method' => $payment_method,
				'date_from'      => $date_from,
				'date_to'        => $date_to,
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
	 * Append invoice collection state to each payment row.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @return array<string, mixed>
	 */
	protected function append_invoice_collection_state( array $payment ) {
		$payment['collection_status'] = '';
		$payment['collection_label']  = '';

		$invoice_id = isset( $payment['invoice_id'] ) ? absint( $payment['invoice_id'] ) : 0;

		if ( $invoice_id <= 0 ) {
			return $payment;
		}

		$summary = $this->invoice_service->get_invoice_payment_summary( $invoice_id );

		if ( is_wp_error( $summary ) ) {
			return $payment;
		}

		$payment['collection_status'] = isset( $summary['collection_status'] ) ? $summary['collection_status'] : '';
		$payment['collection_label']  = isset( $summary['collection_label'] ) ? $summary['collection_label'] : '';

		return $payment;
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
	 * Humanize a machine key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Get tone for collection status badge.
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
