<?php
/**
 * Quote admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Super_Mechanic\Helpers\PDF_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles quote admin flows within process detail screens.
 */
class Quote_Admin_Controller {
	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * PDF service.
	 *
	 * @var PDF_Service
	 */
	protected $pdf_service;

	/**
	 * Constructor.
	 *
	 * @param Quote_Service|null   $service         Quote service.
	 * @param Invoice_Service|null $invoice_service Invoice service.
	 * @param PDF_Service|null     $pdf_service     PDF service.
	 */
	public function __construct( Quote_Service $service = null, Invoice_Service $invoice_service = null, PDF_Service $pdf_service = null ) {
		$this->service         = $service ? $service : new Quote_Service();
		$this->invoice_service = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->pdf_service     = $pdf_service ? $pdf_service : new PDF_Service( $this->invoice_service, $this->service );
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
	 * Maybe handle actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_quote_screen() ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar cotizaciones.', 'super-mechanic' ) );
		}

		if ( isset( $_GET['sm_quote_action'] ) && 'download_pdf' === sanitize_key( wp_unslash( $_GET['sm_quote_action'] ) ) ) {
			$this->handle_download_pdf();
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_quote_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_quote_operation'] ) ) : '';

		switch ( $operation ) {
			case 'create_quote':
				$this->handle_create_quote();
				break;
			case 'create_from_maintenance':
				$this->handle_create_from_maintenance();
				break;
			case 'create_invoice':
				$this->handle_create_invoice();
				break;
			case 'save_quote':
				$this->handle_save_quote();
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
			case 'send_quote':
				$this->handle_send_quote();
				break;
			case 'approve_quote':
				$this->handle_approve_quote();
				break;
			case 'reject_quote':
				$this->handle_reject_quote();
				break;
			case 'cancel_quote':
				$this->handle_cancel_quote();
				break;
			case 'delete_quote':
				$this->handle_delete_quote();
				break;
		}
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_quote_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$map    = array(
			'quote_created'         => __( 'Cotizacion creada correctamente.', 'super-mechanic' ),
			'quote_saved'           => __( 'Cotizacion actualizada correctamente.', 'super-mechanic' ),
			'quote_deleted'         => __( 'Cotizacion eliminada correctamente.', 'super-mechanic' ),
			'quote_sent'            => __( 'Cotizacion enviada correctamente.', 'super-mechanic' ),
			'quote_approved'        => __( 'Cotizacion aprobada correctamente.', 'super-mechanic' ),
			'quote_rejected'        => __( 'Cotizacion rechazada correctamente.', 'super-mechanic' ),
			'quote_cancelled'       => __( 'Cotizacion cancelada correctamente.', 'super-mechanic' ),
			'quote_item_added'      => __( 'Item agregado correctamente.', 'super-mechanic' ),
			'quote_item_updated'    => __( 'Item actualizado correctamente.', 'super-mechanic' ),
			'quote_item_deleted'    => __( 'Item eliminado correctamente.', 'super-mechanic' ),
			'quote_generated_maint' => __( 'Cotizacion generada desde mantenimiento.', 'super-mechanic' ),
			'invoice_created'       => __( 'Factura creada correctamente desde la cotizacion.', 'super-mechanic' ),
		);

		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}

