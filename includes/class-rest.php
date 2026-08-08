<?php
/**
 * REST API: custom /wc-ps/v1/products and /wc-ps/v1/cart/add endpoints.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {

	private const NAMESPACE = 'wc-ps/v1';
	private const CACHE_KEY = 'wcps_products_cache';
	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/* ─────────────────────────────────────────────────────────────────────
	 * Bootstrap
	 * ───────────────────────────────────────────────────────────────────── */

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		add_action( 'save_post_product',                      [ $this, 'bust_cache' ] );
		add_action( 'delete_post',                            [ $this, 'bust_cache' ] );
		add_action( 'woocommerce_delete_product_transients',  [ $this, 'bust_cache' ] );
	}

	public function register_routes(): void {

		// ── GET /wc-ps/v1/products ──────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/products',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_products' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'search' => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
					'bust'   => [ 'sanitize_callback' => 'absint',              'default' => 0  ],
				],
			]
		);

		// ── POST /wc-ps/v1/cart/add ─────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/cart/add',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_to_cart' ],
				'permission_callback' => static function ( \WP_REST_Request $request ): bool|\WP_Error {
					// Accept WordPress REST nonce (X-WP-Nonce header) OR plugin nonce in body.
					$wp_nonce   = $request->get_header( 'X-WP-Nonce' );
					$body_nonce = $request->get_param( 'nonce' );

					if ( $wp_nonce && wp_verify_nonce( $wp_nonce, 'wp_rest' ) ) {
						return true;
					}
					if ( $body_nonce && Security::verify_nonce( $body_nonce ) ) {
						return true;
					}

					return new \WP_Error(
						'wcps_forbidden',
						__( 'Security check failed.', 'wc-product-sidebar' ),
						[ 'status' => 403 ]
					);
				},
				'args' => [
					'product_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'quantity' => [
						'sanitize_callback' => 'absint',
						'default'           => 1,
					],
					'nonce' => [
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					],
				],
			]
		);
	}

	/**
	 * Return the current admin configuration.
	 *
	 * This endpoint is intentionally uncached. It lets the frontend obtain
	 * fresh settings even when the shop page itself is served by a page/CDN
	 * cache.
	 */
	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$s = Settings::all();

		$response = new \WP_REST_Response(
			[
				'bgColor'              => $s['bg_color'],
				'iconColor'            => $s['icon_color'],
				'ribbonColor'          => $s['ribbon_color'],
				'fontColor'            => $s['font_color'],
				'fontSize'             => $s['font_size'],
				'fontFamily'           => ! empty( $s['google_font'] )
					? $s['google_font']
					: $s['font_family'],
				'buttonLabel'          => $s['button_label'],
				'ribbonPosition'       => $s['ribbon_position'],
				'showCategoryHeadings' => (bool) $s['show_category_headings'],
				'featuredFirst'        => (bool) $s['featured_first'],
				'hiddenCategories'     => array_map( 'intval', (array) $s['hidden_categories'] ),
				'version'              => WCPS_VERSION . '-' . wp_date( 'U' ),
			],
			200
		);

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * GET /wc-ps/v1/products
	 * ───────────────────────────────────────────────────────────────────── */

	public function get_products( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$search   = (string) $request->get_param( 'search' );
		$bust     = (bool)   $request->get_param( 'bust' );
		$settings = Settings::all();

		// Serve from transient cache on full (non-search) requests
		if ( ! $search && ! $bust ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( $cached !== false ) {
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		$hidden_cats = array_map( 'intval', (array) ( $settings['hidden_categories'] ?? [] ) );

		$args = [
			'status'   => 'publish',
			'limit'    => 200,
			'paginate' => false,
			'return'   => 'objects',
			'orderby'  => 'title',
			'order'    => 'ASC',
		];

		if ( $search ) {
			$args['s'] = $search;
		}

		/** @var \WC_Product[] $raw */
		$raw = wc_get_products( $args );

		if ( empty( $raw ) ) {
			return new \WP_REST_Response( [], 200 );
		}

		$products = [];

		foreach ( $raw as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			// ── Variable: expose each purchasable variation as its own row ──
			if ( $product->is_type( 'variable' ) ) {
				/** @var \WC_Product_Variable $product */
				foreach ( $product->get_available_variations() as $vd ) {

					$variation = wc_get_product( $vd['variation_id'] );
					if ( ! $variation instanceof \WC_Product_Variation ) {
						continue;
					}

					// Build variation label from attributes
					$attr_labels = array_filter( array_values( $vd['attributes'] ) );
					$label = $product->get_name();
					if ( $attr_labels ) {
						$label .= ' — ' . implode( ', ', $attr_labels );
					}

					// Categories from parent
					$cats = $this->build_categories( $product->get_category_ids(), $hidden_cats );
					if ( $cats === null ) {
						continue; // parent in hidden category
					}

					$thumb_id  = $variation->get_image_id() ?: $product->get_image_id();
					$thumb_url = $thumb_id
						? wp_get_attachment_image_url( (int) $thumb_id, 'thumbnail' )
						: wc_placeholder_img_src( 'thumbnail' );

					$products[] = [
						'id'          => $variation->get_id(),
						'parent_id'   => $product->get_id(),
						'name'        => $label,
						'price'       => (float) $variation->get_price(),
						'price_html'  => $variation->get_price_html(),
						'featured'    => $product->is_featured(),
						'categories'  => $cats,
						'thumbnail'   => esc_url( (string) $thumb_url ),
						'in_stock'    => $variation->is_in_stock(),
						'stock_qty'   => $variation->get_manage_stock() ? $variation->get_stock_quantity() : null,
						'type'        => 'variation',
						'attributes'  => $vd['attributes'],  // passed back to add_to_cart
						'permalink'   => get_permalink( $product->get_id() ),
					];
				}
				continue;
			}

			// ── Simple / other non-variable products ──────────────────────
			$cats = $this->build_categories( $product->get_category_ids(), $hidden_cats );
			if ( $cats === null ) {
				continue;
			}

			$thumb_id  = $product->get_image_id();
			$thumb_url = $thumb_id
				? wp_get_attachment_image_url( (int) $thumb_id, 'thumbnail' )
				: wc_placeholder_img_src( 'thumbnail' );

			$products[] = [
				'id'         => $product->get_id(),
				'parent_id'  => null,
				'name'       => $product->get_name(),
				'price'      => (float) $product->get_price(),
				'price_html' => $product->get_price_html(),
				'featured'   => $product->is_featured(),
				'categories' => $cats,
				'thumbnail'  => esc_url( (string) $thumb_url ),
				'in_stock'   => $product->is_in_stock(),
				'stock_qty'  => $product->get_manage_stock() ? $product->get_stock_quantity() : null,
				'type'       => $product->get_type(),
				'attributes' => [],
				'permalink'  => get_permalink( $product->get_id() ),
			];
		}

		// Featured-first sort
		if ( $settings['featured_first'] ?? true ) {
			usort( $products, static fn( $a, $b ) => (int) $b['featured'] <=> (int) $a['featured'] );
		}

		if ( ! $search ) {
			set_transient( self::CACHE_KEY, $products, self::CACHE_TTL );
		}

		return new \WP_REST_Response( $products, 200 );
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * POST /wc-ps/v1/cart/add
	 *
	 * Root cause of "only first product survives":
	 *   new WC_Cart() creates an EMPTY cart and never loads session data.
	 *   Every REST call overwrites the session with a single-item cart.
	 *
	 * Fix: always call get_cart_from_session() after WC_Cart is created,
	 *   and explicitly save session data at end of request.
	 * ───────────────────────────────────────────────────────────────────── */

	public function add_to_cart( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		// ── 1. Resolve product ───────────────────────────────────────────
		$product_id = (int) $request->get_param( 'product_id' ); // ← must be on its own line
		$quantity   = max( 1, (int) $request->get_param( 'quantity' ) );

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error(
				'wcps_invalid_product',
				__( 'Product not found.', 'wc-product-sidebar' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $product->is_in_stock() ) {
			return new \WP_Error(
				'wcps_out_of_stock',
				__( 'Product is out of stock.', 'wc-product-sidebar' ),
				[ 'status' => 400 ]
			);
		}

		// ── 2. Boot WooCommerce session + cart (with existing data) ──────
		$this->ensure_wc_cart();

		// ── 3. Add to cart with correct arguments per product type ───────
		if ( $product->is_type( 'variation' ) ) {
			/**
			 * WC_Cart::add_to_cart( $product_id, $qty, $variation_id, $variation )
			 *   $product_id   = parent product ID
			 *   $variation_id = the variation's own ID
			 *   $variation    = [ 'attribute_pa_size' => 'large', … ]
			 *
			 * Passing only the variation ID (without parent + attributes) causes
			 * WooCommerce to silently reject the cart item.
			 */
			/** @var \WC_Product_Variation $product */
			$parent_id  = $product->get_parent_id();
			$attributes = $product->get_variation_attributes(); // keyed by taxonomy slug

			$cart_item_key = WC()->cart->add_to_cart(
				$parent_id,
				$quantity,
				$product_id,
				$attributes
			);
		} else {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
		}

		if ( ! $cart_item_key ) {
			// WooCommerce adds its own notice; surface it if available
			$notices = wc_get_notices( 'error' );
			$msg     = ! empty( $notices )
				? wp_strip_all_tags( $notices[0]['notice'] )
				: __( 'Could not add product to cart.', 'wc-product-sidebar' );

			wc_clear_notices();

			return new \WP_Error( 'wcps_add_to_cart_failed', $msg, [ 'status' => 500 ] );
		}

		// ── 4. Recalculate totals and PERSIST the session ────────────────
		WC()->cart->calculate_totals();
		$this->save_session();

		// ── 5. Return updated cart state ─────────────────────────────────
		return new \WP_REST_Response(
			[
				'success'    => true,
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'cart_total' => WC()->cart->get_cart_total(),
				'cart_url'   => wc_get_cart_url(),
				'message'    => sprintf(
					/* translators: %s: product name */
					__( '"%s" added to your cart.', 'wc-product-sidebar' ),
					esc_html( $product->get_name() )
				),
			],
			200
		);
	}

	/* ─────────────────────────────────────────────────────────────────────
	 * Helpers
	 * ───────────────────────────────────────────────────────────────────── */

	/**
	 * Boot WooCommerce session + cart so they reflect EXISTING session data.
	 *
	 * The critical rule: after creating a new WC_Cart object you MUST call
	 * get_cart_from_session(), otherwise the in-memory cart is empty and
	 * save_data() will overwrite the stored session with that empty state.
	 */
	private function ensure_wc_cart(): void {

		/*
		 * Always let WooCommerce initialize the session/customer/cart stack.
		 * This is important for REST requests because WooCommerce normally
		 * initializes the cart during the regular frontend request lifecycle.
		 *
		 * Do not manually construct WC_Cart here: doing so can create an empty
		 * in-memory cart which then overwrites the existing session.
		 */
		if ( ! WC()->cart ) {
			wc_load_cart();
		}

		/*
		 * If WooCommerce created the session after the cart object was already
		 * present, make sure the cart reflects the current session. This is
		 * intentionally only done when a session exists and the cart has not
		 * already been populated for this request.
		 */
		if ( WC()->session && WC()->cart && ! WC()->cart->is_empty() ) {
			return;
		}

		if ( WC()->session && WC()->cart ) {
			WC()->cart->get_cart_from_session();
		}
	}

	/**
	 * Explicitly save session to the database AND (re)send the session
	 * cookie to the browser.
	 *
	 * Root cause of "notice says added, but the cart is empty":
	 *
	 *   WooCommerce normally sets the `wp_woocommerce_session_*` cookie
	 *   from WC_Session_Handler::set_customer_session_cookie(), which is
	 *   only hooked to the `woocommerce_set_cart_cookies` action. That
	 *   action is fired from WC_Cart::maybe_set_cart_cookies(), which is
	 *   itself only hooked to the `wp` action.
	 *
	 *   The WordPress REST API dispatches and `die()`s during the
	 *   `parse_request` hook — well BEFORE the `wp` action ever fires.
	 *   So for a first-time guest visitor (no session cookie yet), the
	 *   cart item gets added and saved to the session in the database,
	 *   but the browser is never given the cookie that identifies that
	 *   session. The very next page load (e.g. opening the cart) starts
	 *   a brand new anonymous session with an empty cart — even though
	 *   the REST response reported success.
	 *
	 * Fix: explicitly (re)send the session cookie ourselves.
	 */
	private function save_session(): void {
		if ( ! WC()->session ) {
			return;
		}

		// Use WooCommerce's own session serializer when available so all
		// cart/session fields expected by the current WooCommerce version
		// are persisted consistently.
		if ( is_callable( [ WC()->cart, 'set_session' ] ) ) {
			WC()->cart->set_session();
		} else {
			// Backward-compatible fallback for older WooCommerce versions.
			WC()->session->set( 'cart',                    WC()->cart->get_cart_for_session() );
			WC()->session->set( 'cart_totals',             WC()->cart->get_totals() );
			WC()->session->set( 'applied_coupons',         WC()->cart->get_applied_coupons() );
			WC()->session->set( 'coupon_discount_totals',  WC()->cart->get_coupon_discount_totals() );
		}

		// Persist to database
		if ( is_callable( [ WC()->session, 'save_data' ] ) ) {
			WC()->session->save_data();
		}

		// Make sure the browser actually receives/refreshes the session
		// cookie — normally done via the `wp` hook, which REST requests
		// never reach.
		if ( is_callable( [ WC()->session, 'set_customer_session_cookie' ] ) ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Build category array for a product, returning null if the product
	 * belongs to a hidden category (signals caller to skip the product).
	 *
	 * @param  int[]  $term_ids     Product category term IDs
	 * @param  int[]  $hidden_cats  Admin-configured hidden category IDs
	 * @return array<array{id:int,name:string}>|null
	 */
	private function build_categories( array $term_ids, array $hidden_cats ): ?array {
		$categories = [];

		foreach ( $term_ids as $term_id ) {
			if ( in_array( (int) $term_id, $hidden_cats, true ) ) {
				return null; // whole product should be skipped
			}
			$term = get_term( $term_id, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$categories[] = [
					'id'   => $term->term_id,
					'name' => $term->name,
				];
			}
		}

		return $categories;
	}

	/**
	 * Bust the products transient cache.
	 */
	public function bust_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
