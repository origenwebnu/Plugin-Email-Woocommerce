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
	 * Get the public URL to the storage directory.
	 *
	 * @return string
	 */
	public static function get_directory_url() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['baseurl'] ) . self::DIRECTORY;
	}

	/**
	 * Ensure the storage directory exists and is protected.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_directory() {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'wcce_upload_dir', $upload_dir['error'] );
		}

		$directory = self::get_directory();

		if ( ! file_exists( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		if ( ! is_dir( $directory ) || ! wp_is_writable( $directory ) ) {
			return new WP_Error(
				'wcce_upload_dir',
				__( 'La carpeta de subidas no es escribible. Revisa los permisos de wp-content/uploads.', 'wc-custom-emails' )
			);
		}

		$htaccess = trailingslashit( $directory ) . '.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" );
		}

		$index = trailingslashit( $directory ) . 'index.php';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$wrappers = trailingslashit( $directory ) . 'wrappers';

		if ( ! file_exists( $wrappers ) ) {
			wp_mkdir_p( $wrappers );
		}

		return true;
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
	 * Get the PHP wrapper path used by WooCommerce template loading.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return string
	 */
	public static function get_wrapper_file_path( $email_id ) {
		$safe_id = sanitize_file_name( $email_id );

		return trailingslashit( self::get_directory() ) . 'wrappers/' . $safe_id . '.php';
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
		if ( ! self::has_custom_template( $email_id ) ) {
			return false;
		}

		$path = self::get_file_path( $email_id );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );

		return false === $content ? false : $content;
	}

	/**
	 * Save custom template content.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @param string $content  HTML content.
	 * @return true|WP_Error
	 */
	public static function save_template_content( $email_id, $content ) {
		$directory_ready = self::ensure_directory();

		if ( is_wp_error( $directory_ready ) ) {
			return $directory_ready;
		}

		$path = self::get_file_path( $email_id );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $path, $content );

		if ( false === $result ) {
			return new WP_Error(
				'wcce_save_failed',
				__( 'No se pudo guardar la plantilla personalizada.', 'wc-custom-emails' )
			);
		}

		self::write_wrapper_file( $email_id );

		return true;
	}

	/**
	 * Regenerate PHP wrappers for templates uploaded before v1.1.0.
	 */
	public static function sync_existing_wrappers() {
		$directory_ready = self::ensure_directory();

		if ( is_wp_error( $directory_ready ) ) {
			return;
		}

		$files = glob( trailingslashit( self::get_directory() ) . '*.html' );

		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$email_id = basename( $file, '.html' );

			if ( empty( $email_id ) ) {
				continue;
			}

			self::write_wrapper_file( $email_id );
		}
	}

	/**
	 * Create or update the PHP wrapper used by WooCommerce templates.
	 *
	 * @param string $email_id WooCommerce email ID.
	 */
	private static function write_wrapper_file( $email_id ) {
		$wrapper_path = self::get_wrapper_file_path( $email_id );
		$safe_id      = sanitize_key( $email_id );
		$content      = <<<PHP
<?php
/**
 * Wrapper template generated by WC Custom Emails.
 *
 * @package WC_Custom_Emails
 */

defined( 'ABSPATH' ) || exit;

\$email_object = isset( \$email ) && is_a( \$email, 'WC_Email' ) ? \$email : null;
\$custom_html  = WCCE_Storage::get_template_content( '{$safe_id}' );

if ( false === \$custom_html ) {
	return;
}

if ( \$email_object ) {
	echo WCCE_Email_Manager::process_placeholders( \$custom_html, \$email_object );
} else {
	echo \$custom_html;
}
PHP;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $wrapper_path, $content );
	}

	/**
	 * Delete a custom template.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return bool
	 */
	public static function delete_template( $email_id ) {
		$path    = self::get_file_path( $email_id );
		$wrapper = self::get_wrapper_file_path( $email_id );

		if ( file_exists( $wrapper ) ) {
			unlink( $wrapper );
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		return unlink( $path );
	}

	/**
	 * Get file metadata for admin display.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return array<string, string|int>
	 */
	public static function get_template_meta( $email_id ) {
		$path = self::get_file_path( $email_id );

		if ( ! file_exists( $path ) ) {
			return array();
		}

		return array(
			'path' => $path,
			'size' => (int) filesize( $path ),
			'date' => (int) filemtime( $path ),
		);
	}
}
