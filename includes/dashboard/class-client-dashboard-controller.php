<?php
/**
 * Client dashboard controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Assets;
use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Attachments\Process_Timeline_Service;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders reusable client dashboard sections.
 */
class Client_Dashboard_Controller {
	protected $service;
	protected $quote_service;
	protected $invoice_service;
	protected $attachment_service;
	protected $process_timeline_service;
	protected $comment_service;
	protected $notification_service;
	protected $download_service;
	protected $client_process_view_service;

	public function __construct( Dashboard_Service $service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Attachment_Service $attachment_service = null, Process_Timeline_Service $process_timeline_service = null, Comment_Service $comment_service = null, Notification_Service $notification_service = null, Download_Service $download_service = null, Client_Process_View_Service $client_process_view_service = null ) {
		$this->service                      = $service ? $service : new Dashboard_Service();
		$this->quote_service                = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service              = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->attachment_service           = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->process_timeline_service     = $process_timeline_service ? $process_timeline_service : new Process_Timeline_Service();
		$this->comment_service              = $comment_service ? $comment_service : new Comment_Service();
		$this->notification_service         = $notification_service ? $notification_service : new Notification_Service();
		$this->download_service             = $download_service ? $download_service : new Download_Service();
		$this->client_process_view_service  = $client_process_view_service ? $client_process_view_service : new Client_Process_View_Service( $this->service, $this->quote_service, $this->invoice_service, $this->comment_service );
	}

