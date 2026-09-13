<?php
/**
 * Security: nonce generation/verification, sanitization helpers.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Security {

	public const NONCE_ACTION = 'wcps_nonce';

	/**
	 * Generate a nonce string for frontend use.
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify a nonce. Returns true on success, false on failure.
	 */
	public static function verify_nonce( string $nonce ): bool {
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Sanitize a plain text string.
	 */
	public static function sanitize_text( mixed $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize an integer (absolute, no negatives).
	 */
	public static function sanitize_int( mixed $value ): int {
		return absint( $value );
	}

	/**
	 * Sanitize a hex color string.
	 */
	public static function sanitize_hex_color( mixed $value ): string {
		$sanitized = sanitize_hex_color( (string) $value );
		return $sanitized ?? '';
	}

	/**
	 * Sanitize a CSS font-size value (e.g. "14px", "1.2rem").
	 */
	public static function sanitize_css_size( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );
		if ( preg_match( '/^\d+(\.\d+)?(px|rem|em|%)$/', $value ) ) {
			return $value;
		}
		return '14px';
	}

	/**
	 * Sanitize a boolean-ish setting.
	 */
	public static function sanitize_bool( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize an array of integers (e.g. category IDs).
	 */
	public static function sanitize_int_array( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$clean = array_map( 'absint', $value );
		$clean = array_filter( $clean, static fn( $id ) => $id > 0 );
		$clean = array_unique( $clean );
		return array_values( $clean );
	}

	/**
	 * Sanitize a Google Font name (alphanumeric + spaces).
	 */
	public static function sanitize_font_family( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );
		// Allow letters, digits, spaces, and plus signs (URL-encoded Google fonts)
		$value = preg_replace( '/[^a-zA-Z0-9 +\-,]/', '', $value );
		return $value ?? '';
	}
}
