<?php
/**
 * Quote service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Commercial\Commercial_Hooks_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Integrations\WooCommerce\Woo_Product_Service;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Relations\Client_Vehicle_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles quote business rules.
 */
class Quote_Service {
	protected $repository;
	protected $item_repository;
	protected $process_service;
	protected $maintenance_service;
	protected $client_vehicle_repository;
	protected $event_dispatcher;
	protected $transaction_repository;
	protected $access_control_service;
	protected $settings_service;
	protected $business_context_service;
	protected $woo_product_service;
	protected $commercial_hooks_service;

	public function __construct( Quote_Repository $repository = null, Quote_Item_Repository $item_repository = null, Process_Service $process_service = null, Maintenance_Service $maintenance_service = null, Client_Vehicle_Repository $client_vehicle_repository = null, Event_Dispatcher $event_dispatcher = null, Quote_Transaction_Repository $transaction_repository = null, Access_Control_Service $access_control_service = null, Settings_Service $settings_service = null, Business_Context_Service $business_context_service = null, Woo_Product_Service $woo_product_service = null, Commercial_Hooks_Service $commercial_hooks_service = null ) {
		$this->repository                = $repository ? $repository : new Quote_Repository();
		$this->item_repository           = $item_repository ? $item_repository : new Quote_Item_Repository();
		$this->process_service           = $process_service ? $process_service : new Process_Service();
		$this->maintenance_service       = $maintenance_service;
		$this->client_vehicle_repository = $client_vehicle_repository ? $client_vehicle_repository : new Client_Vehicle_Repository();
		$this->event_dispatcher          = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->transaction_repository    = $transaction_repository ? $transaction_repository : new Quote_Transaction_Repository();
		$this->access_control_service    = $access_control_service ? $access_control_service : new Access_Control_Service( null, $this->client_vehicle_repository, null, $this->repository );
		$this->settings_service          = $settings_service ? $settings_service : new Settings_Service();
		$this->business_context_service  = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->woo_product_service       = $woo_product_service ? $woo_product_service : new Woo_Product_Service();
		$this->commercial_hooks_service  = $commercial_hooks_service ? $commercial_hooks_service : new Commercial_Hooks_Service();
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

	public function create_quote( array $data ) {
		$data  = $this->prepare_quote_data( $data, false );
		$valid = $this->validate_quote_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );
		if ( false === $inserted ) {
			return new WP_Error( 'sm_quote_create_failed', __( 'No fue posible crear la cotizacion.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $inserted );
		$this->commercial_hooks_service->dispatch(
			'sm_quote_created',
			array(
				'business_id' => isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0,
				'entity_id'   => absint( $inserted ),
				'entity_type' => 'quote',
				'data'        => array(
					'quote_id'     => absint( $inserted ),
					'process_id'   => isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0,
					'client_id'    => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
					'status'       => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'draft',
					'triggered_by' => get_current_user_id(),
				),
			)
		);

		return $inserted;
	}

	public function update_quote( $quote_id, array $data ) {
		$quote_id = absint( $quote_id );
		$quote    = $this->repository->get_by_id( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_quote_data( array_merge( $quote, $data ), true );
		$valid = $this->validate_quote_data( $data, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update( $quote_id, $data ) ) {
			return new WP_Error( 'sm_quote_update_failed', __( 'No fue posible actualizar la cotizacion.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $quote_id );

		return true;
	}

	public function delete_quote( $quote_id ) {
		$quote_id = absint( $quote_id );
		if ( ! $this->repository->get_by_id( $quote_id ) ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		$this->item_repository->delete_by_quote_id( $quote_id );
		if ( ! $this->repository->delete( $quote_id ) ) {
			return new WP_Error( 'sm_quote_delete_failed', __( 'No fue posible eliminar la cotizacion.', 'super-mechanic' ) );
		}

		return true;
	}

	public function get_quote( $quote_id ) {
		return $this->repository->get_by_id( $quote_id );
	}

	public function get_quotes( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	public function get_quotes_for_user( $user_id, array $args = array() ) {
		$user_id = absint( $user_id );

		if ( $this->access_control_service->user_has_full_access( $user_id ) ) {
			return $this->get_quotes( $args );
		}

		$quotes = $this->get_quotes( $args );

		return array_values(
			array_filter(
				$quotes,
				function ( $quote ) use ( $user_id ) {
					return ! empty( $quote['id'] ) && $this->access_control_service->user_can_access_quote( $user_id, absint( $quote['id'] ) );
				}
			)
		);
	}

	public function get_approved_quotes_for_process( $process_id ) {
		return $this->repository->get_all(
			array(
				'process_id' => absint( $process_id ),
				'status'     => 'approved',
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
	}

	public function get_convertible_quote( $quote_id, $process_id = 0 ) {
		$quote_id   = absint( $quote_id );
		$process_id = absint( $process_id );
		$quote      = $this->get_quote( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		if ( $process_id && absint( $quote['process_id'] ) !== $process_id ) {
			return new WP_Error( 'sm_quote_process_mismatch', __( 'La cotizacion no pertenece al proceso actual.', 'super-mechanic' ) );
		}

		if ( 'approved' !== $quote['status'] ) {
			return new WP_Error( 'sm_quote_not_approved', __( 'Solo se puede facturar una cotizacion aprobada.', 'super-mechanic' ) );
		}

		return $quote;
	}

	public function count_quotes( array $args = array() ) {
		return $this->repository->count_all( $args );
	}

	public function get_quote_items( $quote_id ) {
		return $this->item_repository->get_by_quote_id( $quote_id );
	}

	public function add_quote_item( $quote_id, array $data ) {
		$quote_id = absint( $quote_id );
		$quote    = $this->repository->get_by_id( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		$raw_data            = $data;
		$data['quote_id']    = $quote_id;
		$data['business_id'] = ! empty( $quote['business_id'] ) ? absint( $quote['business_id'] ) : $this->resolve_business_id();
		$data                = $this->prepare_item_data( $data );
		$valid               = $this->validate_quote_item_data(
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
			return new WP_Error( 'sm_quote_item_create_failed', __( 'No fue posible agregar el item de la cotizacion.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( $quote_id );

		return $inserted;
	}

	public function update_quote_item( $item_id, array $data ) {
		$item_id = absint( $item_id );
		$item    = $this->item_repository->get_by_id( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'sm_quote_item_not_found', __( 'El item de la cotizacion no existe.', 'super-mechanic' ) );
		}

		$raw_data = $data;
		$merged   = array_merge( $item, $data );
		$merged   = $this->apply_legacy_woo_sanitization( $merged, $raw_data, $item );
		if ( ! empty( $item['quote_id'] ) ) {
			$parent_quote = $this->repository->get_by_id( absint( $item['quote_id'] ) );
			if ( is_array( $parent_quote ) && ! empty( $parent_quote['business_id'] ) ) {
				$merged['business_id'] = absint( $parent_quote['business_id'] );
			}
		}

		$data  = $this->prepare_item_data( $merged );
		$valid = $this->validate_quote_item_data(
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
			return new WP_Error( 'sm_quote_item_update_failed', __( 'No fue posible actualizar el item de la cotizacion.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( absint( $item['quote_id'] ) );

		return true;
	}

	public function delete_quote_item( $item_id ) {
		$item_id = absint( $item_id );
		$item    = $this->item_repository->get_by_id( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'sm_quote_item_not_found', __( 'El item de la cotizacion no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->item_repository->delete( $item_id ) ) {
			return new WP_Error( 'sm_quote_item_delete_failed', __( 'No fue posible eliminar el item de la cotizacion.', 'super-mechanic' ) );
		}

		$this->recalculate_totals( absint( $item['quote_id'] ) );

		return true;
	}

	public function recalculate_totals( $quote_id ) {
		$quote = $this->repository->get_by_id( $quote_id );
		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		$subtotal = 0.0;
		foreach ( $this->item_repository->get_by_quote_id( $quote_id ) as $item ) {
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

		$tax_total      = (float) $quote['tax_total'];
		$discount_total = (float) $quote['discount_total'];
		$grand_total    = round( $subtotal + $tax_total - $discount_total, 2 );

		$this->repository->update(
			$quote_id,
			array(
				'subtotal'       => round( $subtotal, 2 ),
				'tax_total'      => round( $tax_total, 2 ),
				'discount_total' => round( $discount_total, 2 ),
				'grand_total'    => $grand_total,
			)
		);

		return true;
	}

	public function generate_quote_number() {
		do {
			$quote_number = sprintf( 'SMQ-%s-%04d', gmdate( 'YmdHis' ), wp_rand( 1000, 9999 ) );
		} while ( $this->repository->get_by_quote_number( $quote_number ) );

		return $quote_number;
	}

	public function create_quote_from_maintenance( $process_id, $args = array() ) {
		$process = $this->process_service->get_process( $process_id );
		if ( ! $process ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}
		if ( 'maintenance' !== $process['process_type'] ) {
			return new WP_Error( 'sm_invalid_quote_process_type', __( 'Solo se puede generar una cotizacion automatica desde procesos de mantenimiento.', 'super-mechanic' ) );
		}

		$source = $this->get_maintenance_service()->get_quote_source_data( $process_id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		return $this->transaction_repository->run_in_transaction(
			function () use ( $process_id, $process, $source, $args ) {
				$quote_id = $this->create_quote(
					array(
						'process_id' => $process_id,
						'client_id'  => ! empty( $args['client_id'] ) ? absint( $args['client_id'] ) : absint( $process['client_id'] ),
						'notes'      => ! empty( $source['maintenance']['diagnosis'] ) ? $source['maintenance']['diagnosis'] : '',
						'status'     => 'draft',
					)
				);

				if ( is_wp_error( $quote_id ) ) {
					return $quote_id;
				}

				foreach ( $source['parts'] as $index => $part ) {
					$result = $this->add_quote_item(
						$quote_id,
						array(
							'item_type'    => 'part',
							'reference_id' => absint( $part['id'] ),
							'label'        => $part['part_name'],
							'description'  => isset( $part['notes'] ) ? $part['notes'] : '',
							'quantity'     => $part['quantity'],
							'unit_price'   => $part['unit_price'],
							'sort_order'   => $index + 1,
						)
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				$offset = count( $source['parts'] );
				foreach ( $source['labor'] as $index => $labor ) {
					$result = $this->add_quote_item(
						$quote_id,
						array(
							'item_type'    => 'labor',
							'reference_id' => absint( $labor['id'] ),
							'label'        => $labor['description'],
							'description'  => '',
							'quantity'     => $labor['hours'],
							'unit_price'   => $labor['hour_rate'],
							'sort_order'   => $offset + $index + 1,
						)
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				$result = $this->recalculate_totals( $quote_id );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$this->event_dispatcher->dispatch(
					'quote_created_from_maintenance',
					array(
						'quote_id'      => absint( $quote_id ),
						'process_id'    => absint( $process_id ),
						'client_id'     => absint( $process['client_id'] ),
						'triggered_by'  => get_current_user_id(),
					)
				);

				return $quote_id;
			}
		);
	}

	public function send_quote( $quote_id ) {
		$quote = $this->repository->get_by_id( $quote_id );
		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}
		if ( 'draft' !== $quote['status'] ) {
			return new WP_Error( 'sm_invalid_quote_status', __( 'Solo se pueden enviar cotizaciones en borrador.', 'super-mechanic' ) );
		}

		$result = $this->repository->update( $quote_id, array( 'status' => 'sent' ) );

		if ( $result ) {
			$this->event_dispatcher->dispatch(
				'quote_sent',
				array(
					'quote_id'      => absint( $quote_id ),
					'process_id'    => absint( $quote['process_id'] ),
					'client_id'     => absint( $quote['client_id'] ),
					'triggered_by'  => get_current_user_id(),
				)
			);
		}

		return $result;
	}

	public function approve_quote( $quote_id, $user_id ) {
		$quote = $this->repository->get_by_id( $quote_id );
		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}
		if ( 'sent' !== $quote['status'] ) {
			return new WP_Error( 'sm_invalid_quote_status', __( 'Solo se pueden aprobar cotizaciones enviadas.', 'super-mechanic' ) );
		}
		if ( ! $this->user_can_access_quote( $user_id, $quote_id ) ) {
			return new WP_Error( 'sm_quote_access_denied', __( 'No tienes acceso a esta cotizacion.', 'super-mechanic' ) );
		}

		$result = $this->repository->update(
			$quote_id,
			array(
				'status'             => 'approved',
				'approved_by_client' => 1,
				'approved_at'        => current_time( 'mysql' ),
				'rejected_at'        => null,
				'rejection_reason'   => '',
			)
		);

		if ( $result ) {
			$this->event_dispatcher->dispatch(
				'quote_approved',
				array(
					'quote_id'      => absint( $quote_id ),
					'process_id'    => absint( $quote['process_id'] ),
					'client_id'     => absint( $quote['client_id'] ),
					'triggered_by'  => absint( $user_id ),
				)
			);
			$this->commercial_hooks_service->dispatch(
				'sm_quote_approved',
				array(
					'business_id' => isset( $quote['business_id'] ) ? absint( $quote['business_id'] ) : 0,
					'entity_id'   => absint( $quote_id ),
					'entity_type' => 'quote',
					'data'        => array(
						'quote_id'     => absint( $quote_id ),
						'process_id'   => absint( $quote['process_id'] ),
						'client_id'    => absint( $quote['client_id'] ),
						'triggered_by' => absint( $user_id ),
					),
				)
			);
		}

		return $result;
	}

	public function reject_quote( $quote_id, $user_id, $reason = '' ) {
		$quote = $this->repository->get_by_id( $quote_id );
		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}
		if ( 'sent' !== $quote['status'] ) {
			return new WP_Error( 'sm_invalid_quote_status', __( 'Solo se pueden rechazar cotizaciones enviadas.', 'super-mechanic' ) );
		}
		if ( ! $this->user_can_access_quote( $user_id, $quote_id ) ) {
			return new WP_Error( 'sm_quote_access_denied', __( 'No tienes acceso a esta cotizacion.', 'super-mechanic' ) );
		}

		$reason = sanitize_textarea_field( $reason );
		$result = $this->repository->update(
			$quote_id,
			array(
				'status'             => 'rejected',
				'approved_by_client' => 0,
				'approved_at'        => null,
				'rejected_at'        => current_time( 'mysql' ),
				'rejection_reason'   => $reason,
			)
		);

		if ( $result ) {
			$this->event_dispatcher->dispatch(
				'quote_rejected',
				array(
					'quote_id'      => absint( $quote_id ),
					'process_id'    => absint( $quote['process_id'] ),
					'client_id'     => absint( $quote['client_id'] ),
					'reason'        => $reason,
					'triggered_by'  => absint( $user_id ),
				)
			);
		}

		return $result;
	}

	public function cancel_quote( $quote_id ) {
		$quote = $this->repository->get_by_id( $quote_id );
		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}
		if ( ! in_array( $quote['status'], array( 'draft', 'sent' ), true ) ) {
			return new WP_Error( 'sm_invalid_quote_status', __( 'Solo se pueden cancelar cotizaciones en borrador o enviadas.', 'super-mechanic' ) );
		}

		$result = $this->repository->update( $quote_id, array( 'status' => 'cancelled' ) );

		if ( $result ) {
			$this->event_dispatcher->dispatch(
				'quote_cancelled',
				array(
					'quote_id'      => absint( $quote_id ),
					'process_id'    => absint( $quote['process_id'] ),
					'client_id'     => absint( $quote['client_id'] ),
					'triggered_by'  => get_current_user_id(),
				)
			);
		}

		return $result;
	}

	public function user_can_access_quote( $user_id, $quote_id ) {
		return $this->access_control_service->user_can_access_quote( $user_id, $quote_id );
	}

	/**
	 * Build printable quote context.
	 *
	 * @param int $quote_id Quote ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_quote_print_context( $quote_id ) {
		$quote_id = absint( $quote_id );
		$quote    = $this->repository->get_by_id( $quote_id );

		if ( ! $quote ) {
			return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
		}

		return array(
			'quote'       => $quote,
			'items'       => $this->get_quote_items( $quote_id ),
			'company'     => sanitize_text_field( $this->settings_service->get_setting( 'business', 'business_name', __( 'Super Mechanic', 'super-mechanic' ) ) ),
			'client_name' => ! empty( $quote['client_name'] ) ? $quote['client_name'] : __( 'Cliente no asignado', 'super-mechanic' ),
		);
	}

	/**
	 * Render printable quote HTML from context.
	 *
	 * @param array<string, mixed> $context Quote printable context.
	 * @return string
	 */
	public function render_quote_printable_html( array $context ) {
		$quote   = $context['quote'];
		$items   = ! empty( $context['items'] ) && is_array( $context['items'] ) ? $context['items'] : array();
		$company = isset( $context['company'] ) ? $context['company'] : __( 'Super Mechanic', 'super-mechanic' );
		$client  = isset( $context['client_name'] ) ? $context['client_name'] : __( 'Cliente no asignado', 'super-mechanic' );

		ob_start();
		echo '<style>';
		echo 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#1f2937;font-size:11px;line-height:1.45;}';
		echo '.sm-quote-print{padding:8px 4px;}';
		echo '.sm-pdf-header{border-bottom:3px solid #0f4fa8;margin-bottom:18px;padding-bottom:12px;}';
		echo '.sm-pdf-brand{font-size:22px;font-weight:700;color:#0f4fa8;margin:0 0 4px;}';
		echo '.sm-pdf-title{font-size:15px;font-weight:700;color:#111827;margin:0;text-transform:uppercase;}';
		echo '.sm-pdf-meta{width:100%;border-collapse:collapse;margin:12px 0 18px;}';
		echo '.sm-pdf-meta td{border:1px solid #e5e7eb;padding:7px 9px;vertical-align:top;}';
		echo '.sm-pdf-label{display:block;color:#6b7280;font-size:9px;text-transform:uppercase;font-weight:700;}';
		echo '.sm-pdf-table{width:100%;border-collapse:collapse;margin-top:8px;}';
		echo '.sm-pdf-table th{background:#f3f4f6;color:#374151;border:1px solid #d1d5db;padding:8px 7px;font-size:9px;text-transform:uppercase;font-weight:700;}';
		echo '.sm-pdf-table td{border:1px solid #e5e7eb;padding:8px 7px;vertical-align:top;}';
		echo '.sm-pdf-num{text-align:right;white-space:nowrap;}';
		echo '.sm-pdf-summary{width:42%;margin:16px 0 0 auto;border-collapse:collapse;}';
		echo '.sm-pdf-summary td{border-bottom:1px solid #e5e7eb;padding:6px 4px;}';
		echo '.sm-pdf-total td{font-size:13px;font-weight:700;color:#0f4fa8;border-top:2px solid #0f4fa8;}';
		echo '.sm-pdf-notes{margin-top:18px;padding-top:10px;border-top:1px solid #e5e7eb;color:#374151;}';
		echo '.sm-pdf-footer{margin-top:18px;color:#6b7280;font-size:9px;border-top:1px solid #e5e7eb;padding-top:8px;}';
		echo '</style>';
		echo '<div class="sm-quote-print">';
		echo '<div class="sm-pdf-header">';
		echo '<h1 class="sm-pdf-brand">' . esc_html( $company ) . '</h1>';
		echo '<p class="sm-pdf-title">' . esc_html( sprintf( __( 'Cotizacion %s', 'super-mechanic' ), $quote['quote_number'] ) ) . '</p>';
		echo '</div>';
		echo '<table class="sm-pdf-meta"><tr>';
		echo '<td><span class="sm-pdf-label">' . esc_html__( 'Cliente', 'super-mechanic' ) . '</span>' . esc_html( $client ) . '</td>';
		echo '<td><span class="sm-pdf-label">' . esc_html__( 'Proceso', 'super-mechanic' ) . '</span>' . esc_html( ! empty( $quote['process_title'] ) ? $quote['process_title'] : '#' . $quote['process_id'] ) . '</td>';
		echo '</tr><tr>';
		echo '<td><span class="sm-pdf-label">' . esc_html__( 'Estado', 'super-mechanic' ) . '</span>' . esc_html( ucwords( str_replace( '_', ' ', $quote['status'] ) ) ) . '</td>';
		echo '<td><span class="sm-pdf-label">' . esc_html__( 'Fecha', 'super-mechanic' ) . '</span>' . esc_html( ! empty( $quote['created_at'] ) ? $quote['created_at'] : '-' ) . '</td>';
		echo '</tr></table>';
		echo '<table class="sm-pdf-table"><thead><tr><th width="22%">' . esc_html__( 'Item', 'super-mechanic' ) . '</th><th width="34%">' . esc_html__( 'Descripcion', 'super-mechanic' ) . '</th><th width="12%" class="sm-pdf-num">' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th width="16%" class="sm-pdf-num">' . esc_html__( 'Precio', 'super-mechanic' ) . '</th><th width="16%" class="sm-pdf-num">' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay items.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr>';
				echo '<td>' . esc_html( $item['label'] ) . '</td>';
				echo '<td>' . esc_html( $item['description'] ) . '</td>';
				echo '<td class="sm-pdf-num">' . esc_html( $item['quantity'] ) . '</td>';
				echo '<td class="sm-pdf-num">' . esc_html( $this->format_money( $item['unit_price'], $quote['currency'] ) ) . '</td>';
				echo '<td class="sm-pdf-num">' . esc_html( $this->format_money( $item['line_total'], $quote['currency'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '<table class="sm-pdf-summary">';
		echo '<tr><td>' . esc_html__( 'Subtotal', 'super-mechanic' ) . '</td><td class="sm-pdf-num">' . esc_html( $this->format_money( $quote['subtotal'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</td><td class="sm-pdf-num">' . esc_html( $this->format_money( $quote['tax_total'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Descuento', 'super-mechanic' ) . '</td><td class="sm-pdf-num">' . esc_html( $this->format_money( $quote['discount_total'], $quote['currency'] ) ) . '</td></tr>';
		echo '<tr class="sm-pdf-total"><td>' . esc_html__( 'Total', 'super-mechanic' ) . '</td><td class="sm-pdf-num">' . esc_html( $this->format_money( $quote['grand_total'], $quote['currency'] ) ) . '</td></tr>';
		echo '</table>';
		if ( ! empty( $quote['notes'] ) ) {
			echo '<div class="sm-pdf-notes"><strong>' . esc_html__( 'Notas', 'super-mechanic' ) . '</strong><br />' . esc_html( $quote['notes'] ) . '</div>';
		}
		echo '<div class="sm-pdf-footer">' . esc_html__( 'Documento generado por Mekvort / Super Mechanic.', 'super-mechanic' ) . '</div>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Get printable HTML.
	 *
	 * @param int $quote_id Quote ID.
	 * @return string
	 */
	public function get_printable_quote_html( $quote_id ) {
		$context = $this->get_quote_print_context( $quote_id );

		if ( is_wp_error( $context ) ) {
			return '<p>' . esc_html( $context->get_error_message() ) . '</p>';
		}

		return $this->render_quote_printable_html( $context );
	}

	/**
	 * Build a predictable quote PDF file name.
	 *
	 * @param int $quote_id Quote ID.
	 * @return string
	 */
	public function get_quote_pdf_filename( $quote_id ) {
		$quote = $this->repository->get_by_id( $quote_id );

		if ( ! $quote ) {
			return 'quote-' . absint( $quote_id ) . '.pdf';
		}

		$number = ! empty( $quote['quote_number'] ) ? sanitize_file_name( strtolower( $quote['quote_number'] ) ) : 'quote-' . absint( $quote_id );

		return $number . '.pdf';
	}

	public function validate_quote_data( array $data, $is_update = false ) {
		$errors           = new WP_Error();
		$allowed_statuses = array( 'draft', 'sent', 'approved', 'rejected', 'expired', 'cancelled' );

		if ( empty( $data['process_id'] ) || ! $this->process_service->get_process( $data['process_id'] ) ) {
			$errors->add( 'invalid_process', __( 'Debes seleccionar un proceso valido para la cotizacion.', 'super-mechanic' ) );
		}

		$process = ! empty( $data['process_id'] ) ? $this->process_service->get_process( $data['process_id'] ) : null;

		if ( is_array( $process ) && ! empty( $process['business_id'] ) && absint( $process['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'La cotización y el proceso deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}
		if ( empty( $data['quote_number'] ) ) {
			$errors->add( 'missing_quote_number', __( 'La cotizacion requiere un numero valido.', 'super-mechanic' ) );
		}
		if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
			$errors->add( 'invalid_quote_status', __( 'El estado de la cotizacion no es valido.', 'super-mechanic' ) );
		}
		if ( $data['client_id'] < 0 ) {
			$errors->add( 'invalid_client', __( 'El cliente asociado no es valido.', 'super-mechanic' ) );
		}

		if ( $data['client_id'] > 0 ) {
			$client_service = new \Super_Mechanic\Clients\Client_Service();
			$client         = $client_service->get_client( $data['client_id'] );

			if ( is_array( $client ) && ! empty( $client['business_id'] ) && absint( $client['business_id'] ) !== absint( $data['business_id'] ) ) {
				$errors->add( 'invalid_business_context', __( 'La cotización y el cliente deben pertenecer al mismo negocio.', 'super-mechanic' ) );
			}
		}
		if ( $is_update && empty( $data['quote_number'] ) ) {
			$errors->add( 'invalid_quote', __( 'La cotizacion no es valida.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	public function validate_quote_item_data( array $data, $is_update = false, array $context = array() ) {
		$errors             = new WP_Error();
		$allowed_item_types = array( 'part', 'labor', 'custom', 'woo_product' );
		$context            = wp_parse_args(
			$context,
			array(
				'raw'           => array(),
				'existing_item' => null,
			)
		);
		$raw_data           = is_array( $context['raw'] ) ? $context['raw'] : array();
		$existing_item      = is_array( $context['existing_item'] ) ? $context['existing_item'] : null;
		$raw_item_type      = sanitize_key( isset( $raw_data['item_type'] ) ? (string) $raw_data['item_type'] : '' );
		$raw_woo_product_id = isset( $raw_data['woo_product_id'] ) ? absint( $raw_data['woo_product_id'] ) : 0;
		$existing_is_inconsistent_woo = $this->is_inconsistent_woo_snapshot_payload( $existing_item );
		$explicit_woo_intent          = $this->has_explicit_woo_intent( $raw_item_type, $raw_woo_product_id, $is_update, $existing_is_inconsistent_woo );

		if ( empty( $data['quote_id'] ) ) {
			$errors->add( 'invalid_quote_id', __( 'El item requiere una cotizacion valida.', 'super-mechanic' ) );
		}
		if ( ! in_array( $data['item_type'], $allowed_item_types, true ) ) {
			$errors->add( 'invalid_item_type', __( 'El tipo de item no es valido.', 'super-mechanic' ) );
		}
		if ( '' === $data['label'] ) {
			$errors->add( 'missing_item_label', __( 'La etiqueta del item es obligatoria.', 'super-mechanic' ) );
		}
		if ( (float) $data['quantity'] <= 0 ) {
			$errors->add( 'invalid_item_quantity', __( 'La cantidad del item debe ser mayor que cero.', 'super-mechanic' ) );
		}
		if ( (float) $data['unit_price'] < 0 ) {
			$errors->add( 'invalid_item_price', __( 'El precio unitario del item no es valido.', 'super-mechanic' ) );
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

	protected function prepare_quote_data( array $data, $is_update ) {
		$process_id = isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;
		$client_id  = isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0;

		if ( ! $client_id && $process ) {
			$client_id = absint( $process['client_id'] );
		}

		return array(
			'business_id'        => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_business_id_from_parents( $process, $client_id ),
			'process_id'         => $process_id,
			'client_id'          => $client_id,
			'quote_number'       => ! empty( $data['quote_number'] ) ? sanitize_text_field( $data['quote_number'] ) : $this->generate_quote_number(),
			'status'             => ! empty( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
			'currency'           => ! empty( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : $this->get_default_currency(),
			'subtotal'           => isset( $data['subtotal'] ) ? $this->normalize_decimal( $data['subtotal'] ) : 0,
			'tax_total'          => isset( $data['tax_total'] ) ? $this->normalize_decimal( $data['tax_total'] ) : 0,
			'discount_total'     => isset( $data['discount_total'] ) ? $this->normalize_decimal( $data['discount_total'] ) : 0,
			'grand_total'        => isset( $data['grand_total'] ) ? $this->normalize_decimal( $data['grand_total'] ) : 0,
			'notes'              => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'approved_by_client' => ! empty( $data['approved_by_client'] ) ? 1 : 0,
			'approved_at'        => isset( $data['approved_at'] ) ? $data['approved_at'] : null,
			'rejected_at'        => isset( $data['rejected_at'] ) ? $data['rejected_at'] : null,
			'rejection_reason'   => isset( $data['rejection_reason'] ) ? sanitize_textarea_field( $data['rejection_reason'] ) : '',
			'created_by'         => ! empty( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id(),
		);
	}

	protected function prepare_item_data( array $data ) {
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

		$quantity   = isset( $data['quantity'] ) ? $this->normalize_decimal( $data['quantity'] ) : 0;
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
			'quote_id'     => isset( $data['quote_id'] ) ? absint( $data['quote_id'] ) : 0,
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

	protected function get_default_currency() {
		return sanitize_text_field( $this->settings_service->get_setting( 'business', 'currency', 'USD' ) );
	}

	/**
	 * Format money for printable output.
	 *
	 * @param mixed  $amount   Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	protected function format_money( $amount, $currency ) {
		return sprintf( '%s %s', esc_html( $currency ), esc_html( number_format_i18n( (float) $amount, 2 ) ) );
	}

	/**
	 * Resolve business ID from parent entities.
	 *
	 * @param array<string,mixed>|null $process   Process row.
	 * @param int                       $client_id Client ID.
	 * @return int
	 */
	protected function resolve_business_id_from_parents( $process, $client_id ) {
		if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
			return max( 1, absint( $process['business_id'] ) );
		}

		$client_id = absint( $client_id );
		if ( $client_id > 0 ) {
			$client_service = new \Super_Mechanic\Clients\Client_Service();
			$client         = $client_service->get_client( $client_id );

			if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
				return max( 1, absint( $client['business_id'] ) );
			}
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
	 * Lazily resolve maintenance service to avoid constructor cycles.
	 *
	 * @return Maintenance_Service
	 */
	protected function get_maintenance_service() {
		if ( null === $this->maintenance_service ) {
			$this->maintenance_service = new Maintenance_Service();
		}

		return $this->maintenance_service;
	}
}
