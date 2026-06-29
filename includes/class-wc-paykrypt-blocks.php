<?php
/**
 * PayKrypt Checkout Blocks integration.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers PayKrypt with WooCommerce Checkout Blocks.
 */
final class WC_PayKrypt_Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'paykrypt';

	/**
	 * Gateway settings.
	 *
	 * @var array<string,mixed>
	 */
	protected $settings = array();

	/**
	 * Initializes the integration.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paykrypt_settings', array() );
	}

	/**
	 * Returns whether this payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns script handles for the frontend.
	 *
	 * @return array<int,string>
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'paykrypt-for-woocommerce-blocks',
			plugins_url( 'assets/js/paykrypt-blocks.js', PAYKRYPT_WC_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			PAYKRYPT_WC_VERSION,
			true
		);

		return array( 'paykrypt-for-woocommerce-blocks' );
	}

	/**
	 * Returns gateway data exposed to Checkout Blocks.
	 *
	 * @return array<string,mixed>
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) ? (string) $this->settings['title'] : __( 'Crypto via PayKrypt', 'paykrypt-for-woocommerce' ),
			'description' => isset( $this->settings['description'] ) ? (string) $this->settings['description'] : __( 'Pay securely with cryptocurrency using PayKrypt.', 'paykrypt-for-woocommerce' ),
			'supports'    => array( 'products' ),
		);
	}
}
