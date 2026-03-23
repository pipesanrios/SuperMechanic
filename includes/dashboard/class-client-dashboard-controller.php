<?php
/**
 * Client dashboard controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

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

	public function __construct( Dashboard_Service $service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Attachment_Service $attachment_service = null, Process_Timeline_Service $process_timeline_service = null, Comment_Service $comment_service = null, Notification_Service $notification_service = null, Download_Service $download_service = null ) {
		$this->service                  = $service ? $service : new Dashboard_Service();
		$this->quote_service            = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service          = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->attachment_service       = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->process_timeline_service = $process_timeline_service ? $process_timeline_service : new Process_Timeline_Service();
		$this->comment_service          = $comment_service ? $comment_service : new Comment_Service();
		$this->notification_service     = $notification_service ? $notification_service : new Notification_Service();
		$this->download_service         = $download_service ? $download_service : new Download_Service();
	}

	public function render_dashboard( $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$profile = $this->service->get_client_profile_data( $user_id );

		if ( empty( $profile ) ) {
			return '<div class="sm-client-dashboard"><p>' . esc_html__( 'No hay un cliente vinculado a este usuario.', 'super-mechanic' ) . '</p></div>';
		}

		$activity      = $this->service->get_client_recent_activity( $user_id, 10 );
		$notifications = $this->get_client_notifications_data( $user_id, 5 );
		$comments      = $this->get_recent_client_comments( $user_id, 5 );
		$name          = trim( $profile['first_name'] . ' ' . $profile['last_name'] );

		ob_start();
		echo '<div class="sm-client-dashboard">';
		echo '<h2>' . esc_html__( 'Mi panel', 'super-mechanic' ) . '</h2>';
		echo '<p><strong>' . esc_html( $name ) . '</strong></p>';
		if ( ! empty( $profile['email'] ) ) {
			echo '<p>' . esc_html( $profile['email'] ) . '</p>';
		}
		echo '<h3>' . esc_html__( 'Vehiculos', 'super-mechanic' ) . '</h3>';
		echo $this->render_vehicles( $user_id );
		echo '<h3>' . esc_html__( 'Procesos recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_processes( $user_id );
		echo '<h3>' . esc_html__( 'Cotizaciones recientes', 'super-mechanic' ) . '</h3>';
		echo $this->render_quotes( $user_id );
		echo '<h3>' . esc_html__( 'Facturas recientes', 'super-mechanic' ) . '</h3>';
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
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Titulo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actualizado', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $processes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay procesos vinculados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $processes as $process ) {
				$vehicle = trim( $process['vehicle_make'] . ' ' . $process['vehicle_model'] );
				$status  = ucwords( str_replace( '_', ' ', $process['status'] ) );
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
				echo '<td>' . esc_html( $vehicle ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['updated_at'] ) ? $process['updated_at'] : $process['created_at'] ) . '</td>';
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
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Numero', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $quotes ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay cotizaciones registradas.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $quotes as $quote ) {
				echo '<tr>';
				echo '<td>' . esc_html( $quote['quote_number'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $quote['process_title'] ) ? $quote['process_title'] : __( 'Proceso', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $quote['status'] ) ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $quote['grand_total'], 2 ) ) . ' ' . esc_html( $quote['currency'] ) . '</td>';
				echo '<td>' . esc_html( $quote['created_at'] ) . '</td>';
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
				echo '<td><a href="' . esc_url( add_query_arg( 'invoice_id', absint( $invoice['id'] ) ) ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

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

	protected function get_recent_client_comments( $user_id, $limit = 5 ) {
		$comments  = array();
		$processes = $this->service->get_client_processes( $user_id, array( 'per_page' => 20 ) );

		foreach ( $processes as $process ) {
			foreach ( $this->comment_service->get_client_visible_process_comments( absint( $process['id'] ) ) as $comment ) {
				$comments[] = $comment;
			}
		}

		usort(
			$comments,
			static function ( $left, $right ) {
				return strtotime( (string) $right['created_at'] ) <=> strtotime( (string) $left['created_at'] );
			}
		);

		return array_slice( $comments, 0, max( 1, absint( $limit ) ) );
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
