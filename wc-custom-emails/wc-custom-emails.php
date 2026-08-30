<?php
/**
 * Plugin Name:       WC Custom Emails
 * Plugin URI:        https://github.com/Plugin-Email-Woocommerce
 * Description:       Personaliza todas las plantillas HTML de correo de WooCommerce desde un panel de administración.
 * Version:           1.2.0
 * Author:            WC Custom Emails
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Text Domain:       wc-custom-emails
 * Domain Path:       /languages
 *
 * @package WC_Custom_Emails
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCE_VERSION', '1.2.0' );
define( 'WCCE_PLUGIN_FILE', __FILE__ );
define( 'WCCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin bootstrap.
 */
final class WC_Custom_Emails {

	/**
	 * Singleton instance.
	 *
	 * @var WC_Custom_Emails|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Custom_Emails
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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( WCCE_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Initialize plugin after all plugins are loaded.
	 */
	public function init() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->load_dependencies();
		WCCE_Admin::instance();
		WCCE_Email_Manager::instance();
		WCCE_Storage::sync_existing_wrappers();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once WCCE_PLUGIN_DIR . 'includes/class-wcce-storage.php';
		require_once WCCE_PLUGIN_DIR . 'includes/class-wcce-admin.php';
		require_once WCCE_PLUGIN_DIR . 'includes/class-wcce-email-manager.php';
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Show admin notice when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'WC Custom Emails requiere que WooCommerce esté instalado y activo.', 'wc-custom-emails' )
		);
	}

	/**
	 * Create storage directory on activation.
	 */
	public function activate() {
		require_once WCCE_PLUGIN_DIR . 'includes/class-wcce-storage.php';
		WCCE_Storage::ensure_directory();
	}
}

WC_Custom_Emails::instance();
