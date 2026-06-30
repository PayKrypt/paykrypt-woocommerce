<?php

namespace PayKryptWooCommerce\Tests;

use PAYKFOWO_API_Exception;

class PayKryptApiExceptionTest extends TestCase {
	public function test_it_exposes_api_error_details(): void {
		$exception = new PAYKFOWO_API_Exception(
			'Invalid API key',
			401,
			'authentication_error',
			'{"error":{"message":"Invalid API key"}}'
		);

		$this->assertSame( 'Invalid API key', $exception->getMessage() );
		$this->assertSame( 401, $exception->get_status_code() );
		$this->assertSame( 'authentication_error', $exception->get_paykrypt_code() );
		$this->assertSame( '{"error":{"message":"Invalid API key"}}', $exception->get_response_body() );
	}
}

