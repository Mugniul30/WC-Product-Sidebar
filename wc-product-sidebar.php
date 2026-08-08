<?php
/**
 * Plugin Name:       WC Product Sidebar
 * Plugin URI:        https://github.com/Mugniul30/WC-Product-Sidebar.git
 * Description:       A React-based WooCommerce product sidebar with slide-in panel, category grouping, and AJAX add-to-cart.
 * Version:           2.0.0
 * Author:            Mugniul Afif
 * Author URI:        https://github.com/Mugniul30/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-product-sidebar
 * Domain Path:       /languages

 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'WCPS_VERSION',      '2.4.0' );
define( 'WCPS_FILE',         __FILE__ );
define( 'WCPS_PATH',         plugin_dir_path( __FILE__ ) );
define( 'WCPS_URL',          plugin_dir_url( __FILE__ ) );
define( 'WCPS_ASSETS_URL',   WCPS_URL . 'assets/' );
define( 'WCPS_BUILD_URL',    WCPS_URL . 'frontend/build/' );
define( 'WCPS_BUILD_PATH',   WCPS_PATH . 'frontend/build/' );

/**
 * Check WooCommerce is active before loading the plugin.
 */
function wcps_check_dependencies(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WC Product Sidebar requires WooCommerce to be installed and active.', 'wc-product-sidebar' )
			);
		} );
		return false;
	}
	return true;
}

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function wcps_init(): void {
	if ( ! wcps_check_dependencies() ) {
		return;
	}

	// Autoload includes
	$files = [
		'includes/class-security.php',
		'includes/class-settings.php',
		'includes/class-rest.php',
		'includes/class-assets.php',
		'includes/class-init.php',
	];

	foreach ( $files as $file ) {
		$path = WCPS_PATH . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	// Boot the main init class
	\WCPS\Init::instance();
}
add_action( 'plugins_loaded', 'wcps_init' );

/**
 * Activation hook: flush rewrite rules & set defaults.
 */
register_activation_hook( __FILE__, function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'WC Product Sidebar requires WooCommerce.', 'wc-product-sidebar' ) );
	}

	// Load settings class for defaults
	require_once WCPS_PATH . 'includes/class-settings.php';

	// Set default options if not already set
	if ( ! get_option( 'wcps_settings' ) ) {
		update_option( 'wcps_settings', \WCPS\Settings::defaults() );
	}

	flush_rewrite_rules();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
	delete_transient( 'wcps_products_cache' );
	flush_rewrite_rules();
} );
