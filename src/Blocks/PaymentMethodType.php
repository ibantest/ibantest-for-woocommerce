<?php

namespace Ibantest\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PaymentMethodType extends AbstractPaymentMethodType {
	protected $name = Plugin::GATEWAY_ID;

	public function __construct( private Plugin $plugin ) {}

	public function initialize(): void {
		$this->settings = $this->plugin->settings();
	}

	public function is_active(): bool {
		return $this->plugin->gateway()->is_available();
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'ibantest-blocks-checkout',
			WC_IBANTEST_PLUGIN_URL . '/assets/js/blocks-checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			WC_IBANTEST_VERSION,
			true
		);

		wp_set_script_translations( 'ibantest-blocks-checkout', 'ibantest-for-woocommerce', WC_IBANTEST_PLUGIN_PATH . '/languages' );

		return [ 'ibantest-blocks-checkout' ];
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_script_handles_for_admin(): array {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_style_handles(): array {
		wp_register_style(
			'ibantest-checkout',
			WC_IBANTEST_PLUGIN_URL . '/assets/css/checkout.css',
			[],
			WC_IBANTEST_VERSION
		);

		return [ 'ibantest-checkout' ];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		return $this->plugin->gateway()->block_data();
	}
}
