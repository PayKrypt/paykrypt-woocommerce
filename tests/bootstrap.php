<?php

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( 'WC_Logger' ) ) {
	class WC_Logger {
		public function debug( $message, $context = array() ) {}
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	class WC_Payment_Gateway {
		public $id = '';
		public $method_title = '';
		public $method_description = '';
		public $has_fields = false;
		public $supports = array();
		public $form_fields = array();
		public $settings = array();
		public $title = '';
		public $description = '';
		public $enabled = 'no';

		public function init_settings() {}

		public function process_admin_options() {}

		public function get_option( $key, $default = '' ) {
			return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wc_string_to_bool' ) ) {
	function wc_string_to_bool( $value ) {
		return true === $value || 'yes' === $value || '1' === $value || 1 === $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( intval( $value ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return untrailingslashit( $string ) . '/';
	}
}

if ( ! function_exists( 'wc_get_logger' ) ) {
	function wc_get_logger() {
		return new WC_Logger();
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wc-paykrypt-api-exception.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-paykrypt-client.php';
require_once dirname( __DIR__ ) . '/includes/class-paykrypt-wc-gateway.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-paykrypt-order-sync.php';
