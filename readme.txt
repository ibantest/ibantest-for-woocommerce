=== IBANTEST for WooCommerce ===
Contributors: IBANTEST
Tags: woocommerce, IBAN, BIC, SEPA, direct debit, Lastschrift, sichere Zahlungsweise, Validierung, validation, SWIFT-Code, WooCommerce Subscriptions
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
WC requires at least: 10.0
WC tested up to: 10.7
Stable tag: 2.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides SEPA direct debit payment with IBAN validation for WooCommerce.

== Description ==

**IBANTEST for WooCommerce** extends WooCommerce with a SEPA direct debit payment method including IBANTEST API validation of the entered IBAN.
It helps to avoid incorrect IBAN entries and return debits.

To validate IBANs in checkout, an account at [IBANTEST](https://www.ibantest.com "IBANTEST") with a valid API key and credits is required.

== Features ==

* SEPA direct debit payment for WooCommerce
* IBAN-only checkout experience with automatic IBANTEST API validation
* Configurable validation timing: after typing delay, on field blur, or only when placing the order
* Displays validation status and optional bank details when returned by the IBANTEST API
* Checkout Blocks compatibility
* High-Performance Order Storage compatibility
* Export SEPA Direct Debit XML
* Encrypted storage of IBAN, BIC and account holder
* SEPA Mandate generation and handling
* Configurable masking of IBAN characters in admin order view and emails
* Admin onboarding with API key verification and credit overview
* Translations for German, Italian, French, Dutch, Spanish and Polish
* supports WooCommerce Subscriptions

== Installation ==

We recommend installing IBANTEST for WooCommerce through the WordPress backend.
Go to the payment method settings for configuration.

Version 2.x is a breaking rewrite for current WordPress and WooCommerce versions. Configure a `WC_IBANTEST_ENCRYPTION_KEY` constant in `wp-config.php` before enabling the payment method.

== Screenshots ==

1. Plugin settings
2. SEPA creditor payment information
3. SEPA Mandate
4. Hide IBAN characters
5. Admin order view with SEPA Direct Debit XML download

== Do you want to report a bug or improve IBANTEST for WooCommerce? ==

[GitHub repository](https://github.com/ibantest/ibantest-for-woocommerce).

== Development ==

Run the automated tests with `composer test`.
Update translation sources and compiled language artifacts with `composer i18n`.
The source tree may keep the local Composer `vendor/` directory for development and tests. Release ZIPs must never be created from the source tree directly.
Create a release-ready copy with production dependencies only via `composer build:release`; the output is written to `build/ibantest-for-woocommerce` and excludes development files before installing `vendor/` with `--no-dev`.

== Changelog ==

= 2.0.0 =
* Modern rewrite for WordPress 6.9 and WooCommerce 10.7.
* Added Checkout Blocks integration.
* Added HPOS-compatible order meta handling.
* Added encryption-required storage policy and nonce-protected SEPA export.
* Added PHPUnit test setup.
* Added IBAN-only checkout UX with configurable validation timing.
* Added AJAX status display and optional bank detail display in classic checkout and Checkout Blocks.
* Added multilingual translation files for German, Italian, French, Dutch, Spanish and Polish.
* Improved release build, metadata and cleanup workflow.

= 1.3.1 =
* WooCommerce 3.5 compatibility
* Wordpress 5 compatibility

= 1.3.0 =
* WooCommerce Subscriptions support added

= 1.2.0 =
* rename woocommerce-ibantest to ibantest-for-woocommerce

= 1.1.0 =
* rename assets folder to files for deploy reasons

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Breaking rewrite. Existing plugin settings and stored order meta are not migrated.

= 1.0.0 =
no upgrade - just install :)
