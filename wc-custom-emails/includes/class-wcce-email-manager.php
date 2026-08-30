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
		add_filter( 'woocommerce_mail_callback_params', array( $this, 'replace_outgoing_email' ), 999, 2 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_custom_template' ), 999, 3 );
	}

	/**
	 * Replace the final email body before WordPress sends it.
	 *
	 * This hook works for classic PHP templates and for the block email editor.
	 *
	 * @param array    $params Mail callback params.
	 * @param WC_Email $email  Email object.
	 * @return array
	 */
	public function replace_outgoing_email( $params, $email ) {
		if ( ! is_a( $email, 'WC_Email' ) || empty( $email->id ) || ! is_array( $params ) ) {
			return $params;
		}

		$custom_html = WCCE_Storage::get_template_content( $email->id );

		if ( false === $custom_html ) {
			return $params;
		}

		$processed = self::process_placeholders( $custom_html, $email );

		if ( method_exists( $email, 'style_inline' ) ) {
			$params[2] = $email->style_inline( $processed );
		} else {
			$params[2] = $processed;
		}

		return $params;
	}

	/**
	 * Point WooCommerce to a PHP wrapper that outputs the custom HTML.
	 *
	 * @param string $template      Template path.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @return string
	 */
	public function locate_custom_template( $template, $template_name, $template_path ) {
		if ( 0 !== strpos( $template_name, 'emails/' ) || '.php' !== substr( $template_name, -4 ) ) {
			return $template;
		}

		$email_id = self::get_email_id_for_template( $template_name );

		if ( ! $email_id || ! WCCE_Storage::has_custom_template( $email_id ) ) {
			return $template;
		}

		$wrapper = WCCE_Storage::get_wrapper_file_path( $email_id );

		return file_exists( $wrapper ) ? $wrapper : $template;
	}

	/**
	 * Find the WooCommerce email ID for a template file.
	 *
	 * @param string $template_name Template file name.
	 * @return string|null
	 */
	public static function get_email_id_for_template( $template_name ) {
		foreach ( self::get_all_emails() as $email ) {
			if ( ! empty( $email->template_html ) && $email->template_html === $template_name ) {
				return $email->id;
			}
		}

		return null;
	}

	/**
	 * Replace WooCommerce placeholders in custom HTML.
	 *
	 * @param string   $html  Custom HTML content.
	 * @param WC_Email $email Email object.
	 * @return string
	 */
	public static function process_placeholders( $html, $email ) {
		if ( ! is_a( $email, 'WC_Email' ) ) {
			return $html;
		}

		if ( method_exists( $email, 'setup_locale' ) ) {
			$email->setup_locale();
		}

		if ( method_exists( $email, 'set_placeholders' ) ) {
			$email->set_placeholders();
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

		self::prepare_email_for_preview( $email );

		if ( ! empty( $email->template_html ) && function_exists( 'wc_get_template_html' ) ) {
			ob_start();

			$html = '';

			try {
				$html = wc_get_template_html(
					$email->template_html,
					array(
						'order'              => is_a( $email->object, 'WC_Order' ) ? $email->object : null,
						'email_heading'      => $email->get_heading(),
						'additional_content' => $email->get_additional_content(),
						'sent_to_admin'      => ! $email->is_customer_email(),
						'plain_text'         => false,
						'email'              => $email,
					)
				);
			} catch ( Throwable $exception ) {
				$html = '';
			}

			ob_end_clean();

			if ( ! empty( $html ) ) {
				return $html;
			}
		}

		$raw_template = self::get_raw_template_content( $email );

		if ( ! is_wp_error( $raw_template ) && '' !== trim( $raw_template ) ) {
			return $raw_template;
		}

		return self::get_starter_template( $email );
	}

	/**
	 * Prepare locale, sample data and placeholders before rendering a template.
	 *
	 * @param WC_Email $email Email object.
	 */
	private static function prepare_email_for_preview( $email ) {
		if ( method_exists( $email, 'setup_locale' ) ) {
			$email->setup_locale();
		}

		if ( empty( $email->object ) ) {
			self::maybe_set_sample_object( $email );
		}

		if ( is_a( $email->object, 'WC_Order' ) ) {
			$order = $email->object;

			$email->recipient                               = $order->get_billing_email();
			$email->placeholders['{order_date}']              = wc_format_datetime( $order->get_date_created() );
			$email->placeholders['{order_number}']            = $order->get_order_number();
			$email->placeholders['{order_billing_full_name}'] = $order->get_formatted_billing_full_name();
		}

		if ( is_a( $email->object, 'WP_User' ) ) {
			$user = $email->object;

			$email->recipient                            = $user->user_email;
			$email->placeholders['{customer_username}']  = $user->user_login;
			$email->placeholders['{customer_email}']     = $user->user_email;
			$email->placeholders['{customer_name}']      = $user->display_name;
			$email->placeholders['{account_login_url}']  = wc_get_page_permalink( 'myaccount' );
			$email->placeholders['{reset_password_url}'] = wp_lostpassword_url();
		}

		if ( method_exists( $email, 'set_placeholders' ) ) {
			$email->set_placeholders();
		}
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

		$template_path = self::resolve_template_path( $email->template_html );

		if ( ! $template_path ) {
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
				'Plantilla original de WooCommerce. Puede contener código PHP. Úsala como referencia para crear tu HTML personalizado.',
				'wc-custom-emails'
			)
		);

		return $notice . $content;
	}

	/**
	 * Generate a simple editable HTML starter template.
	 *
	 * @param WC_Email $email Email object.
	 * @return string
	 */
	public static function get_starter_template( $email ) {
		$heading      = method_exists( $email, 'get_heading' ) ? $email->get_heading() : $email->get_title();
		$placeholders = self::get_placeholders( $email );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $heading ); ?></title>
