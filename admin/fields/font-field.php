<?php
/**
 * Font settings field helper.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function font_field( array $settings ): void {
	$popular_fonts = [
		'inherit'         => __( 'Theme default', 'wc-product-sidebar' ),
		'Inter'           => 'Inter',
		'Roboto'          => 'Roboto',
		'Open Sans'       => 'Open Sans',
		'Lato'             => 'Lato',
		'Poppins'         => 'Poppins',
		'Montserrat'      => 'Montserrat',
		'Nunito'          => 'Nunito',
		'Source Sans Pro' => 'Source Sans Pro',
		'Raleway'         => 'Raleway',
		'PT Sans'         => 'PT Sans',
	];
	?>
	<div class="wcps-font-grid">
		<div class="wcps-field-row">
			<label for="font_size"><?php esc_html_e( 'Base font size', 'wc-product-sidebar' ); ?></label>
			<input type="text" id="font_size" name="font_size" value="<?php echo esc_attr( $settings['font_size'] ); ?>" placeholder="14px" class="small-text" />
			<p class="description"><?php esc_html_e( 'Examples: 14px, 1rem, 0.875em.', 'wc-product-sidebar' ); ?></p>
		</div>
		<div class="wcps-field-row">
			<label for="font_family"><?php esc_html_e( 'Font family', 'wc-product-sidebar' ); ?></label>
			<select id="font_family" name="font_family">
				<?php foreach ( $popular_fonts as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_family'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="wcps-field-row">
		<label for="google_font"><?php esc_html_e( 'Custom Google Font', 'wc-product-sidebar' ); ?></label>
		<div class="wcps-input-with-hint">
			<input type="text" id="google_font" name="google_font" value="<?php echo esc_attr( $settings['google_font'] ); ?>" placeholder="e.g. Figtree" class="regular-text" />
			<span><?php esc_html_e( 'Overrides the font family selection.', 'wc-product-sidebar' ); ?></span>
		</div>
	</div>
	<?php
}
