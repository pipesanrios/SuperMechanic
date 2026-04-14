<?php
/**
 * Plugin Name: Mekvort
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

// Register activation and deactivation hooks with scheduler wiring.
register_activation_hook( SM_PLUGIN_FILE, 'sm_activate_plugin' );
register_deactivation_hook( SM_PLUGIN_FILE, 'sm_deactivate_plugin' );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function sm_activate_plugin() {
	Super_Mechanic\Activator::activate();
	$superadmin_bootstrap = new Super_Mechanic\Users\Superadmin_Bootstrap_Service();
	$superadmin_bootstrap->ensure_bootstrap_superadmin();
	Super_Mechanic\CRM\Crm_Scheduler_Service::ensure_scheduled_event();
}

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function sm_deactivate_plugin() {
	Super_Mechanic\CRM\Crm_Scheduler_Service::clear_scheduled_event();
	Super_Mechanic\Deactivator::deactivate();
}

/**
 * Resolve operational locale for plugin domain only.
 *
 * @return string
 */
function sm_get_operational_locale() {
	$allowed_locales = array( 'en_US', 'es_ES', 'it_IT' );
	$default_locale  = 'en_US';

	$settings = get_option( 'sm_settings', array() );
	if ( is_array( $settings ) && isset( $settings['business'] ) && is_array( $settings['business'] ) ) {
		$candidate = isset( $settings['business']['locale'] ) ? sanitize_text_field( (string) $settings['business']['locale'] ) : '';
		if ( in_array( $candidate, $allowed_locales, true ) ) {
			return $candidate;
		}
	}

	$legacy = get_option( 'super_mechanic_settings', array() );
	if ( is_array( $legacy ) ) {
		$candidate = isset( $legacy['language_locale'] ) ? sanitize_text_field( (string) $legacy['language_locale'] ) : '';
		if ( in_array( $candidate, $allowed_locales, true ) ) {
			return $candidate;
		}
	}

	return $default_locale;
}

/**
 * Filter plugin locale for Super Mechanic textdomain only.
 *
 * @param string $locale Locale.
 * @param string $domain Domain.
 * @return string
 */
function sm_filter_plugin_locale( $locale, $domain ) {
	if ( 'super-mechanic' !== $domain ) {
		return $locale;
	}

	return sm_get_operational_locale();
}

/**
 * Load plugin textdomain on init to avoid early i18n loading notices.
 *
 * @return void
 */
function sm_load_textdomain() {
	add_filter( 'plugin_locale', 'sm_filter_plugin_locale', 10, 2 );
	load_plugin_textdomain( 'super-mechanic', false, dirname( plugin_basename( SM_PLUGIN_FILE ) ) . '/languages' );
	remove_filter( 'plugin_locale', 'sm_filter_plugin_locale', 10 );
}

/**
 * Initialize the plugin once other plugins have loaded.
 *
 * @return void
 */
function sm_run_plugin() {
	$plugin = new Super_Mechanic\Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'sm_run_plugin' );
add_action( 'init', 'sm_load_textdomain', 0 );
