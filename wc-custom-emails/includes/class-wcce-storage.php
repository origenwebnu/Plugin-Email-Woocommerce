<?php
/**
 * File storage for custom email templates.
 *
 * @package WC_Custom_Emails
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles reading and writing custom email HTML files.
 */
class WCCE_Storage {

	const DIRECTORY = 'wc-custom-emails';

	/**
	 * Get the absolute path to the storage directory.
	 *
	 * @return string
	 */
	public static function get_directory() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . self::DIRECTORY;
	}

	/**
	 * Ensure the storage directory exists and is protected.
	 */
	public static function ensure_directory() {
		$directory = self::get_directory();

		if ( ! file_exists( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		$htaccess = trailingslashit( $directory ) . '.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" );
		}

		$index = trailingslashit( $directory ) . 'index.php';

		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Get the file path for a specific email template.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return string
	 */
	public static function get_file_path( $email_id ) {
		$safe_id = sanitize_file_name( $email_id );

		return trailingslashit( self::get_directory() ) . $safe_id . '.html';
	}

	/**
	 * Check whether a custom template exists for an email.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return bool
	 */
	public static function has_custom_template( $email_id ) {
		$path = self::get_file_path( $email_id );

		return file_exists( $path ) && filesize( $path ) > 0;
	}

	/**
	 * Read custom template content.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return string|false
	 */
	public static function get_template_content( $email_id ) {
		$path = self::get_file_path( $email_id );

		if ( ! self::has_custom_template( $email_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $path );
	}

	/**
	 * Save custom template content.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @param string $content  HTML content.
	 * @return true|WP_Error
	 */
	public static function save_template_content( $email_id, $content ) {
		self::ensure_directory();

		$path = self::get_file_path( $email_id );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $path, $content );

		if ( false === $result ) {
			return new WP_Error(
				'wcce_save_failed',
				__( 'No se pudo guardar la plantilla personalizada.', 'wc-custom-emails' )
			);
		}

		return true;
	}

	/**
	 * Delete a custom template.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return bool
	 */
	public static function delete_template( $email_id ) {
		$path = self::get_file_path( $email_id );

		if ( ! file_exists( $path ) ) {
			return true;
		}

		return unlink( $path );
	}
}
