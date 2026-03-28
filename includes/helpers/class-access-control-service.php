<?php
/**
 * Centralized access control service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Attachments\Attachment_Repository;
use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Invoices\Invoice_Repository;
use Super_Mechanic\Processes\Process_Repository;
use Super_Mechanic\Quotes\Quote_Repository;
use Super_Mechanic\Relations\Client_Vehicle_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves ownership and role-based access across the active architecture.
 */
class Access_Control_Service {
	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Client-vehicle repository.
	 *
	 * @var Client_Vehicle_Repository
	 */
	protected $client_vehicle_repository;

	/**
	 * Process repository.
	 *
	 * @var Process_Repository
	 */
	protected $process_repository;

	/**
	 * Quote repository.
	 *
	 * @var Quote_Repository
	 */
	protected $quote_repository;

	/**
	 * Invoice repository.
	 *
	 * @var Invoice_Repository
	 */
	protected $invoice_repository;

	/**
	 * Attachment repository.
	 *
	 * @var Attachment_Repository
	 */
	protected $attachment_repository;
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null            $client_service            Client service.
	 * @param Client_Vehicle_Repository|null $client_vehicle_repository Client-vehicle repository.
	 * @param Process_Repository|null        $process_repository        Process repository.
	 * @param Quote_Repository|null          $quote_repository          Quote repository.
	 * @param Invoice_Repository|null        $invoice_repository        Invoice repository.
	 * @param Attachment_Repository|null     $attachment_repository     Attachment repository.
	 */
	public function __construct( Client_Service $client_service = null, Client_Vehicle_Repository $client_vehicle_repository = null, Process_Repository $process_repository = null, Quote_Repository $quote_repository = null, Invoice_Repository $invoice_repository = null, Attachment_Repository $attachment_repository = null, Business_Context_Service $business_context_service = null ) {
		$this->client_service            = $client_service ? $client_service : new Client_Service();
		$this->client_vehicle_repository = $client_vehicle_repository ? $client_vehicle_repository : new Client_Vehicle_Repository();
		$this->process_repository        = $process_repository ? $process_repository : new Process_Repository();
		$this->quote_repository          = $quote_repository ? $quote_repository : new Quote_Repository();
		$this->invoice_repository        = $invoice_repository ? $invoice_repository : new Invoice_Repository();
		$this->attachment_repository     = $attachment_repository ? $attachment_repository : new Attachment_Repository();
		$this->business_context_service  = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Resolve the linked client ID for a WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_client_id_by_user_id( $user_id ) {
		$user_id   = absint( $user_id );
		$client_id = absint( get_user_meta( $user_id, 'sm_client_id', true ) );

		if ( $client_id && $this->client_service->get_client( $client_id ) ) {
			return $client_id;
		}

		return 0;
	}

	/**
	 * Check whether the user has full system access.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function user_has_full_access( $user_id ) {
		$user_id = absint( $user_id );

		return user_can( $user_id, 'sm_manage_plugin' );
	}

	/**
	 * Check whether the user is staff with process visibility restrictions.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function user_is_limited_process_staff( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || $this->user_has_full_access( $user_id ) ) {
			return false;
		}

		return user_can( $user_id, 'sm_manage_processes' );
	}

	/**
	 * Check whether a user can access a vehicle.
	 *
	 * @param int $user_id    User ID.
	 * @param int $vehicle_id Vehicle ID.
	 * @return bool
	 */
	public function user_can_access_vehicle( $user_id, $vehicle_id ) {
		$user_id    = absint( $user_id );
		$vehicle_id = absint( $vehicle_id );

		if ( ! $user_id || ! $vehicle_id ) {
			return false;
		}

		if ( $this->user_has_full_access( $user_id ) ) {
			return true;
		}

		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return false;
		}

		$relations = $this->client_vehicle_repository->get_by_client(
			$client_id,
			array(
				'current_only' => true,
			)
		);

		foreach ( $relations as $relation ) {
			if ( absint( $relation['vehicle_id'] ) === $vehicle_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a user can access a process.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return bool
	 */
	public function user_can_access_process( $user_id, $process_id ) {
		$user_id    = absint( $user_id );
		$process_id = absint( $process_id );
		$process    = $this->process_repository->get_by_id( $process_id );

		if ( ! $user_id || ! $process ) {
			return false;
		}

		if ( ! $this->row_matches_current_business( $process ) ) {
			return false;
		}

		if ( $this->user_has_full_access( $user_id ) ) {
			return true;
		}

		if ( $this->user_is_limited_process_staff( $user_id ) ) {
			return ! empty( $process['assigned_to'] ) && absint( $process['assigned_to'] ) === $user_id;
		}

		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return false;
		}

		if ( absint( $process['client_id'] ) === $client_id ) {
			return true;
		}

		return $this->user_can_access_vehicle( $user_id, absint( $process['vehicle_id'] ) );
	}

	/**
	 * Check whether a user can access a quote.
	 *
	 * @param int $user_id  User ID.
	 * @param int $quote_id Quote ID.
	 * @return bool
	 */
	public function user_can_access_quote( $user_id, $quote_id ) {
		$user_id  = absint( $user_id );
		$quote_id = absint( $quote_id );
		$quote    = $this->quote_repository->get_by_id( $quote_id );

		if ( ! $user_id || ! $quote ) {
			return false;
		}

		if ( ! $this->row_matches_current_business( $quote ) ) {
			return false;
		}

		if ( $this->user_has_full_access( $user_id ) ) {
			return true;
		}

		if ( $this->user_is_limited_process_staff( $user_id ) ) {
			return ! empty( $quote['process_id'] ) && $this->user_can_access_process( $user_id, absint( $quote['process_id'] ) );
		}

		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return false;
		}

		if ( absint( $quote['client_id'] ) === $client_id ) {
			return true;
		}

		if ( ! empty( $quote['process_id'] ) && $this->user_can_access_process( $user_id, absint( $quote['process_id'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether a user can access an invoice.
	 *
	 * @param int $user_id    User ID.
	 * @param int $invoice_id Invoice ID.
	 * @return bool
	 */
	public function user_can_access_invoice( $user_id, $invoice_id ) {
		$user_id    = absint( $user_id );
		$invoice_id = absint( $invoice_id );
		$invoice    = $this->invoice_repository->get_by_id( $invoice_id );

		if ( ! $user_id || ! $invoice ) {
			return false;
		}

		if ( ! $this->row_matches_current_business( $invoice ) ) {
			return false;
		}

		if ( $this->user_has_full_access( $user_id ) ) {
			return true;
		}

		if ( $this->user_is_limited_process_staff( $user_id ) ) {
			return ! empty( $invoice['process_id'] ) && $this->user_can_access_process( $user_id, absint( $invoice['process_id'] ) );
		}

		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return false;
		}

		if ( absint( $invoice['client_id'] ) === $client_id ) {
			return true;
		}

		if ( ! empty( $invoice['process_id'] ) && $this->user_can_access_process( $user_id, absint( $invoice['process_id'] ) ) ) {
			return true;
		}

		if ( ! empty( $invoice['quote_id'] ) && $this->user_can_access_quote( $user_id, absint( $invoice['quote_id'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether a user can access an attachment.
	 *
	 * @param int  $user_id       User ID.
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $client_safe   Whether client visibility rules must apply.
	 * @return bool
	 */
	public function user_can_access_attachment( $user_id, $attachment_id, $client_safe = true ) {
		$user_id       = absint( $user_id );
		$attachment_id = absint( $attachment_id );
		$attachment    = $this->attachment_repository->get_by_id( $attachment_id );

		if ( ! $user_id || ! $attachment ) {
			return false;
		}

		if ( ! $this->row_matches_current_business( $attachment ) ) {
			return false;
		}

		if ( $this->user_has_full_access( $user_id ) ) {
			return true;
		}

		if ( $this->user_is_limited_process_staff( $user_id ) ) {
			return ! empty( $attachment['process_id'] ) && $this->user_can_access_process( $user_id, absint( $attachment['process_id'] ) );
		}

		if ( $client_safe && ( empty( $attachment['is_client_visible'] ) || ! empty( $attachment['is_internal'] ) ) ) {
			return false;
		}

		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return false;
		}

		if ( ! empty( $attachment['client_id'] ) && absint( $attachment['client_id'] ) === $client_id ) {
			return true;
		}

		if ( ! empty( $attachment['process_id'] ) && $this->user_can_access_process( $user_id, absint( $attachment['process_id'] ) ) ) {
			return true;
		}

		if ( ! empty( $attachment['vehicle_id'] ) && $this->user_can_access_vehicle( $user_id, absint( $attachment['vehicle_id'] ) ) ) {
			return true;
		}

		if ( 'quote' === $attachment['object_type'] && ! empty( $attachment['object_id'] ) ) {
			return $this->user_can_access_quote( $user_id, absint( $attachment['object_id'] ) );
		}

		if ( 'invoice' === $attachment['object_type'] && ! empty( $attachment['object_id'] ) ) {
			return $this->user_can_access_invoice( $user_id, absint( $attachment['object_id'] ) );
		}

		return false;
	}

	/**
	 * Filter process datasets against a user access policy.
	 *
	 * @param int                                $user_id   User ID.
	 * @param array<int, array<string, mixed>>   $processes Process rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function filter_processes_for_user( $user_id, array $processes ) {
		$allowed = array();

		foreach ( $processes as $process ) {
			if ( empty( $process['id'] ) || ! $this->user_can_access_process( $user_id, absint( $process['id'] ) ) ) {
				continue;
			}

			$allowed[] = $process;
		}

		return $allowed;
	}

	/**
	 * Ensure row belongs to the active business context when available.
	 *
	 * @param array<string,mixed> $row Data row.
	 * @return bool
	 */
	protected function row_matches_current_business( array $row ) {
		if ( empty( $row['business_id'] ) ) {
			return true;
		}

		$current_business_id = absint( $this->business_context_service->resolve_business_id() );

		return $current_business_id === absint( $row['business_id'] );
	}
}
