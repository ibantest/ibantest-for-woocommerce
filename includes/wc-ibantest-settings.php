<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_IBANTEST_Settings' ) ) :

	/**
	 * Adds Settings Interface to WooCommerce Settings Tabs
	 *
	 * @class        WC_IBANTEST_Settings
	 * @version        1.0.0
	 * @author        IBANTEST
	 */
	class WC_IBANTEST_Settings {
		/**
		 * Returns the fields.
		 */
		public static function fields() {
			$settings = [
				'enabled'                 => [
					'title'       => __( 'Enable/Disable SEPA Direct Debit', 'ibantest-for-woocommerce' ),
					'label'       => __( 'Enable SEPA Direct Debit', 'ibantest-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				],
				'title'                   => [
					'title'       => __( 'Title', 'ibantest-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Payment method title.', 'ibantest-for-woocommerce' ),
					'default'     => __( 'SEPA Direct Debit', 'ibantest-for-woocommerce' ),
					'desc_tip'    => true,
				],
				'description'             => [
					'title'       => __( 'Description', 'ibantest-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description.', 'ibantest-for-woocommerce' ),
					'default'     => __( 'Pay conveniently by SEPA Direct Debit.', 'ibantest-for-woocommerce' ),
					'desc_tip'    => true,
				],
				'credentials'             => [
					'title'       => __( 'IBANTEST API credentials', 'ibantest-for-woocommerce' ),
					'description' => __( 'Create your IBANTEST account now and <b>receive 100 credits for free</b>. <br />- no annual fee<br />- no setup fee <br /> <a href="https://www.ibantest.com/">https://www.ibantest.com</a>', 'ibantest-for-woocommerce' ),
					'type'        => 'title',
				],
				'apikey'                  => [
					'title'       => __( 'IBANTEST API Key', 'ibantest-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Get your API keys from your IBANTEST account.', 'ibantest-for-woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				],
				'encryption'              => [
					'title' => __( 'Encryption', 'ibantest-for-woocommerce' ),
					'type'  => 'title',
				],
				'payment_info'            => [
					'title' => __( 'SEPA creditor payment information', 'ibantest-for-woocommerce' ),
					'type'  => 'title',
				],
				'creditor_name'           => [
					'title' => __( 'Company name', 'ibantest-for-woocommerce' ),
					'type'  => 'text',
				],
				'creditor_account_holder' => [
					'title' => __( 'Company account holder', 'ibantest-for-woocommerce' ),
					'type'  => 'text',
				],
				'creditor_account_iban'   => [
					'title' => __( 'Company account IBAN', 'ibantest-for-woocommerce' ),
					'type'  => 'text',
				],
				'creditor_agent_bic'      => [
					'title' => __( 'Company account BIC', 'ibantest-for-woocommerce' ),
					'type'  => 'text',
				],
				'creditor_id'             => [
					'title' => __( 'Company identification number', 'ibantest-for-woocommerce' ),
					'type'  => 'text',
				],
				'sepa_xml_format'         => [
					'title'       => __( 'SEPA XML Export Format', 'ibantest-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose the correct format for the SEPA XML export. Please ask your bank what format is required.', 'ibantest-for-woocommerce' ),
					'options'     => array(
						'pain.001.001.03' => __( 'pain.001.001.03', 'ibantest-for-woocommerce' ),
						'pain.001.002.03' => __( 'pain.001.002.03', 'ibantest-for-woocommerce' ),
						'pain.008.001.02' => __( 'pain.008.001.02', 'ibantest-for-woocommerce' ),
						'pain.008.002.02' => __( 'pain.008.002.02', 'ibantest-for-woocommerce' ),
					),
					'desc_tip'    => false,
					'default'     => 'pain.008.002.02',
				],
				'sepa_mandate_section'    => [
					'title' => __( 'SEPA-Mandate', 'ibantest-for-woocommerce' ),
					'type'  => 'title',
				],
				'mandate_text'            => array(
					'title'       => __( 'SEPA Mandate Text', 'ibantest-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => __( '[creditor_name]
debtee identification number: [creditor_id]
mandate reference number: [mandate_id].
<h4>SEPA Direct Debit Mandate</h4>
I/We herewith authorise you to collect payments from my/our account by direct debit.
At the same time, I/we instruct my/our bank to honour direct debits drawn by

Note: I/We can demand reimbursement of the debited amount within eight weeks, starting with the debit date. 
The transaction is subject to the conditions agreed with my/our bank.

<strong>Debtor:</strong>
Account holder: [creditor_account_holder]
Street: [street]
Postcode: [postcode]
City: [city]
Country: [country]
IBAN: [creditor_account_iban]
BIC: [creditor_agent_bic]

[city], [date], [creditor_account_holder]

This letter is done automatically and is valid without signature.

<hr/>

Please notice: Period for pre-information of the SEPA direct debit is shortened to one day.', 'ibantest-for-woocommerce' ),
					'css'         => 'min-height: 250px;',
					'description' => __( 'available placeholders: [creditor_name], [creditor_id], [mandate_id], [creditor_account_holder], [street], [postcode], [city], [country], [creditor_account_iban], [creditor_agent_bic], [city], [date]', 'ibantest-for-woocommerce' ),
				),
				'enable_mandate_checkbox' => array(
					'title'       => __( 'Checkbox agreement', 'ibantest-for-woocommerce' ),
					'label'       => __( 'Enable "agree to SEPA mandate" checkbox', 'ibantest-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => __( 'If enabled, the user has to accept the SEPA Direct Debit Mandate.', 'ibantest-for-woocommerce' ),
					'default'     => 'yes',
				),
				'mandate_checkbox_label'  => array(
					'title'    => __( 'Checkbox label', 'ibantest-for-woocommerce' ),
					'type'     => 'text',
					'default'  => __( 'I hereby agree to the [link]SEPA Direct Debit Mandate[/link].', 'ibantest-for-woocommerce' ),
					'desc_tip' => true,
				),
				'mandate_id_format'       => array(
					'title'       => __( 'Mandate ID Format', 'ibantest-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'You can change the Mandate ID Format by adding a prefix and/or suffix. [id] is a placeholder for the automatically generated ID.', 'ibantest-for-woocommerce' ),
					'default'     => 'MANDATE[id]',
				),
				'options'                 => [
					'title' => __( 'Options', 'ibantest-for-woocommerce' ),
					'type'  => 'title',
				],
				'hide_iban_chars'         => array(
					'title'       => __( 'Hide IBAN characters', 'ibantest-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Select if you want to hide certain characters of an IBAN', 'ibantest-for-woocommerce' ),
					'options'     => array(
						'full'       => __( 'show full IBAN', 'ibantest-for-woocommerce' ),
						'last3'      => __( 'show last 3 chars', 'ibantest-for-woocommerce' ),
						'last4'      => __( 'show last 4 chars', 'ibantest-for-woocommerce' ),
						'firstlast3' => __( 'show first and last 3 chars', 'ibantest-for-woocommerce' ),
						'firstlast4' => __( 'show first and last 4 chars', 'ibantest-for-woocommerce' ),
					),
					'default'     => 'full',
					'desc_tip'    => false
				),
			];

			if ( WC_IBANTEST_Encryption::instance()->is_enabled() ) {
				$settings['encryption']['description'] = __( 'Encryption is fully configured. The data is stored in encrypted form.', 'ibantest-for-woocommerce' );

				$settings = array_merge( $settings, array(
					'remember' => array(
						'title'       => __( 'Remember for user', 'ibantest-for-woocommerce' ),
						'type'        => 'select',
						'description' => __( 'Save account data as user meta if user has/creates a customer account.', 'ibantest-for-woocommerce' ),
						'options'     => array(
							'yes' => __( 'yes', 'ibantest-for-woocommerce' ),
							'no'  => __( 'no', 'ibantest-for-woocommerce' ),
						),
						'default'     => 'no',
					)
				) );
			} else {
				$settings['encryption']['description'] =
					__( 'This plugin offers the possibility to store the bank data of the users (IBAN, BIC and account holder) encrypted. ', 'ibantest-for-woocommerce' ) . "<br />" .
					sprintf( __( 'If you want to use encryption, just insert the following code in your <a href="%s" target="_blank">wp-config.php</a> ', 'ibantest-for-woocommerce' ), 'https://codex.wordpress.org/Editing_wp-config.php' ) .
					'<pre style="overflow: scroll"><code>define( \'WC_IBANTEST_ENCRYPTION_KEY\', \'' . WC_IBANTEST_Encryption()->generate_random_key() . '\' );</code></pre>';
			}

			return apply_filters( 'ibantest_for_woocommerce_gateway_settings', $settings );
		}
	}

endif;
