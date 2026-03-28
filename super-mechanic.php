<?php
/**
 * Plugin Name: Super Mechanic
 * Plugin URI: https://mardisom.dev/super-mechanic
 * Description: Base modular plugin scaffold for workshop management.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Mardisom Devs
 * Author URI: https://mardisom.com
 * Text Domain: super-mechanic
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
if ( ! defined( 'SM_PLUGIN_VERSION' ) ) {
    define( 'SM_PLUGIN_VERSION', '0.1.0' );
}

if ( ! defined( 'SM_PLUGIN_FILE' ) ) {
    define( 'SM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'SM_PLUGIN_PATH' ) ) {
    define( 'SM_PLUGIN_PATH', plugin_dir_path( SM_PLUGIN_FILE ) );
}

if ( ! defined( 'SM_PLUGIN_URL' ) ) {
    define( 'SM_PLUGIN_URL', plugin_dir_url( SM_PLUGIN_FILE ) );
}

// Autoloader.
require_once SM_PLUGIN_PATH . 'includes/autoloader.php';

// Register activation and deactivation hooks.  Use unqualified names; leading
// backslashes are not required and can confuse call_user_func when the class
// isn't yet loaded.
register_activation_hook( SM_PLUGIN_FILE, array( 'Super_Mechanic\\Activator', 'activate' ) );
register_deactivation_hook( SM_PLUGIN_FILE, array( 'Super_Mechanic\\Deactivator', 'deactivate' ) );

// Initialize the plugin once other plugins have loaded.
function sm_run_plugin() {
    load_plugin_textdomain( 'super-mechanic', false, dirname( plugin_basename( SM_PLUGIN_FILE ) ) . '/languages' );

    $plugin = new Super_Mechanic\Plugin();
    $plugin->init();
}

add_action( 'plugins_loaded', 'sm_run_plugin' );
