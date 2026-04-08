<?php
/**
 * Demo service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Demo;

use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Invoices\Payment_Repository;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles demo mode state and reproducible demo commercial dataset.
 */
class Demo_Service {
	/**
	 * Demo mode option key.
	 */
	const OPTION_DEMO_MODE = 'sm_54d_demo_mode';

	/**
	 * Demo metadata option key.
	 */
	const OPTION_DEMO_META = 'sm_54d_demo_meta';

	/**
	 * Canonical demo business slug.
	 */
	const DEMO_BUSINESS_SLUG = 'sm-commercial-demo';

	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Business repository.
	 *
	 * @var Business_Repository
	 */
	protected $business_repository;

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

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
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	protected $payment_repository;

	/**
	 * Constructor.
	 *
	 * @param Business_Service|null    $business_service Business service.
	 * @param Business_Repository|null $business_repository Business repository.
	 * @param Client_Service|null      $client_service Client service.
	 * @param Vehicle_Service|null     $vehicle_service Vehicle service.
	 * @param Process_Service|null     $process_service Process service.
	 * @param Quote_Service|null       $quote_service Quote service.
	 * @param Invoice_Service|null     $invoice_service Invoice service.
	 * @param Payment_Repository|null  $payment_repository Payment repository.
	 */
	public function __construct(
		Business_Service $business_service = null,
		Business_Repository $business_repository = null,
		Client_Service $client_service = null,
		Vehicle_Service $vehicle_service = null,
		Process_Service $process_service = null,
		Quote_Service $quote_service = null,
		Invoice_Service $invoice_service = null,
		Payment_Repository $payment_repository = null
	) {
		$this->business_service    = $business_service ? $business_service : new Business_Service();
		$this->business_repository = $business_repository ? $business_repository : new Business_Repository();
		$this->client_service      = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service     = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service     = $process_service ? $process_service : new Process_Service();
		$this->quote_service       = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service     = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->payment_repository  = $payment_repository ? $payment_repository : new Payment_Repository();
	}

	/**
	 * Whether demo mode is enabled.
	 *
	 * @return bool
	 */
	public function is_demo_mode() {
		return '1' === (string) get_option( self::OPTION_DEMO_MODE, '0' );
	}

	/**
	 * Enable demo mode.
	 *
	 * @return bool
	 */
	public function enable_demo_mode() {
		$meta = $this->get_demo_meta();
		if ( empty( $meta['enabled_first_at'] ) ) {
			$meta['enabled_first_at'] = current_time( 'mysql' );
		}
		$meta['enabled_last_at'] = current_time( 'mysql' );

		update_option( self::OPTION_DEMO_MODE, '1', false );
		update_option( self::OPTION_DEMO_META, $meta, false );

		return true;
	}

	/**
	 * Disable demo mode.
	 *
	 * @return bool
	 */
	public function disable_demo_mode() {
		$meta = $this->get_demo_meta();
		$meta['disabled_last_at'] = current_time( 'mysql' );

		update_option( self::OPTION_DEMO_MODE, '0', false );
		update_option( self::OPTION_DEMO_META, $meta, false );

		return true;
	}

	/**
	 * Return demo state and dataset summary.
	 *
	 * @param int $business_id Optional business scope.
	 * @return array<string,mixed>
	 */
	public function get_demo_state( $business_id = 0 ) {
		$resolved_business_id = absint( $business_id );
		if ( $resolved_business_id <= 0 ) {
			$demo_business = $this->business_repository->get_by_slug( self::DEMO_BUSINESS_SLUG );
			if ( is_array( $demo_business ) && ! empty( $demo_business['id'] ) ) {
				$resolved_business_id = absint( $demo_business['id'] );
			}
		}

		return array(
			'is_demo_mode'  => $this->is_demo_mode(),
			'meta'          => $this->get_demo_meta(),
			'business_id'   => $resolved_business_id,
			'dataset'       => $this->get_demo_dataset_summary( $resolved_business_id ),
			'masked_sample' => $this->mask_sensitive_value( 'john.doe@supermechanic.local' ),
		);
	}

