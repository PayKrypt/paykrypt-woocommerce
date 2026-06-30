<?php
/**
 * PayKrypt API client.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin REST client that uses WordPress HTTP APIs.
 */
class PAYKFOWO_Client {
	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * Merchant API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Request timeout.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * WooCommerce logger.
	 *
	 * @var WC_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string $api_base_url API base URL.
	 * @param string $api_key Merchant API key.
	 * @param int    $timeout Timeout in seconds.
	 * @param bool   $debug Whether to log debug details.
	 */
	public function __construct( $api_base_url, $api_key, $timeout = 30, $debug = false ) {
		$this->api_base_url = untrailingslashit( trim( (string) $api_base_url ) );
		$this->api_key      = trim( (string) $api_key );
		$this->timeout      = max( 5, absint( $timeout ) );
		$this->debug        = wc_string_to_bool( $debug );
		$this->logger       = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
	}

	/**
	 * Creates a PayKrypt payment intent.
	 *
	 * @param array<string,mixed> $payload Request body.
	 * @param string              $idempotency_key Idempotency key.
	 * @return array<string,mixed>
	 * @throws PAYKFOWO_API_Exception When the API fails.
	 */
	public function create_payment_intent( array $payload, $idempotency_key ) {
		return $this->request( 'POST', '/v1/payment-intents', $payload, (string) $idempotency_key );
	}

	/**
	 * Retrieves a PayKrypt payment intent.
	 *
	 * @param string $payment_intent_id Payment intent ID.
	 * @return array<string,mixed>
	 * @throws PAYKFOWO_API_Exception When the API fails.
	 */
	public function retrieve_payment_intent( $payment_intent_id ) {
		$payment_intent_id = rawurlencode( (string) $payment_intent_id );
		return $this->request( 'GET', '/v1/payment-intents/' . $payment_intent_id );
	}

	/**
	 * Sends an API request.
	 *
	 * @param string                   $method HTTP method.
	 * @param string                   $path Request path.
	 * @param array<string,mixed>|null $payload JSON payload.
	 * @param string                   $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>
	 * @throws PAYKFOWO_API_Exception When the API fails.
	 */
	private function request( $method, $path, $payload = null, $idempotency_key = '' ) {
		if ( '' === $this->api_base_url ) {
			throw new PAYKFOWO_API_Exception( esc_html__( 'PayKrypt API base URL is not configured.', 'paykrypt-for-woocommerce' ) );
		}

		if ( '' === $this->api_key ) {
			throw new PAYKFOWO_API_Exception( esc_html__( 'PayKrypt API key is not configured.', 'paykrypt-for-woocommerce' ) );
		}

		$url     = $this->api_base_url . $path;
		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		);

		if ( '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => $this->timeout,
		);

		if ( null !== $payload ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$this->log( 'Request ' . $method . ' ' . $path );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = sanitize_text_field( $response->get_error_message() );
			$this->log( 'Request failed: ' . $error_message );
			throw new PAYKFOWO_API_Exception( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Sanitized above; exception messages are not direct output.
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			$error_code    = '';

			if ( is_array( $decoded ) ) {
				if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
					$error_message = $decoded['error']['message'] ?? $error_message;
					$error_code    = $decoded['error']['code'] ?? '';
				} else {
					$error_message = $decoded['message'] ?? $decoded['error'] ?? $error_message;
					$error_code    = $decoded['code'] ?? '';
				}
			}

			$this->log(
				'Request returned error',
				array(
					'status' => $status_code,
				)
			);

			$error_message = sanitize_text_field( (string) $error_message );
			$error_code    = sanitize_key( (string) $error_code );
			throw new PAYKFOWO_API_Exception( $error_message, $status_code, $error_code, $body ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Values are sanitized or retained as non-output diagnostic data.
		}

		if ( '' === $body ) {
			return array();
		}

		if ( ! is_array( $decoded ) ) {
			throw new PAYKFOWO_API_Exception( esc_html__( 'PayKrypt returned an invalid JSON response.', 'paykrypt-for-woocommerce' ), $status_code, '', $body ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Raw body is retained as non-output diagnostic data.
		}

		$this->log( 'Request succeeded', array( 'status' => $status_code ) );

		return $decoded;
	}

	/**
	 * Writes debug logs when enabled.
	 *
	 * @param string              $message Log message.
	 * @param array<string,mixed> $context Context.
	 */
	private function log( $message, array $context = array() ) {
		if ( ! $this->debug || ! $this->logger ) {
			return;
		}

		$context['source'] = 'paykrypt';
		$this->logger->debug( $message, $context );
	}
}
