<?php
/**
 * Client portal service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Portal;

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Attachments\Process_Timeline_Service;
use Super_Mechanic\Dashboard\Client_Process_View_Service;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable read/orchestration service for the enhanced client portal.
 */
class Client_Portal_Service {
	/**
	 * Dashboard service dependency.
	 *
	 * @var Dashboard_Service
	 */
	protected $dashboard_service;

	/**
	 * Process view helper.
	 *
	 * @var Client_Process_View_Service
	 */
	protected $client_process_view_service;

	/**
	 * Attachment service dependency.
	 *
	 * @var Attachment_Service
	 */
	protected $attachment_service;

	/**
	 * Timeline service dependency.
	 *
	 * @var Process_Timeline_Service
	 */
	protected $process_timeline_service;

	/**
	 * Download service dependency.
	 *
	 * @var Download_Service
	 */
	protected $download_service;
	/**
	 * Quote service dependency.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Invoice service dependency.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null           $dashboard_service Dashboard service.
	 * @param Client_Process_View_Service|null $client_process_view_service Process view service.
	 * @param Attachment_Service|null          $attachment_service Attachment service.
	 * @param Process_Timeline_Service|null    $process_timeline_service Timeline service.
	 * @param Download_Service|null            $download_service Download service.
	 */
	public function __construct( Dashboard_Service $dashboard_service = null, Client_Process_View_Service $client_process_view_service = null, Attachment_Service $attachment_service = null, Process_Timeline_Service $process_timeline_service = null, Download_Service $download_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null ) {
		$this->dashboard_service           = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->client_process_view_service = $client_process_view_service ? $client_process_view_service : new Client_Process_View_Service();
		$this->attachment_service          = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->process_timeline_service    = $process_timeline_service ? $process_timeline_service : new Process_Timeline_Service();
		$this->download_service            = $download_service ? $download_service : new Download_Service();
		$this->quote_service               = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service             = $invoice_service ? $invoice_service : new Invoice_Service();
	}

	/**
	 * Get portal process list with status metadata.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_processes( $user_id, $limit = 20 ) {
		$rows = $this->dashboard_service->get_client_processes(
			absint( $user_id ),
			array(
				'per_page' => max( 1, absint( $limit ) ),
			)
		);

		$enriched = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status_key   = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$status_label = $this->get_status_label( $status_key );
			$badge_class  = $this->get_status_badge_class( $status_key );

			$row['status_key']   = $status_key;
			$row['status_label'] = $status_label;
			$row['badge_class']  = $badge_class;
			$enriched[]          = $row;
		}

		return $enriched;
	}

	/**
	 * Get recent client-facing activity.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_history( $user_id, $limit = 12 ) {
		return $this->dashboard_service->get_client_recent_activity( absint( $user_id ), max( 1, absint( $limit ) ) );
	}

	/**
	 * Get process documents that are visible for the client.
	 *
	 * @param int $user_id User ID.
	 * @param int $process_id Process ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_process_documents( $user_id, $process_id ) {
		$user_id    = absint( $user_id );
		$process_id = absint( $process_id );

		if ( $user_id <= 0 || $process_id <= 0 ) {
			return array();
		}

		if ( ! $this->dashboard_service->user_can_access_client_process( $user_id, $process_id ) ) {
			return array();
		}

		$attachments = $this->attachment_service->get_client_visible_process_attachments( $process_id );
		$documents   = array();

		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$download_url = '';
			if ( $this->attachment_service->is_client_downloadable_attachment( $attachment ) ) {
				$download_url = $this->download_service->get_download_url( 'attachment', absint( isset( $attachment['id'] ) ? $attachment['id'] : 0 ) );
			}

			$documents[] = array(
				'id'            => absint( isset( $attachment['id'] ) ? $attachment['id'] : 0 ),
				'title'         => sanitize_text_field( isset( $attachment['title'] ) ? (string) $attachment['title'] : '' ),
				'attachment_type' => sanitize_text_field( isset( $attachment['attachment_type'] ) ? (string) $attachment['attachment_type'] : '' ),
				'created_at'    => sanitize_text_field( isset( $attachment['created_at'] ) ? (string) $attachment['created_at'] : '' ),
				'download_url'  => esc_url_raw( $download_url ),
			);
		}

		return $documents;
	}

	/**
	 * Get process timeline events visible to the client.
	 *
	 * @param int $user_id User ID.
	 * @param int $process_id Process ID.
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_process_timeline( $user_id, $process_id, $limit = 10 ) {
		$user_id    = absint( $user_id );
		$process_id = absint( $process_id );
		$limit      = max( 1, absint( $limit ) );

		if ( $user_id <= 0 || $process_id <= 0 ) {
			return array();
		}

		if ( ! $this->dashboard_service->user_can_access_client_process( $user_id, $process_id ) ) {
			return array();
		}

		$timeline = $this->process_timeline_service->get_process_timeline( $process_id, true );
		if ( empty( $timeline ) ) {
			return array();
		}

		return array_slice( is_array( $timeline ) ? $timeline : array(), 0, $limit );
	}

	/**
	 * Get portal process KPIs.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,int>
	 */
	public function get_process_kpis( $user_id ) {
		$processes = $this->get_processes( $user_id, 100 );
		$kpis      = array(
			'total'      => 0,
			'active'     => 0,
			'completed'  => 0,
			'pending'    => 0,
		);

		foreach ( $processes as $process ) {
			$status = isset( $process['status_key'] ) ? sanitize_key( (string) $process['status_key'] ) : '';
			++$kpis['total'];

			if ( 'completed' === $status || 'delivered' === $status ) {
				++$kpis['completed'];
				continue;
			}

			if ( 'pending' === $status || 'waiting_approval' === $status ) {
				++$kpis['pending'];
				continue;
			}

			++$kpis['active'];
		}

		return $kpis;
	}

