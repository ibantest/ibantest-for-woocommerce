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
					'title'       => __( 'Enable/Disable SEPA Direct Debit', 'woocommerce-ibantest' ),
					'label'       => __( 'Enable SEPA Direct Debit', 'woocommerce-ibantest' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				],
				'title'                   => [
					'title'       => __( 'Title', 'woocommerce-ibantest' ),
					'type'        => 'text',
					'description' => __( 'Payment method title.', 'woocommerce-ibantest' ),
					'default'     => __( 'SEPA Direct Debit', 'woocommerce-ibantest' ),
					'desc_tip'    => true,
				],
				'description'             => [
					'title'       => __( 'Description', 'woocommerce-ibantest' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description.', 'woocommerce-ibantest' ),
					'default'     => __( 'Pay conveniently by SEPA Direct Debit.', 'woocommerce-ibantest' ),
					'desc_tip'    => true,
				],
				'credentials'             => [
					'title'       => __( 'IBANTEST API credentials', 'woocommerce-ibantest' ),
					'description' => __( 'Create your IBANTEST account now and <b>receive 100 credits for free</b>. <br />- no annual fee<br />- no setup fee <br /> <a href="https://www.ibantest.com/">https://www.ibantest.com</a>', 'woocommerce-ibantest' ),
					'type'        => 'title',
				],
				'apikey'                  => [
					'title'       => __( 'IBANTEST API Key', 'woocommerce-ibantest' ),
					'type'        => 'text',
					'description' => __( 'Get your API keys from your IBANTEST account.', 'woocommerce-ibantest' ),
					'default'     => '',
					'desc_tip'    => true,
				],
				'encryption'              => [
					'title' => __( 'Encryption', 'woocommerce-ibantest' ),
					'type'  => 'title',
				],
				'payment_info'            => [
					'title' => __( 'SEPA creditor payment information', 'woocommerce-ibantest' ),
					'type'  => 'title',
				],
				'creditor_name'           => [
					'title' => __( 'Company name', 'woocommerce-ibantest' ),
					'type'  => 'text',
				],
				'creditor_account_holder' => [
					'title' => __( 'Company account holder', 'woocommerce-ibantest' ),
					'type'  => 'text',
				],
				'creditor_account_iban'   => [
					'title' => __( 'Company account IBAN', 'woocommerce-ibantest' ),
					'type'  => 'text',
				],
				'creditor_agent_bic'      => [
					'title' => __( 'Company account BIC', 'woocommerce-ibantest' ),
					'type'  => 'text',
				],
				'creditor_id'             => [
					'title' => __( 'Company identification number', 'woocommerce-ibantest' ),
					'type'  => 'text',
				],
				'sepa_xml_format'         => [
					'title'       => __( 'SEPA XML Export Format', 'woocommerce-ibantest' ),
					'type'        => 'select',
					'description' => __( 'Choose the correct format for the SEPA XML export. Please ask your bank what format is required.', 'woocommerce-ibantest' ),
					'options'     => array(
						'pain.001.001.03' => __( 'pain.001.001.03', 'woocommerce-ibantest' ),
						'pain.001.002.03' => __( 'pain.001.002.03', 'woocommerce-ibantest' ),
						'pain.008.001.02' => __( 'pain.008.001.02', 'woocommerce-ibantest' ),
						'pain.008.002.02' => __( 'pain.008.002.02', 'woocommerce-ibantest' ),
					),
					'desc_tip'    => false,
					'default'     => 'pain.008.002.02',
				],
				'sepa_mandate_section'    => [
					'title' => __( 'SEPA-Mandate', 'woocommerce-ibantest' ),
					'type'  => 'title',
				],
				'mandate_text'            => array(
					'title'       => __( 'SEPA Mandate Text', 'woocommerce-ibantest' ),
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

Please notice: Period for pre-information of the SEPA direct debit is shortened to one day.', 'woocommerce-ibantest' ),
					'css'         => 'min-height: 250px;',
					'description' => __( 'available placeholders: [creditor_name], [creditor_id], [mandate_id], [creditor_account_holder], [street], [postcode], [city], [country], [creditor_account_iban], [creditor_agent_bic], [city], [date]', 'woocommerce-ibantest' ),
				),
				'enable_mandate_checkbox' => array(
					'title'       => __( 'Checkbox agreement', 'woocommerce-ibantest' ),
					'label'       => __( 'Enable "agree to SEPA mandate" checkbox', 'woocommerce-ibantest' ),
					'type'        => 'checkbox',
					'description' => __( 'If enabled, the user has to accept the SEPA Direct Debit Mandate.', 'woocommerce-ibantest' ),
					'default'     => 'yes',
				),
				'mandate_checkbox_label'  => array(
					'title'    => __( 'Checkbox label', 'woocommerce-ibantest' ),
					'type'     => 'text',
					'default'  => __( 'I hereby agree to the [link]SEPA Direct Debit Mandate[/link].', 'woocommerce-ibantest' ),
					'desc_tip' => true,
				),
				'mandate_id_format'       => array(
					'title'       => __( 'Mandate ID Format', 'woocommerce-ibantest' ),
					'type'        => 'text',
					'description' => __( 'You can change the Mandate ID Format by adding a prefix and/or suffix. [id] is a placeholder for the automatically generated ID.', 'woocommerce-ibantest' ),
					'default'     => 'MANDATE[id]',
				),
				'options'                 => [
					'title' => __( 'Options', 'woocommerce-ibantest' ),
					'type'  => 'title',
				],
				'hide_iban_chars'         => array(
					'title'       => __( 'Hide IBAN characters', 'woocommerce-ibantest' ),
					'type'        => 'select',
					'description' => __( 'Select if you want to hide certain characters of an IBAN', 'woocommerce-ibantest' ),
					'options'     => array(
						'full'       => __( 'show full IBAN', 'woocommerce-ibantest' ),
						'last3'      => __( 'show last 3 chars', 'woocommerce-ibantest' ),
						'last4'      => __( 'show last 4 chars', 'woocommerce-ibantest' ),
						'firstlast3' => __( 'show first and last 3 chars', 'woocommerce-ibantest' ),
						'firstlast4' => __( 'show first and last 4 chars', 'woocommerce-ibantest' ),
					),
					'default'     => 'full',
					'desc_tip'    => false
				),
			];

			if ( WC_IBANTEST_Encryption::instance()->is_enabled() ) {
				$settings['encryption']['description'] = __( 'Encryption is fully configured. The data is stored in encrypted form.', 'woocommerce-ibantest' );

				$settings = array_merge( $settings, array(
					'remember' => array(
						'title'       => __( 'Remember for user', 'woocommerce-ibantest' ),
						'type'        => 'select',
						'description' => __( 'Save account data as user meta if user has/creates a customer account.', 'woocommerce-ibantest' ),
						'options'     => array(
							'yes' => __( 'yes', 'woocommerce-ibantest' ),
							'no'  => __( 'no', 'woocommerce-ibantest' ),
						),
						'default'     => 'no',
					)
				) );
			} else {
				$settings['encryption']['description'] =
					__( 'This plugin offers the possibility to store the bank data of the users (IBAN, BIC and account holder) encrypted. ', 'woocommerce-ibantest' ) . "<br />" .
					sprintf( __( 'If you want to use encryption, just insert the following code in your <a href="%s" target="_blank">wp-config.php</a> ', 'woocommerce-ibantest' ), 'https://codex.wordpress.org/Editing_wp-config.php' ) .
					'<pre style="overflow: scroll"><code>define( \'WC_IBANTEST_ENCRYPTION_KEY\', \'' . WC_IBANTEST_Encryption()->generate_random_key() . '\' );</code></pre>';
			}

			return apply_filters( 'woocommerce_ibantest_gateway_settings', $settings );
		}
	}

endif;