		if ( 'quote_error' === $notice ) {
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
	 * Render quotes panel in process detail.
	 *
	 * @param array<string, mixed> $process Process data.
	 * @return void
	 */
	public function render_process_panel( $process ) {
		$process_id = absint( $process['id'] );
		$quotes     = $this->service->get_quotes(
			array(
				'process_id' => $process_id,
				'per_page'   => 50,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
		$quote_id   = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;
		$quote      = $quote_id ? $this->service->get_quote( $quote_id ) : null;

		if ( ! $quote && ! empty( $quotes ) ) {
			$quote = $quotes[0];
		}

		echo '<h2>' . esc_html__( 'Cotizacion', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Gestiona versiones, items, totales y aprobacion del cliente para este proceso.', 'super-mechanic' ) . '</p>';
		echo '<p>';
		echo '<form method="post" style="display:inline-block;margin-right:8px;">';
		echo '<input type="hidden" name="sm_quote_operation" value="create_quote" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		wp_nonce_field( 'sm_create_quote', 'sm_create_quote_nonce' );
		submit_button( __( 'Nueva cotizacion', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
		if ( 'maintenance' === $process['process_type'] ) {
			echo '<form method="post" style="display:inline-block;">';
			echo '<input type="hidden" name="sm_quote_operation" value="create_from_maintenance" />';
			echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
			wp_nonce_field( 'sm_create_quote_from_maintenance', 'sm_create_quote_from_maintenance_nonce' );
			submit_button( __( 'Generar desde mantenimiento', 'super-mechanic' ), 'primary', 'submit', false );
			echo '</form>';
		}
		echo '</p>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $quotes ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Aun no hay cotizaciones para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $quotes as $row ) {
				$link = $this->get_process_url( $process_id, array( 'quote_id' => absint( $row['id'] ) ) );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $link ) . '">' . esc_html( $row['quote_number'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $row['grand_total'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $link ) . '">' . esc_html__( 'Abrir', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		if ( ! $quote ) {
			return;
		}

		$quote     = $this->service->get_quote( absint( $quote['id'] ) );
		$items     = $this->service->get_quote_items( absint( $quote['id'] ) );
		$invoices  = $this->invoice_service->get_invoices(
			array(
				'quote_id' => absint( $quote['id'] ),
				'per_page' => 20,
			)
		);
		$has_invoice = ! empty( $invoices );

		echo '<hr />';
		echo '<h3>' . esc_html( sprintf( __( 'Cotizacion activa: %s', 'super-mechanic' ), $quote['quote_number'] ) ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Estado', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->humanize_key( $quote['status'] ) ) . '</p>';
		echo '<p>';
		if ( $this->pdf_service->can_generate_pdf() ) {
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_quote_pdf_download_url( $process_id, absint( $quote['id'] ) ) ) . '">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</a>';
		} else {
			echo '<span class="button button-secondary disabled" aria-disabled="true">' . esc_html__( 'Descargar PDF', 'super-mechanic' ) . '</span> ';
			echo '<span class="description">' . esc_html__( 'Instala Dompdf, mPDF o TCPDF para habilitar la descarga real en PDF.', 'super-mechanic' ) . '</span>';
		}
		echo '</p>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_quote_operation" value="save_quote" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
		wp_nonce_field( 'sm_save_quote', 'sm_save_quote_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="quote_currency">' . esc_html__( 'Moneda', 'super-mechanic' ) . '</label></th><td><input type="text" name="currency" id="quote_currency" value="' . esc_attr( $quote['currency'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_tax_total">' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="tax_total" id="quote_tax_total" value="' . esc_attr( $quote['tax_total'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_discount_total">' . esc_html__( 'Descuento', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="discount_total" id="quote_discount_total" value="' . esc_attr( $quote['discount_total'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="quote_notes" class="large-text" rows="5">' . esc_textarea( $quote['notes'] ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar cotizacion', 'super-mechanic' ) );
		echo '</form>';

		echo '<p>';
		$this->render_status_action_form( 'send_quote', 'sm_send_quote', 'sm_send_quote_nonce', __( 'Enviar', 'super-mechanic' ), $process_id, $quote );
		$this->render_status_action_form( 'approve_quote', 'sm_approve_quote', 'sm_approve_quote_nonce', __( 'Aprobar', 'super-mechanic' ), $process_id, $quote );
		$this->render_status_action_form( 'cancel_quote', 'sm_cancel_quote', 'sm_cancel_quote_nonce', __( 'Cancelar', 'super-mechanic' ), $process_id, $quote );
		if ( 'approved' === $quote['status'] && ! $has_invoice ) {
			echo '<form method="post" style="display:inline-block;margin-right:8px;">';
			echo '<input type="hidden" name="sm_quote_operation" value="create_invoice" />';
			echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
			echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
			wp_nonce_field( 'sm_create_invoice_from_quote', 'sm_create_invoice_from_quote_nonce' );
			submit_button( __( 'Crear factura', 'super-mechanic' ), 'primary', 'submit', false );
			echo '</form>';
		} elseif ( $has_invoice ) {
			echo '<span style="display:inline-block;margin-right:8px;"><strong>' . esc_html__( 'Factura ya generada para esta cotizacion.', 'super-mechanic' ) . '</strong></span>';
		}
		echo '</p>';
		echo '<form method="post" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="sm_quote_operation" value="reject_quote" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
		wp_nonce_field( 'sm_reject_quote', 'sm_reject_quote_nonce' );
		echo '<label for="quote_rejection_reason"><strong>' . esc_html__( 'Motivo de rechazo', 'super-mechanic' ) . '</strong></label><br />';
		echo '<textarea name="rejection_reason" id="quote_rejection_reason" class="large-text" rows="3"></textarea><br />';
		submit_button( __( 'Rechazar cotizacion', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '<form method="post" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="sm_quote_operation" value="delete_quote" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
		wp_nonce_field( 'sm_delete_quote', 'sm_delete_quote_nonce' );
		submit_button( __( 'Eliminar cotizacion', 'super-mechanic' ), 'delete', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Items', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Orden', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio unitario', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No hay items en esta cotizacion.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr><td colspan="7"><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
				echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
				echo '<input type="hidden" name="item_id" value="' . esc_attr( absint( $item['id'] ) ) . '" />';
				wp_nonce_field( 'sm_update_quote_item', 'sm_update_quote_item_nonce' );
				echo '<table style="width:100%;"><tr>';
				echo '<td><input type="number" name="sort_order" value="' . esc_attr( $item['sort_order'] ) . '" class="small-text" /></td>';
				echo '<td><select name="item_type"><option value="part" ' . selected( $item['item_type'], 'part', false ) . '>part</option><option value="labor" ' . selected( $item['item_type'], 'labor', false ) . '>labor</option><option value="custom" ' . selected( $item['item_type'], 'custom', false ) . '>custom</option></select></td>';
				echo '<td><input type="text" name="label" value="' . esc_attr( $item['label'] ) . '" class="regular-text" /><br /><textarea name="description" class="large-text" rows="2">' . esc_textarea( $item['description'] ) . '</textarea></td>';
				echo '<td><input type="number" step="0.01" min="0.01" name="quantity" value="' . esc_attr( $item['quantity'] ) . '" class="small-text" /></td>';
				echo '<td><input type="number" step="0.01" min="0" name="unit_price" value="' . esc_attr( $item['unit_price'] ) . '" class="small-text" /></td>';
				echo '<td>' . esc_html( $this->format_money( $item['line_total'], $quote['currency'] ) ) . '</td>';
				echo '<td><button type="submit" name="sm_quote_operation" value="update_item" class="button button-secondary button-small">' . esc_html__( 'Actualizar', 'super-mechanic' ) . '</button> <button type="submit" name="sm_quote_operation" value="delete_item" class="button button-link-delete">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</button></td>';
				echo '</tr></table>';
				echo '</form></td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<h3>' . esc_html__( 'Agregar item', 'super-mechanic' ) . '</h3>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_quote_operation" value="add_item" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_quote_item', 'sm_add_quote_item_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="quote_item_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label></th><td><select name="item_type" id="quote_item_type"><option value="part">part</option><option value="labor">labor</option><option value="custom">custom</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="quote_item_label">' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</label></th><td><input type="text" name="label" id="quote_item_label" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_item_description">' . esc_html__( 'Descripcion', 'super-mechanic' ) . '</label></th><td><textarea name="description" id="quote_item_description" class="large-text" rows="3"></textarea></td></tr>';
		echo '<tr><th scope="row"><label for="quote_item_quantity">' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0.01" name="quantity" id="quote_item_quantity" class="small-text" value="1" /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_item_unit_price">' . esc_html__( 'Precio unitario', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="unit_price" id="quote_item_unit_price" class="small-text" value="0" /></td></tr>';
		echo '<tr><th scope="row"><label for="quote_item_sort_order">' . esc_html__( 'Orden', 'super-mechanic' ) . '</label></th><td><input type="number" name="sort_order" id="quote_item_sort_order" class="small-text" value="' . esc_attr( count( $items ) + 1 ) . '" /></td></tr>';
		echo '</table>';
		submit_button( __( 'Agregar item', 'super-mechanic' ) );
		echo '</form>';
		echo '<h3>' . esc_html__( 'Totales', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Subtotal', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $quote['subtotal'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $quote['tax_total'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Descuento', 'super-mechanic' ) . '</th><td>' . esc_html( $this->format_money( $quote['discount_total'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><td><strong>' . esc_html( $this->format_money( $quote['grand_total'], $quote['currency'] ) ) . '</strong></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Handle create quote.
	 *
	 * @return void
	 */
	protected function handle_create_quote() {
		check_admin_referer( 'sm_create_quote', 'sm_create_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$result     = $this->service->create_quote( array( 'process_id' => $process_id ) );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab( $process_id, array( 'sm_notice' => 'quote_error' ) );
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_created',
				'quote_id'  => absint( $result ),
			)
		);
	}

	/**
	 * Handle create from maintenance.
	 *
	 * @return void
	 */
	protected function handle_create_from_maintenance() {
		check_admin_referer( 'sm_create_quote_from_maintenance', 'sm_create_quote_from_maintenance_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$result     = $this->service->create_quote_from_maintenance( $process_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab( $process_id, array( 'sm_notice' => 'quote_error' ) );
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_generated_maint',
				'quote_id'  => absint( $result ),
			)
		);
	}

	/**
	 * Handle create invoice.
	 *
	 * @return void
	 */
	protected function handle_create_invoice() {
		check_admin_referer( 'sm_create_invoice_from_quote', 'sm_create_invoice_from_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$quote      = $this->service->get_convertible_quote( $quote_id, $process_id );

		if ( is_wp_error( $quote ) ) {
			$this->store_errors( $quote );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$result = $this->invoice_service->create_invoice_from_quote( $quote_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'super-mechanic-processes',
					'action'     => 'edit',
					'id'         => $process_id,
					'tab'        => 'invoice',
					'invoice_id' => absint( $result ),
					'sm_notice'  => 'invoice_created',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle save quote.
	 *
	 * @return void
	 */
	protected function handle_save_quote() {
		check_admin_referer( 'sm_save_quote', 'sm_save_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = $this->service->update_quote(
			$quote_id,
			array(
				'currency'       => isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : '',
				'tax_total'      => isset( $_POST['tax_total'] ) ? wp_unslash( $_POST['tax_total'] ) : 0,
				'discount_total' => isset( $_POST['discount_total'] ) ? wp_unslash( $_POST['discount_total'] ) : 0,
				'notes'          => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_saved',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle add item.
	 *
	 * @return void
	 */
	protected function handle_add_item() {
		check_admin_referer( 'sm_add_quote_item', 'sm_add_quote_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = $this->service->add_quote_item(
			$quote_id,
			array(
				'item_type'   => isset( $_POST['item_type'] ) ? wp_unslash( $_POST['item_type'] ) : 'custom',
				'label'       => isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'quantity'    => isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 1,
				'unit_price'  => isset( $_POST['unit_price'] ) ? wp_unslash( $_POST['unit_price'] ) : 0,
				'sort_order'  => isset( $_POST['sort_order'] ) ? wp_unslash( $_POST['sort_order'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_item_added',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle update item.
	 *
	 * @return void
	 */
	protected function handle_update_item() {
		check_admin_referer( 'sm_update_quote_item', 'sm_update_quote_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$result     = $this->service->update_quote_item(
			$item_id,
			array(
				'quote_id'    => $quote_id,
				'item_type'   => isset( $_POST['item_type'] ) ? wp_unslash( $_POST['item_type'] ) : 'custom',
				'label'       => isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'quantity'    => isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 1,
				'unit_price'  => isset( $_POST['unit_price'] ) ? wp_unslash( $_POST['unit_price'] ) : 0,
				'sort_order'  => isset( $_POST['sort_order'] ) ? wp_unslash( $_POST['sort_order'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_item_updated',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle delete item.
	 *
	 * @return void
	 */
	protected function handle_delete_item() {
		check_admin_referer( 'sm_update_quote_item', 'sm_update_quote_item_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$result     = $this->service->delete_quote_item( $item_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_item_deleted',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle send quote.
	 *
	 * @return void
	 */
	protected function handle_send_quote() {
		check_admin_referer( 'sm_send_quote', 'sm_send_quote_nonce' );
		$this->handle_state_action( 'send_quote', 'quote_sent' );
	}

	/**
	 * Handle approve quote.
	 *
	 * @return void
	 */
	protected function handle_approve_quote() {
		check_admin_referer( 'sm_approve_quote', 'sm_approve_quote_nonce' );
		$this->handle_state_action( 'approve_quote', 'quote_approved', get_current_user_id() );
	}

	/**
	 * Handle reject quote.
	 *
	 * @return void
	 */
	protected function handle_reject_quote() {
		check_admin_referer( 'sm_reject_quote', 'sm_reject_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$reason     = isset( $_POST['rejection_reason'] ) ? wp_unslash( $_POST['rejection_reason'] ) : '';
		$result     = $this->service->reject_quote( $quote_id, get_current_user_id(), $reason );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_rejected',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle cancel quote.
	 *
	 * @return void
	 */
	protected function handle_cancel_quote() {
		check_admin_referer( 'sm_cancel_quote', 'sm_cancel_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = $this->service->cancel_quote( $quote_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => 'quote_cancelled',
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Handle delete quote.
	 *
	 * @return void
	 */
	protected function handle_delete_quote() {
		check_admin_referer( 'sm_delete_quote', 'sm_delete_quote_nonce' );
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = $this->service->delete_quote( $quote_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab( $process_id, array( 'sm_notice' => 'quote_error' ) );
		}

		$this->redirect_to_quote_tab( $process_id, array( 'sm_notice' => 'quote_deleted' ) );
	}

	/**
	 * Handle generic state action.
	 *
	 * @param string   $method  Method name.
	 * @param string   $notice  Notice key.
	 * @param int|null $user_id Optional user ID.
	 * @return void
	 */
	/**
	 * Handle quote PDF download.
	 *
	 * @return void
	 */
	protected function handle_download_pdf() {
		$process_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$quote_id   = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sm_download_quote_pdf_' . $quote_id ) ) {
			$this->store_errors( new WP_Error( 'sm_invalid_nonce', __( 'No fue posible validar la descarga del PDF.', 'super-mechanic' ) ) );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$context = $this->assert_quote_context( $quote_id, $process_id );
		if ( is_wp_error( $context ) ) {
			$this->store_errors( $context );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$result = $this->pdf_service->generate_quote_pdf( $quote_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->pdf_service->stream_pdf( $result['filename'], $result['content'] );
	}

	protected function handle_state_action( $method, $notice, $user_id = null ) {
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$quote_id   = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		$result     = null === $user_id ? $this->service->{$method}( $quote_id ) : $this->service->{$method}( $quote_id, $user_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_quote_tab(
				$process_id,
				array(
					'sm_notice' => 'quote_error',
					'quote_id'  => $quote_id,
				)
			);
		}

		$this->redirect_to_quote_tab(
			$process_id,
			array(
				'sm_notice' => $notice,
				'quote_id'  => $quote_id,
			)
		);
	}

	/**
	 * Render state action form.
	 *
	 * @param string               $operation  Operation.
	 * @param string               $action     Nonce action.
	 * @param string               $nonce_name Nonce name.
	 * @param string               $label      Button label.
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $quote      Quote data.
	 * @return void
	 */
	protected function render_status_action_form( $operation, $action, $nonce_name, $label, $process_id, $quote ) {
		echo '<form method="post" style="display:inline-block;margin-right:8px;">';
		echo '<input type="hidden" name="sm_quote_operation" value="' . esc_attr( $operation ) . '" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process_id ) ) . '" />';
		echo '<input type="hidden" name="quote_id" value="' . esc_attr( absint( $quote['id'] ) ) . '" />';
		wp_nonce_field( $action, $nonce_name );
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Build process URL for quote tab.
	 *
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $args       Extra args.
	 * @return string
	 */
	protected function get_process_url( $process_id, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page'   => 'super-mechanic-processes',
					'action' => 'edit',
					'id'     => absint( $process_id ),
					'tab'    => 'quote',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Redirect to quote tab.
	 *
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $args       Args.
	 * @return void
	 */
	protected function get_quote_pdf_download_url( $process_id, $quote_id ) {
		$url = $this->get_process_url(
			$process_id,
			array(
				'quote_id'        => absint( $quote_id ),
				'sm_quote_action' => 'download_pdf',
			)
		);

		return wp_nonce_url( $url, 'sm_download_quote_pdf_' . absint( $quote_id ) );
	}

	protected function redirect_to_quote_tab( $process_id, $args = array() ) {
		wp_safe_redirect( $this->get_process_url( $process_id, $args ) );
		exit;
	}

	/**
	 * Humanize key.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Format money.
	 *
	 * @param mixed  $value    Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	protected function format_money( $value, $currency ) {
		return sprintf( '%s %s', sanitize_text_field( (string) $currency ), number_format_i18n( (float) $value, 2 ) );
	}

	/**
	 * Check quote screen.
	 *
	 * @return bool
	 */
	protected function is_quote_screen() {
		return isset( $_GET['page'], $_GET['action'], $_GET['tab'] )
			&& 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& 'quote' === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	/**
	 * Store errors.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Get transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_quote_errors_' . get_current_user_id();
	}

	/**
	 * Ensure the quote belongs to the current process.
	 *
	 * @param int $quote_id   Quote ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	protected function assert_quote_context( $quote_id, $process_id ) {
		$quote = $this->service->get_quote( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		if ( absint( $quote['process_id'] ) !== absint( $process_id ) ) {
			return new WP_Error( 'sm_quote_process_mismatch', __( 'La cotizacion no pertenece al proceso actual.', 'super-mechanic' ) );
		}

		return true;
	}
}