	/**
	 * Build a compact client quick summary payload.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,int>
	 */
	public function get_client_quick_summary( $user_id ) {
		$user_id   = absint( $user_id );
		$vehicles  = $this->dashboard_service->get_client_vehicles( $user_id );
		$processes = $this->get_processes( $user_id, 100 );
		$kpis      = $this->get_process_kpis( $user_id );
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );

		$summary = array(
			'vehicles_total'   => is_array( $vehicles ) ? count( $vehicles ) : 0,
			'active_processes' => isset( $kpis['active'] ) ? absint( $kpis['active'] ) : 0,
			'total_processes'  => isset( $kpis['total'] ) ? absint( $kpis['total'] ) : 0,
			'quotes_total'     => 0,
			'open_quotes'      => 0,
			'invoices_total'   => 0,
			'open_invoices'    => 0,
		);

		if ( $client_id <= 0 ) {
			return $summary;
		}

		$quotes = $this->quote_service->get_quotes_for_user(
			$user_id,
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);
		$summary['quotes_total'] = is_array( $quotes ) ? count( $quotes ) : 0;

		if ( is_array( $quotes ) ) {
			foreach ( $quotes as $quote ) {
				$status = isset( $quote['status'] ) ? sanitize_key( (string) $quote['status'] ) : '';
				if ( in_array( $status, array( 'draft', 'sent', 'pending' ), true ) ) {
					++$summary['open_quotes'];
				}
			}
		}

		$invoices = $this->invoice_service->get_invoices_for_user(
			$user_id,
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);
		$summary['invoices_total'] = is_array( $invoices ) ? count( $invoices ) : 0;

		if ( is_array( $invoices ) ) {
			foreach ( $invoices as $invoice ) {
				$balance_due = isset( $invoice['balance_due'] ) ? (float) $invoice['balance_due'] : 0.0;
				$status      = isset( $invoice['status'] ) ? sanitize_key( (string) $invoice['status'] ) : '';
				if ( $balance_due > 0.0 && ! in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
					++$summary['open_invoices'];
				}
			}
		}

