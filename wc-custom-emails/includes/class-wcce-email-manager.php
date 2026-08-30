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
	 * @return string
	 */
	public static function get_default_html( $email ) {
		if ( ! is_a( $email, 'WC_Email' ) ) {
			return '';
		}

		self::maybe_set_sample_object( $email );

		remove_filter( 'woocommerce_email_get_content_html', array( self::instance(), 'replace_email_html' ), 999 );

		$html = $email->get_content_html();

		add_filter( 'woocommerce_email_get_content_html', array( self::instance(), 'replace_email_html' ), 999, 2 );

		return $html;
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

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
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
