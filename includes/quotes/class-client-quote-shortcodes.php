<?php
/**
 * Client quote shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Super_Mechanic\Assets;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Download_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers quote-related shortcodes for client access.
 */
class Client_Quote_Shortcodes {
	protected $service;
	protected $dashboard_service;
	protected $download_service;

	public function __construct( Quote_Service $service = null, Dashboard_Service $dashboard_service = null, Download_Service $download_service = null ) {
		$this->service           = $service ? $service : new Quote_Service();
		$this->dashboard_service = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->download_service  = $download_service ? $download_service : new Download_Service();
	}

	public function register_hooks() {
		add_shortcode( 'sm_client_quotes', array( $this, 'render_client_quotes' ) );
		add_shortcode( 'sm_client_quote_detail', array( $this, 'render_client_quote_detail' ) );
		add_shortcode( 'sm_client_quote_action', array( $this, 'render_client_quote_action' ) );
	}

	public function render_client_quotes( $atts = array() ) {
		Assets::enqueue_client_assets();

		$guard = $this->guard_access();
		if ( $guard ) {
			return $guard;
		}

		$quotes = $this->service->get_quotes_for_user(
			get_current_user_id(),
			array(
				'client_id' => $this->dashboard_service->get_client_id_by_user_id( get_current_user_id() ),
				'per_page'  => 50,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);

		ob_start();
		echo '<div class="sm-client-ui sm-client-quotes">';
		echo '<div class="sm-client-header"><div><h2 class="sm-client-title">' . esc_html__( 'Cotizaciones', 'super-mechanic' ) . '</h2><p class="sm-client-subtitle">' . esc_html__( 'Revisa, descarga y responde las cotizaciones disponibles.', 'super-mechanic' ) . '</p></div><span class="sm-client-badge sm-client-badge-primary">' . esc_html__( 'Acción cliente', 'super-mechanic' ) . '</span></div>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Número', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $quotes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay cotizaciones disponibles.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $quotes as $quote ) {
				echo '<tr>';
				echo '<td>' . esc_html( $quote['quote_number'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $quote['process_title'] ) ? $quote['process_title'] : __( 'Proceso', 'super-mechanic' ) . ' #' . absint( $quote['process_id'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $quote['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $quote['grand_total'], $quote['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $quote['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( add_query_arg( 'quote_id', absint( $quote['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a>';
				if ( $this->download_service->can_generate_pdf() ) {
					echo ' | <a href="' . esc_url( $this->download_service->get_download_url( 'quote_pdf', absint( $quote['id'] ) ) ) . '">' . esc_html__( 'PDF', 'super-mechanic' ) . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_client_quote_detail( $atts = array() ) {
		Assets::enqueue_client_assets();

		$guard = $this->guard_access();
		if ( $guard ) {
			return $guard;
		}

		$atts     = shortcode_atts( array( 'id' => 0 ), $atts, 'sm_client_quote_detail' );
		$quote_id = absint( $atts['id'] );
		if ( ! $quote_id && isset( $_GET['quote_id'] ) ) {
			$quote_id = absint( wp_unslash( $_GET['quote_id'] ) );
		}
		if ( ! $quote_id ) {
			return '<p>' . esc_html__( 'Debe indicar una cotización válida.', 'super-mechanic' ) . '</p>';
		}
		if ( ! $this->service->user_can_access_quote( get_current_user_id(), $quote_id ) ) {
			return '<p>' . esc_html__( 'No tiene acceso a esta cotización.', 'super-mechanic' ) . '</p>';
		}

		$quote = $this->service->get_quote( $quote_id );
		$items = $this->service->get_quote_items( $quote_id );
		if ( ! $quote ) {
			return '<p>' . esc_html__( 'La cotización no existe.', 'super-mechanic' ) . '</p>';
		}

		ob_start();
		echo '<div class="sm-client-ui sm-client-quote-detail">';
		echo '<div class="sm-client-header"><div><h3 class="sm-client-title">' . esc_html( $quote['quote_number'] ) . '</h3><p class="sm-client-subtitle">' . esc_html__( 'Detalle completo de la cotización y acciones disponibles.', 'super-mechanic' ) . '</p></div><span class="sm-client-badge sm-client-badge-primary">' . esc_html( $this->humanize_key( $quote['status'] ) ) . '</span></div>';
		echo '<p><strong>' . esc_html__( 'Estado', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->humanize_key( $quote['status'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Proceso', 'super-mechanic' ) . ':</strong> ' . esc_html( ! empty( $quote['process_title'] ) ? $quote['process_title'] : __( 'Sin título', 'super-mechanic' ) ) . '</p>';
		if ( $this->download_service->can_generate_pdf() ) {
			echo '<p><a class="button button-secondary sm-client-button-secondary" href="' . esc_url( $this->download_service->get_download_url( 'quote_pdf', absint( $quote['id'] ) ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a></p>';
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Descripción', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay ítems en esta cotización.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $item['item_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $item['label'] ) . '</td>';
				echo '<td>' . esc_html( $item['description'] ) . '</td>';
				echo '<td>' . esc_html( $item['quantity'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['unit_price'], $quote['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $item['line_total'], $quote['currency'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Subtotal', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->format_money( $quote['subtotal'], $quote['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Impuestos', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->format_money( $quote['tax_total'], $quote['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Descuento', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->format_money( $quote['discount_total'], $quote['currency'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Total', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->format_money( $quote['grand_total'], $quote['currency'] ) ) . '</p>';
		if ( ! empty( $quote['notes'] ) ) {
			echo '<p><strong>' . esc_html__( 'Notas', 'super-mechanic' ) . ':</strong> ' . esc_html( $quote['notes'] ) . '</p>';
		}
		if ( 'sent' === $quote['status'] ) {
			echo '<h4>' . esc_html__( 'Acciones del cliente', 'super-mechanic' ) . '</h4>';
			echo '<form method="post" class="sm-client-form-grid">';
			echo '<input type="hidden" name="sm_client_quote_action" value="approve" />';
			echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
			wp_nonce_field( 'sm_client_quote_action_' . absint( $quote['id'] ), 'sm_client_quote_action_nonce' );
			submit_button( __( 'Aprobar cotización', 'super-mechanic' ), 'primary', 'submit', false );
			echo '</form>';
			echo '<form method="post" class="sm-client-form-grid" style="margin-top:12px;">';
			echo '<input type="hidden" name="sm_client_quote_action" value="reject" />';
			echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
			wp_nonce_field( 'sm_client_quote_action_' . absint( $quote['id'] ), 'sm_client_quote_action_nonce' );
			echo '<label for="sm_client_rejection_reason_' . esc_attr( absint( $quote['id'] ) ) . '">' . esc_html__( 'Motivo de rechazo', 'super-mechanic' ) . '</label><br />';
			echo '<textarea id="sm_client_rejection_reason_' . esc_attr( absint( $quote['id'] ) ) . '" name="rejection_reason" class="large-text" rows="3"></textarea><br />';
			submit_button( __( 'Rechazar cotización', 'super-mechanic' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_client_quote_action( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para gestionar sus cotizaciones.', 'super-mechanic' ) . '</p>';
		}
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) || empty( $_POST['sm_client_quote_action'] ) ) {
			return '';
		}

		$action   = sanitize_key( wp_unslash( $_POST['sm_client_quote_action'] ) );
		$quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$nonce    = isset( $_POST['sm_client_quote_action_nonce'] ) ? wp_unslash( $_POST['sm_client_quote_action_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sm_client_quote_action_' . $quote_id ) ) {
			return '<p>' . esc_html__( 'La acción no es válida.', 'super-mechanic' ) . '</p>';
		}
		if ( ! $this->service->user_can_access_quote( get_current_user_id(), $quote_id ) ) {
			return '<p>' . esc_html__( 'No tiene acceso a esta cotización.', 'super-mechanic' ) . '</p>';
		}

		if ( 'approve' === $action ) {
			$result = $this->service->approve_quote( $quote_id, get_current_user_id() );
		} elseif ( 'reject' === $action ) {
			$result = $this->service->reject_quote( $quote_id, get_current_user_id(), isset( $_POST['rejection_reason'] ) ? wp_unslash( $_POST['rejection_reason'] ) : '' );
		} else {
			return '<p>' . esc_html__( 'Acción no soportada.', 'super-mechanic' ) . '</p>';
		}

		if ( is_wp_error( $result ) ) {
			return '<p>' . esc_html( $result->get_error_message() ) . '</p>';
		}

		return '<p>' . esc_html__( 'La cotización fue actualizada correctamente.', 'super-mechanic' ) . '</p>';
	}

	protected function guard_access() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para continuar.', 'super-mechanic' ) . '</p>';
		}
		if ( ! current_user_can( 'sm_view_own_processes' ) && ! current_user_can( 'sm_view_own_vehicles' ) ) {
			return '<p>' . esc_html__( 'No tiene permisos para ver cotizaciones.', 'super-mechanic' ) . '</p>';
		}
		if ( ! $this->dashboard_service->get_client_id_by_user_id( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) . '</p>';
		}

		return '';
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function format_money( $value, $currency ) {
		return sprintf( '%s %s', sanitize_text_field( (string) $currency ), number_format_i18n( (float) $value, 2 ) );
	}
}
