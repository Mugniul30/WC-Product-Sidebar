<?php
/**
 * Modern admin settings page.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS\Admin;

use WCPS\Security;
use WCPS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_wcps_bust_cache', [ $this, 'handle_bust_cache' ] );
		add_action( 'wp_ajax_wcps_reset_settings', [ $this, 'handle_reset_settings' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Product Sidebar', 'wc-product-sidebar' ),
			__( 'Product Sidebar', 'wc-product-sidebar' ),
			'manage_woocommerce',
			'wcps-settings',
			[ $this, 'render_page' ]
		);
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['wcps_save'], $_POST['wcps_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['wcps_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'wcps_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wc-product-sidebar' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wc-product-sidebar' ) );
		}

		$raw = wp_unslash( $_POST );

		// Checkboxes are absent from POST when disabled, so explicitly persist
		// false values rather than accidentally retaining the previous value.
		$raw['show_category_headings'] = isset( $raw['show_category_headings'] );
		$raw['featured_first']         = isset( $raw['featured_first'] );
		$raw['hidden_categories']      = isset( $raw['hidden_categories'] ) && is_array( $raw['hidden_categories'] )
			? $raw['hidden_categories']
			: [];

		$saved = Settings::sanitize_and_save( $raw );

		// Remove product cache immediately because visibility/order settings
		// affect the REST product response.
		delete_transient( 'wcps_products_cache' );

		// Force a clean option read for this request and make the save outcome
		// explicit in the URL after redirect.
		wp_safe_redirect(
			add_query_arg(
				[
					'page'       => 'wcps-settings',
					'wcps_saved' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_bust_cache(): void {
		check_ajax_referer( 'wcps_bust_cache', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wc-product-sidebar' ) ], 403 );
		}

		delete_transient( 'wcps_products_cache' );
		wp_send_json_success( [ 'message' => __( 'Product cache cleared.', 'wc-product-sidebar' ) ] );
	}

	public function handle_reset_settings(): void {
		check_ajax_referer( 'wcps_reset_settings', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wc-product-sidebar' ) ], 403 );
		}

		$defaults = Settings::defaults();
		update_option( 'wcps_settings', $defaults );
		delete_transient( 'wcps_products_cache' );

		wp_send_json_success(
			[
				'message'  => __( 'Settings restored to defaults.', 'wc-product-sidebar' ),
				'settings' => $defaults,
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = Settings::all();
		$cats     = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( isset( $_GET['wcps_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wcps_saved'] ) ) ) {
			add_settings_error(
				'wcps_messages',
				'wcps_saved',
				__( 'Settings saved and the storefront configuration has been updated.', 'wc-product-sidebar' ),
				'updated'
			);
		}

		settings_errors( 'wcps_messages' );

		require_once WCPS_PATH . 'admin/fields/color-field.php';
		require_once WCPS_PATH . 'admin/fields/font-field.php';
		require_once WCPS_PATH . 'admin/fields/toggle-field.php';

		$category_count = ! is_wp_error( $cats ) ? count( $cats ) : 0;
		$hidden_count   = count( (array) $settings['hidden_categories'] );
		?>
		<div class="wrap wcps-admin-wrap">
			<div class="wcps-admin-shell">

				<header class="wcps-admin-header">
					<div class="wcps-brand">
						<div class="wcps-brand-mark" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
								<path d="M5 7.5h14M7 3.5h10l2 4v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-12l2-4Z"/>
								<path d="M9 11.5h6M9 15.5h4"/>
							</svg>
						</div>
						<div>
							<div class="wcps-eyebrow"><?php esc_html_e( 'WooCommerce extension', 'wc-product-sidebar' ); ?></div>
							<h1><?php esc_html_e( 'Product Sidebar', 'wc-product-sidebar' ); ?></h1>
							<p><?php esc_html_e( 'Configure the storefront product drawer, appearance and product visibility.', 'wc-product-sidebar' ); ?></p>
						</div>
					</div>

					<div class="wcps-header-actions">
						<span class="wcps-status-pill">
							<span class="wcps-status-dot"></span>
							<?php esc_html_e( 'Active', 'wc-product-sidebar' ); ?>
						</span>
						<a class="button wcps-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Preview Store', 'wc-product-sidebar' ); ?>
						</a>
					</div>
				</header>

				<div class="wcps-stat-grid">
					<div class="wcps-stat-card">
						<span class="wcps-stat-icon">01</span>
						<div><strong><?php echo esc_html( $category_count ); ?></strong><span><?php esc_html_e( 'Product categories', 'wc-product-sidebar' ); ?></span></div>
					</div>
					<div class="wcps-stat-card">
						<span class="wcps-stat-icon">02</span>
						<div><strong><?php echo esc_html( $hidden_count ); ?></strong><span><?php esc_html_e( 'Hidden categories', 'wc-product-sidebar' ); ?></span></div>
					</div>
					<div class="wcps-stat-card">
						<span class="wcps-stat-icon">03</span>
						<div><strong><?php echo esc_html( $settings['ribbon_position'] === 'left' ? 'L' : 'R' ); ?></strong><span><?php esc_html_e( 'Ribbon position', 'wc-product-sidebar' ); ?></span></div>
					</div>
					<div class="wcps-stat-card">
						<span class="wcps-stat-icon">04</span>
						<div><strong><?php echo esc_html( $settings['font_size'] ); ?></strong><span><?php esc_html_e( 'Base font size', 'wc-product-sidebar' ); ?></span></div>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wcps-settings' ) ); ?>" id="wcps-settings-form" novalidate>
					<?php wp_nonce_field( 'wcps_save_settings', 'wcps_nonce' ); ?>
					<input type="hidden" name="wcps_save" value="1" />

					<nav class="wcps-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'wc-product-sidebar' ); ?>">
						<button type="button" class="wcps-tab is-active" data-tab="appearance">
							<span class="wcps-tab-index">01</span><?php esc_html_e( 'Appearance', 'wc-product-sidebar' ); ?>
						</button>
						<button type="button" class="wcps-tab" data-tab="behavior">
							<span class="wcps-tab-index">02</span><?php esc_html_e( 'Behavior', 'wc-product-sidebar' ); ?>
						</button>
						<button type="button" class="wcps-tab" data-tab="visibility">
							<span class="wcps-tab-index">03</span><?php esc_html_e( 'Visibility', 'wc-product-sidebar' ); ?>
						</button>
					</nav>

					<div class="wcps-tab-panel is-active" data-panel="appearance">
						<div class="wcps-layout">
							<section class="wcps-card">
								<div class="wcps-card-heading">
									<div>
										<span class="wcps-section-kicker"><?php esc_html_e( 'Visual system', 'wc-product-sidebar' ); ?></span>
										<h2><?php esc_html_e( 'Colors & typography', 'wc-product-sidebar' ); ?></h2>
										<p><?php esc_html_e( 'Control the visual language used by the sidebar and trigger.', 'wc-product-sidebar' ); ?></p>
									</div>
								</div>
								<div class="wcps-field-grid">
									<?php
									\WCPS\Admin\Fields\color_field( 'bg_color', __( 'Sidebar background', 'wc-product-sidebar' ), $settings['bg_color'] );
									\WCPS\Admin\Fields\color_field( 'ribbon_color', __( 'Ribbon color', 'wc-product-sidebar' ), $settings['ribbon_color'] );
									\WCPS\Admin\Fields\color_field( 'icon_color', __( 'Accent / icon color', 'wc-product-sidebar' ), $settings['icon_color'] );
									\WCPS\Admin\Fields\color_field( 'font_color', __( 'Text color', 'wc-product-sidebar' ), $settings['font_color'] );
								?>
								</div>
								<div class="wcps-divider"></div>
								<?php \WCPS\Admin\Fields\font_field( $settings ); ?>
							</section>

							<aside class="wcps-card wcps-live-card">
								<div class="wcps-card-heading">
									<div>
										<span class="wcps-section-kicker"><?php esc_html_e( 'Live preview', 'wc-product-sidebar' ); ?></span>
										<h2><?php esc_html_e( 'Storefront trigger', 'wc-product-sidebar' ); ?></h2>
									</div>
									<span class="wcps-live-badge"><?php esc_html_e( 'LIVE', 'wc-product-sidebar' ); ?></span>
								</div>
								<div class="wcps-device-preview">
									<div class="wcps-device-top"><span></span><span></span><span></span></div>
									<div class="wcps-device-body">
										<div class="wcps-preview-lines"><i></i><i></i><i></i><i></i></div>
										<div id="wcps-ribbon-preview" class="wcps-ribbon-preview"
											style="background:<?php echo esc_attr( $settings['ribbon_color'] ); ?>;color:<?php echo esc_attr( $settings['icon_color'] ); ?>">
											<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 7.5h14M7 3.5h10l2 4v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-12l2-4Z"/></svg>
											<span class="wcps-ribbon-label"><?php echo esc_html( $settings['button_label'] ); ?></span>
										</div>
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Preview updates as you change the label and colors.', 'wc-product-sidebar' ); ?></p>
							</aside>
						</div>

						<section class="wcps-card wcps-spacing-card">
							<div class="wcps-card-heading">
								<div>
									<span class="wcps-section-kicker"><?php esc_html_e( 'Trigger control', 'wc-product-sidebar' ); ?></span>
									<h2><?php esc_html_e( 'Ribbon settings', 'wc-product-sidebar' ); ?></h2>
								</div>
							</div>
							<div class="wcps-field-grid">
								<div class="wcps-field-row">
									<label for="button_label"><?php esc_html_e( 'Button label', 'wc-product-sidebar' ); ?></label>
									<input type="text" id="button_label" name="button_label" value="<?php echo esc_attr( $settings['button_label'] ); ?>" class="regular-text" maxlength="40" />
									<p class="description"><?php esc_html_e( 'Keep it short for a compact trigger.', 'wc-product-sidebar' ); ?></p>
								</div>
								<div class="wcps-field-row">
									<label for="ribbon_position"><?php esc_html_e( 'Ribbon position', 'wc-product-sidebar' ); ?></label>
									<select id="ribbon_position" name="ribbon_position">
										<option value="left" <?php selected( $settings['ribbon_position'], 'left' ); ?>><?php esc_html_e( 'Left edge', 'wc-product-sidebar' ); ?></option>
										<option value="right" <?php selected( $settings['ribbon_position'], 'right' ); ?>><?php esc_html_e( 'Right edge', 'wc-product-sidebar' ); ?></option>
									</select>
								</div>
							</div>
						</section>
					</div>

					<div class="wcps-tab-panel" data-panel="behavior">
						<section class="wcps-card">
							<div class="wcps-card-heading">
								<div>
									<span class="wcps-section-kicker"><?php esc_html_e( 'Storefront behavior', 'wc-product-sidebar' ); ?></span>
									<h2><?php esc_html_e( 'How products are presented', 'wc-product-sidebar' ); ?></h2>
									<p><?php esc_html_e( 'These controls directly affect the React product sidebar.', 'wc-product-sidebar' ); ?></p>
								</div>
							</div>
							<div class="wcps-feature-grid">
								<?php
								\WCPS\Admin\Fields\toggle_field(
									'show_category_headings',
									__( 'Show category headings', 'wc-product-sidebar' ),
									(bool) $settings['show_category_headings'],
									__( 'Group products into collapsible category sections.', 'wc-product-sidebar' )
								);
								\WCPS\Admin\Fields\toggle_field(
									'featured_first',
									__( 'Featured products first', 'wc-product-sidebar' ),
									(bool) $settings['featured_first'],
									__( 'Prioritize WooCommerce featured products in the list.', 'wc-product-sidebar' )
								);
								?>
							</div>
						</section>

						<section class="wcps-card wcps-info-card">
							<div class="wcps-info-icon">i</div>
							<div>
								<h3><?php esc_html_e( 'AJAX cart & search', 'wc-product-sidebar' ); ?></h3>
								<p><?php esc_html_e( 'Live product search, quantity controls and AJAX add-to-cart are built into the storefront component. No additional switch is required.', 'wc-product-sidebar' ); ?></p>
							</div>
						</section>
					</div>

					<div class="wcps-tab-panel" data-panel="visibility">
						<section class="wcps-card">
							<div class="wcps-card-heading wcps-card-heading--split">
								<div>
									<span class="wcps-section-kicker"><?php esc_html_e( 'Catalog visibility', 'wc-product-sidebar' ); ?></span>
									<h2><?php esc_html_e( 'Hide product categories', 'wc-product-sidebar' ); ?></h2>
									<p><?php esc_html_e( 'Hidden categories and their products will be excluded from the sidebar.', 'wc-product-sidebar' ); ?></p>
								</div>
								<div class="wcps-category-tools">
									<input type="search" id="wcps-category-search" placeholder="<?php esc_attr_e( 'Search categories…', 'wc-product-sidebar' ); ?>" />
									<button type="button" class="button wcps-select-visible"><?php esc_html_e( 'Select visible', 'wc-product-sidebar' ); ?></button>
									<button type="button" class="button wcps-clear-visible"><?php esc_html_e( 'Clear visible', 'wc-product-sidebar' ); ?></button>
								</div>
							</div>
							<div class="wcps-cat-list" id="wcps-category-list">
								<?php if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) : ?>
									<?php foreach ( $cats as $cat ) : ?>
										<label class="wcps-cat-item" data-category="<?php echo esc_attr( strtolower( $cat->name ) ); ?>">
											<input type="checkbox" name="hidden_categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, (array) $settings['hidden_categories'], false ) ); ?> />
											<span class="wcps-cat-copy">
												<strong><?php echo esc_html( $cat->name ); ?></strong>
												<small><?php echo esc_html( number_format_i18n( $cat->count ) ); ?> <?php esc_html_e( 'products', 'wc-product-sidebar' ); ?></small>
											</span>
											<span class="wcps-cat-check" aria-hidden="true">
												<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
											</span>
										</label>
									<?php endforeach; ?>
								<?php else : ?>
									<div class="wcps-empty-state"><?php esc_html_e( 'No product categories found.', 'wc-product-sidebar' ); ?></div>
								<?php endif; ?>
							</div>
						</section>
					</div>

					<div class="wcps-savebar">
						<div>
							<strong><?php esc_html_e( 'Product Sidebar settings', 'wc-product-sidebar' ); ?></strong>
							<span class="wcps-unsaved" hidden><?php esc_html_e( 'Unsaved changes', 'wc-product-sidebar' ); ?></span>
						</div>
						<div class="wcps-save-actions">
							<button type="button" class="button wcps-reset-settings" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcps_reset_settings' ) ); ?>"><?php esc_html_e( 'Reset', 'wc-product-sidebar' ); ?></button>
							<button type="button" class="button wcps-bust-cache" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcps_bust_cache' ) ); ?>"><?php esc_html_e( 'Clear product cache', 'wc-product-sidebar' ); ?></button>
							<button type="submit" class="button button-primary wcps-save-button"><?php esc_html_e( 'Save changes', 'wc-product-sidebar' ); ?></button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
