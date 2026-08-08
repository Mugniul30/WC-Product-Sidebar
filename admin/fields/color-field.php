<?php
/**
 * Color picker field helper.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function color_field( string $name, string $label, string $value ): void {
	?>
	<div class="wcps-field-row">
		<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<input
			type="text"
			id="<?php echo esc_attr( $name ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="wcps-color-picker"
			data-default-color="<?php echo esc_attr( $value ); ?>"
		/>
	</div>
	<?php
}
