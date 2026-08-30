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

		if ( true === $prepared ) {
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
		}

		return self::get_raw_template_content( $email );
	}

	/**
	 * Prepare locale, sample data and placeholders before rendering a template.
	 *
	 * @param WC_Email $email Email object.
	 * @return bool
	 */
	private static function prepare_email_for_preview( $email ) {
		if ( method_exists( $email, 'setup_locale' ) ) {
			$email->setup_locale();
		}

		if ( empty( $email->object ) ) {
			self::maybe_set_sample_object( $email );
		}

		if ( empty( $email->object ) ) {
			if ( method_exists( $email, 'restore_locale' ) ) {
				$email->restore_locale();
			}

			return false;
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

			$email->recipient                           = $user->user_email;
			$email->placeholders['{customer_username}'] = $user->user_login;
			$email->placeholders['{customer_email}']    = $user->user_email;
			$email->placeholders['{customer_name}']     = $user->display_name;
			$email->placeholders['{account_login_url}']  = wc_get_page_permalink( 'myaccount' );
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

		if ( self::email_uses_order_object( $email ) ) {
			$order = self::get_sample_order();

			if ( $order ) {
				$email->object = $order;
				return;
			}
		}

		$user = self::get_sample_user();

		if ( $user ) {
			$email->object = $user;
		}
	}

	/**
	 * Find a real order or create a mock one for template rendering.
	 *
	 * @return WC_Order|null
	 */
	private static function get_sample_order() {
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'status'  => 'any',
				)
			);

			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		$legacy_orders = get_posts(
			array(
				'post_type'      => 'shop_order',
				'post_status'    => array_keys( wc_get_order_statuses() ),
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $legacy_orders ) ) {
			return wc_get_order( $legacy_orders[0] );
		}

		return self::create_mock_order();
	}

	/**
	 * Find any available user for user-based email templates.
	 *
	 * @return WP_User|null
	 */
	private static function get_sample_user() {
		if ( ! function_exists( 'get_users' ) ) {
			return null;
		}

		$users = get_users(
			array(
				'number' => 1,
				'role'   => 'customer',
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		$users = get_users(
			array(
				'number' => 1,
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		return null;
	}

	/**
	 * Build an in-memory order when the store has no orders yet.
	 *
	 * @return WC_Order|null
	 */
	private static function create_mock_order() {
		if ( ! class_exists( 'WC_Order' ) ) {
			return null;
		}

		$order = new WC_Order();
		$order->set_status( 'processing' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_date_created( time() );
		$order->set_billing_first_name( 'Juan' );
		$order->set_billing_last_name( 'Perez' );
		$order->set_billing_company( 'Empresa Demo' );
		$order->set_billing_address_1( 'Calle Ejemplo 123' );
		$order->set_billing_city( 'Madrid' );
		$order->set_billing_postcode( '28001' );
		$order->set_billing_country( 'ES' );
		$order->set_billing_email( 'cliente@ejemplo.com' );
		$order->set_billing_phone( '600000000' );
		$order->set_shipping_first_name( 'Juan' );
		$order->set_shipping_last_name( 'Perez' );
		$order->set_shipping_address_1( 'Calle Ejemplo 123' );
		$order->set_shipping_city( 'Madrid' );
		$order->set_shipping_postcode( '28001' );
		$order->set_shipping_country( 'ES' );
		$order->set_total( 49.99 );

		return $order;
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
			$plugin_template = WC()->plugin_path() . '/templates/' . $email->template_html;

			if ( file_exists( $plugin_template ) ) {
				$template_path = $plugin_template;
			}
		}

		if ( ! $template_path || ! file_exists( $template_path ) ) {
			return new WP_Error(
				'wcce_template_missing',
				__( 'No se pudo localizar la plantilla estándar de WooCommerce.', 'wc-custom-emails' )
			);
		}

		$parts = array(
			self::read_template_file( 'emails/email-styles.php' ),
			self::read_template_file( 'emails/email-header.php' ),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			file_get_contents( $template_path ),
			self::read_template_file( 'emails/email-footer.php' ),
		);

		$content = trim( implode( "\n\n", array_filter( $parts ) ) );

		if ( '' === $content ) {
			return new WP_Error(
				'wcce_template_empty',
				__( 'La plantilla estándar de WooCommerce está vacía.', 'wc-custom-emails' )
			);
		}

		$notice = sprintf(
			"<!-- %s -->\n",
			esc_html__(
				'Plantilla original de WooCommerce. Puede contener código PHP y variables. Úsala como referencia para crear tu HTML personalizado.',
				'wc-custom-emails'
			)
		);

		return $notice . $content;
	}

	/**
	 * Read a WooCommerce email template file from disk.
	 *
	 * @param string $template Relative template path.
	 * @return string
	 */
	private static function read_template_file( $template ) {
		$template_path = wc_locate_template( $template );

		if ( ! $template_path || ! file_exists( $template_path ) ) {
			$plugin_template = WC()->plugin_path() . '/templates/' . $template;

			if ( file_exists( $plugin_template ) ) {
				$template_path = $plugin_template;
			}
		}

		if ( ! $template_path || ! file_exists( $template_path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $template_path );

		return false === $content ? '' : $content;
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
