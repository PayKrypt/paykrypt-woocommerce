<?php
/**
 * Plugin Name: PayKrypt for WooCommerce
 * Plugin URI: https://docs.paykrypt.io/woocommerce
 * Description: Accept crypto payments in WooCommerce through PayKrypt hosted checkout.
 * Version: 0.1.0
 * Author: PayKrypt
 * Author URI: https://paykrypt.io
 * Developer: PayKrypt
 * Developer URI: https://github.com/PayKrypt/paykrypt-woocommerce
 * Text Domain: paykrypt-for-woocommerce
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.9
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAYKFOWO_VERSION', '0.1.0' );
define( 'PAYKFOWO_FILE', __FILE__ );
define( 'PAYKFOWO_PATH', plugin_dir_path( __FILE__ ) );

require_once PAYKFOWO_PATH . 'includes/class-paykfowo-api-exception.php';
require_once PAYKFOWO_PATH . 'includes/class-paykfowo-client.php';
require_once PAYKFOWO_PATH . 'includes/class-paykfowo-order-sync.php';

/**
 * Adds the custom cron interval used for payment polling.
 *
 * @param array<string,array<string,int|string>> $schedules Registered schedules.
 * @return array<string,array<string,int|string>>
 */
function paykfowo_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['paykfowo_five_minutes'] ) ) {
		$schedules['paykfowo_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS, // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Payment statuses need timely reconciliation.
			'display'  => __( 'Every five minutes', 'paykrypt-for-woocommerce' ),
		);
	}

	return $schedules;
}
add_filter( 'cron_schedules', 'paykfowo_cron_schedules' ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Payment statuses need timely reconciliation.

/**
 * Adds suggested PayKrypt disclosure text to the privacy policy guide.
 */
function paykfowo_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content  = '<p>' . esc_html__( 'When PayKrypt is enabled and a customer chooses it at checkout, the store sends the order total, currency, order number, billing email address, and the configured payment restrictions to PayKrypt to create and monitor a cryptocurrency payment.', 'paykrypt-for-woocommerce' ) . '</p>';
	$content .= sprintf(
		'<p>%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>.</p>',
		esc_html__( 'PayKrypt processes this information under its', 'paykrypt-for-woocommerce' ),
		esc_url( 'https://paykrypt.io/privacy' ),
		esc_html__( 'Privacy Policy', 'paykrypt-for-woocommerce' )
	);

	wp_add_privacy_policy_content( __( 'PayKrypt for WooCommerce', 'paykrypt-for-woocommerce' ), wp_kses_post( $content ) );
}
add_action( 'admin_init', 'paykfowo_add_privacy_policy_content' );

/**
 * Declares WooCommerce feature compatibility.
 */
function paykfowo_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'paykfowo_declare_compatibility' );

/**
 * Schedules polling on plugin activation.
 */
function paykfowo_activate() {
	if ( ! wp_next_scheduled( 'paykfowo_poll_orders' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'paykfowo_five_minutes', 'paykfowo_poll_orders' );
	}
}
register_activation_hook( __FILE__, 'paykfowo_activate' );

/**
 * Clears polling on plugin deactivation.
 */
function paykfowo_deactivate() {
	wp_clear_scheduled_hook( 'paykfowo_poll_orders' );
}
register_deactivation_hook( __FILE__, 'paykfowo_deactivate' );

/**
 * Shows a dependency notice when WooCommerce is unavailable.
 */
function paykfowo_missing_woocommerce_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'PayKrypt for WooCommerce requires WooCommerce to be installed and active.', 'paykrypt-for-woocommerce' );
		echo '</p></div>';
	}
}

/**
 * Initializes the payment gateway once WooCommerce is loaded.
 */
function paykfowo_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'paykfowo_missing_woocommerce_notice' );
		return;
	}

	require_once PAYKFOWO_PATH . 'includes/class-paykfowo-gateway.php';
	require_once PAYKFOWO_PATH . 'includes/class-paykfowo-admin.php';

	PAYKFOWO_Order_Sync::init();
	PAYKFOWO_Admin::init();
}
add_action( 'plugins_loaded', 'paykfowo_init', 20 );

/**
 * Registers PayKrypt with WooCommerce Checkout Blocks.
 *
 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry Registry instance.
 */
function paykfowo_register_blocks_payment_method( $payment_method_registry ) {
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	require_once PAYKFOWO_PATH . 'includes/class-paykfowo-blocks.php';
	$payment_method_registry->register( new PAYKFOWO_Blocks() );
}
add_action( 'woocommerce_blocks_payment_method_type_registration', 'paykfowo_register_blocks_payment_method' );

/**
 * Registers the gateway with WooCommerce.
 *
 * @param array<int,string> $gateways Gateway class names.
 * @return array<int,string>
 */
function paykfowo_add_gateway( $gateways ) {
	$gateways[] = 'PAYKFOWO_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'paykfowo_add_gateway' );
