=== PayKrypt for WooCommerce ===
Contributors: paykryptio
Tags: woocommerce, crypto payments, bitcoin payments, payment gateway, cryptocurrency
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.0
WC requires at least: 7.0
WC tested up to: 10.9
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payments in WooCommerce through PayKrypt hosted checkout.

== Description ==

PayKrypt for WooCommerce lets merchants accept cryptocurrency payments through PayKrypt hosted checkout.

The plugin creates a PayKrypt payment intent for each WooCommerce order, redirects the customer to PayKrypt hosted checkout, and updates the WooCommerce order by polling PayKrypt payment intent status. Webhooks are not required for this version.

= Features =

* Hosted crypto checkout powered by PayKrypt.
* Payment intent creation with per-order idempotency keys.
* Automatic WooCommerce order status polling.
* Manual order status sync from the WooCommerce order actions menu.
* Production and custom environment settings.
* Optional allowed asset and chain filters.
* HPOS-compatible order metadata.
* WooCommerce Checkout Blocks support.

= Requirements =

* WordPress 6.0 or higher.
* WooCommerce 7.0 or higher.
* PHP 7.4 or higher.
* A PayKrypt merchant API key that can create and read payment intents.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin, or install it from the WordPress.org plugin directory.
3. Activate PayKrypt for WooCommerce.
4. In the PayKrypt merchant dashboard, create an API key that can create and read payment intents.
5. In WooCommerce, open Settings > Payments > PayKrypt.
6. Paste the PayKrypt API key and configure expiry, amount tolerance, and optional allowed chains/assets.

== External Service ==

This plugin connects to PayKrypt, a third-party hosted cryptocurrency payment service, when a customer chooses PayKrypt at checkout and while WooCommerce synchronizes the resulting payment status.

To create a payment intent, the plugin sends the order total, currency, order number in the payment description, customer billing email address, configured allowed chains and assets, expiry, and amount tolerance to `https://api.paykrypt.io`. The merchant API key is sent in the Authorization header. During scheduled and manual status synchronization, the plugin sends the PayKrypt payment intent ID to the same API. The returned intent ID, checkout URL, status, last synchronization time, and API error message are stored as private WooCommerce order metadata.

After the payment intent is created, the customer is redirected to PayKrypt hosted checkout at `https://gate.paykrypt.io`. PayKrypt may collect information from the customer under its own policies. The service is required for this plugin to process payments; no data is sent to PayKrypt until the merchant configures the plugin and a customer selects PayKrypt.

* PayKrypt service: https://paykrypt.io/
* PayKrypt API documentation: https://docs.paykrypt.io/
* PayKrypt Terms of Use: https://paykrypt.io/tos
* PayKrypt Privacy Policy: https://paykrypt.io/privacy

== Order Status ==

* `awaiting_payment`, `detected`, `confirming`: order remains pending/on-hold.
* `confirmed` with `transactionsSummary.isFullyPaid`: order is marked paid.
* `expired` or `canceled`: order is cancelled when not already paid.
* `underpaid` or `overpaid`: an order note is added for merchant review.

== Frequently Asked Questions ==

= Do customers pay inside WooCommerce? =

No. Customers are redirected to PayKrypt hosted checkout. PayKrypt handles the crypto payment experience, address selection, QR code, and payment status page.

= Does this plugin require webhooks? =

No. This version uses polling to reconcile WooCommerce orders with PayKrypt payment intents.

= Which API key should I use? =

Use a PayKrypt merchant API key in the `pk_...` format that can create and read payment intents.

= Does the plugin support Checkout Blocks? =

Yes. The plugin registers a payment method for WooCommerce Checkout Blocks as well as classic checkout.

= Does the plugin store private wallet keys? =

No. The plugin stores only PayKrypt payment intent metadata and calls PayKrypt APIs using the configured merchant API key.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added hosted PayKrypt checkout redirect flow.
* Added polling-based order reconciliation.
* Added HPOS and Checkout Blocks compatibility.

== Upgrade Notice ==

= 0.1.0 =

Initial public release.