	/**
	 * Simple masking helper for demo showcases.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function mask_sensitive_value( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || ! $this->is_demo_mode() ) {
			return $value;
		}

		if ( false !== strpos( $value, '@' ) ) {
			$parts  = explode( '@', $value, 2 );
			$local  = isset( $parts[0] ) ? (string) $parts[0] : '';
			$domain = isset( $parts[1] ) ? (string) $parts[1] : '';
			if ( '' === $domain ) {
				return '***';
			}

			$prefix = strlen( $local ) > 0 ? substr( $local, 0, 1 ) : '';

			return $prefix . '***@' . $domain;
		}

		$digits = preg_replace( '/\D+/', '', $value );
		if ( strlen( $digits ) >= 7 ) {
			return '***' . substr( $digits, -3 );
		}

		return substr( $value, 0, 2 ) . '***';
	}

	/**
	 * Seed deterministic demo commercial dataset.
	 *
	 * @param int $business_id Optional business ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function seed_demo_dataset( $business_id = 0 ) {
		$admin_user_id = $this->resolve_admin_user_id();
		if ( $admin_user_id <= 0 ) {
			return new WP_Error( 'sm_demo_admin_missing', __( 'No administrator user is available for demo seed.', 'super-mechanic' ) );
		}

		$current_user_id      = get_current_user_id();
		$previous_active      = get_user_meta( $admin_user_id, Business_Context_Service::USER_META_ACTIVE_BUSINESS_ID, true );
		$previous_allowed     = get_user_meta( $admin_user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, true );
		$target_business_id   = absint( $business_id );

		try {
			if ( $target_business_id > 0 ) {
				$business = $this->business_service->get_business( $target_business_id );
				if ( ! is_array( $business ) ) {
					return new WP_Error( 'sm_demo_business_invalid', __( 'Target business does not exist for demo seed.', 'super-mechanic' ) );
				}
			} else {
				$target_business_id = $this->ensure_demo_business();
				if ( $target_business_id <= 0 ) {
					return new WP_Error( 'sm_demo_business_create_failed', __( 'Demo business could not be created for seed.', 'super-mechanic' ) );
				}
			}

			$this->set_user_business_scope( $admin_user_id, $target_business_id );
			wp_set_current_user( $admin_user_id );

			$seed_result = $this->seed_business_dataset( $target_business_id, $admin_user_id );
			if ( is_wp_error( $seed_result ) ) {
				return $seed_result;
			}

			$this->touch_demo_meta_after_seed( $target_business_id );

			return array(
				'success'     => true,
				'business_id' => $target_business_id,
				'seed_result' => $seed_result,
				'dataset'     => $this->get_demo_dataset_summary( $target_business_id ),
			);
		} finally {
			$this->restore_user_scope_meta( $admin_user_id, $previous_active, $previous_allowed );
			wp_set_current_user( $current_user_id );
		}
	}

	/**
	 * Build dataset summary.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>
	 */
	public function get_demo_dataset_summary( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array(
				'available' => false,
				'message'   => __( 'Demo business is not available yet.', 'super-mechanic' ),
			);
		}

