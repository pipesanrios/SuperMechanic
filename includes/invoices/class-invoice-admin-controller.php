<?php
/**
 * Invoice admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Helpers\PDF_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles invoice admin flows inside process detail.
 */
class Invoice_Admin_Controller {
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
	 * Constructor.
	 *
	 * @param Invoice_Service|null $service     Invoice service.
	 * @param PDF_Service|null     $pdf_service PDF service.
	 */
	public function __construct( Invoice_Service $service = null, PDF_Service $pdf_service = null ) {
		$this->service     = $service ? $service : new Invoice_Service();
		$this->pdf_service = $pdf_service ? $pdf_service : new PDF_Service( $this->service );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Maybe handle invoice actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_invoice_screen() ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar facturas.', 'super-mechanic' ) );
		}

		if ( isset( $_GET['sm_invoice_action'] ) && 'download_pdf' === sanitize_key( wp_unslash( $_GET['sm_invoice_action'] ) ) ) {
			$this->handle_download_pdf();
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_invoice_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_invoice_operation'] ) ) : '';

		switch ( $operation ) {
			case 'create_from_quote':
				$this->handle_create_from_quote();
				break;
			case 'create_manual_invoice':
				$this->handle_create_manual_invoice();
				break;
			case 'save_invoice':
				$this->handle_save_invoice();
				break;
			case 'delete_invoice':
				$this->handle_delete_invoice();
				break;
			case 'issue_invoice':
				$this->handle_issue_invoice();
				break;
			case 'cancel_invoice':
				$this->handle_cancel_invoice();
				break;
			case 'add_item':
				$this->handle_add_item();
				break;
			case 'update_item':
				$this->handle_update_item();
				break;
			case 'delete_item':
				$this->handle_delete_item();
				break;
			case 'add_payment':
				$this->handle_add_payment();
				break;
			case 'update_payment':
				$this->handle_update_payment();
				break;
			case 'delete_payment':
				$this->handle_delete_payment();
				break;
		}
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_invoice_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$map    = array(
			'invoice_created'      => __( 'Factura creada correctamente.', 'super-mechanic' ),
			'invoice_saved'        => __( 'Factura actualizada correctamente.', 'super-mechanic' ),
			'invoice_deleted'      => __( 'Factura eliminada correctamente.', 'super-mechanic' ),
			'invoice_issued'       => __( 'Factura emitida correctamente.', 'super-mechanic' ),
			'invoice_cancelled'    => __( 'Factura cancelada correctamente.', 'super-mechanic' ),
			'invoice_item_added'   => __( 'Item agregado correctamente.', 'super-mechanic' ),
			'invoice_item_updated' => __( 'Item actualizado correctamente.', 'super-mechanic' ),
			'invoice_item_deleted' => __( 'Item eliminado correctamente.', 'super-mechanic' ),
			'payment_added'        => __( 'Pago registrado correctamente.', 'super-mechanic' ),
			'payment_updated'      => __( 'Pago actualizado correctamente.', 'super-mechanic' ),
			'payment_deleted'      => __( 'Pago eliminado correctamente.', 'super-mechanic' ),
		);

		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}

