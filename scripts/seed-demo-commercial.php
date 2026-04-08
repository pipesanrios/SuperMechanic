<?php
/**
 * Seed commercial demo dataset.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

use Super_Mechanic\Demo\Demo_Service;

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

function sm_demo54d_out( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

function sm_demo54d_fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

$business_id = 0;
$enable_mode = false;

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--business=' ) ) {
		$business_id = absint( substr( $arg, strlen( '--business=' ) ) );
	}
	if ( '--enable-demo' === $arg ) {
		$enable_mode = true;
	}
}

$service = new Demo_Service();
$result  = $service->seed_demo_dataset( $business_id );

if ( is_wp_error( $result ) ) {
	sm_demo54d_fail( 'Demo seed failed: ' . $result->get_error_message() );
}

if ( $enable_mode ) {
	$service->enable_demo_mode();
}

$state = $service->get_demo_state( isset( $result['business_id'] ) ? absint( $result['business_id'] ) : 0 );

sm_demo54d_out( 'Demo commercial seed completed.' );
sm_demo54d_out( wp_json_encode( $state ) );