		return array(
			'available'    => true,
			'business_id'  => $business_id,
			'clients'      => (int) $this->client_service->count_clients( array( 'business_id' => $business_id ) ),
			'vehicles'     => (int) $this->vehicle_service->count_vehicles( array( 'business_id' => $business_id ) ),
			'processes'    => (int) $this->process_service->count_processes( array( 'business_id' => $business_id ) ),
			'quotes'       => (int) $this->quote_service->count_quotes( array( 'business_id' => $business_id ) ),
			'invoices'     => (int) $this->invoice_service->count_invoices( array( 'business_id' => $business_id ) ),
			'payments'     => (int) $this->payment_repository->count_all( array( 'business_id' => $business_id ) ),
			'last_seed_at' => isset( $this->get_demo_meta()['last_seed_at'] ) ? (string) $this->get_demo_meta()['last_seed_at'] : '',
		);
	}

	/**
	 * Ensure canonical demo business.
	 *
	 * @return int
	 */
	protected function ensure_demo_business() {
		$existing = $this->business_repository->get_by_slug( self::DEMO_BUSINESS_SLUG );
		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$this->business_service->update_business(
				(int) $existing['id'],
				array(
					'name'     => 'Super Mechanic Demo Business',
					'slug'     => self::DEMO_BUSINESS_SLUG,
					'status'   => 'active',
					'timezone' => 'UTC',
					'currency' => 'USD',
				)
			);

			return (int) $existing['id'];
		}

		$created = $this->business_service->create_business(
			array(
				'name'     => 'Super Mechanic Demo Business',
				'slug'     => self::DEMO_BUSINESS_SLUG,
				'status'   => 'active',
				'timezone' => 'UTC',
				'currency' => 'USD',
			)
		);

		return is_wp_error( $created ) ? 0 : (int) $created;
	}

	/**
	 * Seed business demo entities.
	 *
	 * @param int $business_id Business ID.
	 * @param int $admin_user_id Admin user ID.
	 * @return array<string,int>|WP_Error
	 */
	protected function seed_business_dataset( $business_id, $admin_user_id ) {
		$definitions = array(
			array(
				'idx'          => '01',
				'first_name'   => 'Laura',
				'last_name'    => 'Garcia',
				'email'        => 'demo.commercial.01@supermechanic.local',
				'phone'        => '+1-555-540-001',
				'document_id'  => 'SM-DEMO-5401',
				'brand'        => 'Toyota',
				'model'        => 'Corolla',
				'plate'        => 'DM54D-001',
				'vin'          => 'SM54DDEMO000001',
				'quote_amount' => 640.00,
				'pay_ratio'    => 1.00,
				'status'       => 'in_progress',
			),
			array(
				'idx'          => '02',
				'first_name'   => 'Michael',
				'last_name'    => 'Bennet',
				'email'        => 'demo.commercial.02@supermechanic.local',
				'phone'        => '+1-555-540-002',
				'document_id'  => 'SM-DEMO-5402',
				'brand'        => 'Ford',
				'model'        => 'Escape',
				'plate'        => 'DM54D-002',
				'vin'          => 'SM54DDEMO000002',
				'quote_amount' => 920.00,
				'pay_ratio'    => 0.50,
				'status'       => 'in_progress',
			),
			array(
				'idx'          => '03',
				'first_name'   => 'Sophie',
				'last_name'    => 'Reed',
				'email'        => 'demo.commercial.03@supermechanic.local',
				'phone'        => '+1-555-540-003',
				'document_id'  => 'SM-DEMO-5403',
				'brand'        => 'Honda',
				'model'        => 'Civic',
				'plate'        => 'DM54D-003',
				'vin'          => 'SM54DDEMO000003',
				'quote_amount' => 480.00,
				'pay_ratio'    => 0.00,
				'status'       => 'pending',
			),
		);

		$result = array(
			'clients'   => 0,
			'vehicles'  => 0,
			'processes' => 0,
			'quotes'    => 0,
			'invoices'  => 0,
			'payments'  => 0,
		);

		foreach ( $definitions as $row ) {
			$client = $this->ensure_demo_client( $business_id, $row );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			if ( ! empty( $client['created'] ) ) {
				$result['clients']++;
			}

			$vehicle = $this->ensure_demo_vehicle( $business_id, (int) $client['client_id'], $row );
			if ( is_wp_error( $vehicle ) ) {
				return $vehicle;
			}
			if ( ! empty( $vehicle['created'] ) ) {
				$result['vehicles']++;
			}

			$process = $this->ensure_demo_process( $business_id, (int) $client['client_id'], (int) $vehicle['vehicle_id'], $row );
			if ( is_wp_error( $process ) ) {
				return $process;
			}
			if ( ! empty( $process['created'] ) ) {
				$result['processes']++;
			}

			$quote = $this->ensure_demo_quote( $business_id, (int) $process['process_id'], (int) $client['client_id'], $admin_user_id, $row );
			if ( is_wp_error( $quote ) ) {
				return $quote;
			}
			if ( ! empty( $quote['created'] ) ) {
				$result['quotes']++;
			}

			$invoice = $this->ensure_demo_invoice( (int) $quote['quote_id'], $row );
			if ( is_wp_error( $invoice ) ) {
				return $invoice;
			}
			if ( ! empty( $invoice['created'] ) ) {
				$result['invoices']++;
			}

			$payment = $this->ensure_demo_payment( (int) $invoice['invoice_id'], $row );
			if ( is_wp_error( $payment ) ) {
				return $payment;
			}
			if ( ! empty( $payment['created'] ) ) {
				$result['payments']++;
			}
		}

		return $result;
	}

	/**
	 * Ensure one demo client.
	 *
	 * @param int                $business_id Business ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_client( $business_id, array $definition ) {
		$email    = isset( $definition['email'] ) ? sanitize_email( (string) $definition['email'] ) : '';
		$existing = $this->client_service->get_clients(
			array(
				'exact_email' => $email,
				'business_id' => $business_id,
				'per_page'    => 1,
			)
		);

		$payload = array(
			'business_id' => $business_id,
			'first_name'  => isset( $definition['first_name'] ) ? (string) $definition['first_name'] : '',
			'last_name'   => isset( $definition['last_name'] ) ? (string) $definition['last_name'] : '',
			'email'       => $email,
			'phone'       => isset( $definition['phone'] ) ? (string) $definition['phone'] : '',
			'document_id' => isset( $definition['document_id'] ) ? (string) $definition['document_id'] : '',
			'notes'       => '54D demo commercial seed client',
		);

		if ( ! empty( $existing ) && is_array( $existing[0] ) ) {
			$client_id = absint( $existing[0]['id'] );
			$updated   = $this->client_service->update_client( $client_id, $payload );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			return array(
				'client_id' => $client_id,
				'created'   => false,
			);
		}

		$created = $this->client_service->create_client( $payload );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'client_id' => absint( $created ),
			'created'   => true,
		);
	}

	/**
	 * Ensure one demo vehicle.
	 *
	 * @param int                $business_id Business ID.
	 * @param int                $client_id Client ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_vehicle( $business_id, $client_id, array $definition ) {
		$plate    = isset( $definition['plate'] ) ? strtoupper( (string) $definition['plate'] ) : '';
		$existing = $this->vehicle_service->get_vehicles(
			array(
				'exact_plate' => $plate,
				'business_id' => $business_id,
				'per_page'    => 1,
			)
		);

		$payload = array(
			'business_id' => $business_id,
			'client_id'   => $client_id,
			'brand'       => isset( $definition['brand'] ) ? (string) $definition['brand'] : 'Demo',
			'model'       => isset( $definition['model'] ) ? (string) $definition['model'] : 'Vehicle',
			'year'        => 2023,
			'plate'       => $plate,
			'vin'         => isset( $definition['vin'] ) ? (string) $definition['vin'] : '',
			'color'       => 'Silver',
			'notes'       => '54D demo commercial seed vehicle',
		);

		if ( ! empty( $existing ) && is_array( $existing[0] ) ) {
			$vehicle_id = absint( $existing[0]['id'] );
			$updated    = $this->vehicle_service->update_vehicle( $vehicle_id, $payload );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			return array(
				'vehicle_id' => $vehicle_id,
				'created'    => false,
			);
		}

		$created = $this->vehicle_service->create_vehicle( $payload );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'vehicle_id' => absint( $created ),
			'created'    => true,
		);
	}

	/**
	 * Ensure one demo process.
	 *
	 * @param int                $business_id Business ID.
	 * @param int                $client_id Client ID.
	 * @param int                $vehicle_id Vehicle ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_process( $business_id, $client_id, $vehicle_id, array $definition ) {
		$title    = '54D Commercial Demo Process ' . ( isset( $definition['idx'] ) ? (string) $definition['idx'] : '00' );
		$rows     = $this->process_service->get_processes(
			array(
				'client_id'  => $client_id,
				'vehicle_id' => $vehicle_id,
				'business_id'=> $business_id,
				'per_page'   => 100,
			)
		);
		$existing = null;
		foreach ( $rows as $row ) {
			if ( isset( $row['title'] ) && (string) $row['title'] === $title ) {
				$existing = $row;
				break;
			}
		}

		$status  = isset( $definition['status'] ) ? sanitize_key( (string) $definition['status'] ) : 'pending';
		$payload = array(
			'business_id'   => $business_id,
			'vehicle_id'    => $vehicle_id,
			'client_id'     => $client_id,
			'process_type'  => 'maintenance',
			'status'        => $status,
			'title'         => $title,
			'internal_notes'=> '54D demo commercial seed process',
			'opened_at'     => current_time( 'mysql' ),
		);

		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$process_id = absint( $existing['id'] );
			$updated    = $this->process_service->update_process( $process_id, $payload );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			return array(
				'process_id' => $process_id,
				'created'    => false,
			);
		}

		$created = $this->process_service->create_process( $payload );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'process_id' => absint( $created ),
			'created'    => true,
		);
	}

	/**
	 * Ensure one demo quote with one custom line.
	 *
	 * @param int                $business_id Business ID.
	 * @param int                $process_id Process ID.
	 * @param int                $client_id Client ID.
	 * @param int                $admin_user_id User ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_quote( $business_id, $process_id, $client_id, $admin_user_id, array $definition ) {
		$marker = '54D-DEMO-QUOTE-' . ( isset( $definition['idx'] ) ? (string) $definition['idx'] : '00' );
		$rows   = $this->quote_service->get_quotes(
			array(
				'process_id'  => $process_id,
				'business_id' => $business_id,
				'per_page'    => 50,
			)
		);
		$existing = null;
		foreach ( $rows as $row ) {
			if ( isset( $row['notes'] ) && (string) $row['notes'] === $marker ) {
				$existing = $row;
				break;
			}
		}

		$amount = isset( $definition['quote_amount'] ) ? (float) $definition['quote_amount'] : 0.0;
		if ( $amount <= 0 ) {
			$amount = 100.0;
		}

		$payload = array(
			'business_id' => $business_id,
			'process_id'  => $process_id,
			'client_id'   => $client_id,
			'status'      => 'approved',
			'notes'       => $marker,
			'created_by'  => $admin_user_id,
		);

		$created_flag = false;
		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$quote_id = absint( $existing['id'] );
			$updated  = $this->quote_service->update_quote( $quote_id, $payload );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		} else {
			$created = $this->quote_service->create_quote( $payload );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$quote_id = absint( $created );
			$created_flag = true;
		}

		$items = $this->quote_service->get_quote_items( $quote_id );
		if ( empty( $items ) ) {
			$added = $this->quote_service->add_quote_item(
				$quote_id,
				array(
					'item_type'   => 'custom',
					'label'       => 'Commercial Demo Service',
					'description' => 'Seeded demo service line item',
					'quantity'    => 1,
					'unit_price'  => $amount,
					'sort_order'  => 1,
					'business_id' => $business_id,
				)
			);
			if ( is_wp_error( $added ) ) {
				return $added;
			}
		}

		return array(
			'quote_id' => $quote_id,
			'created'  => $created_flag,
		);
	}

	/**
	 * Ensure one demo invoice from quote.
	 *
	 * @param int                $quote_id Quote ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_invoice( $quote_id, array $definition ) {
		$marker = '54D-DEMO-INVOICE-' . ( isset( $definition['idx'] ) ? (string) $definition['idx'] : '00' );
		$quote  = $this->quote_service->get_quote( $quote_id );
		if ( ! is_array( $quote ) || empty( $quote['process_id'] ) ) {
			return new WP_Error( 'sm_demo_quote_invalid', __( 'Demo quote is invalid for invoice generation.', 'super-mechanic' ) );
		}

		$invoices = $this->invoice_service->get_invoices(
			array(
				'process_id'  => absint( $quote['process_id'] ),
				'business_id' => absint( isset( $quote['business_id'] ) ? $quote['business_id'] : 0 ),
				'per_page'    => 50,
			)
		);
		foreach ( $invoices as $invoice ) {
			if ( isset( $invoice['notes'] ) && (string) $invoice['notes'] === $marker ) {
				return array(
					'invoice_id' => absint( $invoice['id'] ),
					'created'    => false,
				);
			}
		}

		$created = $this->invoice_service->create_invoice_from_quote(
			$quote_id,
			array(
				'status'   => 'issued',
				'notes'    => $marker,
				'currency' => isset( $quote['currency'] ) ? (string) $quote['currency'] : 'USD',
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'invoice_id' => absint( $created ),
			'created'    => true,
		);
	}

	/**
	 * Ensure one demo payment (full, partial, or none).
	 *
	 * @param int                $invoice_id Invoice ID.
	 * @param array<string,mixed> $definition Row definition.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function ensure_demo_payment( $invoice_id, array $definition ) {
		$ratio = isset( $definition['pay_ratio'] ) ? (float) $definition['pay_ratio'] : 0.0;
		if ( $ratio <= 0 ) {
			return array(
				'payment_id' => 0,
				'created'    => false,
			);
		}

		$reference = '54D-DEMO-PAY-' . ( isset( $definition['idx'] ) ? (string) $definition['idx'] : '00' );
		foreach ( $this->invoice_service->get_payments( $invoice_id ) as $payment ) {
			if ( isset( $payment['reference'] ) && (string) $payment['reference'] === $reference ) {
				return array(
					'payment_id' => absint( $payment['id'] ),
					'created'    => false,
				);
			}
		}

		$invoice = $this->invoice_service->get_invoice( $invoice_id );
		if ( ! is_array( $invoice ) ) {
			return new WP_Error( 'sm_demo_invoice_invalid', __( 'Demo invoice is invalid for payment seed.', 'super-mechanic' ) );
		}

		$grand_total = isset( $invoice['grand_total'] ) ? (float) $invoice['grand_total'] : 0.0;
		$amount      = round( $grand_total * min( 1, max( 0, $ratio ) ), 2 );
		if ( $amount <= 0 ) {
			return array(
				'payment_id' => 0,
				'created'    => false,
			);
		}

		$created = $this->invoice_service->add_payment(
			$invoice_id,
			array(
				'payment_date'   => current_time( 'mysql' ),
				'amount'         => $amount,
				'payment_method' => 'cash',
				'reference'      => $reference,
				'notes'          => '54D demo commercial payment',
				'received_by'    => get_current_user_id(),
				'business_id'    => ! empty( $invoice['business_id'] ) ? absint( $invoice['business_id'] ) : 0,
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'payment_id' => absint( $created ),
			'created'    => true,
		);
	}

	/**
	 * Resolve an admin user for seed actions.
	 *
	 * @return int
	 */
	protected function resolve_admin_user_id() {
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 && user_can( $current_user_id, 'sm_manage_plugin' ) ) {
			return $current_user_id;
		}

		$admins = get_users(
			array(
				'role__in' => array( 'administrator', 'sm_admin' ),
				'fields'   => 'ids',
				'number'   => 1,
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);

		return ! empty( $admins ) ? absint( $admins[0] ) : 0;
	}

	/**
	 * Set one user to one active business for seed runtime.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return void
	 */
	protected function set_user_business_scope( $user_id, $business_id ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		if ( $user_id <= 0 || $business_id <= 0 ) {
			return;
		}

		$raw_allowed = get_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, true );
		$allowed     = is_array( $raw_allowed ) ? $raw_allowed : array();
		if ( is_string( $raw_allowed ) ) {
			$allowed = array_filter( array_map( 'absint', explode( ',', $raw_allowed ) ) );
		}
		$allowed[ $business_id ] = $business_id;

		update_user_meta( $user_id, Business_Context_Service::USER_META_ACTIVE_BUSINESS_ID, $business_id );
		update_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, array_values( $allowed ) );
	}

	/**
	 * Restore user scope metadata.
	 *
	 * @param int   $user_id User ID.
	 * @param mixed $previous_active Previous active business meta.
	 * @param mixed $previous_allowed Previous allowed business meta.
	 * @return void
	 */
	protected function restore_user_scope_meta( $user_id, $previous_active, $previous_allowed ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		if ( '' === $previous_active || null === $previous_active ) {
			delete_user_meta( $user_id, Business_Context_Service::USER_META_ACTIVE_BUSINESS_ID );
		} else {
			update_user_meta( $user_id, Business_Context_Service::USER_META_ACTIVE_BUSINESS_ID, $previous_active );
		}

		if ( '' === $previous_allowed || null === $previous_allowed ) {
			delete_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS );
		} else {
			update_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, $previous_allowed );
		}
	}

	/**
	 * Get demo metadata payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_demo_meta() {
		$meta = get_option( self::OPTION_DEMO_META, array() );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Track seed execution in metadata.
	 *
	 * @param int $business_id Business ID.
	 * @return void
	 */
	protected function touch_demo_meta_after_seed( $business_id ) {
		$meta = $this->get_demo_meta();
		$meta['last_seed_at']       = current_time( 'mysql' );
		$meta['last_seed_user_id']  = get_current_user_id();
		$meta['last_seed_business'] = absint( $business_id );
		update_option( self::OPTION_DEMO_META, $meta, false );
	}
}
