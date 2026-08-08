<?php
/**
 * Init: bootstrap all plugin modules.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Init {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot(): void {
		// Load text domain
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Register REST routes
		( new Rest() )->register();

		// Register assets
		( new Assets() )->register();

		// Admin settings (only in admin)
		if ( is_admin() ) {
			require_once WCPS_PATH . 'admin/settings-page.php';
			( new \WCPS\Admin\SettingsPage() )->register();
		}

		// Declare WooCommerce HPOS compatibility
		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					WCPS_FILE,
					true
				);
			}
		} );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-product-sidebar',
			false,
			dirname( plugin_basename( WCPS_FILE ) ) . '/languages'
		);
	}
}
