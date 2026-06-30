<?php
/**
 * PayKrypt order polling and synchronization.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes WooCommerce orders with PayKrypt payment intents.
 */
class PAYKFOWO_Order_Sync {
	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'paykfowo_poll_orders', array( __CLASS__, 'poll_orders' ) );
	}

	/**
	 * Polls a batch of PayKrypt orders.
	 */
	public static function poll_orders() {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'PAYKFOWO_Gateway' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 25,
				'status'     => array( 'pending', 'on-hold' ),
				'orderby'    => 'date',
				'order'      => 'ASC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find orders with PayKrypt intents.
					array(
						'key'     => PAYKFOWO_Gateway::META_INTENT_ID,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			if ( $order instanceof WC_Order ) {
				self::sync_order( $order );
			}
		}
	}

	/**
	 * Synchronizes a single order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param bool     $manual Whether the sync was manually requested by an admin.
	 * @return string Final observed PayKrypt status.
	 * @throws PAYKFOWO_API_Exception When the API fails.
	 */
	public static function sync_order( WC_Order $order, $manual = false ) {
		if ( ! class_exists( 'PAYKFOWO_Gateway' ) ) {
			return '';
		}

		$intent_id = (string) $order->get_meta( PAYKFOWO_Gateway::META_INTENT_ID );
		if ( '' === $intent_id ) {
			return '';
		}

		$client = self::get_client_from_settings();
		$intent = $client->retrieve_payment_intent( $intent_id );
		$status = isset( $intent['status'] ) ? (string) $intent['status'] : '';

		$previous_status = (string) $order->get_meta( PAYKFOWO_Gateway::META_STATUS );
		$status_changed  = $status && $status !== $previous_status;

		if ( $status ) {
			$order->update_meta_data( PAYKFOWO_Gateway::META_STATUS, $status );
		}
		$order->update_meta_data( PAYKFOWO_Gateway::META_LAST_SYNC_AT, gmdate( 'c' ) );
		$order->delete_meta_data( PAYKFOWO_Gateway::META_LAST_ERROR );

		$summary       = isset( $intent['transactionsSummary'] ) && is_array( $intent['transactionsSummary'] ) ? $intent['transactionsSummary'] : array();
		$is_fully_paid = ! empty( $summary['isFullyPaid'] );

		switch ( $status ) {
			case 'confirmed':
				if ( $is_fully_paid ) {
					if ( ! $order->is_paid() ) {
						$order->payment_complete( $intent_id );
						$order->add_order_note(
							sprintf(
								/* translators: 1: PayKrypt intent ID. */
								__( 'PayKrypt payment confirmed and fully paid. Payment intent: %s', 'paykrypt-for-woocommerce' ),
								$intent_id
							)
						);
					} elseif ( $manual ) {
						$order->add_order_note( __( 'Manual PayKrypt sync: payment is already confirmed.', 'paykrypt-for-woocommerce' ) );
					}
				} else {
					self::add_status_note_if_needed(
						$order,
						$manual || $status_changed,
						__( 'PayKrypt marked the payment confirmed, but the transaction summary is not fully paid. Review the PayKrypt dashboard before fulfillment.', 'paykrypt-for-woocommerce' )
					);
				}
				break;

			case 'overpaid':
				if ( ! $order->is_paid() && $is_fully_paid ) {
					$order->payment_complete( $intent_id );
				}
				self::add_status_note_if_needed(
					$order,
					$manual || $status_changed,
					__( 'PayKrypt reports this payment as overpaid. Review the excess amount before refund handling.', 'paykrypt-for-woocommerce' )
				);
				break;

			case 'underpaid':
				if ( ! $order->has_status( 'on-hold' ) ) {
					$order->update_status( 'on-hold' );
				}
				self::add_status_note_if_needed(
					$order,
					$manual || $status_changed,
					self::build_underpaid_note( $summary )
				);
				break;

			case 'expired':
			case 'canceled':
				if ( ! $order->has_status( 'cancelled' ) && ! $order->is_paid() ) {
					$order->update_status(
						'cancelled',
						sprintf(
							/* translators: 1: PayKrypt payment status. */
							__( 'PayKrypt payment %s.', 'paykrypt-for-woocommerce' ),
							$status
						)
					);
				} else {
					self::add_status_note_if_needed(
						$order,
						$manual || $status_changed,
						sprintf(
							/* translators: 1: PayKrypt payment status. */
							__( 'Manual PayKrypt sync observed terminal status: %s.', 'paykrypt-for-woocommerce' ),
							$status
						)
					);
				}
				break;

			case 'awaiting_payment':
			case 'detected':
			case 'confirming':
				self::add_status_note_if_needed(
					$order,
					$manual || $status_changed,
					sprintf(
						/* translators: 1: PayKrypt payment status. */
						__( 'PayKrypt payment status: %s.', 'paykrypt-for-woocommerce' ),
						$status
					)
				);
				break;

			default:
				self::add_status_note_if_needed(
					$order,
					$manual || $status_changed,
					sprintf(
						/* translators: 1: PayKrypt payment status. */
						__( 'PayKrypt returned an unrecognized payment status: %s.', 'paykrypt-for-woocommerce' ),
						$status ? $status : __( 'unknown', 'paykrypt-for-woocommerce' )
					)
				);
				break;
		}

		$order->save();

		return $status;
	}

	/**
	 * Builds a configured PayKrypt client.
	 *
	 * @return PAYKFOWO_Client
	 */
	private static function get_client_from_settings() {
		$settings    = get_option( 'woocommerce_paykrypt_settings', array() );
		$environment = isset( $settings['environment'] ) ? $settings['environment'] : 'production';

		if ( 'custom' === $environment ) {
			$api_base_url = isset( $settings['custom_api_base_url'] ) ? $settings['custom_api_base_url'] : '';
		} else {
			$api_base_url = 'https://api.paykrypt.io';
		}

		return new PAYKFOWO_Client(
			$api_base_url,
			isset( $settings['api_key'] ) ? $settings['api_key'] : '',
			30,
			isset( $settings['debug'] ) ? $settings['debug'] : 'no'
		);
	}

	/**
	 * Adds an order note when needed.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param bool     $should_add Whether to add the note.
	 * @param string   $note Note content.
	 */
	private static function add_status_note_if_needed( WC_Order $order, $should_add, $note ) {
		if ( $should_add && $note ) {
			$order->add_order_note( $note );
		}
	}

	/**
	 * Builds an underpayment note.
	 *
	 * @param array<string,mixed> $summary Transaction summary.
	 * @return string
	 */
	private static function build_underpaid_note( array $summary ) {
		if ( isset( $summary['outstandingFiat'], $summary['fiatCurrency'] ) ) {
			return sprintf(
				/* translators: 1: outstanding amount, 2: fiat currency. */
				__( 'PayKrypt reports this payment as underpaid. Outstanding amount: %1$s %2$s.', 'paykrypt-for-woocommerce' ),
				$summary['outstandingFiat'],
				$summary['fiatCurrency']
			);
		}

		return __( 'PayKrypt reports this payment as underpaid.', 'paykrypt-for-woocommerce' );
	}
}
