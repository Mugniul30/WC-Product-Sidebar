<?php
/**
 * Uninstall: remove all plugin data from the database.
 *
 * @package WC_Product_Sidebar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings
delete_option( 'wcps_settings' );

// Remove any transients
delete_transient( 'wcps_products_cache' );

// Remove user-level transients if multisite
if ( is_multisite() ) {
	$sites = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'wcps_settings' );
		delete_transient( 'wcps_products_cache' );
		restore_current_blog();
	}
}