		if ( 'invoice_error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
				}
			}
		}
	}

	/**
	 * Render process invoice panel.
	 *
	 * @param array<string, mixed> $process Process data.
	 * @return void
	 */
	public function render_process_panel( $process ) {
		$process_id    = absint( $process['id'] );
		$invoices      = $this->service->get_invoices(
			array(
				'process_id' => $process_id,
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
		$invoice_id     = isset( $_GET['invoice_id'] ) ? absint( wp_unslash( $_GET['invoice_id'] ) ) : 0;
		$active_invoice = $invoice_id ? $this->service->get_invoice( $invoice_id ) : null;

		if ( $active_invoice && absint( $active_invoice['process_id'] ) !== $process_id ) {
			$active_invoice = null;
		}

		if ( ! $active_invoice && ! empty( $invoices ) ) {
			$active_invoice = $invoices[0];
		}

		echo '<h2>' . esc_html__( 'Facturas', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Gestiona facturas, items, pagos y saldo pendiente del proceso.', 'super-mechanic' ) . '</p>';

		$approved_quotes = $this->service->get_approved_quotes_for_process( $process_id );
		if ( ! empty( $approved_quotes ) ) {
			echo '<div style="margin:12px 0;">';
			foreach ( $approved_quotes as $quote ) {
				echo '<form method="post" style="display:inline-block;margin-right:8px;">';
				echo '<input type="hidden" name="sm_invoice_operation" value="create_from_quote" />';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
				echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
				wp_nonce_field( 'sm_create_invoice_from_quote', 'sm_create_invoice_from_quote_nonce' );
				submit_button( sprintf( __( 'Crear factura desde %s', 'super-mechanic' ), $quote['quote_number'] ), 'secondary', 'submit', false );
				echo '</form>';
			}
			echo '</div>';
		}

		echo '<div style="margin:12px 0 20px;">';
		echo '<form method="post" class="sm-inline-form">';
		echo '<input type="hidden" name="sm_invoice_operation" value="create_manual_invoice" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="client_id" value="' . esc_attr( ! empty( $process['client_id'] ) ? absint( $process['client_id'] ) : 0 ) . '" />';
		wp_nonce_field( 'sm_create_manual_invoice', 'sm_create_manual_invoice_nonce' );
		echo '<table class="form-table" role="presentation" style="max-width:760px;">';
		echo '<tr><th scope="row"><label for="invoice_create_currency">' . esc_html__( 'Moneda', 'super-mechanic' ) . '</label></th><td><input type="text" name="currency" id="invoice_create_currency" value="" class="small-text" placeholder="USD" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_create_due_date">' . esc_html__( 'Fecha de vencimiento', 'super-mechanic' ) . '</label></th><td><input type="date" name="due_date" id="invoice_create_due_date" value="" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_create_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="invoice_create_notes" class="large-text" rows="3"></textarea><p class="description">' . esc_html__( 'Crea una factura manual asociada al proceso actual, incluso sin cotización previa.', 'super-mechanic' ) . '</p></td></tr>';
		echo '</table>';
		submit_button( __( 'Crear factura manual', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado de factura', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado de pago', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pagado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pendiente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $invoices ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No hay facturas para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $invoices as $invoice ) {
				$payment_summary = $this->service->get_invoice_payment_summary( absint( $invoice['id'] ) );
				$link = $this->get_process_url( $process_id, array( 'invoice_id' => absint( $invoice['id'] ) ) );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $invoice['invoice_number'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->humanize_key( $invoice['status'] ) ) . '</td>';
				echo '<td>' . esc_html( ! is_wp_error( $payment_summary ) ? $payment_summary['collection_label'] : __( 'Pendiente', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['grand_total'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( ! is_wp_error( $payment_summary ) ? $payment_summary['total_paid'] : 0, $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( ! is_wp_error( $payment_summary ) ? $payment_summary['remaining_balance'] : $invoice['grand_total'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $invoice['issued_at'] ) ? $invoice['issued_at'] : $invoice['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $link ) . '">' . esc_html__( 'Abrir', 'super-mechanic' ) . '</a>';
				if ( $this->pdf_service->can_generate_pdf() ) {
					echo ' | <a href="' . esc_url( $this->get_invoice_pdf_download_url( $process_id, absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		if ( ! $active_invoice ) {
			return;
		}

		$items    = $this->service->get_invoice_items( absint( $active_invoice['id'] ) );
		$payments = $this->service->get_payments( absint( $active_invoice['id'] ) );

		echo '<hr />';
		echo '<h3>' . esc_html( sprintf( __( 'Factura activa: %s', 'super-mechanic' ), $active_invoice['invoice_number'] ) ) . '</h3>';

		if ( isset( $_GET['invoice_view'] ) && 'print' === sanitize_key( wp_unslash( $_GET['invoice_view'] ) ) ) {
			echo $this->service->get_printable_invoice_html( absint( $active_invoice['id'] ) );
			return;
		}

		$payment_summary = $this->service->get_invoice_payment_summary( absint( $active_invoice['id'] ) );
		echo '<p><strong>' . esc_html__( 'Estado de factura', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->humanize_key( $active_invoice['status'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Estado de pago', 'super-mechanic' ) . ':</strong> ' . esc_html( ! is_wp_error( $payment_summary ) ? $payment_summary['collection_label'] : __( 'Pendiente', 'super-mechanic' ) ) . '</p>';
		echo '<p><a class="button" target="_blank" href="' . esc_url( $this->get_process_url( $process_id, array( 'invoice_id' => absint( $active_invoice['id'] ), 'invoice_view' => 'print' ) ) ) . '">' . esc_html__( 'Ver version imprimible', 'super-mechanic' ) . '</a> ';
		if ( $this->pdf_service->can_generate_pdf() ) {
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_invoice_pdf_download_url( $process_id, absint( $active_invoice['id'] ) ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a>';
		} else {
			echo '<span class="button button-secondary disabled" aria-disabled="true">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</span> ';
			echo '<span class="description">' . esc_html__( 'Instala Dompdf, mPDF o TCPDF para habilitar la descarga real en PDF.', 'super-mechanic' ) . '</span>';
		}
		echo '</p>';

		echo '<form method="post">';
		echo '<input type="hidden" name="sm_invoice_operation" value="save_invoice" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $active_invoice['id'] ) ) . '" />';
		wp_nonce_field( 'sm_save_invoice', 'sm_save_invoice_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="invoice_currency">' . esc_html__( 'Moneda', 'super-mechanic' ) . '</label></th><td><input type="text" name="currency" id="invoice_currency" value="' . esc_attr( $active_invoice['currency'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_tax_mode">' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</label></th><td><select name="tax_mode" id="invoice_tax_mode"><option value="fixed">' . esc_html__( 'Monto fijo', 'super-mechanic' ) . '</option><option value="percent">' . esc_html__( 'Porcentaje', 'super-mechanic' ) . '</option></select> <input type="number" step="0.01" min="0" name="tax_value" id="invoice_tax_value" value="' . esc_attr( $active_invoice['tax_total'] ) . '" class="small-text" /> <span class="description">' . esc_html__( 'Si eliges porcentaje, se calcula sobre el subtotal actual y se guarda como monto.', 'super-mechanic' ) . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_discount_mode">' . esc_html__( 'Descuento', 'super-mechanic' ) . '</label></th><td><select name="discount_mode" id="invoice_discount_mode"><option value="fixed">' . esc_html__( 'Monto fijo', 'super-mechanic' ) . '</option><option value="percent">' . esc_html__( 'Porcentaje', 'super-mechanic' ) . '</option></select> <input type="number" step="0.01" min="0" name="discount_value" id="invoice_discount_value" value="' . esc_attr( $active_invoice['discount_total'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Totales actuales aplicados', 'super-mechanic' ) . '</th><td><span class="description">' . esc_html( sprintf( __( 'Impuestos %1$s | Descuento %2$s', 'super-mechanic' ), $this->format_money( $active_invoice['tax_total'], $active_invoice['currency'] ), $this->format_money( $active_invoice['discount_total'], $active_invoice['currency'] ) ) ) . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_due_date">' . esc_html__( 'Fecha de vencimiento', 'super-mechanic' ) . '</label></th><td><input type="date" name="due_date" id="invoice_due_date" value="' . esc_attr( $active_invoice['due_date'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="invoice_notes" class="large-text" rows="5">' . esc_textarea( $active_invoice['notes'] ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar factura', 'super-mechanic' ) );
		echo '</form>';

		echo '<div style="margin:16px 0;">';
		if ( 'draft' === $active_invoice['status'] ) {
			$this->render_action_form( 'issue_invoice', 'sm_issue_invoice', 'sm_issue_invoice_nonce', __( 'Emitir factura', 'super-mechanic' ), $process_id, $active_invoice['id'] );
		}
		if ( in_array( $active_invoice['status'], array( 'draft', 'issued', 'partially_paid', 'overdue' ), true ) ) {
			$this->render_action_form( 'cancel_invoice', 'sm_cancel_invoice', 'sm_cancel_invoice_nonce', __( 'Cancelar factura', 'super-mechanic' ), $process_id, $active_invoice['id'] );
		}
		if ( in_array( $active_invoice['status'], array( 'draft', 'cancelled' ), true ) ) {
			$this->render_action_form( 'delete_invoice', 'sm_delete_invoice', 'sm_delete_invoice_nonce', __( 'Eliminar factura', 'super-mechanic' ), $process_id, $active_invoice['id'], 'delete' );
		}
		echo '</div>';
		echo '<h4>' . esc_html__( 'Items de factura', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Orden', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No hay items en esta factura.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr><td colspan="7"><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
				echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $active_invoice['id'] ) ) . '" />';
				echo '<input type="hidden" name="item_id" value="' . esc_attr( absint( $item['id'] ) ) . '" />';
				wp_nonce_field( 'sm_update_invoice_item', 'sm_update_invoice_item_nonce' );
				echo '<table style="width:100%;"><tr>';
				echo '<td><input type="number" name="sort_order" value="' . esc_attr( $item['sort_order'] ) . '" class="small-text" /></td>';
				echo '<td><select name="item_type"><option value="part" ' . selected( $item['item_type'], 'part', false ) . '>part</option><option value="labor" ' . selected( $item['item_type'], 'labor', false ) . '>labor</option><option value="custom" ' . selected( $item['item_type'], 'custom', false ) . '>custom</option><option value="quote_item" ' . selected( $item['item_type'], 'quote_item', false ) . '>quote_item</option></select></td>';
				echo '<td><input type="text" name="label" value="' . esc_attr( $item['label'] ) . '" class="regular-text" /><br /><textarea name="description" class="large-text" rows="2">' . esc_textarea( $item['description'] ) . '</textarea></td>';
				echo '<td><input type="number" step="0.01" min="0.01" name="quantity" value="' . esc_attr( $item['quantity'] ) . '" class="small-text" /></td>';
				echo '<td><input type="number" step="0.01" min="0" name="unit_price" value="' . esc_attr( $item['unit_price'] ) . '" class="small-text" /></td>';
				echo '<td>' . esc_html( $this->format_money( $item['line_total'], $active_invoice['currency'] ) ) . '</td>';
				echo '<td><button type="submit" name="sm_invoice_operation" value="update_item" class="button button-secondary button-small">' . esc_html__( 'Actualizar', 'super-mechanic' ) . '</button> <button type="submit" name="sm_invoice_operation" value="delete_item" class="button button-link-delete">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</button></td>';
				echo '</tr></table></form></td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Agregar item', 'super-mechanic' ) . '</h4>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_invoice_operation" value="add_item" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $active_invoice['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_invoice_item', 'sm_add_invoice_item_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="invoice_item_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label></th><td><select name="item_type" id="invoice_item_type"><option value="part">part</option><option value="labor">labor</option><option value="custom">custom</option><option value="quote_item">quote_item</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_item_label">' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</label></th><td><input type="text" name="label" id="invoice_item_label" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_item_description">' . esc_html__( 'Descripcion', 'super-mechanic' ) . '</label></th><td><textarea name="description" id="invoice_item_description" class="large-text" rows="3"></textarea></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_item_quantity">' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</label></th><td><input type="number" name="quantity" id="invoice_item_quantity" class="small-text" step="0.01" min="0.01" value="1" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_item_unit_price">' . esc_html__( 'Precio unitario', 'super-mechanic' ) . '</label></th><td><input type="number" name="unit_price" id="invoice_item_unit_price" class="small-text" step="0.01" min="0" value="0" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_item_sort_order">' . esc_html__( 'Orden', 'super-mechanic' ) . '</label></th><td><input type="number" name="sort_order" id="invoice_item_sort_order" class="small-text" value="' . esc_attr( count( $items ) + 1 ) . '" /></td></tr>';
		echo '</table>';
		submit_button( __( 'Agregar item', 'super-mechanic' ) );
		echo '</form>';

		echo '<h4>' . esc_html__( 'Pagos', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Monto', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Metodo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Referencia', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Notas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $payments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay pagos registrados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $payments as $payment ) {
				echo '<tr><td colspan="6"><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
				echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $active_invoice['id'] ) ) . '" />';
				echo '<input type="hidden" name="payment_id" value="' . esc_attr( absint( $payment['id'] ) ) . '" />';
				wp_nonce_field( 'sm_update_invoice_payment', 'sm_update_invoice_payment_nonce' );
				echo '<table style="width:100%;"><tr>';
				echo '<td><input type="datetime-local" name="payment_date" value="' . esc_attr( $this->format_datetime_for_input( $payment['payment_date'] ) ) . '" /></td>';
				echo '<td><input type="number" step="0.01" min="0.01" name="amount" value="' . esc_attr( $payment['amount'] ) . '" class="small-text" /></td>';
				echo '<td><select name="payment_method">' . $this->get_payment_method_options_html( $payment['payment_method'] ) . '</select></td>';
				echo '<td><input type="text" name="reference" value="' . esc_attr( $payment['reference'] ) . '" class="regular-text" /></td>';
				echo '<td><input type="text" name="notes" value="' . esc_attr( $payment['notes'] ) . '" class="regular-text" /></td>';
				echo '<td>';
				if ( $this->pdf_service->can_generate_pdf() ) {
					echo '<a class="button button-secondary button-small" href="' . esc_url( $this->get_payment_receipt_download_url( absint( $payment['id'] ) ) ) . '">' . esc_html__( 'Comprobante', 'super-mechanic' ) . '</a> ';
				}
				echo '<button type="submit" name="sm_invoice_operation" value="update_payment" class="button button-secondary button-small">' . esc_html__( 'Actualizar', 'super-mechanic' ) . '</button> <button type="submit" name="sm_invoice_operation" value="delete_payment" class="button button-link-delete">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</button></td>';
				echo '</tr></table></form></td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<h4>' . esc_html__( 'Registrar pago', 'super-mechanic' ) . '</h4>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_invoice_operation" value="add_payment" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $active_invoice['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_invoice_payment', 'sm_add_invoice_payment_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="invoice_payment_date">' . esc_html__( 'Fecha de pago', 'super-mechanic' ) . '</label></th><td><input type="datetime-local" name="payment_date" id="invoice_payment_date" value="' . esc_attr( gmdate( 'Y-m-d\TH:i' ) ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_payment_amount">' . esc_html__( 'Monto', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0.01" name="amount" id="invoice_payment_amount" class="small-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_payment_method">' . esc_html__( 'Metodo', 'super-mechanic' ) . '</label></th><td><select name="payment_method" id="invoice_payment_method">' . $this->get_payment_method_options_html( 'transfer' ) . '</select></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_payment_reference">' . esc_html__( 'Referencia', 'super-mechanic' ) . '</label></th><td><input type="text" name="reference" id="invoice_payment_reference" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="invoice_payment_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="invoice_payment_notes" class="large-text" rows="3"></textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Registrar pago', 'super-mechanic' ) );
		echo '</form>';

		echo '<h4>' . esc_html__( 'Resumen', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped" style="max-width:520px;"><tbody>';
		echo '<tr><th>' . esc_html__( 'Subtotal', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $active_invoice['subtotal'], $active_invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $active_invoice['tax_total'], $active_invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Descuento', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $active_invoice['discount_total'], $active_invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Estado de cobro', 'super-mechanic' ) . '</th><td>' . esc_html( ! is_wp_error( $payment_summary ) ? $payment_summary['collection_label'] : __( 'Pendiente', 'super-mechanic' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><td><strong>' . esc_html( $this->format_money( $active_invoice['grand_total'], $active_invoice['currency'] ) ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Pagado', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $active_invoice['amount_paid'], $active_invoice['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Saldo pendiente', 'super-mechanic' ) . '</th><td><strong>' . esc_html( $this->format_money( $active_invoice['balance_due'], $active_invoice['currency'] ) ) . '</strong></td></tr>';
		echo '</tbody></table>';
	}

	protected function handle_create_from_quote() {
		check_admin_referer( 'sm_create_invoice_from_quote', 'sm_create_invoice_from_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = $this->service->create_invoice_from_quote( $quote_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, 0, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, absint( $result ), 'invoice_created' );
	}

	protected function handle_create_manual_invoice() {
		check_admin_referer( 'sm_create_manual_invoice', 'sm_create_manual_invoice_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$client_id  = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;

		if ( ! $process_id ) {
			$this->store_errors( new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) ) );
			$this->redirect_to_invoice_tab( $process_id, 0, 'invoice_error' );
		}

		$result = $this->service->create_invoice(
			array(
				'process_id' => $process_id,
				'client_id'  => $client_id,
				'currency'   => isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : '',
				'due_date'   => isset( $_POST['due_date'] ) ? wp_unslash( $_POST['due_date'] ) : '',
				'notes'      => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, 0, 'invoice_error' );
		}

		$this->redirect_to_invoice_tab( $process_id, absint( $result ), 'invoice_created' );
	}

	protected function handle_save_invoice() {
		check_admin_referer( 'sm_save_invoice', 'sm_save_invoice_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$invoice      = $this->service->get_invoice( $invoice_id );
		$adjustments  = $this->service->normalize_adjustment_totals(
			! empty( $invoice['subtotal'] ) ? (float) $invoice['subtotal'] : 0,
			array(
				'tax_mode'       => isset( $_POST['tax_mode'] ) ? wp_unslash( $_POST['tax_mode'] ) : 'fixed',
				'tax_value'      => isset( $_POST['tax_value'] ) ? wp_unslash( $_POST['tax_value'] ) : ( isset( $_POST['tax_total'] ) ? wp_unslash( $_POST['tax_total'] ) : 0 ),
				'discount_mode'  => isset( $_POST['discount_mode'] ) ? wp_unslash( $_POST['discount_mode'] ) : 'fixed',
				'discount_value' => isset( $_POST['discount_value'] ) ? wp_unslash( $_POST['discount_value'] ) : ( isset( $_POST['discount_total'] ) ? wp_unslash( $_POST['discount_total'] ) : 0 ),
			)
		);
		$result     = $this->service->update_invoice(
			$invoice_id,
			array(
				'currency'       => isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : '',
				'tax_total'      => $adjustments['tax_total'],
				'discount_total' => $adjustments['discount_total'],
				'due_date'       => isset( $_POST['due_date'] ) ? wp_unslash( $_POST['due_date'] ) : '',
				'notes'          => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_saved' );
	}

	protected function handle_delete_invoice() {
		check_admin_referer( 'sm_delete_invoice', 'sm_delete_invoice_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->delete_invoice( $invoice_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, 0, 'invoice_deleted' );
	}

	protected function handle_issue_invoice() {
		check_admin_referer( 'sm_issue_invoice', 'sm_issue_invoice_nonce' );
		$this->handle_invoice_state_action( 'issue_invoice', 'invoice_issued' );
	}

	protected function handle_cancel_invoice() {
		check_admin_referer( 'sm_cancel_invoice', 'sm_cancel_invoice_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->update_invoice( $invoice_id, array( 'status' => 'cancelled' ) );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_cancelled' );
	}
	protected function handle_add_item() {
		check_admin_referer( 'sm_add_invoice_item', 'sm_add_invoice_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->add_invoice_item( $invoice_id, $this->get_item_payload() );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_item_added' );
	}

	protected function handle_update_item() {
		check_admin_referer( 'sm_update_invoice_item', 'sm_update_invoice_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$context    = $this->assert_item_context( $item_id, $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->update_invoice_item( $item_id, $this->get_item_payload() );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_item_updated' );
	}

	protected function handle_delete_item() {
		check_admin_referer( 'sm_update_invoice_item', 'sm_update_invoice_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$context    = $this->assert_item_context( $item_id, $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->delete_invoice_item( $item_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_item_deleted' );
	}

	protected function handle_add_payment() {
		check_admin_referer( 'sm_add_invoice_payment', 'sm_add_invoice_payment_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->add_payment( $invoice_id, $this->get_payment_payload() );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'payment_added' );
	}

	protected function handle_update_payment() {
		check_admin_referer( 'sm_update_invoice_payment', 'sm_update_invoice_payment_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$payment_id = isset( $_POST['payment_id'] ) ? absint( wp_unslash( $_POST['payment_id'] ) ) : 0;
		$context    = $this->assert_payment_context( $payment_id, $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->update_payment( $payment_id, $this->get_payment_payload() );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'payment_updated' );
	}

	protected function handle_delete_payment() {
		check_admin_referer( 'sm_update_invoice_payment', 'sm_update_invoice_payment_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$payment_id = isset( $_POST['payment_id'] ) ? absint( wp_unslash( $_POST['payment_id'] ) ) : 0;
		$context    = $this->assert_payment_context( $payment_id, $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->delete_payment( $payment_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'payment_deleted' );
	}

	protected function handle_download_pdf() {
		$process_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$invoice_id = isset( $_GET['invoice_id'] ) ? absint( wp_unslash( $_GET['invoice_id'] ) ) : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sm_download_invoice_pdf_' . $invoice_id ) ) {
			$this->store_errors( new WP_Error( 'sm_invalid_nonce', __( 'No fue posible validar la descarga del PDF.', 'super-mechanic' ) ) );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}

		$context = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}

		$result = $this->pdf_service->generate_invoice_pdf( $invoice_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}

		$this->pdf_service->stream_pdf( $result['filename'], $result['content'] );
	}

	protected function handle_invoice_state_action( $method, $notice ) {
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$context    = $this->assert_invoice_context( $invoice_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$result     = $this->service->{$method}( $invoice_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_invoice_tab( $process_id, $invoice_id, 'invoice_error' );
		}
		$this->redirect_to_invoice_tab( $process_id, $invoice_id, $notice );
	}

	protected function get_item_payload() {
		return array(
			'item_type'   => isset( $_POST['item_type'] ) ? wp_unslash( $_POST['item_type'] ) : 'custom',
			'label'       => isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '',
			'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'quantity'    => isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 1,
			'unit_price'  => isset( $_POST['unit_price'] ) ? wp_unslash( $_POST['unit_price'] ) : 0,
			'sort_order'  => isset( $_POST['sort_order'] ) ? wp_unslash( $_POST['sort_order'] ) : 0,
		);
	}

	protected function get_payment_payload() {
		return array(
			'payment_date'   => isset( $_POST['payment_date'] ) ? wp_unslash( $_POST['payment_date'] ) : '',
			'amount'         => isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : 0,
			'payment_method' => isset( $_POST['payment_method'] ) ? wp_unslash( $_POST['payment_method'] ) : '',
			'reference'      => isset( $_POST['reference'] ) ? wp_unslash( $_POST['reference'] ) : '',
			'notes'          => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			'received_by'    => get_current_user_id(),
		);
	}

	protected function get_payment_method_options() {
		return array(
			'cash'     => __( 'Efectivo', 'super-mechanic' ),
			'transfer' => __( 'Transferencia', 'super-mechanic' ),
			'card'     => __( 'Tarjeta', 'super-mechanic' ),
			'check'    => __( 'Cheque', 'super-mechanic' ),
			'other'    => __( 'Otro', 'super-mechanic' ),
		);
	}

	protected function get_payment_method_options_html( $selected_value = '' ) {
		$html = '';

		foreach ( $this->get_payment_method_options() as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $selected_value, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return $html;
	}

	protected function render_action_form( $operation, $action_name, $nonce_name, $label, $process_id, $invoice_id, $button_class = 'secondary' ) {
		echo '<form method="post" style="display:inline-block;margin-right:8px;">';
		echo '<input type="hidden" name="sm_invoice_operation" value="' . esc_attr( $operation ) . '" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process_id ) ) . '" />';
		echo '<input type="hidden" name="invoice_id" value="' . esc_attr( absint( $invoice_id ) ) . '" />';
		wp_nonce_field( $action_name, $nonce_name );
		submit_button( $label, $button_class, 'submit', false );
		echo '</form>';
	}

	protected function get_process_url( $process_id, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => absint( $process_id ), 'tab' => 'invoice' ), $args ), admin_url( 'admin.php' ) );
	}

	protected function get_invoice_pdf_download_url( $process_id, $invoice_id ) {
		$url = $this->get_process_url(
			$process_id,
			array(
				'invoice_id'        => absint( $invoice_id ),
				'sm_invoice_action' => 'download_pdf',
			)
		);

		return wp_nonce_url( $url, 'sm_download_invoice_pdf_' . absint( $invoice_id ) );
	}

	protected function get_payment_receipt_download_url( $payment_id ) {
		return home_url(
			wp_nonce_url(
				add_query_arg(
					array(
						'sm_document_download' => 1,
						'sm_document_type'     => 'payment_receipt',
						'sm_document_id'       => absint( $payment_id ),
					),
					'/'
				),
				'sm_download_document_payment_receipt_' . absint( $payment_id )
			)
		);
	}

	protected function redirect_to_invoice_tab( $process_id, $invoice_id = 0, $notice = '' ) {
		$args = array();
		if ( $invoice_id > 0 ) {
			$args['invoice_id'] = absint( $invoice_id );
		}
		if ( '' !== $notice ) {
			$args['sm_notice'] = $notice;
		}
		wp_safe_redirect( $this->get_process_url( $process_id, $args ) );
		exit;
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function format_money( $value, $currency ) {
		return sprintf( '%s %s', sanitize_text_field( (string) $currency ), number_format_i18n( (float) $value, 2 ) );
	}

	protected function format_datetime_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	protected function is_invoice_screen() {
		return isset( $_GET['page'], $_GET['action'], $_GET['tab'] )
			&& 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& 'invoice' === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	protected function get_error_transient_key() {
		return 'sm_invoice_errors_' . get_current_user_id();
	}

	/**
	 * Ensure the invoice belongs to the current process.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	protected function assert_invoice_context( $invoice_id, $process_id ) {
		$invoice = $this->service->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
		}

		if ( absint( $invoice['process_id'] ) !== absint( $process_id ) ) {
			return new WP_Error( 'sm_invoice_process_mismatch', __( 'La factura no pertenece al proceso actual.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Ensure the item belongs to the current invoice and process.
	 *
	 * @param int $item_id    Item ID.
	 * @param int $invoice_id Invoice ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	protected function assert_item_context( $item_id, $invoice_id, $process_id ) {
		$context = $this->assert_invoice_context( $invoice_id, $process_id );

		if ( is_wp_error( $context ) ) {
			return $context;
		}

		foreach ( $this->service->get_invoice_items( $invoice_id ) as $item ) {
			if ( absint( $item['id'] ) === absint( $item_id ) ) {
				return true;
			}
		}

		return new WP_Error( 'sm_invoice_item_not_found', __( 'El item no pertenece a la factura seleccionada.', 'super-mechanic' ) );
	}

	/**
	 * Ensure the payment belongs to the current invoice and process.
	 *
	 * @param int $payment_id Payment ID.
	 * @param int $invoice_id Invoice ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	protected function assert_payment_context( $payment_id, $invoice_id, $process_id ) {
		$context = $this->assert_invoice_context( $invoice_id, $process_id );

		if ( is_wp_error( $context ) ) {
			return $context;
		}

		foreach ( $this->service->get_payments( $invoice_id ) as $payment ) {
			if ( absint( $payment['id'] ) === absint( $payment_id ) ) {
				return true;
			}
		}

		return new WP_Error( 'sm_invoice_payment_not_found', __( 'El pago no pertenece a la factura seleccionada.', 'super-mechanic' ) );
	}
}









