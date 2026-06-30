<?php

namespace PayKryptWooCommerce\Tests;

use PAYKFOWO_Gateway;

class PayKryptGatewayTest extends TestCase {
	public function test_parse_csv_setting_trims_removes_empty_values_and_deduplicates(): void {
		$this->assertSame(
			array( 'ethereum', 'tron', 'base' ),
			PAYKFOWO_Gateway::parse_csv_setting( ' ethereum, tron,,ethereum, base ' )
		);
	}

	public function test_production_urls_are_defaults(): void {
		$gateway = new PAYKFOWO_Gateway();
		$gateway->settings = array();

		$this->assertSame( 'https://api.paykrypt.io', $gateway->get_api_base_url() );
		$this->assertSame( 'https://gate.paykrypt.io', $gateway->get_gateway_base_url() );
	}

	public function test_production_urls_are_used_for_production_environment(): void {
		$gateway = new PAYKFOWO_Gateway();
		$gateway->settings = array( 'environment' => 'production' );

		$this->assertSame( 'https://api.paykrypt.io', $gateway->get_api_base_url() );
		$this->assertSame( 'https://gate.paykrypt.io', $gateway->get_gateway_base_url() );
	}

	public function test_custom_urls_are_trimmed_and_checkout_url_is_constructed(): void {
		$gateway = new PAYKFOWO_Gateway();
		$gateway->settings = array(
			'environment'             => 'custom',
			'custom_api_base_url'     => 'http://localhost:3000/',
			'custom_gateway_base_url' => 'http://localhost:3001/',
		);

		$this->assertSame( 'http://localhost:3000', $gateway->get_api_base_url() );
		$this->assertSame( 'http://localhost:3001', $gateway->get_gateway_base_url() );
		$this->assertSame( 'http://localhost:3001/pay/pi_123', $gateway->build_checkout_url( 'pi_123' ) );
	}
}
