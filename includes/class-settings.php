<?php
/**
 * Settings: manages plugin options with sanitization.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	private const OPTION_KEY = 'wcps_settings';

	/** @var array<string, mixed> */
	private static array $cache = [];

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			// Appearance (matches Peptides World purple storefront theme)
			'bg_color'             => '#faf7fe',
			'icon_color'           => '#ffffff',
			'ribbon_color'         => '#7b1fa2',
			'font_color'           => '#2d1b4e',
			'font_size'            => '14px',
			'font_family'          => 'inherit',
			'google_font'          => '',
			'ribbon_position'      => 'left',

			// Behaviour
			'show_category_headings' => true,
			'featured_first'         => true,
			'hidden_categories'      => [],

			// Button label
			'button_label'         => 'Products',
		];
	}

	/**
	 * Retrieve all settings (merged with defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( ! empty( self::$cache ) ) {
			return self::$cache;
		}

		$saved        = get_option( self::OPTION_KEY, [] );
		self::$cache  = wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
		return self::$cache;
	}

	/**
	 * Retrieve a single setting by key.
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$all = self::all();
		return $all[ $key ] ?? $fallback;
	}

	/**
	 * Sanitize and save posted settings.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function sanitize_and_save( array $raw ): array {
		$ribbon_position = in_array( $raw['ribbon_position'] ?? 'left', [ 'left', 'right' ], true ) ? $raw['ribbon_position'] : 'left';

		$clean = [
			'bg_color'               => Security::sanitize_hex_color( $raw['bg_color'] ?? '' ) ?: '#faf7fe',
			'icon_color'             => Security::sanitize_hex_color( $raw['icon_color'] ?? '' ) ?: '#ffffff',
			'ribbon_color'           => Security::sanitize_hex_color( $raw['ribbon_color'] ?? '' ) ?: '#7b1fa2',
			'font_color'             => Security::sanitize_hex_color( $raw['font_color'] ?? '' ) ?: '#1f2937',
			'font_size'              => Security::sanitize_css_size( $raw['font_size'] ?? '14px' ),
			'font_family'            => Security::sanitize_font_family( $raw['font_family'] ?? 'inherit' ),
			'google_font'            => Security::sanitize_font_family( $raw['google_font'] ?? '' ),
			'ribbon_position'        => $ribbon_position,
			'show_category_headings' => Security::sanitize_bool( $raw['show_category_headings'] ?? true ),
			'featured_first'         => Security::sanitize_bool( $raw['featured_first'] ?? true ),
			'hidden_categories'      => Security::sanitize_int_array( $raw['hidden_categories'] ?? [] ),
			'button_label'           => Security::sanitize_text( $raw['button_label'] ?? 'Products' ),
		];

		update_option( self::OPTION_KEY, $clean );
		self::$cache = $clean;

		return $clean;
	}

	/**
	 * Build inline CSS custom properties from settings.
	 */
	public static function build_css_vars(): string {
		$s = self::all();

		$font_stack = $s['font_family'] !== 'inherit' && $s['font_family'] !== ''
			? sprintf( '"%s", sans-serif', esc_attr( $s['font_family'] ) )
			: 'inherit';

		return sprintf(
			'--wcps-bg: %s; --wcps-icon: %s; --wcps-ribbon: %s; --wcps-font-color: %s; --wcps-font-size: %s; --wcps-font-family: %s;',
			esc_attr( $s['bg_color'] ),
			esc_attr( $s['icon_color'] ),
			esc_attr( $s['ribbon_color'] ),
			esc_attr( $s['font_color'] ),
			esc_attr( $s['font_size'] ),
			$font_stack
		);
	}
}
