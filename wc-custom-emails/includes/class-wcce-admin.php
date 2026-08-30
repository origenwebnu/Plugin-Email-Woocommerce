<?php
/**
 * Admin page for managing custom WooCommerce email templates.
 *
 * @package WC_Custom_Emails
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Emails WC" admin menu and handles uploads.
 */
class WCCE_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var WCCE_Admin|null
	 */
	private static $instance = null;

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'wc-custom-emails';

	/**
	 * Get singleton instance.
	 *
	 * @return WCCE_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wcce_upload_email', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_wcce_delete_email', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_wcce_download_default', array( $this, 'handle_download_default' ) );
	}

	/**
	 * Register admin menu item.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Emails WC', 'wc-custom-emails' ),
			__( 'Emails WC', 'wc-custom-emails' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-email-alt2',
			56
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wcce-admin',
			WCCE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WCCE_VERSION
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'wc-custom-emails' ) );
		}

		$emails = WCCE_Email_Manager::get_all_emails();

		include WCCE_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * Handle HTML file upload for an email.
	 */
	public function handle_upload() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wc-custom-emails' ) );
		}

		check_admin_referer( 'wcce_upload_email' );

		$email_id = isset( $_POST['email_id'] ) ? sanitize_key( wp_unslash( $_POST['email_id'] ) ) : '';

		if ( empty( $email_id ) ) {
			$this->redirect_with_notice( 'error', __( 'Email no válido.', 'wc-custom-emails' ) );
		}

		if ( empty( $_FILES['email_html']['name'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Debes seleccionar un archivo HTML.', 'wc-custom-emails' ) );
		}

		$file = $_FILES['email_html'];

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			$this->redirect_with_notice( 'error', __( 'Error al subir el archivo.', 'wc-custom-emails' ) );
		}

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( 'html' !== $extension && 'htm' !== $extension ) {
			$this->redirect_with_notice( 'error', __( 'Solo se permiten archivos .html o .htm.', 'wc-custom-emails' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file['tmp_name'] );

		if ( false === $content || '' === trim( $content ) ) {
			$this->redirect_with_notice( 'error', __( 'El archivo HTML está vacío.', 'wc-custom-emails' ) );
		}

		$result = WCCE_Storage::save_template_content( $email_id, $content );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %s: email ID */
				__( 'Plantilla personalizada guardada para "%s".', 'wc-custom-emails' ),
				$email_id
			)
		);
	}

	/**
	 * Handle deletion of a custom template.
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wc-custom-emails' ) );
		}

		check_admin_referer( 'wcce_delete_email' );

		$email_id = isset( $_GET['email_id'] ) ? sanitize_key( wp_unslash( $_GET['email_id'] ) ) : '';

		if ( empty( $email_id ) ) {
			$this->redirect_with_notice( 'error', __( 'Email no válido.', 'wc-custom-emails' ) );
		}

		WCCE_Storage::delete_template( $email_id );

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %s: email ID */
				__( 'Plantilla personalizada eliminada para "%s". Se usará la plantilla estándar de WooCommerce.', 'wc-custom-emails' ),
				$email_id
			)
		);
	}

	/**
	 * Download the default WooCommerce HTML for an email.
	 */
	public function handle_download_default() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wc-custom-emails' ) );
		}

		check_admin_referer( 'wcce_download_default' );

		$email_id = isset( $_GET['email_id'] ) ? sanitize_key( wp_unslash( $_GET['email_id'] ) ) : '';
		$emails   = WCCE_Email_Manager::get_all_emails();
		$email    = null;

		foreach ( $emails as $wc_email ) {
			if ( $wc_email->id === $email_id ) {
				$email = $wc_email;
				break;
			}
		}

		if ( ! $email ) {
			$this->redirect_with_notice( 'error', __( 'Email no encontrado.', 'wc-custom-emails' ) );
		}

		$html = WCCE_Email_Manager::get_default_html( $email );

		if ( is_wp_error( $html ) ) {
			$this->redirect_with_notice( 'error', $html->get_error_message() );
		}

		if ( '' === trim( $html ) ) {
			$this->redirect_with_notice(
				'error',
				__( 'No se pudo generar la plantilla estándar. Comprueba que exista al menos un pedido en la tienda.', 'wc-custom-emails' )
			);
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $email_id ) . '-default.html"' );
		header( 'Content-Length: ' . strlen( $html ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		exit;
	}

	/**
	 * Redirect back to admin page with a notice.
	 *
	 * @param string $type    Notice type: success|error.
	 * @param string $message Notice message.
	 */
	private function redirect_with_notice( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'        => self::PAGE_SLUG,
				'wcce_notice' => $type,
				'wcce_msg'    => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
