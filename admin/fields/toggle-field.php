<?php
/**
 * Toggle field helper.
 *
 * @package WC_Product_Sidebar
 */

declare(strict_types=1);

namespace WCPS\Admin\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function toggle_field( string $name, string $label, bool $checked, string $description = '' ): void {
	?>
	<div class="wcps-feature">
		<label class="wcps-toggle-label" for="<?php echo esc_attr( $name ); ?>">
			<span class="wcps-toggle">
				<input type="checkbox" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
				<span class="wcps-toggle-slider"></span>
			</span>
			<span class="wcps-feature-copy">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
			</span>
		</label>
	</div>
	<?php
}
