<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WC_Custom_Emails
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$upload_dir = wp_upload_dir();
$directory  = trailingslashit( $upload_dir['basedir'] ) . 'wc-custom-emails';

if ( is_dir( $directory ) ) {
	$files = glob( trailingslashit( $directory ) . '*' );

	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}

	rmdir( $directory );
}
