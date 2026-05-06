<?php

namespace Ibantest\WooCommerce\Tests\Unit;

use Ibantest\WooCommerce\Gateway;
use Ibantest\WooCommerce\Plugin;
use Ibantest\WooCommerce\Services\IbanService;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase {
	private Gateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$_POST = [];
		$GLOBALS['ibantest_test_notices'] = [];
		WC()->session->reset();

		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => [
				'enabled'                  => 'yes',
				'title'                    => 'SEPA',
				'description'              => 'Pay by SEPA',
				'enable_mandate_checkbox' => 'yes',
				'mandate_checkbox_label'  => 'Agree',
				'hide_iban_chars'         => 'last4',
			],
		];

		$this->gateway = new Gateway( Plugin::instance() );
		$this->gateway->settings = $GLOBALS['ibantest_test_options']['woocommerce_ibantest_settings'];
		Plugin::instance()->replace_iban_service( new IbanService( $this->gateway->settings ) );
	}

	public function test_masks_iban_by_default(): void {
		$this->assertSame( '******************3000', $this->gateway->hide_iban( 'DE89370400440532013000' ) );
	}

	public function test_exposes_block_payment_method_data(): void {
		$data = $this->gateway->block_data();

		$this->assertSame( 'SEPA', $data['title'] );
		$this->assertSame( 'Pay by SEPA', $data['description'] );
		$this->assertTrue( $data['mandateRequired'] );
		$this->assertStringContainsString( 'ibantest_validate_iban', $data['validateIbanUrl'] );
		$this->assertSame( 'debounce', $data['validationTrigger'] );
		$this->assertSame( 450, $data['validationDelay'] );
		$this->assertArrayHasKey( 'ibanHelpText', $data );
	}

	public function test_gateway_settings_include_iban_validation_controls(): void {
		$fields = $this->gateway->get_form_fields();

		$this->assertSame( 'select', $fields['iban_validation_trigger']['type'] );
		$this->assertSame( 'debounce', $fields['iban_validation_trigger']['default'] );
		$this->assertArrayHasKey( 'focusout', $fields['iban_validation_trigger']['options'] );
		$this->assertArrayHasKey( 'submit', $fields['iban_validation_trigger']['options'] );
		$this->assertSame( 'number', $fields['iban_validation_delay']['type'] );
		$this->assertSame( 450, $fields['iban_validation_delay']['default'] );
	}

	public function test_block_data_sanitizes_validation_settings(): void {
		$this->gateway->settings['iban_validation_trigger'] = 'invalid';
		$this->gateway->settings['iban_validation_delay']   = 9000;

		$data = $this->gateway->block_data();

		$this->assertSame( 'debounce', $data['validationTrigger'] );
		$this->assertSame( 5000, $data['validationDelay'] );
	}

	public function test_block_data_exposes_configured_submit_mode(): void {
		$this->gateway->settings['iban_validation_trigger'] = 'submit';
		$this->gateway->settings['iban_validation_delay']   = 0;

		$data = $this->gateway->block_data();

		$this->assertSame( 'submit', $data['validationTrigger'] );
		$this->assertSame( 0, $data['validationDelay'] );
	}

	public function test_provider_icon_and_setup_state_without_api_key(): void {
		$this->assertStringContainsString( 'assets/img/ibantest-icon.svg', $this->gateway->icon );
		$this->assertTrue( $this->gateway->needs_setup() );
		$this->assertFalse( $this->gateway->is_account_connected() );
		$this->assertFalse( $this->gateway->is_onboarding_started() );
		$this->assertFalse( $this->gateway->is_onboarding_completed() );
	}

	public function test_provider_setup_state_with_api_key(): void {
		$this->gateway->settings['apikey'] = 'abc123';

		$this->assertFalse( $this->gateway->needs_setup() );
		$this->assertTrue( $this->gateway->is_account_connected() );
		$this->assertTrue( $this->gateway->is_onboarding_started() );
		$this->assertTrue( $this->gateway->is_onboarding_completed() );
	}

	public function test_posted_payment_data_requires_only_iban_and_derives_holder_from_billing(): void {
		$_POST = [
			'ibantest_account_iban'     => 'DE02 6005 0101 0002 0343 04',
			'ibantest_mandate_checkbox' => '1',
			'billing_first_name'        => 'Erika',
			'billing_last_name'         => 'Mustermann',
		];

		$data = $this->gateway->posted_payment_data();

		$this->assertSame( 'DE02 6005 0101 0002 0343 04', $data['iban'] );
		$this->assertSame( '', $data['bic'] );
		$this->assertSame( 'Erika Mustermann', $data['holder'] );
		$this->assertTrue( $data['mandate_accepted'] );
	}

	public function test_posted_payment_data_prefers_company_as_holder(): void {
		$_POST = [
			'ibantest_account_iban'     => 'DE02 6005 0101 0002 0343 04',
			'ibantest_mandate_checkbox' => '1',
			'billing_company'           => 'Acme GmbH',
			'billing_first_name'        => 'Erika',
			'billing_last_name'         => 'Mustermann',
		];

		$data = $this->gateway->posted_payment_data();

		$this->assertSame( 'Acme GmbH', $data['holder'] );
	}

	public function test_posted_payment_data_accepts_checkout_blocks_payment_method_data(): void {
		$_POST = [
			'ibantest_account_iban'     => 'DE02600501010002034304',
			'ibantest_account_bic'      => 'soladest600',
			'ibantest_account_holder'   => 'Blocks Customer GmbH',
			'ibantest_mandate_checkbox' => '1',
			'ibantest_iban_validated'   => 'DE02600501010002034304',
			'ibantest_account_bank'     => 'LBBW/BW-Bank Stuttgart',
		];

		$data = $this->gateway->posted_payment_data();

		$this->assertSame( 'DE02600501010002034304', $data['iban'] );
		$this->assertSame( 'SOLADEST600', $data['bic'] );
		$this->assertSame( 'Blocks Customer GmbH', $data['holder'] );
		$this->assertTrue( $data['mandate_accepted'] );
	}

	public function test_submit_validation_without_api_key_adds_unavailable_notice(): void {
		$_POST = [
			'payment_method'            => 'ibantest',
			'ibantest_account_iban'     => 'DE89 3704 0044 0532 0130 00',
			'ibantest_mandate_checkbox' => '1',
		];

		$this->assertFalse( $this->gateway->validate_fields() );
		$this->assertSame( 'error', $GLOBALS['ibantest_test_notices'][0]['type'] );
		$this->assertSame( 'IBAN validation is currently not possible. Please try again later.', $GLOBALS['ibantest_test_notices'][0]['message'] );
	}

	public function test_submit_validation_uses_cached_remote_bank_data_for_bic(): void {
		$this->gateway->settings['apikey'] = 'test-key';
		Plugin::instance()->replace_iban_service( new IbanService( $this->gateway->settings ) );

		$_POST = [
			'payment_method'            => 'ibantest',
			'ibantest_account_iban'     => 'DE02 6005 0101 0002 0343 04',
			'ibantest_mandate_checkbox' => '1',
		];

		WC()->session->set(
			'ibantest_' . md5( 'DE02600501010002034304' ),
			[
				'valid'         => true,
				'bic'           => 'SOLADEST600',
				'bankNameShort' => 'LBBW/BW-Bank Stuttgart',
			]
		);

		$this->assertTrue( $this->gateway->validate_fields() );
		$this->assertSame( 'SOLADEST600', $_POST['ibantest_account_bic'] );
	}
}
