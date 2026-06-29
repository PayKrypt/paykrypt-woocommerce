<?php
/**
 * PayKrypt admin helpers.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds admin order actions for PayKrypt.
 */
class WC_PayKrypt_Admin {
	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'add_order_action' ) );
		add_action( 'woocommerce_order_action_paykrypt_sync_order', array( __CLASS__, 'handle_order_action' ) );
	}

	/**
	 * Adds the manual sync order action.
	 *
	 * @param array<string,string> $actions Existing actions.
	 * @return array<string,string>
	 */
	public static function add_order_action( $actions ) {
		$actions['paykrypt_sync_order'] = __( 'Sync PayKrypt payment status', 'paykrypt-for-woocommerce' );
		return $actions;
	}

	/**
	 * Handles manual sync from the order actions dropdown.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public static function handle_order_action( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $order->get_meta( PayKrypt_WC_Gateway::META_INTENT_ID ) ) {
			$order->add_order_note( __( 'Manual PayKrypt sync skipped: this order has no PayKrypt payment intent.', 'paykrypt-for-woocommerce' ) );
			return;
		}

		try {
			$status = WC_PayKrypt_Order_Sync::sync_order( $order, true );
			$order->add_order_note(
				sprintf(
					/* translators: 1: PayKrypt payment status. */
					__( 'Manual PayKrypt sync completed. Current PayKrypt status: %s.', 'paykrypt-for-woocommerce' ),
					$status ? $status : __( 'unknown', 'paykrypt-for-woocommerce' )
				)
			);
			$order->save();
		} catch ( WC_PayKrypt_API_Exception $exception ) {
			$order->update_meta_data( PayKrypt_WC_Gateway::META_LAST_ERROR, $exception->getMessage() );
			$order->add_order_note(
				sprintf(
					/* translators: 1: API error message. */
					__( 'Manual PayKrypt sync failed: %s', 'paykrypt-for-woocommerce' ),
					$exception->getMessage()
				)
			);
			$order->save();
		}
	}
}
