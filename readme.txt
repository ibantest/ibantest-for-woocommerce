=== IBANTEST for WooCommerce  ===
Contributors: IBANTEST
Tags: woocommerce, IBAN, BIC, SEPA, direct debit, Lastschrift, sichere Zahlungsweise, Validierung, validation, SWIFT-Code, WooCommerce Subscriptions
Requires at least: 3.8
Tested up to: 4.9
Requires PHP: 5.6.0
WC requires at least: 3.0
WC tested up to: 3.4
Stable tag: 1.3.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides direct debit payment with IBAN and BIC validation for WooCommerce

== Description ==

** IBANTEST for WooCommerce ** extends WooCommerce by the payment method direct debit including (optional) validation of the entered IBAN.
It helps to avoid incorrect IBAN entries and return debits.

To use the full functionality of IBAN validation, an account at [IBANTEST](https://www.ibantest.com "IBANTEST") with credits is required.
Create your IBANTEST account now and receive 100 credits for free (there is neither an annual fee nor setup costs).
We also have a fair usage feature: same IBAN validations within 15 minutes are charged only once.

Please have a look at our [IBANTEST info page](https://www.ibantest.com/en/wordpress "IBANTEST Info page") or try it at
[IBANTEST Wordpress Demo](https://wpdemo.ibantest.com "IBANTEST Wordpress Demo")

== Features ==

* Direct debit payment for WooCommerce
* IBAN / BIC validation
* best usability with AJAX: finds the matching BIC for the IBAN and automatically fills the BIC field (not available for all countries)
* fair usage: same IBAN validations within 15 minutes are charged only once.
* Export SEPA Direct Debit XML
* Encryption of IBAN, BIC and account holder
* saves (encrypted) bank account data in the user account for the next order
* SEPA Mandate generation and handling
* show only defined number of IBAN-chars in admin order view
* supports WooCommerce Subscriptions

== Installation ==

We recommend installing IBANTEST for WooCommerce through the WordPress Backend.
Go to the plugin-settings for configuration.

== Screenshots ==

1. Plugin settings
2. SEPA creditor payment information
3. SEPA-Mandate
4. Hide IBAN characters
5. Admin order view with SEPA Direct Debit XML download

== Do you want to report a bug or improve IBANTEST for WooCommerce? ==

[GitHub repository](https://github.com/ibantest/ibantest-for-woocommerce).

== Changelog ==
= 1.3.0 =
* WooCommerce Subscriptions support added

= 1.2.0 =
* rename woocommerce-ibantest to ibantest-for-woocommerce

= 1.1.0 =
* rename assets folder to files for deploy reasons

= 1.0.0 =
* Initial release.


== Upgrade Notice ==

= 1.0.0 =
no upgrade - just install :)