	public function render_dashboard( $user_id = null ) {
		Assets::enqueue_client_assets();

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$profile = $this->service->get_client_profile_data( $user_id );

		if ( empty( $profile ) ) {
			return '<div class="sm-client-dashboard"><p>' . esc_html__( 'No hay un cliente vinculado a este usuario.', 'super-mechanic' ) . '</p></div>';
		}

		$activity      = $this->service->get_client_recent_activity( $user_id, 10 );
		$notifications = $this->get_client_notifications_data( $user_id, 5 );
		$comments      = $this->client_process_view_service->get_recent_client_comments( $user_id, 5 );
		$name          = trim( $profile['first_name'] . ' ' . $profile['last_name'] );
		$comment_notice = $this->handle_process_comment_submission( $user_id );
		$requested_process_id = $this->get_requested_process_id();

		ob_start();
		echo '<div class="sm-client-ui sm-client-dashboard">';
		echo '<div class="sm-client-header">';
		echo '<div>';
		echo '<h2 class="sm-client-title">' . esc_html__( 'Mi panel', 'super-mechanic' ) . '</h2>';
		echo '<p class="sm-client-meta"><strong>' . esc_html( $name ) . '</strong></p>';
		if ( ! empty( $profile['email'] ) ) {
			echo '<p class="sm-client-meta">' . esc_html( $profile['email'] ) . '</p>';
		}
		echo '</div>';
		echo '<span class="sm-client-badge sm-client-badge-primary">' . esc_html__( 'Client Portal', 'super-mechanic' ) . '</span>';
		echo '</div>';
		echo '<div class="sm-grid sm-grid-cards" style="margin-bottom:20px;">';
		echo '<article class="sm-card sm-kpi-card"><span class="sm-kpi-label">' . esc_html__( 'Navegación', 'super-mechanic' ) . '</span><p class="sm-kpi-footnote"><a href="#sm-client-vehicles">' . esc_html__( 'Vehículos', 'super-mechanic' ) . '</a> | <a href="#sm-client-processes">' . esc_html__( 'Procesos', 'super-mechanic' ) . '</a> | <a href="#sm-client-quotes">' . esc_html__( 'Cotizaciones', 'super-mechanic' ) . '</a> | <a href="#sm-client-invoices">' . esc_html__( 'Facturas', 'super-mechanic' ) . '</a></p></article>';
		echo '<article class="sm-card sm-kpi-card"><span class="sm-kpi-label">' . esc_html__( 'Documentos', 'super-mechanic' ) . '</span><p class="sm-kpi-footnote">' . esc_html__( 'Descargas seguras de adjuntos, invoices, quotes y comprobantes cuando el entorno PDF está activo.', 'super-mechanic' ) . '</p></article>';
		echo '</div>';
		if ( '' !== $comment_notice ) {
			echo wp_kses_post( $comment_notice );
		}
		if ( $requested_process_id ) {
			echo '<h3>' . esc_html__( 'Detalle del proceso', 'super-mechanic' ) . '</h3>';
			echo $this->render_process_detail( $requested_process_id, $user_id );
		}
		echo '<h3 id="sm-client-vehicles">' . esc_html__( 'Vehiculos', 'super-mechanic' ) . '</h3>';
		echo $this->render_vehicles( $user_id );
		echo '<h3 id="sm-client-processes">' . esc_html__( 'Procesos recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_processes( $user_id );
		echo '<h3 id="sm-client-quotes">' . esc_html__( 'Cotizaciones recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_quotes( $user_id );
		echo '<h3 id="sm-client-invoices">' . esc_html__( 'Facturas recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_invoices( $user_id );
		echo '<h3>' . esc_html__( 'Comentarios recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_recent_comments_table( $comments );
		echo '<h3>' . esc_html__( 'Notificaciones recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_recent_notifications_table( $notifications, false );
		echo '<h3>' . esc_html__( 'Actividad reciente', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Accion', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mensaje', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $activity ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'Sin actividad reciente.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $activity as $row ) {
				echo '<tr><td>' . esc_html( $row['created_at'] ) . '</td><td>' . esc_html( ucwords( str_replace( '_', ' ', $row['action_type'] ) ) ) . '</td><td>' . esc_html( $row['message'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_vehicles( $user_id = null ) {
		$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
		$vehicles = $this->service->get_client_vehicles( $user_id );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Placa', 'super-mechanic' ) . '</th><th>' . esc_html__( 'VIN', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Relacion', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $vehicles ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay vehiculos vinculados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $vehicles as $vehicle ) {
				$label = trim( $vehicle['make'] . ' ' . $vehicle['model'] );
				echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( $vehicle['plate'] ) . '</td><td>' . esc_html( $vehicle['vin'] ) . '</td><td>' . esc_html( ucwords( str_replace( '_', ' ', $vehicle['ownership_type'] ) ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_processes( $user_id = null ) {
		$user_id   = $user_id ? absint( $user_id ) : get_current_user_id();
		$processes = $this->service->get_client_processes( $user_id, array( 'per_page' => 20 ) );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Titulo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado operativo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado financiero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Accesos rapidos', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $processes ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No hay procesos vinculados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $processes as $process ) {
				$vehicle = trim( $process['vehicle_make'] . ' ' . $process['vehicle_model'] );
				$status  = ucwords( str_replace( '_', ' ', $process['status'] ) );
				$financial = $this->client_process_view_service->get_process_financial_snapshot( $user_id, $process );
				$detail_url = add_query_arg( 'process_id', absint( $process['id'] ) );
				if ( ! empty( $process['vehicle_plate'] ) ) {
					$vehicle .= ' - ' . $process['vehicle_plate'];
				}
				if ( ! empty( $process['derived_status_label'] ) ) {
					$status .= ' (' . $process['derived_status_label'] . ')';
				}
				echo '<tr>';
				echo '<td>' . esc_html( $process['id'] ) . '</td>';
				echo '<td>' . esc_html( $process['title'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $process['process_type'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . esc_html( $financial['label'] ) . '</td>';
				echo '<td>' . esc_html( $vehicle ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a>';
				echo ' | <a href="' . esc_url( $detail_url . '#sm-process-documents' ) . '">' . esc_html__( 'Documentos', 'super-mechanic' ) . '</a>';
				echo ' | <a href="' . esc_url( $detail_url . '#sm-process-finance' ) . '">' . esc_html__( 'Facturacion', 'super-mechanic' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_quotes( $user_id = null ) {
		$user_id   = $user_id ? absint( $user_id ) : get_current_user_id();
		$client_id = $this->service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return '<p>' . esc_html__( 'No hay cotizaciones disponibles.', 'super-mechanic' ) . '</p>';
		}

		$quotes = $this->quote_service->get_quotes_for_user(
			$user_id,
			array(
				'client_id' => $client_id,
				'per_page'  => 10,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $quotes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay cotizaciones registradas.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $quotes as $quote ) {
				echo '<tr>';
				echo '<td>' . esc_html( $quote['quote_number'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $quote['process_title'] ) ? $quote['process_title'] : __( 'Proceso', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $quote['status'] ) ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $quote['grand_total'], 2 ) ) . ' ' . esc_html( $quote['currency'] ) . '</td>';
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

		return (string) ob_get_clean();
	}

	public function render_invoices( $user_id = null ) {
		$user_id   = $user_id ? absint( $user_id ) : get_current_user_id();
		$client_id = $this->service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return '<p>' . esc_html__( 'No hay facturas disponibles.', 'super-mechanic' ) . '</p>';
		}

		$invoices = $this->invoice_service->get_invoices_for_user(
			$user_id,
			array(
				'client_id' => $client_id,
				'per_page'  => 10,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);

		$total_balance    = 0.0;
		$balance_currency = '';
		$single_currency  = true;
		foreach ( $invoices as $invoice ) {
			$total_balance += (float) $invoice['balance_due'];

			if ( '' === $balance_currency ) {
				$balance_currency = (string) $invoice['currency'];
			} elseif ( $balance_currency !== (string) $invoice['currency'] ) {
				$single_currency = false;
			}
		}

		ob_start();
		if ( ! empty( $invoices ) ) {
			if ( $single_currency ) {
				echo '<p><strong>' . esc_html__( 'Balance pendiente total:', 'super-mechanic' ) . '</strong> ' . esc_html( number_format_i18n( $total_balance, 2 ) ) . ' ' . esc_html( $balance_currency ) . '</p>';
			} else {
				echo '<p><strong>' . esc_html__( 'Balance pendiente total:', 'super-mechanic' ) . '</strong> ' . esc_html__( 'Disponible por factura debido a multiples monedas.', 'super-mechanic' ) . '</p>';
			}
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pagado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Saldo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vence', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Detalle', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $invoices ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No hay facturas registradas.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $invoices as $invoice ) {
				$invoice = $this->invoice_service->append_collection_state( $invoice );
				$status  = ucwords( str_replace( '_', ' ', $invoice['status'] ) );

				if ( ! empty( $invoice['collection_label'] ) ) {
					$status .= ' (' . $invoice['collection_label'] . ')';
				}

				echo '<tr>';
				echo '<td>' . esc_html( $invoice['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $invoice['process_title'] ) ? $invoice['process_title'] : __( 'Proceso', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $invoice['grand_total'], 2 ) ) . ' ' . esc_html( $invoice['currency'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $invoice['amount_paid'], 2 ) ) . ' ' . esc_html( $invoice['currency'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $invoice['balance_due'], 2 ) ) . ' ' . esc_html( $invoice['currency'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $invoice['due_date'] ) ? $invoice['due_date'] : '-' ) . '</td>';
				echo '<td><a href="' . esc_url( add_query_arg( 'invoice_id', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a>';
				if ( $this->download_service->can_generate_pdf() ) {
					echo ' | <a href="' . esc_url( $this->download_service->get_download_url( 'invoice_pdf', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'PDF', 'super-mechanic' ) . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_process_detail( $process_id, $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$process = $this->client_process_view_service->get_client_process_by_id( $user_id, $process_id );

		if ( empty( $process ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a este proceso.', 'super-mechanic' ) . '</p>';
		}

		$quotes    = $this->client_process_view_service->get_process_quotes_for_user( $user_id, absint( $process['id'] ) );
		$invoices  = $this->client_process_view_service->get_process_invoices_for_user( $user_id, absint( $process['id'] ) );
		$financial = $this->client_process_view_service->get_process_financial_snapshot( $user_id, $process, $invoices );
		$vehicle   = trim( $process['vehicle_make'] . ' ' . $process['vehicle_model'] );

		if ( ! empty( $process['vehicle_plate'] ) ) {
			$vehicle .= ' - ' . $process['vehicle_plate'];
		}

		ob_start();
		echo '<div class="sm-client-process-detail">';
		echo '<p><a href="' . esc_url( remove_query_arg( 'process_id' ) ) . '">' . esc_html__( 'Volver al panel', 'super-mechanic' ) . '</a></p>';
		echo '<table class="widefat striped" style="max-width:760px;"><tbody>';
		echo '<tr><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><td>' . esc_html( ! empty( $process['title'] ) ? $process['title'] : '#' . absint( $process['id'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><td>' . esc_html( ucwords( str_replace( '_', ' ', $process['process_type'] ) ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Estado base', 'super-mechanic' ) . '</th><td>' . esc_html( ucwords( str_replace( '_', ' ', $process['status'] ) ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Estado derivado', 'super-mechanic' ) . '</th><td>' . esc_html( ! empty( $process['derived_status_label'] ) ? $process['derived_status_label'] : __( 'No disponible', 'super-mechanic' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Estado financiero', 'super-mechanic' ) . '</th><td>' . esc_html( $financial['label'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</th><td>' . esc_html( $vehicle ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Ultima actualizacion', 'super-mechanic' ) . '</th><td>' . esc_html( ! empty( $process['updated_at'] ) ? $process['updated_at'] : $process['created_at'] ) . '</td></tr>';
		echo '</tbody></table>';

		echo '<h4 id="sm-process-quotes">' . esc_html__( 'Cotizaciones relacionadas', 'super-mechanic' ) . '</h4>';
		echo $this->render_process_quotes_table( $quotes );

		echo '<h4 id="sm-process-finance">' . esc_html__( 'Facturas y pagos', 'super-mechanic' ) . '</h4>';
		echo $this->render_process_invoices_table( $invoices );

		echo '<h4 id="sm-process-documents">' . esc_html__( 'Documentos del proceso', 'super-mechanic' ) . '</h4>';
		echo $this->render_process_documents( absint( $process['id'] ), $user_id );

		echo '<h4>' . esc_html__( 'Timeline', 'super-mechanic' ) . '</h4>';
		echo $this->render_process_timeline( absint( $process['id'] ), $user_id );

		echo '<h4>' . esc_html__( 'Comentarios', 'super-mechanic' ) . '</h4>';
		echo $this->render_process_comments( absint( $process['id'] ), $user_id );
		echo $this->render_process_comment_form( absint( $process['id'] ), $user_id );
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_process_documents( $process_id, $user_id = null ) {
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		$process_id = absint( $process_id );

		if ( ! $this->service->user_can_access_client_process( $user_id, $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a los documentos de este proceso.', 'super-mechanic' ) . '</p>';
		}

		$attachments = $this->attachment_service->get_client_visible_process_attachments( $process_id );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Documento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Archivo', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay documentos visibles para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $attachments as $attachment ) {
				$download_url = $this->get_attachment_download_url( $attachment );

				echo '<tr>';
				echo '<td>' . esc_html( $attachment['title'] ) . '<br /><small>' . esc_html( $attachment['description'] ) . '</small></td>';
				echo '<td>' . esc_html( $attachment['attachment_type'] ) . '</td>';
				echo '<td>' . esc_html( $attachment['created_at'] ) . '</td>';
				if ( '' !== $download_url ) {
					echo '<td><a href="' . esc_url( $download_url ) . '">' . esc_html__( 'Descargar', 'super-mechanic' ) . '</a></td>';
				} else {
					echo '<td>' . esc_html__( 'No disponible', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_process_timeline( $process_id, $user_id = null ) {
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		$process_id = absint( $process_id );

		if ( ! $this->service->user_can_access_client_process( $user_id, $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a la timeline de este proceso.', 'super-mechanic' ) . '</p>';
		}

		$timeline = $this->process_timeline_service->get_process_timeline( $process_id, true );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Evento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $timeline ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No hay eventos visibles para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $timeline as $event ) {
				echo '<tr>';
				echo '<td>' . esc_html( $event['event_date'] ) . '</td>';
				echo '<td>' . esc_html( $event['event_label'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $event['event_type'] ) ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_process_comments( $process_id, $user_id = null ) {
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		$process_id = absint( $process_id );

		if ( ! $this->service->user_can_access_client_process( $user_id, $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a los comentarios de este proceso.', 'super-mechanic' ) . '</p>';
		}

		$comments = $this->comment_service->get_client_visible_process_comments( $process_id );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mensaje', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No hay comentarios visibles para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				echo '<tr>';
				echo '<td>' . esc_html( $comment['created_at'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $comment['comment_type'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $comment['content'] ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_client_notifications( $user_id = null ) {
		$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
		$client_id = $this->service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return '<p>' . esc_html__( 'No hay notificaciones disponibles.', 'super-mechanic' ) . '</p>';
		}

		$notifications = $this->notification_service->get_client_notifications( $client_id, array( 'per_page' => 50, 'orderby' => 'created_at', 'order' => 'DESC' ) );

		ob_start();
		echo '<form method="post" style="margin-bottom:12px;">';
		wp_nonce_field( 'sm_client_notification_mark_all_read', 'sm_client_notification_all_nonce' );
		echo '<input type="hidden" name="sm_client_comment_operation" value="mark_all_read" />';
		echo '<button type="submit">' . esc_html__( 'Marcar todas como leidas', 'super-mechanic' ) . '</button>';
		echo '</form>';
		echo $this->render_recent_notifications_table( $notifications, true );

		return (string) ob_get_clean();
	}

	protected function get_client_notifications_data( $user_id, $limit = 5 ) {
		$client_id = $this->service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		return $this->notification_service->get_client_notifications( $client_id, array( 'per_page' => $limit, 'orderby' => 'created_at', 'order' => 'DESC' ) );
	}

	protected function get_requested_process_id() {
		return isset( $_GET['process_id'] ) ? absint( wp_unslash( $_GET['process_id'] ) ) : 0;
	}

	protected function render_process_quotes_table( array $quotes ) {
		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $quotes ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay cotizaciones relacionadas.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $quotes as $quote ) {
				echo '<tr>';
				echo '<td>' . esc_html( $quote['quote_number'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $quote['status'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $quote['grand_total'], $quote['currency'] ) ) . '</td>';
				echo '<td><a href="' . esc_url( add_query_arg( 'quote_id', absint( $quote['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a>';
				if ( $this->download_service->can_generate_pdf() ) {
					echo ' | <a href="' . esc_url( $this->download_service->get_download_url( 'quote_pdf', absint( $quote['id'] ) ) ) . '">' . esc_html__( 'PDF', 'super-mechanic' ) . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	protected function render_process_invoices_table( array $invoices ) {
		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Saldo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pagos', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $invoices ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay facturas relacionadas.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $invoices as $invoice ) {
				$payments = $this->invoice_service->get_payments( absint( $invoice['id'] ) );
				$status   = ucwords( str_replace( '_', ' ', $invoice['status'] ) );

				if ( ! empty( $invoice['collection_label'] ) ) {
					$status .= ' (' . $invoice['collection_label'] . ')';
				}

				echo '<tr>';
				echo '<td>' . esc_html( $invoice['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['grand_total'], $invoice['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( $invoice['balance_due'], $invoice['currency'] ) ) . '</td>';
				echo '<td>';
				if ( empty( $payments ) ) {
					echo esc_html__( 'Sin pagos registrados', 'super-mechanic' );
				} else {
					foreach ( $payments as $payment ) {
						echo '<div>';
						echo esc_html( $payment['payment_date'] ) . ' - ' . esc_html( $this->format_money( $payment['amount'], $invoice['currency'] ) );
						if ( $this->download_service->can_generate_pdf() ) {
							echo ' <a href="' . esc_url( $this->download_service->get_download_url( 'payment_receipt', absint( $payment['id'] ) ) ) . '">' . esc_html__( 'Comprobante', 'super-mechanic' ) . '</a>';
						}
						echo '</div>';
					}
				}
				echo '</td>';
				echo '<td><a href="' . esc_url( add_query_arg( 'invoice_id', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a>';
				if ( $this->download_service->can_generate_pdf() ) {
					echo ' | <a href="' . esc_url( $this->download_service->get_download_url( 'invoice_pdf', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'PDF', 'super-mechanic' ) . '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	protected function render_process_comment_form( $process_id, $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $this->service->user_can_access_client_process( $user_id, $process_id ) ) {
			return '';
		}

		ob_start();
		echo '<form method="post" style="margin-top:12px;">';
		wp_nonce_field( 'sm_client_process_comment_' . absint( $process_id ), 'sm_client_process_comment_nonce' );
		echo '<input type="hidden" name="sm_client_process_comment_action" value="create" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process_id ) ) . '" />';
		echo '<label for="sm_client_process_comment_content_' . esc_attr( absint( $process_id ) ) . '">' . esc_html__( 'Enviar comentario al taller', 'super-mechanic' ) . '</label><br />';
		echo '<textarea id="sm_client_process_comment_content_' . esc_attr( absint( $process_id ) ) . '" name="content" class="large-text" rows="4" required></textarea><br />';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Enviar comentario', 'super-mechanic' ) . '</button>';
		echo '</form>';

		return (string) ob_get_clean();
	}

	protected function handle_process_comment_submission( $user_id ) {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( ! is_user_logged_in() || 'POST' !== $request_method ) {
			return '';
		}

		if ( empty( $_POST['sm_client_process_comment_action'] ) || 'create' !== sanitize_key( wp_unslash( $_POST['sm_client_process_comment_action'] ) ) ) {
			return '';
		}

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$nonce      = isset( $_POST['sm_client_process_comment_nonce'] ) ? wp_unslash( $_POST['sm_client_process_comment_nonce'] ) : '';
		$content    = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$client_id  = $this->service->get_client_id_by_user_id( $user_id );

		if ( ! $process_id || ! wp_verify_nonce( $nonce, 'sm_client_process_comment_' . $process_id ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'No fue posible validar el comentario enviado.', 'super-mechanic' ) . '</p></div>';
		}

		if ( ! $this->service->user_can_access_client_process( $user_id, $process_id ) ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'No tienes acceso a este proceso.', 'super-mechanic' ) . '</p></div>';
		}

		$result = $this->comment_service->create_comment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'author_user_id'    => $user_id,
				'author_client_id'  => $client_id,
				'comment_type'      => 'client_message',
				'content'           => $content,
				'is_internal'       => 0,
				'is_client_visible' => 1,
				'status'            => 'published',
			)
		);

		if ( is_wp_error( $result ) ) {
			return '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		}

		return '<div class="notice notice-success"><p>' . esc_html__( 'Tu comentario fue enviado correctamente.', 'super-mechanic' ) . '</p></div>';
	}

	protected function format_money( $amount, $currency ) {
		return number_format_i18n( (float) $amount, 2 ) . ' ' . sanitize_text_field( (string) $currency );
	}

	protected function render_recent_comments_table( $comments ) {
		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Contenido', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No hay comentarios recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				echo '<tr><td>' . esc_html( $comment['created_at'] ) . '</td><td>' . esc_html( ucwords( str_replace( '_', ' ', $comment['comment_type'] ) ) ) . '</td><td>' . esc_html( $comment['content'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	protected function render_recent_notifications_table( $notifications, $show_actions ) {
		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Titulo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mensaje', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th>';
		if ( $show_actions ) {
			echo '<th>' . esc_html__( 'Accion', 'super-mechanic' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $notifications ) ) {
			echo '<tr><td colspan="' . esc_attr( $show_actions ? 5 : 4 ) . '">' . esc_html__( 'No hay notificaciones.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $notifications as $notification ) {
				echo '<tr>';
				echo '<td>' . esc_html( $notification['created_at'] ) . '</td>';
				echo '<td>' . esc_html( $notification['title'] ) . '</td>';
				echo '<td>' . esc_html( $notification['message'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $notification['is_read'] ) ? __( 'Leida', 'super-mechanic' ) : __( 'Pendiente', 'super-mechanic' ) ) . '</td>';
				if ( $show_actions ) {
					echo '<td>';
					if ( empty( $notification['is_read'] ) ) {
						echo '<form method="post">';
						wp_nonce_field( 'sm_client_notification_mark_read', 'sm_client_notification_nonce' );
						echo '<input type="hidden" name="sm_client_comment_operation" value="mark_read" />';
						echo '<input type="hidden" name="notification_id" value="' . esc_attr( absint( $notification['id'] ) ) . '" />';
						echo '<button type="submit">' . esc_html__( 'Marcar leida', 'super-mechanic' ) . '</button>';
						echo '</form>';
					} else {
						echo esc_html__( 'Sin accion', 'super-mechanic' );
					}
					echo '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	/**
	 * Build a protected client download URL for an attachment.
	 *
	 * @param array<string, mixed> $attachment Attachment data.
	 * @return string
	 */
	protected function get_attachment_download_url( array $attachment ) {
		if ( empty( $attachment['id'] ) ) {
			return '';
		}

		if ( ! $this->attachment_service->is_client_downloadable_attachment( $attachment ) ) {
			return '';
		}

		return $this->download_service->get_download_url( 'attachment', absint( $attachment['id'] ) );
	}
}