		return $summary;
	}

	/**
	 * Build a compact process summary payload for the portal.
	 *
	 * @param int                              $user_id User ID.
	 * @param int                              $selected_process_id Optional selected process ID.
	 * @param array<int,array<string,mixed>>   $processes Preloaded processes.
	 * @param array<int,array<string,mixed>>   $history Preloaded history.
	 * @return array<string,mixed>
	 */
	public function get_process_summary_card( $user_id, $selected_process_id = 0, array $processes = array(), array $history = array() ) {
		$user_id            = absint( $user_id );
		$selected_process_id = absint( $selected_process_id );

		if ( empty( $processes ) ) {
			$processes = $this->get_processes( $user_id, 20 );
		}

		if ( empty( $processes ) ) {
			return array();
		}

		$selected_process = array();
		foreach ( $processes as $process ) {
			$process_id = absint( isset( $process['id'] ) ? $process['id'] : 0 );
			if ( $selected_process_id > 0 && $process_id === $selected_process_id ) {
				$selected_process = $process;
				break;
			}
		}

		if ( empty( $selected_process ) ) {
			$selected_process = $processes[0];
		}

		$process_id     = absint( isset( $selected_process['id'] ) ? $selected_process['id'] : 0 );
		$status_key     = isset( $selected_process['status_key'] ) ? sanitize_key( (string) $selected_process['status_key'] ) : sanitize_key( (string) ( isset( $selected_process['status'] ) ? $selected_process['status'] : '' ) );
		$derived_status = isset( $selected_process['derived_status'] ) ? sanitize_key( (string) $selected_process['derived_status'] ) : '';
		$priority       = $this->resolve_priority_payload( $status_key, $derived_status );

		if ( empty( $history ) ) {
			$history = $this->get_recent_history( $user_id, 12 );
		}
		$last_activity = $this->resolve_last_activity( $history, $process_id );

		return array(
			'process_id'        => $process_id,
			'title'             => sanitize_text_field( isset( $selected_process['title'] ) ? (string) $selected_process['title'] : '' ),
			'status_label'      => sanitize_text_field( isset( $selected_process['status_label'] ) ? (string) $selected_process['status_label'] : $this->get_status_label( $status_key ) ),
			'status_badge_class'=> sanitize_text_field( isset( $selected_process['badge_class'] ) ? (string) $selected_process['badge_class'] : $this->get_status_badge_class( $status_key ) ),
			'priority_key'      => $priority['key'],
			'priority_label'    => $priority['label'],
			'priority_badge'    => $priority['badge_class'],
			'last_change'       => sanitize_text_field( isset( $selected_process['updated_at'] ) && '' !== (string) $selected_process['updated_at'] ? (string) $selected_process['updated_at'] : (string) ( isset( $selected_process['created_at'] ) ? $selected_process['created_at'] : '' ) ),
			'last_activity'     => $last_activity,
			'cta_url'           => esc_url_raw( add_query_arg( 'process_id', $process_id ) ),
		);
	}

	/**
	 * Get normalized status label.
	 *
	 * @param string $status_key Status key.
	 * @return string
	 */
	public function get_status_label( $status_key ) {
		$status_key = sanitize_key( (string) $status_key );
		$map        = array(
			'pending'         => __( 'Pending', 'super-mechanic' ),
			'in_progress'     => __( 'In progress', 'super-mechanic' ),
			'waiting_approval'=> __( 'Waiting approval', 'super-mechanic' ),
			'completed'       => __( 'Completed', 'super-mechanic' ),
			'delivered'       => __( 'Delivered', 'super-mechanic' ),
			'cancelled'       => __( 'Cancelled', 'super-mechanic' ),
		);

		if ( isset( $map[ $status_key ] ) ) {
			return (string) $map[ $status_key ];
		}

		if ( '' === $status_key ) {
			return __( 'Unknown', 'super-mechanic' );
		}

		return ucwords( str_replace( '_', ' ', $status_key ) );
	}

	/**
	 * Resolve status badge class.
	 *
	 * @param string $status_key Status key.
	 * @return string
	 */
	public function get_status_badge_class( $status_key ) {
		$status_key = sanitize_key( (string) $status_key );

		if ( in_array( $status_key, array( 'completed', 'delivered' ), true ) ) {
			return 'sm-portal-badge sm-portal-badge-success';
		}

		if ( in_array( $status_key, array( 'pending', 'waiting_approval' ), true ) ) {
			return 'sm-portal-badge sm-portal-badge-warning';
		}

		if ( 'cancelled' === $status_key ) {
			return 'sm-portal-badge sm-portal-badge-muted';
		}

		return 'sm-portal-badge sm-portal-badge-info';
	}

	/**
	 * Resolve compact priority payload using existing status metadata.
	 *
	 * @param string $status_key Status key.
	 * @param string $derived_status Derived status key.
	 * @return array<string,string>
	 */
	protected function resolve_priority_payload( $status_key, $derived_status ) {
		$status_key     = sanitize_key( (string) $status_key );
		$derived_status = sanitize_key( (string) $derived_status );

		if ( in_array( $derived_status, array( 'waiting_payment', 'waiting_approval' ), true ) || in_array( $status_key, array( 'waiting_approval', 'pending' ), true ) ) {
			return array(
				'key'        => 'critical',
				'label'      => __( 'Critical', 'super-mechanic' ),
				'badge_class'=> 'sm-badge sm-badge-danger',
			);
		}

		if ( in_array( $status_key, array( 'in_progress', 'ready_for_delivery' ), true ) ) {
			return array(
				'key'        => 'warning',
				'label'      => __( 'Attention', 'super-mechanic' ),
				'badge_class'=> 'sm-badge sm-badge-warning',
			);
		}

		return array(
			'key'        => 'normal',
			'label'      => __( 'Stable', 'super-mechanic' ),
			'badge_class'=> 'sm-badge sm-badge-success',
		);
	}

	/**
	 * Resolve latest activity payload.
	 *
	 * @param array<int,array<string,mixed>> $history History rows.
	 * @param int                             $process_id Process ID.
	 * @return string
	 */
	protected function resolve_last_activity( array $history, $process_id ) {
		$process_id = absint( $process_id );
		if ( empty( $history ) || $process_id <= 0 ) {
			return '';
		}

		foreach ( $history as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$event_process_id = isset( $event['process_id'] ) ? absint( $event['process_id'] ) : 0;
			if ( $event_process_id > 0 && $event_process_id !== $process_id ) {
				continue;
			}

			$message = isset( $event['message'] ) ? sanitize_text_field( (string) $event['message'] ) : '';
			$date    = isset( $event['created_at'] ) ? sanitize_text_field( (string) $event['created_at'] ) : '';
			if ( '' === $message && '' === $date ) {
				continue;
			}

			return trim( $date . ( '' !== $message ? ' · ' . $message : '' ) );
		}

		return '';
	}
}