</head>
<body style="margin:0;padding:20px;background:#f7f7f7;font-family:Arial,sans-serif;color:#333;">
	<div style="max-width:600px;margin:0 auto;background:#ffffff;padding:30px;border:1px solid #e5e5e5;">
		<h1 style="margin-top:0;"><?php echo esc_html( $heading ); ?></h1>
		<p>Hola {order_billing_full_name},</p>
		<p>Este es un ejemplo de plantilla para el email <strong><?php echo esc_html( $email->id ); ?></strong>.</p>
		<p><strong>Pedido:</strong> {order_number}</p>
		<p><strong>Fecha:</strong> {order_date}</p>
		<p><strong>Tienda:</strong> {site_title}</p>
		<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
		<p style="font-size:12px;color:#777;">Variables disponibles: <?php echo esc_html( implode( ', ', $placeholders ) ); ?></p>
	</div>
</body>
</html>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Resolve a WooCommerce template path.
	 *
	 * @param string $template Relative template path.
	 * @return string
	 */
	private static function resolve_template_path( $template ) {
		$template_path = '';

		if ( function_exists( 'wc_locate_template' ) ) {
			$template_path = wc_locate_template( $template );
		}

		if ( $template_path && file_exists( $template_path ) ) {
			return $template_path;
		}

		$plugin_template = WC()->plugin_path() . '/templates/' . $template;

		return file_exists( $plugin_template ) ? $plugin_template : '';
	}

	/**
	 * Read a WooCommerce email template file from disk.
	 *
	 * @param string $template Relative template path.
	 * @return string
	 */
	private static function read_template_file( $template ) {
		$template_path = self::resolve_template_path( $template );

		if ( ! $template_path ) {
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
		if ( ! is_a( $email, 'WC_Email' ) ) {
			return array();
		}

		self::prepare_email_for_preview( $email );

		if ( empty( $email->placeholders ) ) {
			return array();
		}

		return array_keys( $email->placeholders );
	}
}
