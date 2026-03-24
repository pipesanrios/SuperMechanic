<?php
/**
 * Client process view service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds reusable client-facing process view datasets.
 */
class Client_Process_View_Service {
	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $dashboard_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Comment service.
	 *
	 * @var Comment_Service
	 */
	protected $comment_service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null $dashboard_service Dashboard service.
	 * @param Quote_Service|null     $quote_service     Quote service.
	 * @param Invoice_Service|null   $invoice_service   Invoice service.
	 * @param Comment_Service|null   $comment_service   Comment service.
	 */
	public function __construct( Dashboard_Service $dashboard_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Comment_Service $comment_service = null ) {
		$this->dashboard_service = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->quote_service     = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service   = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->comment_service   = $comment_service ? $comment_service : new Comment_Service();
	}

	/**
	 * Resolve a client process row by user and process ID.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>
	 */
	public function get_client_process_by_id( $user_id, $process_id ) {
		$process_id = absint( $process_id );

		if ( ! $process_id ) {
			return array();
		}

		$processes = $this->dashboard_service->get_client_processes(
			$user_id,
			array(
				'per_page' => 200,
			)
		);

		foreach ( $processes as $process ) {
			if ( absint( $process['id'] ) === $process_id ) {
				return $process;
			}
		}

		return array();
	}

	/**
	 * Resolve quote rows for a process visible to the given user.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_quotes_for_user( $user_id, $process_id ) {
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->quote_service->get_quotes_for_user(
					$user_id,
					array(
						'client_id' => $client_id,
						'per_page'  => 100,
						'orderby'   => 'created_at',
						'order'     => 'DESC',
					)
				),
				static function ( $quote ) use ( $process_id ) {
					return absint( $quote['process_id'] ) === absint( $process_id );
				}
			)
		);
	}

	/**
	 * Resolve invoice rows for a process visible to the given user.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_invoices_for_user( $user_id, $process_id ) {
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		$invoices = array_values(
			array_filter(
				$this->invoice_service->get_invoices_for_user(
					$user_id,
					array(
						'client_id' => $client_id,
						'per_page'  => 100,
						'orderby'   => 'created_at',
						'order'     => 'DESC',
					)
				),
				static function ( $invoice ) use ( $process_id ) {
					return absint( $invoice['process_id'] ) === absint( $process_id );
				}
			)
		);

		foreach ( $invoices as $index => $invoice ) {
			$invoices[ $index ] = $this->invoice_service->append_collection_state( $invoice );
		}

		return $invoices;
	}

	/**
	 * Build a client-facing financial snapshot for a process.
	 *
	 * @param int                  $user_id  User ID.
	 * @param array<string, mixed> $process  Process row.
	 * @param array<int, array<string, mixed>>|null $invoices Invoice rows.
	 * @return array<string, string>
	 */
	public function get_process_financial_snapshot( $user_id, array $process, array $invoices = null ) {
		if ( null === $invoices ) {
			$invoices = $this->get_process_invoices_for_user( $user_id, absint( $process['id'] ) );
		}

		if ( empty( $invoices ) ) {
			return array(
				'label' => __( 'Sin facturas emitidas', 'super-mechanic' ),
			);
		}

		$total_grand     = 0.0;
		$total_balance   = 0.0;
		$currency        = '';
		$single_currency = true;

		foreach ( $invoices as $invoice ) {
			$total_grand   += (float) $invoice['grand_total'];
			$total_balance += (float) $invoice['balance_due'];

			if ( '' === $currency ) {
				$currency = (string) $invoice['currency'];
			} elseif ( $currency !== (string) $invoice['currency'] ) {
				$single_currency = false;
			}
		}

		if ( $total_balance <= 0 && $total_grand > 0 ) {
			$label = __( 'Pagado', 'super-mechanic' );
		} elseif ( $total_balance < $total_grand ) {
			$label = __( 'Pago parcial', 'super-mechanic' );
		} else {
			$label = __( 'Pendiente de pago', 'super-mechanic' );
		}

		if ( $single_currency ) {
			$label .= ' - ' . $this->format_money( $total_balance, $currency ) . ' ' . __( 'pendiente', 'super-mechanic' );
		}

		return array(
			'label' => $label,
		);
	}

	/**
	 * Get the most recent visible comments across client processes.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max results.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_client_comments( $user_id, $limit = 5 ) {
		$comments  = array();
		$processes = $this->dashboard_service->get_client_processes(
			$user_id,
			array(
				'per_page' => 20,
			)
		);

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

	/**
	 * Format a money string for read-only view output.
	 *
	 * @param mixed  $amount   Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	protected function format_money( $amount, $currency ) {
		return number_format_i18n( (float) $amount, 2 ) . ' ' . sanitize_text_field( (string) $currency );
	}
}
