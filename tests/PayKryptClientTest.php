<?php

namespace PayKryptWooCommerce\Tests;

use Brain\Monkey\Functions;
use PAYKFOWO_API_Exception;
use PAYKFOWO_Client;

class PayKryptClientTest extends TestCase {
	public function test_create_payment_intent_sends_authorization_json_and_idempotency_headers(): void {
		Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				'https://api.paykrypt.io/v1/payment-intents',
				\Mockery::on(
					function ( $args ) {
						$body = json_decode( $args['body'], true );

						return 'POST' === $args['method']
							&& 'Bearer pk_test_123' === $args['headers']['Authorization']
							&& 'application/json' === $args['headers']['Content-Type']
							&& 'idem-123' === $args['headers']['Idempotency-Key']
							&& '100.00' === $body['amount']
							&& 'USD' === $body['currency'];
					}
				)
			)
			->andReturn(
				array(
					'response' => array( 'code' => 201, 'message' => 'Created' ),
					'body'     => '{"id":"pi_123","status":"awaiting_payment"}',
				)
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 201 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"id":"pi_123","status":"awaiting_payment"}' );

		$client = new PAYKFOWO_Client( 'https://api.paykrypt.io/', 'pk_test_123' );
		$intent = $client->create_payment_intent(
			array(
				'amount'   => '100.00',
				'currency' => 'USD',
			),
			'idem-123'
		);

		$this->assertSame( 'pi_123', $intent['id'] );
		$this->assertSame( 'awaiting_payment', $intent['status'] );
	}

	public function test_retrieve_payment_intent_uses_get_without_body(): void {
		Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				'https://api.paykrypt.io/v1/payment-intents/pi_123',
				\Mockery::on(
					function ( $args ) {
						return 'GET' === $args['method']
							&& ! isset( $args['body'] )
							&& 'Bearer pk_live_123' === $args['headers']['Authorization'];
					}
				)
			)
			->andReturn(
				array(
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'body'     => '{"id":"pi_123","status":"confirmed","transactionsSummary":{"isFullyPaid":true}}',
				)
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"id":"pi_123","status":"confirmed","transactionsSummary":{"isFullyPaid":true}}' );

		$client = new PAYKFOWO_Client( 'https://api.paykrypt.io', 'pk_live_123' );
		$intent = $client->retrieve_payment_intent( 'pi_123' );

		$this->assertSame( 'confirmed', $intent['status'] );
		$this->assertTrue( $intent['transactionsSummary']['isFullyPaid'] );
	}

	public function test_error_response_is_converted_to_api_exception(): void {
		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 403, 'message' => 'Forbidden' ),
					'body'     => '{"error":{"message":"API key missing payment_intents permission","code":"missing_permission"}}',
				)
			);

		Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 403 );
		Functions\expect( 'wp_remote_retrieve_response_message' )->once()->andReturn( 'Forbidden' );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"error":{"message":"API key missing payment_intents permission","code":"missing_permission"}}' );

		$client = new PAYKFOWO_Client( 'https://api.paykrypt.io', 'pk_live_123' );

		$this->expectException( PAYKFOWO_API_Exception::class );
		$this->expectExceptionMessage( 'API key missing payment_intents permission' );

		try {
			$client->create_payment_intent( array( 'amount' => '100.00', 'currency' => 'USD' ), 'idem-123' );
		} catch ( PAYKFOWO_API_Exception $exception ) {
			$this->assertSame( 403, $exception->get_status_code() );
			$this->assertSame( 'missing_permission', $exception->get_paykrypt_code() );
			throw $exception;
		}
	}
}
