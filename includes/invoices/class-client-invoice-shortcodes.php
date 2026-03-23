<?php
/**
 * Client invoice shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Download_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers client invoice shortcodes.
 */
class Client_Invoice_Shortcodes {
	protected $service;
	protected $dashboard_service;
	protected $download_service;

	public function __construct( Invoice_Service $service = null, Dashboard_Service $dashboard_service = null, Download_Service $download_service = null ) {
		$this->service           = $service ? $service : new Invoice_Service();
		$this->dashboard_service = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->download_service  = $download_service ? $download_service : new Download_Service();
	}

	public function register_hooks() {
		add_shortcode( 'sm_client_invoices', array( $this, 'render_client_invoices' ) );
		add_shortcode( 'sm_client_invoice_detail', array( $this, 'render_client_invoice_detail' ) );
	}

	public function render_client_invoices( $atts = array() ) {
		$atts  = shortcode_atts( array(), $atts, 'sm_client_invoices' );
		$guard = $this->guard_access( 'sm_view_own_processes' );

		if ( '' !== $guard ) {
			return $guard;
		}

		$client_id = $this->dashboard_service->get_client_id_by_user_id( get_current_user_id() );
		$invoices  = $this->service->get_invoices_for_user(
			get_current_user_id(),
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);

		ob_start();
		echo '<div class="sm-client-invoices">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Número', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pagado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pendiente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vencimiento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Detalle', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $invoices ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No hay facturas disponibles.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $invoices as $invoice ) {
				echo '<tr>';
				echo '<td>' . esc_html( $invoice['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $invoice['process_title'] ) ? $invoice['process_title'] : '#' . $invoice['process_id'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $invoice['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['grand_total'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['amount_paid'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['balance_due'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $invoice['due_date'] ) ? $invoice['due_date'] : '-' ) . '</td>';
				echo '<td><a href="' . esc_url( add_query_arg( 'invoice_id', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_client_invoice_detail( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'sm_client_invoice_detail'
		);

		$guard = $this->guard_access( 'sm_view_own_processes' );

		if ( '' !== $guard ) {
			return $guard;
		}

		$invoice_id = absint( $atts['id'] );
		if ( ! $invoice_id && isset( $_GET['invoice_id'] ) ) {
			$invoice_id = absint( wp_unslash( $_GET['invoice_id'] ) );
		}

		if ( ! $invoice_id ) {
			return '<p>' . esc_html__( 'No se indicó una factura válida.', 'super-mechanic' ) . '</p>';
		}

		if ( ! $this->service->user_can_access_invoice( get_current_user_id(), $invoice_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a esta factura.', 'super-mechanic' ) . '</p>';
		}

		$invoice  = $this->service->get_invoice( $invoice_id );
		$items    = $this->service->get_invoice_items( $invoice_id );
		$payments = $this->service->get_payments( $invoice_id );

		if ( ! $invoice ) {
			return '<p>' . esc_html__( 'La factura no existe.', 'super-mechanic' ) . '</p>';
		}

		ob_start();
		echo '<div class="sm-client-invoice-detail">';
		echo '<h3>' . esc_html( sprintf( __( 'Factura %s', 'super-mechanic' ), $invoice['invoice_number'] ) ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Proceso:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['process_title'] ) ? $invoice['process_title'] : '#' . $invoice['process_id'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Estado:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->humanize_key( $invoice['status'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Emitida:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['issued_at'] ) ? $invoice['issued_at'] : '-' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Vencimiento:', 'super-mechanic' ) . '</strong> ' . esc_html( ! empty( $invoice['due_date'] ) ? $invoice['due_date'] : '-' ) . '</p>';
		if ( $this->download_service->can_generate_pdf() ) {
			echo '<p><a class="button button-secondary" href="' . esc_url( $this->download_service->get_download_url( 'invoice_pdf', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a></p>';
		}
		if ( ! empty( $invoice['notes'] ) ) {
			echo '<p><strong>' . esc_html__( 'Notas:', 'super-mechanic' ) . '</strong> ' . esc_html( $invoice['notes'] ) . '</p>';
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Descripción', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay ítems en esta factura.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $item['item_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $item['label'] ) . '</td>';
				echo '<td>' . esc_html( $item['description'] ) . '</td>';
				echo '<td>' . esc_html( $item['quantity'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['unit_price'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['line_total'], $invoice['currency'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Pagos', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Monto', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Método', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Referencia', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Notas', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $payments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay pagos registrados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $payments as $payment ) {
				echo '<tr>';
				echo '<td>' . esc_html( $payment['payment_date'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $payment['amount'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $payment['payment_method'] ) ) . '</td>';
				echo '<td>' . esc_html( $payment['reference'] ) . '</td>';
				echo '<td>' . esc_html( $payment['notes'] ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<table class="widefat striped" style="max-width:520px;margin-top:16px;"><tbody>';
		echo '<tr><th>' . esc_html__( 'Subtotal', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $invoice['subtotal'], $invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $invoice['tax_total'], $invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Descuento', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $invoice['discount_total'], $invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><td><strong>' . esc_html( $this->format_money( $invoice['grand_total'], $invoice['currency'] ) ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Pagado', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $invoice['amount_paid'], $invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Pendiente', 'super-mechanic' ) . '</th><td><strong>' . esc_html( $this->format_money( $invoice['balance_due'], $invoice['currency'] ) ) . '</strong></td></tr>';
		echo '</tbody></table>';

		echo '</div>';

		return (string) ob_get_clean();
	}

	protected function guard_access( $capability ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para acceder a esta sección.', 'super-mechanic' ) . '</p>';
		}

		if ( ! current_user_can( $capability ) ) {
			return '<p>' . esc_html__( 'No tiene permisos para acceder a esta sección.', 'super-mechanic' ) . '</p>';
		}

		if ( ! $this->dashboard_service->get_client_id_by_user_id( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) . '</p>';
		}

		return '';
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function format_money( $amount, $currency ) {
		return sprintf( '%s %s', esc_html( $currency ), esc_html( number_format_i18n( (float) $amount, 2 ) ) );
	}
}
