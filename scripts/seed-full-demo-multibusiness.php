<?php
/**
 * Full multi-business demo dataset seeder.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Automation\Execution_Log_Installer;
use Super_Mechanic\Automation\Execution_Log_Service;
use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\CRM\Crm_Pipeline_Service;
use Super_Mechanic\CRM\Crm_Task_Service;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Flows\Flow_Service;
use Super_Mechanic\Flows\Flow_Step_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Relations\Client_Vehicle_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must run from CLI.\n" );
	exit( 1 );
}

$wp_load = dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR . 'wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found.\n" );
	exit( 1 );
}

require_once $wp_load;

function sm_demo_out( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

function sm_demo_fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

function sm_demo_set_user_business_scope( int $user_id, int $active_business_id, array $allowed_business_ids ): void {
	$allowed_business_ids = array_values(
		array_filter(
			array_map( 'absint', $allowed_business_ids ),
			static function ( int $value ): bool {
				return $value > 0;
			}
		)
	);

	update_user_meta( $user_id, Business_Context_Service::USER_META_ACTIVE_BUSINESS_ID, $active_business_id );
	update_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, $allowed_business_ids );
}

function sm_demo_require_admin(): int {
	$admins = get_users(
		array(
			'role__in' => array( 'administrator', 'sm_admin' ),
			'fields'   => 'ids',
			'number'   => 1,
			'orderby'  => 'ID',
			'order'    => 'ASC',
		)
	);

	$admin_id = ! empty( $admins ) ? absint( $admins[0] ) : 0;
	if ( $admin_id <= 0 ) {
		sm_demo_fail( 'No administrator user available.' );
	}

	wp_set_current_user( $admin_id );
	return $admin_id;
}

function sm_demo_ensure_primary_super_admin( array $all_business_ids ): int {
	$target_email = 'admin@mardisom.com';
	$target_login = 'admin';
	$user         = get_user_by( 'email', $target_email );

	if ( ! $user ) {
		$user = get_user_by( 'login', $target_login );
	}

	if ( ! $user ) {
		$generated_password = wp_generate_password( 24, true, true );
		$user_id            = wp_create_user( $target_login, $generated_password, $target_email );
		if ( is_wp_error( $user_id ) ) {
			sm_demo_fail( 'Failed creating super admin user: ' . $user_id->get_error_message() );
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user instanceof WP_User ) {
			sm_demo_fail( 'Failed resolving newly created super admin user.' );
		}

		sm_demo_out( 'Created super admin user `admin` with email admin@mardisom.com.' );
		sm_demo_out( 'Generated temporary password: ' . $generated_password );
	}

	if ( ! $user instanceof WP_User ) {
		sm_demo_fail( 'Invalid super admin user resolution.' );
	}

	$user->set_role( 'administrator' );
	$user->add_cap( 'sm_manage_plugin', true );

	wp_update_user(
		array(
			'ID'           => (int) $user->ID,
			'user_email'   => $target_email,
			'display_name' => 'SuperMechanic Super Admin',
		)
	);

	$active_business_id = ! empty( $all_business_ids ) ? absint( $all_business_ids[0] ) : 1;
	sm_demo_set_user_business_scope( (int) $user->ID, $active_business_id, $all_business_ids );

	return (int) $user->ID;
}

function sm_demo_ensure_business( Business_Repository $repository, string $slug, string $name, bool $is_default = false ): int {
	$existing = $repository->get_by_slug( $slug );
	if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
		$repository->update(
			(int) $existing['id'],
			array(
				'slug'       => $slug,
				'name'       => $name,
				'status'     => 'active',
				'is_default' => $is_default ? 1 : 0,
				'timezone'   => 'Europe/Rome',
				'currency'   => 'USD',
			)
		);
		return (int) $existing['id'];
	}

	$inserted = $repository->insert(
		array(
			'slug'       => $slug,
			'name'       => $name,
			'status'     => 'active',
			'is_default' => $is_default ? 1 : 0,
			'timezone'   => 'Europe/Rome',
			'currency'   => 'USD',
		)
	);

	if ( $inserted <= 0 ) {
		sm_demo_fail( 'Failed creating business: ' . $slug );
	}

	return $inserted;
}

function sm_demo_ensure_user( string $login, string $email, string $display_name, string $role, int $active_business_id, array $allowed_business_ids, int $client_id = 0 ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $email );
		if ( is_wp_error( $user_id ) ) {
			sm_demo_fail( 'Failed creating user ' . $login . ': ' . $user_id->get_error_message() );
		}
		$user = get_user_by( 'id', (int) $user_id );
	}

	if ( ! $user instanceof WP_User ) {
		sm_demo_fail( 'Failed resolving user ' . $login );
	}

	if ( get_role( $role ) ) {
		$user->set_role( $role );
	}

	wp_update_user(
		array(
			'ID'           => (int) $user->ID,
			'display_name' => $display_name,
			'user_email'   => $email,
		)
	);

	if ( $client_id > 0 ) {
		update_user_meta( (int) $user->ID, 'sm_client_id', $client_id );
	}

	sm_demo_set_user_business_scope( (int) $user->ID, $active_business_id, $allowed_business_ids );

	return (int) $user->ID;
}

function sm_demo_ensure_flow_catalog( Flow_Service $flow_service, Flow_Step_Service $step_service ): void {
	$catalog = array(
		'maintenance' => 'Maintenance',
		'pre_delivery' => 'Pre Delivery',
		'paperwork' => 'Paperwork',
	);

	foreach ( $catalog as $process_type => $label ) {
		$flow = $flow_service->get_flow_for_process_type( $process_type );
		if ( ! is_array( $flow ) || empty( $flow['id'] ) ) {
			$flow_id = $flow_service->create_flow(
				array(
					'name'         => 'Demo ' . $label . ' Flow',
					'process_type' => $process_type,
					'description'  => 'Auto-seeded demo flow.',
					'is_active'    => 1,
				)
			);
			if ( is_wp_error( $flow_id ) ) {
				sm_demo_fail( 'Failed creating flow ' . $process_type . ': ' . $flow_id->get_error_message() );
			}
			$flow = $flow_service->get_flow( (int) $flow_id );
		}

		if ( ! is_array( $flow ) || empty( $flow['id'] ) ) {
			sm_demo_fail( 'Could not resolve flow for process type: ' . $process_type );
		}

		$flow_id = (int) $flow['id'];
		$steps   = $step_service->get_steps_by_flow( $flow_id, false );
		if ( ! empty( $steps ) ) {
			continue;
		}

		$step_defs = array(
			array( 'key' => 'intake', 'label' => 'Intake', 'order' => 1, 'is_initial' => 1, 'is_final' => 0 ),
			array( 'key' => 'execution', 'label' => 'Execution', 'order' => 2, 'is_initial' => 0, 'is_final' => 0 ),
			array( 'key' => 'closeout', 'label' => 'Closeout', 'order' => 3, 'is_initial' => 0, 'is_final' => 1 ),
		);

		foreach ( $step_defs as $step_def ) {
			$step_id = $step_service->create_step(
				array(
					'flow_id'           => $flow_id,
					'step_key'          => $process_type . '_' . $step_def['key'],
					'step_label'        => $step_def['label'],
					'step_order'        => $step_def['order'],
					'is_initial'        => $step_def['is_initial'],
					'is_final'          => $step_def['is_final'],
					'requires_approval' => 0,
					'requires_note'     => 0,
					'is_active'         => 1,
				)
			);

			if ( is_wp_error( $step_id ) ) {
				sm_demo_fail( 'Failed creating flow step for ' . $process_type . ': ' . $step_id->get_error_message() );
			}
		}
	}
}

function sm_demo_find_process( Process_Service $process_service, int $client_id, int $vehicle_id, string $title ): ?array {
	$rows = $process_service->get_processes(
		array(
			'client_id'  => $client_id,
			'vehicle_id' => $vehicle_id,
			'per_page'   => 200,
			'orderby'    => 'id',
			'order'      => 'DESC',
		)
	);

	foreach ( $rows as $row ) {
		if ( isset( $row['title'] ) && $row['title'] === $title ) {
			return $row;
		}
	}

	return null;
}

function sm_demo_seed_business_dataset( int $business_id, string $prefix, array $mechanic_user_ids, int $admin_user_id ): array {
	$client_service       = new Client_Service();
	$vehicle_service      = new Vehicle_Service();
	$relation_service     = new Client_Vehicle_Service();
	$process_service      = new Process_Service();
	$maintenance_service  = new Maintenance_Service();
	$pipeline_service     = new Crm_Pipeline_Service();
	$task_service         = new Crm_Task_Service();
	$appointment_service  = new Appointment_Service();
	$quote_service        = new Quote_Service();
	$invoice_service      = new Invoice_Service();
	$execution_log_service = new Execution_Log_Service();

	$created = array(
		'clients'      => 0,
		'vehicles'     => 0,
		'processes'    => 0,
		'opportunities' => 0,
		'tasks'        => 0,
		'appointments' => 0,
		'quotes'       => 0,
		'invoices'     => 0,
		'payments'     => 0,
		'client_users' => 0,
		'logs'         => 0,
	);

	$base_ts = strtotime( '2026-04-05 09:00:00' );

	for ( $i = 1; $i <= 12; $i++ ) {
		$suffix     = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
		$first_name = $prefix . ' Client';
		$last_name  = $suffix;
		$email      = strtolower( $prefix ) . '.client.' . $suffix . '@supermechanic.local';
		$document   = strtoupper( $prefix ) . '-CL-' . $suffix;
		$phone      = '+1-555-' . ( 700000 + $business_id * 1000 + $i );

		$existing_client = $client_service->get_clients(
			array(
				'exact_email' => $email,
				'per_page'    => 1,
			)
		);

		if ( empty( $existing_client ) ) {
			$client_id = $client_service->create_client(
				array(
					'first_name'  => $first_name,
					'last_name'   => $last_name,
					'email'       => $email,
					'phone'       => $phone,
					'document_id' => $document,
					'notes'       => 'Auto-seeded demo client for business ' . $business_id,
				)
			);
			if ( is_wp_error( $client_id ) ) {
				sm_demo_fail( 'Failed creating client ' . $email . ': ' . $client_id->get_error_message() );
			}
			$created['clients']++;
			$client = $client_service->get_client( (int) $client_id );
		} else {
			$client = $existing_client[0];
			$client_service->update_client(
				(int) $client['id'],
				array(
					'first_name'  => $first_name,
					'last_name'   => $last_name,
					'email'       => $email,
					'phone'       => $phone,
					'document_id' => $document,
					'notes'       => 'Auto-seeded demo client for business ' . $business_id,
				)
			);
			$client = $client_service->get_client( (int) $client['id'] );
		}

		$client_id = isset( $client['id'] ) ? (int) $client['id'] : 0;
		if ( $client_id <= 0 ) {
			sm_demo_fail( 'Invalid client id for ' . $email );
		}

		$client_login = strtolower( $prefix ) . '_client_' . $suffix;
		$user_exists  = get_user_by( 'login', $client_login );
		sm_demo_ensure_user( $client_login, $email, $first_name . ' ' . $last_name, 'sm_client', $business_id, array( $business_id ), $client_id );
		if ( ! $user_exists ) {
			$created['client_users']++;
		}

		$plate   = strtoupper( $prefix ) . '-PL-' . $suffix;
		$vehicle = $vehicle_service->get_vehicles(
			array(
				'exact_plate' => $plate,
				'per_page'    => 1,
			)
		);

		if ( empty( $vehicle ) ) {
			$vehicle_id = $vehicle_service->create_vehicle(
				array(
					'client_id' => $client_id,
					'brand'     => ( 0 === $i % 2 ) ? 'Toyota' : 'Ford',
					'model'     => 'Demo Model ' . $suffix,
					'year'      => 2020 + ( $i % 5 ),
					'vin'       => strtoupper( $prefix ) . 'VIN000000' . $suffix,
					'plate'     => $plate,
					'color'     => ( 0 === $i % 2 ) ? 'Blue' : 'White',
					'notes'     => 'Auto-seeded demo vehicle.',
				)
			);
			if ( is_wp_error( $vehicle_id ) ) {
				sm_demo_fail( 'Failed creating vehicle ' . $plate . ': ' . $vehicle_id->get_error_message() );
			}
			$created['vehicles']++;
			$vehicle = $vehicle_service->get_vehicle( (int) $vehicle_id );
		} else {
			$vehicle = $vehicle[0];
		}

		$vehicle_id = isset( $vehicle['id'] ) ? (int) $vehicle['id'] : 0;
		if ( $vehicle_id <= 0 ) {
			sm_demo_fail( 'Invalid vehicle id for plate ' . $plate );
		}

		$relation_service->assign_vehicle_to_client( $client_id, $vehicle_id );

		$process_title = $prefix . ' Demo Process ' . $suffix;
		$process_type  = ( 0 === $i % 4 ) ? 'pre_delivery' : 'maintenance';
		$status        = ( 0 === $i % 5 ) ? 'pending' : 'in_progress';
		$opened_at     = gmdate( 'Y-m-d H:i:s', $base_ts + ( $i * DAY_IN_SECONDS ) );
		$due_date      = gmdate( 'Y-m-d H:i:s', $base_ts + ( $i * DAY_IN_SECONDS ) + ( 3 * DAY_IN_SECONDS ) );
		$assigned_to   = $mechanic_user_ids[ $i % count( $mechanic_user_ids ) ];

		$existing_process = sm_demo_find_process( $process_service, $client_id, $vehicle_id, $process_title );
		if ( null === $existing_process ) {
			$process_id = $process_service->create_process(
				array(
					'vehicle_id'     => $vehicle_id,
					'client_id'      => $client_id,
					'process_type'   => $process_type,
					'status'         => $status,
					'title'          => $process_title,
					'internal_notes' => 'Auto-seeded demo process for business ' . $business_id,
					'opened_at'      => $opened_at,
					'due_date'       => $due_date,
					'assigned_to'    => $assigned_to,
				)
			);
			if ( is_wp_error( $process_id ) ) {
				sm_demo_fail( 'Failed creating process ' . $process_title . ': ' . $process_id->get_error_message() );
			}
			$created['processes']++;
			$process = $process_service->get_process( (int) $process_id );
		} else {
			$process_service->update_process(
				(int) $existing_process['id'],
				array(
					'status'         => $status,
					'title'          => $process_title,
					'internal_notes' => 'Auto-seeded demo process for business ' . $business_id,
					'opened_at'      => $opened_at,
					'due_date'       => $due_date,
					'assigned_to'    => $assigned_to,
				)
			);
			$process = $process_service->get_process( (int) $existing_process['id'] );
		}

		$process_id = isset( $process['id'] ) ? (int) $process['id'] : 0;
		if ( $process_id <= 0 ) {
			sm_demo_fail( 'Invalid process id for ' . $process_title );
		}

		if ( 'maintenance' === $process_type ) {
			$maintenance_result = $maintenance_service->update_maintenance(
				$process_id,
				array(
					'mechanic_id' => $assigned_to,
				)
			);

			if ( is_wp_error( $maintenance_result ) ) {
				sm_demo_fail( 'Failed assigning maintenance mechanic for process ' . $process_id . ': ' . $maintenance_result->get_error_message() );
			}
		}

		$opportunity_title = $prefix . ' Opportunity ' . $suffix;
		$opportunities     = $pipeline_service->get_opportunities(
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
			)
		);

		$opportunity = null;
		foreach ( $opportunities as $row ) {
			if ( isset( $row['title'] ) && $row['title'] === $opportunity_title ) {
				$opportunity = $row;
				break;
			}
		}

		if ( ! is_array( $opportunity ) ) {
			$opportunity_id = $pipeline_service->create_opportunity(
				array(
					'client_id'        => $client_id,
					'vehicle_id'       => $vehicle_id,
					'process_id'       => $process_id,
					'stage'            => 'new_lead',
					'title'            => $opportunity_title,
					'estimated_value'  => 100 + ( $i * 15 ),
					'currency'         => 'USD',
					'assigned_user_id' => $assigned_to,
					'notes'            => 'Auto-seeded opportunity.',
				)
			);
			if ( is_wp_error( $opportunity_id ) ) {
				sm_demo_fail( 'Failed creating opportunity ' . $opportunity_title . ': ' . $opportunity_id->get_error_message() );
			}
			$created['opportunities']++;
			$opportunity = $pipeline_service->get_opportunity( (int) $opportunity_id );
		}

		$opportunity_id = isset( $opportunity['id'] ) ? (int) $opportunity['id'] : 0;
		if ( $opportunity_id <= 0 ) {
			sm_demo_fail( 'Invalid opportunity id for process ' . $process_id );
		}

		$task_title = $prefix . ' Task ' . $suffix;
		$tasks      = $task_service->get_tasks_by_pipeline_id( $opportunity_id );
		$task       = null;
		foreach ( is_array( $tasks ) ? $tasks : array() as $task_row ) {
			if ( isset( $task_row['title'] ) && $task_row['title'] === $task_title ) {
				$task = $task_row;
				break;
			}
		}

		$due_at = gmdate( 'Y-m-d H:i:s', $base_ts + ( ( $i % 2 === 0 ) ? -DAY_IN_SECONDS : DAY_IN_SECONDS ) );
		if ( ! is_array( $task ) ) {
			$task_id = $task_service->create_task(
				array(
					'crm_pipeline_id'  => $opportunity_id,
					'title'            => $task_title,
					'task_type'        => 'follow_up',
					'assigned_user_id' => $assigned_to,
					'due_at'           => $due_at,
					'status'           => 'pending',
					'notes'            => 'Auto-seeded task.',
				)
			);
			if ( is_wp_error( $task_id ) ) {
				sm_demo_fail( 'Failed creating task for opportunity ' . $opportunity_id . ': ' . $task_id->get_error_message() );
			}
			$created['tasks']++;
		} else {
			$task_service->update_task(
				(int) $task['id'],
				array(
					'assigned_user_id' => $assigned_to,
					'due_at'           => $due_at,
					'status'           => 'pending',
				)
			);
		}

		$appointment_start = gmdate( 'Y-m-d H:i:s', strtotime( '2026-04-05 10:00:00 +' . ( $i - 1 ) . ' days' ) );
		$appointment_date  = gmdate( 'Y-m-d', strtotime( $appointment_start ) );
		$appointment_notes = 'Auto-seeded demo appointment [' . $prefix . '-' . $suffix . ']';
		$appointments      = $appointment_service->get_appointments(
			array(
				'assigned_to' => $assigned_to,
				'client_id'   => $client_id,
				'date_from'   => $appointment_date,
				'date_to'     => $appointment_date,
				'per_page'    => 50,
				'page'        => 1,
			)
		);

		$appointment = null;
		foreach ( $appointments as $appt ) {
			$appt_notes = isset( $appt['notes'] ) ? (string) $appt['notes'] : '';
			if ( $appt_notes === $appointment_notes ) {
				$appointment = $appt;
				break;
			}
		}

		if ( ! is_array( $appointment ) ) {
			$appointment_id = $appointment_service->create_appointment(
				array(
					'client_id'          => $client_id,
					'vehicle_id'         => $vehicle_id,
					'process_id'         => $process_id,
					'assigned_to'        => $assigned_to,
					'appointment_status' => 'confirmed',
					'appointment_date'   => $appointment_date,
					'start_at'           => $appointment_start,
					'notes'              => $appointment_notes,
				)
			);
			if ( is_wp_error( $appointment_id ) ) {
				sm_demo_fail( 'Failed creating appointment for client ' . $client_id . ': ' . $appointment_id->get_error_message() );
			}
			$created['appointments']++;
		}

		$quote_note_marker = 'Auto-seeded demo quote [' . $prefix . '-' . $suffix . ']';
		$quotes            = $quote_service->get_quotes(
			array(
				'process_id' => $process_id,
				'per_page'   => 50,
			)
		);
		$quote             = null;
		foreach ( $quotes as $quote_row ) {
			if ( isset( $quote_row['notes'] ) && (string) $quote_row['notes'] === $quote_note_marker ) {
				$quote = $quote_row;
				break;
			}
		}

		if ( ! is_array( $quote ) ) {
			$quote_id = $quote_service->create_quote(
				array(
					'process_id' => $process_id,
					'client_id'  => $client_id,
					'quote_number' => 'Q-' . strtoupper( $prefix ) . '-' . $suffix,
					'status'     => 'draft',
					'currency'   => 'USD',
					'notes'      => $quote_note_marker,
					'created_by' => $admin_user_id,
				)
			);
			if ( is_wp_error( $quote_id ) ) {
				sm_demo_fail( 'Failed creating quote for process ' . $process_id . ': ' . $quote_id->get_error_message() );
			}
			$created['quotes']++;
			$quote = $quote_service->get_quote( (int) $quote_id );
		}

		$quote_id = isset( $quote['id'] ) ? (int) $quote['id'] : 0;
		if ( $quote_id > 0 ) {
			$quote_items = $quote_service->get_quote_items( $quote_id );
			if ( empty( $quote_items ) ) {
				$quote_service->add_quote_item(
					$quote_id,
					array(
						'item_type'    => 'custom',
						'label'        => 'Demo labor item',
						'description'  => 'Auto-seeded labor line',
						'quantity'     => 1,
						'unit_price'   => 120 + $i,
						'sort_order'   => 1,
						'business_id'  => $business_id,
					)
				);
			}

			$target_quote_status = ( 0 === $i % 3 ) ? 'approved' : 'sent';
			if ( isset( $quote['status'] ) && $quote['status'] !== $target_quote_status ) {
				$quote_service->update_quote(
					$quote_id,
					array(
						'status'             => $target_quote_status,
						'approved_by_client' => ( 'approved' === $target_quote_status ) ? 1 : 0,
						'approved_at'        => ( 'approved' === $target_quote_status ) ? current_time( 'mysql' ) : null,
						'created_by'         => $admin_user_id,
					)
				);
			}
		}

		$invoice_note_marker = 'Auto-seeded demo invoice [' . $prefix . '-' . $suffix . ']';
		$invoices            = $invoice_service->get_invoices(
			array(
				'process_id' => $process_id,
				'per_page'   => 50,
			)
		);
		$invoice             = null;
		foreach ( $invoices as $invoice_row ) {
			if ( isset( $invoice_row['notes'] ) && (string) $invoice_row['notes'] === $invoice_note_marker ) {
				$invoice = $invoice_row;
				break;
			}
		}

		if ( ! is_array( $invoice ) ) {
			$invoice_status = ( 0 === $i % 4 ) ? 'paid' : 'issued';
			$invoice_id     = $invoice_service->create_invoice(
				array(
					'process_id'     => $process_id,
					'quote_id'       => $quote_id,
					'client_id'      => $client_id,
					'invoice_number' => 'I-' . strtoupper( $prefix ) . '-' . $suffix,
					'status'         => $invoice_status,
					'currency'       => 'USD',
					'subtotal'       => 150 + ( $i * 10 ),
					'tax_total'      => 0,
					'discount_total' => 0,
					'grand_total'    => 150 + ( $i * 10 ),
					'amount_paid'    => 0,
					'balance_due'    => 150 + ( $i * 10 ),
					'issued_at'      => current_time( 'mysql' ),
					'due_date'       => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
					'notes'          => $invoice_note_marker,
					'created_by'     => $admin_user_id,
				)
			);

			if ( is_wp_error( $invoice_id ) ) {
				sm_demo_fail( 'Failed creating invoice for process ' . $process_id . ': ' . $invoice_id->get_error_message() );
			}
			$created['invoices']++;
			$invoice = $invoice_service->get_invoice( (int) $invoice_id );
		}

		$invoice_id = isset( $invoice['id'] ) ? (int) $invoice['id'] : 0;
		if ( $invoice_id > 0 && ( 0 === $i % 4 || 0 === $i % 5 ) ) {
			$payments = $invoice_service->get_payments( $invoice_id );
			if ( empty( $payments ) ) {
				$invoice_total = isset( $invoice['grand_total'] ) ? (float) $invoice['grand_total'] : 0.0;
				$pay_amount    = ( 0 === $i % 4 ) ? $invoice_total : round( $invoice_total * 0.5, 2 );
				$payment_id    = $invoice_service->add_payment(
					$invoice_id,
					array(
						'payment_date'   => current_time( 'mysql' ),
						'amount'         => $pay_amount,
						'payment_method' => 'cash',
						'reference'      => 'AUTO-' . $prefix . '-' . $suffix,
						'notes'          => 'Auto-seeded payment.',
						'received_by'    => $admin_user_id,
					)
				);
				if ( ! is_wp_error( $payment_id ) ) {
					$created['payments']++;
				}
			}
		}

		$logged = $execution_log_service->register_execution(
			$business_id,
			'overdue_tasks_cleanup',
			'bulk_resolve',
			( 0 === $i % 3 ) ? 'auto' : 'confirmable',
			( 0 === $i % 4 ) ? 'blocked' : 'success',
			( 0 === $i % 4 ) ? 0 : ( 1 + ( $i % 3 ) ),
			$assigned_to,
			array(
				'source'      => 'demo_seed',
				'client_id'   => $client_id,
				'process_id'  => $process_id,
				'note'        => 'Auto-seeded execution log row',
				'reason'      => ( 0 === $i % 4 ) ? 'Guardrail prevented mutation' : 'Manual/confirmable execution completed',
			)
		);
		if ( $logged ) {
			$created['logs']++;
		}
	}

	return $created;
}

$bootstrap_admin_user_id = sm_demo_require_admin();

( new Execution_Log_Installer() )->ensure_table();

$business_repository = new Business_Repository();
$business_1_id       = sm_demo_ensure_business( $business_repository, 'default', 'Super Mechanic HQ', true );
$business_2_id       = sm_demo_ensure_business( $business_repository, 'demo-business-2', 'Super Mechanic Branch 2', false );

$all_business_ids = array_values(
	array_unique(
		array_filter(
			array(
				$business_1_id,
				$business_2_id,
				(int) $business_repository->get_default_business_id(),
			)
		)
	)
);

$admin_user_id = sm_demo_ensure_primary_super_admin( $all_business_ids );

sm_demo_set_user_business_scope( $bootstrap_admin_user_id, $business_1_id, $all_business_ids );
sm_demo_set_user_business_scope( $admin_user_id, $business_1_id, $all_business_ids );
wp_set_current_user( $admin_user_id );

// Business 1 users (existing and ensured).
$mechanic_b1_users = array(
	sm_demo_ensure_user( 'sm_qa_mechanic', 'qa.mechanic@supermechanic.local', 'SM QA Mechanic', 'sm_mechanic', $business_1_id, array( $business_1_id ) ),
	sm_demo_ensure_user( 'sm_demo_mech_2', 'demo.mech2@supermechanic.local', 'SM Demo Mechanic 2', 'sm_mechanic', $business_1_id, array( $business_1_id ) ),
	sm_demo_ensure_user( 'sm_demo_mech_3', 'demo.mech3@supermechanic.local', 'SM Demo Mechanic 3', 'sm_mechanic', $business_1_id, array( $business_1_id ) ),
);

// Business 2 users.
$mechanic_b2_users = array(
	sm_demo_ensure_user( 'sm_b2_mech_1', 'b2.mech1@supermechanic.local', 'SM B2 Mechanic 1', 'sm_mechanic', $business_2_id, array( $business_2_id ) ),
	sm_demo_ensure_user( 'sm_b2_mech_2', 'b2.mech2@supermechanic.local', 'SM B2 Mechanic 2', 'sm_mechanic', $business_2_id, array( $business_2_id ) ),
	sm_demo_ensure_user( 'sm_b2_mech_3', 'b2.mech3@supermechanic.local', 'SM B2 Mechanic 3', 'sm_mechanic', $business_2_id, array( $business_2_id ) ),
);

// Ensure flows and steps for both businesses.
foreach ( array( $business_1_id, $business_2_id ) as $biz_id ) {
	sm_demo_set_user_business_scope( $admin_user_id, $biz_id, $all_business_ids );
	wp_set_current_user( $admin_user_id );
	sm_demo_ensure_flow_catalog( new Flow_Service(), new Flow_Step_Service() );
}

// Seed business 1.
sm_demo_set_user_business_scope( $admin_user_id, $business_1_id, $all_business_ids );
wp_set_current_user( $admin_user_id );
$created_b1 = sm_demo_seed_business_dataset( $business_1_id, 'B1', $mechanic_b1_users, $admin_user_id );

// Seed business 2.
sm_demo_set_user_business_scope( $admin_user_id, $business_2_id, $all_business_ids );
wp_set_current_user( $admin_user_id );
$created_b2 = sm_demo_seed_business_dataset( $business_2_id, 'B2', $mechanic_b2_users, $admin_user_id );

// Return admin context to business 1 by default.
sm_demo_set_user_business_scope( $admin_user_id, $business_1_id, $all_business_ids );
wp_set_current_user( $admin_user_id );

$dashboard_service = new Dashboard_Service();

sm_demo_out( '=== Multi-business demo seed complete ===' );
sm_demo_out( 'Business IDs: B1=' . $business_1_id . ' | B2=' . $business_2_id );
sm_demo_out( 'B1 created: ' . wp_json_encode( $created_b1 ) );
sm_demo_out( 'B2 created: ' . wp_json_encode( $created_b2 ) );

foreach ( array( $business_1_id => $mechanic_b1_users, $business_2_id => $mechanic_b2_users ) as $biz_id => $mechanic_ids ) {
	sm_demo_set_user_business_scope( $admin_user_id, $biz_id, $all_business_ids );
	wp_set_current_user( $admin_user_id );
	sm_demo_out( '--- Mechanic KPIs business ' . $biz_id . ' ---' );
	foreach ( $mechanic_ids as $mechanic_id ) {
		$kpis = $dashboard_service->get_mechanic_kpis( $mechanic_id );
		sm_demo_out( 'mechanic ' . $mechanic_id . ': ' . wp_json_encode( $kpis ) );
	}
}

sm_demo_out( 'Done.' );
