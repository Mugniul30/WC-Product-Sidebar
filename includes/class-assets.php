<?php
/**
 * Assets: enqueue frontend scripts/styles and pass config to React.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	/**
	 * Enqueue frontend React app.
	 */
	public function enqueue_frontend(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$settings  = Settings::all();
		$css_vars  = Settings::build_css_vars();

		// --- Google Fonts ---
		if ( ! empty( $settings['google_font'] ) ) {
			$font_slug = urlencode( $settings['google_font'] );
			wp_enqueue_style(
				'wcps-google-font',
				"https://fonts.googleapis.com/css2?family={$font_slug}:wght@400;500;600;700&display=swap",
				[],
				null
			);
		}

		// --- Main CSS (fallback / base styles) ---
		wp_enqueue_style(
			'wcps-frontend',
			WCPS_BUILD_URL . 'main.css',
			[],
			$this->asset_version( WCPS_BUILD_PATH . 'main.css' )
		);

		// Add CSS custom properties inline and explicitly map every visual
		// setting to the storefront selectors. This makes the admin settings
		// authoritative even if the compiled React bundle is cached.
		wp_add_inline_style(
			'wcps-frontend',
			":root { {$css_vars} }\n" . $this->build_dynamic_css( $settings )
		);

		// --- React app bundle ---
		$js_file = WCPS_BUILD_PATH . 'bundle.js';

		wp_enqueue_script(
			'wcps-app',
			WCPS_BUILD_URL . 'bundle.js',
			[],
			$this->asset_version( $js_file ),
			true  // load in footer
		);
		wp_script_add_data( 'wcps-app', 'defer', true );

		// Fresh settings bridge: this runs after the app and bypasses page-cache
		// staleness by fetching the current admin settings from REST.
		wp_enqueue_script(
			'wcps-frontend-settings',
			WCPS_ASSETS_URL . 'js/frontend-settings.js',
			[ 'wcps-app' ],
			WCPS_VERSION,
			true
		);

		// --- Pass data to React via window.wcpsConfig ---
		$config = [
			'apiUrl'       => esc_url( rest_url( 'wc-ps/v1/products' ) ),
			'settingsUrl'  => esc_url( rest_url( 'wc-ps/v1/settings' ) ),
			'cartUrl'      => esc_url( rest_url( 'wc-ps/v1/cart/add' ) ),
			'nonce'        => Security::create_nonce(),
			'wpNonce'      => wp_create_nonce( 'wp_rest' ),
			'cssVars'      => [
				'bg'         => $settings['bg_color'],
				'icon'       => $settings['icon_color'],
				'ribbon'     => $settings['ribbon_color'],
				'fontColor'  => $settings['font_color'],
				'fontSize'   => $settings['font_size'],
				'fontFamily' => $settings['font_family'] !== 'inherit' && $settings['font_family'] !== ''
					? sprintf( '"%s", sans-serif', $settings['font_family'] )
					: 'inherit',
			],
			'settings'     => [
				'showCategoryHeadings' => (bool) $settings['show_category_headings'],
				'featuredFirst'        => (bool) $settings['featured_first'],
				'buttonLabel'          => esc_html( $settings['button_label'] ),
				'ribbonPosition'       => esc_attr( $settings['ribbon_position'] ),
				'hiddenCategories'     => array_map( 'intval', (array) $settings['hidden_categories'] ),
			],
			'cartPageUrl'  => esc_url( wc_get_checkout_url() ),
			'i18n'         => [
				'searchPlaceholder' => __( 'Search products…', 'wc-product-sidebar' ),
				'addToCart'         => __( 'Add to Cart', 'wc-product-sidebar' ),
				'outOfStock'        => __( 'Out of Stock', 'wc-product-sidebar' ),
				'featured'          => __( 'Featured', 'wc-product-sidebar' ),
				'close'             => __( 'Close', 'wc-product-sidebar' ),
				'viewCart'          => __( 'Direct Checkout', 'wc-product-sidebar' ),
				'loading'           => __( 'Loading products…', 'wc-product-sidebar' ),
				'noProducts'        => __( 'No products found.', 'wc-product-sidebar' ),
				'added'             => __( 'Added!', 'wc-product-sidebar' ),
			],
		];

		wp_localize_script( 'wcps-app', 'wcpsConfig', $config );
	}

	/**
	 * Enqueue admin settings page assets.
	 */
	public function enqueue_admin( string $hook ): void {
		if ( strpos( $hook, 'wcps-settings' ) === false ) {
			return;
		}

		// WordPress color picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'wcps-admin',
			WCPS_ASSETS_URL . 'css/admin.css',
			[ 'wp-color-picker' ],
			WCPS_VERSION
		);

		wp_enqueue_script(
			'wcps-admin-js',
			WCPS_ASSETS_URL . 'js/admin.js',
			[ 'wp-color-picker', 'jquery' ],
			WCPS_VERSION,
			true
		);
	}

	/**
	 * Build scoped CSS from the saved admin settings.
	 *
	 * Keep this server-side so appearance settings do not depend on a
	 * particular React build being regenerated.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function build_dynamic_css( array $s ): string {
		$bg         = esc_attr( $s['bg_color'] );
		$icon       = esc_attr( $s['icon_color'] );
		$ribbon     = esc_attr( $s['ribbon_color'] );
		$font_color = esc_attr( $s['font_color'] );
		$font_size  = esc_attr( $s['font_size'] );
		$effective_font = ! empty( $s['google_font'] ) ? $s['google_font'] : $s['font_family'];
		$font_family = $effective_font !== 'inherit' && $effective_font !== ''
			? sprintf( '"%s", sans-serif', esc_attr( $effective_font ) )
			: 'inherit';

		return sprintf(
			'
			#wcps-root {
				--wcps-bg: %1$s;
				--wcps-icon: %2$s;
				--wcps-ribbon: %3$s;
				--wcps-font-color: %4$s;
				--wcps-font-size: %5$s;
				--wcps-font-family: %6$s;
				font-family: var(--wcps-font-family) !important;
				color: var(--wcps-font-color) !important;
				font-size: var(--wcps-font-size) !important;
			}
			#wcps-root .wcps-sidebar {
				background: var(--wcps-bg) !important;
				font-family: var(--wcps-font-family) !important;
				color: var(--wcps-font-color) !important;
			}
			#wcps-root .wcps-ribbon-btn,
			#wcps-root .wcps-sidebar-header,
			#wcps-root .wcps-category-header,
			#wcps-root .wcps-atc-btn,
			#wcps-root .wcps-view-cart-btn {
				background-color: var(--wcps-ribbon) !important;
			}
			#wcps-root .wcps-ribbon-icon,
			#wcps-root .wcps-sidebar-icon {
				color: var(--wcps-icon) !important;
			}
			#wcps-root .wcps-ribbon-btn {
				%s: 0 !important;
				%s: auto !important;
			}
			#wcps-root .wcps-sidebar {
				%s: 0 !important;
				%s: auto !important;
			}
			#wcps-root .wcps-product-name,
			#wcps-root .wcps-product-info,
			#wcps-root .wcps-sidebar-body {
				color: var(--wcps-font-color) !important;
				font-family: var(--wcps-font-family) !important;
			}
			#wcps-root .wcps-product-name {
				color: var(--wcps-font-color) !important;
			}
			#wcps-root .wcps-product-price {
				color: var(--wcps-ribbon) !important;
			}
			#wcps-root .wcps-search-input:focus {
				border-color: var(--wcps-ribbon) !important;
			}
			',
			$bg,
			$icon,
			$ribbon,
			$font_color,
			$font_size,
			$font_family,
			'left' === $s['ribbon_position'] ? 'left' : 'right',
			'left' === $s['ribbon_position'] ? 'right' : 'left'
		);
	}

	/**
	 * Determine whether to load frontend assets.
	 */
	private function should_load(): bool {
		// Load everywhere on the frontend (sidebar is a floating widget)
		return ! is_admin();
	}

	/**
	 * Get a cache-busted version string from file mtime.
	 */
	private function asset_version( string $path ): string {
		if ( file_exists( $path ) ) {
			return WCPS_VERSION . '-' . (string) filemtime( $path );
		}
		return WCPS_VERSION;
	}
}
