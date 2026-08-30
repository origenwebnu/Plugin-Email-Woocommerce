<?php
/**
 * Overrides WooCommerce email templates with custom HTML.
 *
 * @package WC_Custom_Emails
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces default WooCommerce email HTML with uploaded templates.
 */
class WCCE_Email_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var WCCE_Email_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WCCE_Email_Manager
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
		add_filter( 'woocommerce_email_get_content_html', array( $this, 'replace_email_html' ), 999, 2 );
	}

	/**
	 * Replace default email HTML with custom uploaded template.
	 *
	 * @param string   $content Default HTML content.
	 * @param WC_Email $email   Email object.
	 * @return string
	 */
	public function replace_email_html( $content, $email ) {
		if ( ! is_a( $email, 'WC_Email' ) || empty( $email->id ) ) {
			return $content;
		}

		$custom_html = WCCE_Storage::get_template_content( $email->id );

		if ( false === $custom_html ) {
			return $content;
		}

		return $this->process_placeholders( $custom_html, $email );
	}

	/**
	 * Replace WooCommerce placeholders in custom HTML.
	 *
	 * @param string   $html  Custom HTML content.
	 * @param WC_Email $email Email object.
	 * @return string
	 */
	private function process_placeholders( $html, $email ) {
		if ( method_exists( $email, 'setup_locale' ) ) {
			$email->setup_locale();
		}

		$processed = $email->format_string( $html );

		if ( method_exists( $email, 'restore_locale' ) ) {
			$email->restore_locale();
		}

		return $processed;
	}

	/**
	 * Get all registered WooCommerce emails.
	 *
	 * @return WC_Email[]
	 */
	public static function get_all_emails() {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return array();
		}

		return WC()->mailer()->get_emails();
	}

	/**
	 * Get default HTML content for an email (without custom override).
	 *
	 * @param WC_Email $email Email object.
	 * @return string|WP_Error
	 */
	public static function get_default_html( $email ) {
		if ( ! is_a( $email, 'WC_Email' ) ) {
			return new WP_Error( 'wcce_invalid_email', __( 'Email no válido.', 'wc-custom-emails' ) );
		}

		$prepared = self::prepare_email_for_preview( $email );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		remove_filter( 'woocommerce_email_get_content_html', array( self::instance(), 'replace_email_html' ), 999 );

		ob_start();

		$html = '';

		try {
			$html = $email->get_content_html();
		} catch ( Throwable $exception ) {
			$html = '';
		}

		$unexpected_output = ob_get_clean();

		add_filter( 'woocommerce_email_get_content_html', array( self::instance(), 'replace_email_html' ), 999, 2 );

		if ( method_exists( $email, 'restore_locale' ) ) {
			$email->restore_locale();
		}

		if ( ! empty( $html ) ) {
			return $html;
		}

		if ( ! empty( $unexpected_output ) ) {
			return $unexpected_output;
		}

		return self::get_raw_template_content( $email );
	}

	/**
	 * Prepare locale, sample data and placeholders before rendering a template.
	 *
	 * @param WC_Email $email Email object.
	 * @return true|WP_Error
	 */
	private static function prepare_email_for_preview( $email ) {
		if ( method_exists( $email, 'setup_locale' ) ) {
			$email->setup_locale();
		}

		if ( empty( $email->object ) ) {
			self::maybe_set_sample_object( $email );
		}

		if ( empty( $email->object ) ) {
			return new WP_Error(
				'wcce_no_sample_data',
				__( 'No hay pedidos ni usuarios para generar la plantilla. Crea al menos un pedido en WooCommerce e inténtalo de nuevo.', 'wc-custom-emails' )
			);
		}

		if ( is_a( $email->object, 'WC_Order' ) ) {
			$order = $email->object;

			$email->recipient = $order->get_billing_email();
			$email->placeholders['{order_date}']              = wc_format_datetime( $order->get_date_created() );
			$email->placeholders['{order_number}']            = $order->get_order_number();
			$email->placeholders['{order_billing_full_name}'] = $order->get_formatted_billing_full_name();
		}

		if ( is_a( $email->object, 'WP_User' ) ) {
			$user = $email->object;

			$email->recipient                          = $user->user_email;
			$email->placeholders['{customer_username}'] = $user->user_login;
			$email->placeholders['{customer_email}']  = $user->user_email;
			$email->placeholders['{customer_name}']     = $user->display_name;
			$email->placeholders['{account_login_url}'] = wc_get_page_permalink( 'myaccount' );
			$email->placeholders['{reset_password_url}'] = wp_lostpassword_url();
		}

		if ( method_exists( $email, 'set_placeholders' ) ) {
			$email->set_placeholders();
		}

		return true;
	}

	/**
	 * Assign a sample order/user object so preview templates render with data.
	 *
	 * @param WC_Email $email Email object.
	 */
	private static function maybe_set_sample_object( $email ) {
		if ( ! empty( $email->object ) ) {
			return;
		}

		if ( self::email_uses_order_object( $email ) && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'status'  => array_keys( wc_get_order_statuses() ),
				)
			);

			if ( ! empty( $orders ) ) {
				$email->object = $orders[0];
				return;
			}
		}

		if ( function_exists( 'get_users' ) ) {
			$users = get_users(
				array(
					'number' => 1,
					'role'   => 'customer',
				)
			);

			if ( ! empty( $users ) ) {
				$email->object = $users[0];
			}
		}
	}

	/**
	 * Determine whether an email is expected to use a WC_Order object.
	 *
	 * @param WC_Email $email Email object.
	 * @return bool
	 */
	private static function email_uses_order_object( $email ) {
		$user_email_ids = array(
			'customer_new_account',
			'customer_reset_password',
		);

		return ! in_array( $email->id, $user_email_ids, true );
	}

	/**
	 * Read the original WooCommerce template file as a fallback.
	 *
	 * @param WC_Email $email Email object.
	 * @return string|WP_Error
	 */
	public static function get_raw_template_content( $email ) {
		if ( empty( $email->template_html ) ) {
			return new WP_Error(
				'wcce_template_missing',
				__( 'No se pudo localizar la plantilla estándar de WooCommerce.', 'wc-custom-emails' )
			);
		}

		$template_path = wc_locate_template( $email->template_html );

		if ( ! $template_path || ! file_exists( $template_path ) ) {
			return new WP_Error(
				'wcce_template_missing',
				__( 'No se pudo localizar la plantilla estándar de WooCommerce.', 'wc-custom-emails' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $template_path );

		if ( false === $content || '' === trim( $content ) ) {
			return new WP_Error(
				'wcce_template_empty',
				__( 'La plantilla estándar de WooCommerce está vacía.', 'wc-custom-emails' )
			);
		}

		$notice = sprintf(
			"<!-- %s -->\n",
			esc_html__(
				'Plantilla PHP original de WooCommerce. Contiene código PHP que debes adaptar a HTML antes de subirla.',
				'wc-custom-emails'
			)
		);

		return $notice . $content;
	}

	/**
	 * Get available placeholders for an email.
	 *
	 * @param WC_Email $email Email object.
	 * @return string[]
	 */
	public static function get_placeholders( $email ) {
		if ( ! is_a( $email, 'WC_Email' ) || empty( $email->placeholders ) ) {
			return array();
		}

		return array_keys( $email->placeholders );
	}
}
