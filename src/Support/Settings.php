<?php

namespace Ibantest\WooCommerce\Support;

use Ibantest\WooCommerce\Plugin;
use Ibantest\WooCommerce\Services\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( Encryption $encryption ): array {
		$fields = [
			'enabled' => [
				'title'   => __( 'Enable/Disable SEPA Direct Debit', 'ibantest-for-woocommerce' ),
				'label'   => __( 'Enable SEPA Direct Debit', 'ibantest-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'Title', 'ibantest-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown during checkout.', 'ibantest-for-woocommerce' ),
				'default'     => __( 'SEPA Direct Debit', 'ibantest-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'ibantest-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown during checkout.', 'ibantest-for-woocommerce' ),
				'default'     => __( 'Pay conveniently by SEPA Direct Debit.', 'ibantest-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'iban_validation_trigger' => [
				'title'       => __( 'IBAN check timing', 'ibantest-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose when the checkout should validate the IBAN before the final server-side order validation.', 'ibantest-for-woocommerce' ),
				'options'     => [
					'debounce' => __( 'Automatically after typing delay', 'ibantest-for-woocommerce' ),
					'focusout' => __( 'When leaving the IBAN field', 'ibantest-for-woocommerce' ),
					'submit'   => __( 'Only when placing the order', 'ibantest-for-woocommerce' ),
				],
				'default'     => 'debounce',
			],
			'iban_validation_delay' => [
				'title'             => __( 'IBAN check delay in milliseconds', 'ibantest-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Delay before automatic validation starts. Only used when automatic validation after typing delay is selected.', 'ibantest-for-woocommerce' ),
				'default'           => 450,
				'custom_attributes' => [
					'min'  => 0,
					'max'  => 5000,
					'step' => 50,
				],
			],
			'apikey' => [
				'title'       => __( 'IBANTEST API Key', 'ibantest-for-woocommerce' ),
				'type'        => 'password',
				'description' => sprintf(
					/* translators: %s: IBANTEST account registration link. */
					__( 'Used for live IBAN validation and your credit overview. No account yet? Register at IBANTEST and receive 100 free credits: %s', 'ibantest-for-woocommerce' ),
					'<a href="https://www.ibantest.com/" target="_blank" rel="noopener noreferrer">IBANTEST</a>'
				),
				'default'     => '',
				'desc_tip'    => false,
			],
			'encryption' => [
				'title'       => __( 'Encryption', 'ibantest-for-woocommerce' ),
				'type'        => 'title',
				'description' => self::encryption_description( $encryption ),
			],
			'payment_info' => [
				'title' => __( 'SEPA creditor payment information', 'ibantest-for-woocommerce' ),
				'type'  => 'title',
			],
			'creditor_name' => [
				'title' => __( 'Company name', 'ibantest-for-woocommerce' ),
				'type'  => 'text',
			],
			'creditor_account_holder' => [
				'title' => __( 'Company account holder', 'ibantest-for-woocommerce' ),
				'type'  => 'text',
			],
			'creditor_account_iban' => [
				'title' => __( 'Company account IBAN', 'ibantest-for-woocommerce' ),
				'type'  => 'text',
			],
			'creditor_agent_bic' => [
				'title' => __( 'Company account BIC', 'ibantest-for-woocommerce' ),
				'type'  => 'text',
			],
			'creditor_id' => [
				'title' => __( 'Company identification number', 'ibantest-for-woocommerce' ),
				'type'  => 'text',
			],
			'sepa_xml_format' => [
				'title'       => __( 'SEPA XML Export Format', 'ibantest-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Ask your bank which direct debit format is required.', 'ibantest-for-woocommerce' ),
				'options'     => [
					'pain.008.001.02' => 'pain.008.001.02',
					'pain.008.002.02' => 'pain.008.002.02',
					'pain.008.003.02' => 'pain.008.003.02',
				],
				'default'     => 'pain.008.002.02',
			],
			'sepa_mandate_section' => [
				'title' => __( 'SEPA Mandate', 'ibantest-for-woocommerce' ),
				'type'  => 'title',
			],
			'mandate_text' => [
				'title'       => __( 'SEPA Mandate Text', 'ibantest-for-woocommerce' ),
				'type'        => 'textarea',
				'css'         => 'min-height: 250px;',
				'default'     => self::default_mandate_text(),
				'description' => __( 'Available placeholders: [creditor_name], [creditor_id], [mandate_id], [account_holder], [street], [postcode], [city], [country], [iban], [bic], [date].', 'ibantest-for-woocommerce' ),
			],
			'enable_mandate_checkbox' => [
				'title'       => __( 'Checkbox agreement', 'ibantest-for-woocommerce' ),
				'label'       => __( 'Require agreement to the SEPA mandate', 'ibantest-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, customers must accept the SEPA Direct Debit Mandate.', 'ibantest-for-woocommerce' ),
				'default'     => 'yes',
			],
			'mandate_checkbox_label' => [
				'title'   => __( 'Checkbox label', 'ibantest-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'I hereby agree to the SEPA Direct Debit Mandate.', 'ibantest-for-woocommerce' ),
			],
			'mandate_id_format' => [
				'title'       => __( 'Mandate ID Format', 'ibantest-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '[id] is replaced by the WooCommerce order number.', 'ibantest-for-woocommerce' ),
				'default'     => 'MANDATE[id]',
			],
			'hide_iban_chars' => [
				'title'   => __( 'Hide IBAN characters', 'ibantest-for-woocommerce' ),
				'type'    => 'select',
				'options' => [
					'full'       => __( 'Show full IBAN', 'ibantest-for-woocommerce' ),
					'last3'      => __( 'Show last 3 characters', 'ibantest-for-woocommerce' ),
					'last4'      => __( 'Show last 4 characters', 'ibantest-for-woocommerce' ),
					'firstlast3' => __( 'Show first and last 3 characters', 'ibantest-for-woocommerce' ),
					'firstlast4' => __( 'Show first and last 4 characters', 'ibantest-for-woocommerce' ),
				],
				'default' => 'last4',
			],
		];

		return apply_filters( 'ibantest_for_woocommerce_gateway_settings', $fields );
	}

	public static function default_mandate_text(): string {
		return __(
			"[creditor_name]\nCreditor identifier: [creditor_id]\nMandate reference: [mandate_id]\n\nSEPA Direct Debit Mandate\nI authorise the creditor to collect payments from my account by direct debit. At the same time, I instruct my bank to honour direct debits drawn by the creditor.\n\nDebtor:\nAccount holder: [account_holder]\nStreet: [street]\nPostcode: [postcode]\nCity: [city]\nCountry: [country]\nIBAN: [iban]\nBIC: [bic]\n\n[city], [date], [account_holder]\n\nThis mandate was created electronically and is valid without signature.",
			'ibantest-for-woocommerce'
		);
	}

	private static function encryption_description( Encryption $encryption ): string {
		if ( $encryption->is_enabled() ) {
			return __( 'Encryption is configured. Bank account data is stored encrypted.', 'ibantest-for-woocommerce' );
		}

		return sprintf(
			/* translators: %s: generated wp-config.php snippet. */
			__( 'Add this constant to wp-config.php before enabling the gateway: %s', 'ibantest-for-woocommerce' ),
			'<pre><code>define( \'WC_IBANTEST_ENCRYPTION_KEY\', \'' . esc_html( $encryption->generate_random_key() ) . '\' );</code></pre>'
		);
	}
}
