<?php
/**
 * PayKrypt API exception.
 *
 * @package PayKryptWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown for PayKrypt API failures.
 */
class PAYKFOWO_API_Exception extends Exception {
	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected $status_code;

	/**
	 * PayKrypt error code.
	 *
	 * @var string
	 */
	protected $paykrypt_code;

	/**
	 * Raw response body.
	 *
	 * @var string
	 */
	protected $response_body;

	/**
	 * Constructor.
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP status code.
	 * @param string $paykrypt_code PayKrypt error code.
	 * @param string $response_body Raw response body.
	 */
	public function __construct( $message, $status_code = 0, $paykrypt_code = '', $response_body = '' ) {
		parent::__construct( $message, $status_code );
		$this->status_code   = absint( $status_code );
		$this->paykrypt_code = (string) $paykrypt_code;
		$this->response_body = (string) $response_body;
	}

	/**
	 * Returns the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code() {
		return $this->status_code;
	}

	/**
	 * Returns the PayKrypt error code.
	 *
	 * @return string
	 */
	public function get_paykrypt_code() {
		return $this->paykrypt_code;
	}

	/**
	 * Returns the raw response body.
	 *
	 * @return string
	 */
	public function get_response_body() {
		return $this->response_body;
	}
}
