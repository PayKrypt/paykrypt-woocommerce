<?php
/**
 * WooCommerce PayKrypt gateway.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayKrypt WooCommerce payment gateway.
 */
class PayKrypt_WC_Gateway extends WC_Payment_Gateway {
	const META_INTENT_ID       = '_paykrypt_payment_intent_id';
	const META_IDEMPOTENCY_KEY = '_paykrypt_idempotency_key';
	const META_CHECKOUT_URL    = '_paykrypt_checkout_url';
	const META_STATUS          = '_paykrypt_status';
	const META_LAST_SYNC_AT    = '_paykrypt_last_sync_at';
	const META_LAST_ERROR      = '_paykrypt_last_error';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'paykrypt';
		$this->method_title       = __( 'PayKrypt', 'paykrypt-for-woocommerce' );
		$this->method_description = __( 'Accept crypto payments through PayKrypt hosted checkout.', 'paykrypt-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Crypto via PayKrypt', 'paykrypt-for-woocommerce' ) );
		$this->description = $this->get_option( 'description', __( 'Pay securely with cryptocurrency using PayKrypt.', 'paykrypt-for-woocommerce' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initializes admin settings.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                  => array(
				'title'   => __( 'Enable/Disable', 'paykrypt-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayKrypt payments', 'paykrypt-for-woocommerce' ),
				'default' => 'no',
			),
			'title'                    => array(
				'title'       => __( 'Title', 'paykrypt-for-woocommerce' ),
				'type'        => 'text',
				'default'     => __( 'Crypto via PayKrypt', 'paykrypt-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Shown to customers during checkout.', 'paykrypt-for-woocommerce' ),
			),
			'description'              => array(
				'title'   => __( 'Description', 'paykrypt-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely with cryptocurrency using PayKrypt.', 'paykrypt-for-woocommerce' ),
			),
			'environment'              => array(
				'title'   => __( 'Environment', 'paykrypt-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'production',
				'options' => array(
					'production' => __( 'Production', 'paykrypt-for-woocommerce' ),
					'custom'     => __( 'Custom', 'paykrypt-for-woocommerce' ),
				),
			),
			'api_key'                  => array(
				'title'       => __( 'Merchant API Key', 'paykrypt-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Use a PayKrypt API key with the payment_intents permission. Current PayKrypt merchant keys use the pk_... format.', 'paykrypt-for-woocommerce' ),
				'default'     => '',
			),
			'custom_api_base_url'      => array(
				'title'       => __( 'Custom API Base URL', 'paykrypt-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Used only when Environment is Custom.', 'paykrypt-for-woocommerce' ),
				'default'     => '',
				'placeholder' => 'http://localhost:3000',
			),
			'custom_gateway_base_url'  => array(
				'title'       => __( 'Custom Checkout Base URL', 'paykrypt-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Used only when Environment is Custom.', 'paykrypt-for-woocommerce' ),
				'default'     => '',
				'placeholder' => 'http://localhost:3001',
			),
			'expires_in_minutes'       => array(
				'title'       => __( 'Intent Expiry Minutes', 'paykrypt-for-woocommerce' ),
				'type'        => 'number',
				'default'     => '60',
				'description' => __( 'How long a PayKrypt checkout session remains payable.', 'paykrypt-for-woocommerce' ),
			),
			'amount_tolerance_percent' => array(
				'title'       => __( 'Amount Tolerance Percent', 'paykrypt-for-woocommerce' ),
				'type'        => 'number',
				'default'     => '0',
				'description' => __( 'Omit or use 0 for exact payment matching. Positive values allow small underpayments.', 'paykrypt-for-woocommerce' ),
			),
			'allowed_chains'           => array(
				'title'       => __( 'Allowed Chains', 'paykrypt-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'ethereum,tron',
				'description' => __( 'Optional comma-separated chain IDs. Leave empty to use merchant defaults.', 'paykrypt-for-woocommerce' ),
			),
			'allowed_assets'           => array(
				'title'       => __( 'Allowed Assets', 'paykrypt-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'USDT,USDC',
				'description' => __( 'Optional comma-separated asset symbols. Leave empty to use merchant defaults.', 'paykrypt-for-woocommerce' ),
			),
			'debug'                    => array(
				'title'   => __( 'Debug Logging', 'paykrypt-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Log PayKrypt API requests in WooCommerce logs', 'paykrypt-for-woocommerce' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Processes a checkout payment.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string,string>
	 * @throws WC_PayKrypt_API_Exception When PayKrypt cannot create a payment intent.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Unable to load the order for PayKrypt payment.', 'paykrypt-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$existing_checkout_url = $order->get_meta( self::META_CHECKOUT_URL );
		$existing_intent_id    = $order->get_meta( self::META_INTENT_ID );
		if ( $existing_checkout_url && $existing_intent_id ) {
			return array(
				'result'   => 'success',
				'redirect' => esc_url_raw( $existing_checkout_url ),
			);
		}

		try {
			if ( '' === $this->get_gateway_base_url() ) {
				throw new WC_PayKrypt_API_Exception( __( 'PayKrypt checkout base URL is not configured.', 'paykrypt-for-woocommerce' ) );
			}

			$client          = $this->get_client();
			$idempotency_key = $this->get_or_create_idempotency_key( $order );
			$payload         = $this->build_payment_intent_payload( $order );
			$intent          = $client->create_payment_intent( $payload, $idempotency_key );
			$intent_id       = isset( $intent['id'] ) ? (string) $intent['id'] : '';

			if ( '' === $intent_id ) {
				throw new WC_PayKrypt_API_Exception( __( 'PayKrypt did not return a payment intent ID.', 'paykrypt-for-woocommerce' ) );
			}

			$checkout_url = $this->build_checkout_url( $intent_id );

			$order->update_meta_data( self::META_INTENT_ID, $intent_id );
			$order->update_meta_data( self::META_CHECKOUT_URL, $checkout_url );
			$order->update_meta_data( self::META_STATUS, isset( $intent['status'] ) ? (string) $intent['status'] : 'awaiting_payment' );
			$order->update_meta_data( self::META_LAST_SYNC_AT, gmdate( 'c' ) );
			$order->delete_meta_data( self::META_LAST_ERROR );
			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: 1: PayKrypt intent ID. */
					__( 'Awaiting PayKrypt payment. Payment intent: %s', 'paykrypt-for-woocommerce' ),
					$intent_id
				)
			);
			$order->save();

			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}

			return array(
				'result'   => 'success',
				'redirect' => esc_url_raw( $checkout_url ),
			);
		} catch ( WC_PayKrypt_API_Exception $exception ) {
			$message = sprintf(
				/* translators: 1: API error message. */
				__( 'PayKrypt payment could not be started: %s', 'paykrypt-for-woocommerce' ),
				$exception->getMessage()
			);
			$order->update_meta_data( self::META_LAST_ERROR, $exception->getMessage() );
			$order->add_order_note( $message );
			$order->save();
			wc_add_notice( $message, 'error' );

			return array( 'result' => 'failure' );
		}
	}

	/**
	 * Returns the client for current settings.
	 *
	 * @return WC_PayKrypt_Client
	 */
	public function get_client() {
		return new WC_PayKrypt_Client(
			$this->get_api_base_url(),
			$this->get_option( 'api_key', '' ),
			30,
			$this->get_option( 'debug', 'no' )
		);
	}

	/**
	 * Builds the API base URL from settings.
	 *
	 * @return string
	 */
	public function get_api_base_url() {
		$environment = $this->get_option( 'environment', 'production' );
		if ( 'custom' === $environment ) {
			return untrailingslashit( $this->get_option( 'custom_api_base_url', '' ) );
		}

		return 'https://api.paykrypt.io';
	}

	/**
	 * Builds the hosted checkout base URL from settings.
	 *
	 * @return string
	 */
	public function get_gateway_base_url() {
		$environment = $this->get_option( 'environment', 'production' );
		if ( 'custom' === $environment ) {
			return untrailingslashit( $this->get_option( 'custom_gateway_base_url', '' ) );
		}

		return 'https://gate.paykrypt.io';
	}

	/**
	 * Builds the PayKrypt checkout URL.
	 *
	 * @param string $intent_id PayKrypt payment intent ID.
	 * @return string
	 */
	public function build_checkout_url( $intent_id ) {
		return trailingslashit( $this->get_gateway_base_url() ) . 'pay/' . rawurlencode( (string) $intent_id );
	}

	/**
	 * Builds a payment intent payload from a WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string,mixed>
	 */
	private function build_payment_intent_payload( WC_Order $order ) {
		$payload = array(
			'amount'      => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
			'currency'    => $order->get_currency(),
			'description' => sprintf(
				/* translators: 1: order number. */
				__( 'WooCommerce Order #%s', 'paykrypt-for-woocommerce' ),
				$order->get_order_number()
			),
		);

		$email = $order->get_billing_email();
		if ( is_email( $email ) ) {
			$payload['customerEmail'] = $email;
		}

		$allowed_chains = self::parse_csv_setting( $this->get_option( 'allowed_chains', '' ) );
		if ( ! empty( $allowed_chains ) ) {
			$payload['allowedChains'] = $allowed_chains;
		}

		$allowed_assets = self::parse_csv_setting( $this->get_option( 'allowed_assets', '' ) );
		if ( ! empty( $allowed_assets ) ) {
			$payload['allowedAssets'] = $allowed_assets;
		}

		$expires_in_minutes = absint( $this->get_option( 'expires_in_minutes', 60 ) );
		if ( $expires_in_minutes > 0 ) {
			$payload['expiresInMinutes'] = $expires_in_minutes;
		}

		$tolerance = (float) $this->get_option( 'amount_tolerance_percent', 0 );
		if ( $tolerance > 0 ) {
			$payload['amountTolerancePercent'] = $tolerance;
		}

		return $payload;
	}

	/**
	 * Returns an existing order idempotency key or creates one.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	private function get_or_create_idempotency_key( WC_Order $order ) {
		$key = (string) $order->get_meta( self::META_IDEMPOTENCY_KEY );
		if ( '' === $key ) {
			$key = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : md5( uniqid( 'paykrypt-', true ) );
			$order->update_meta_data( self::META_IDEMPOTENCY_KEY, $key );
			$order->save();
		}

		return $key;
	}

	/**
	 * Parses comma-separated settings into unique string values.
	 *
	 * @param string $value Raw setting.
	 * @return array<int,string>
	 */
	public static function parse_csv_setting( $value ) {
		$items = array_filter(
			array_map(
				static function ( $item ) {
					return trim( (string) $item );
				},
				explode( ',', (string) $value )
			)
		);

		return array_values( array_unique( $items ) );
	}
}
