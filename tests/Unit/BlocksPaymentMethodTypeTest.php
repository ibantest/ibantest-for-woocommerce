<?php

namespace Ibantest\WooCommerce\Tests\Unit;

use Ibantest\WooCommerce\Blocks\PaymentMethodType;
use Ibantest\WooCommerce\Plugin;
use Ibantest\WooCommerce\Services\IbanService;
use PHPUnit\Framework\TestCase;

final class BlocksPaymentMethodTypeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->reset_plugin_gateway();
		Plugin::instance()->replace_iban_service( new IbanService( [] ) );
	}

	public function test_uses_gateway_id_as_payment_method_name(): void {
		$type = new PaymentMethodType( Plugin::instance() );

		$this->assertSame( 'ibantest', $type->get_name() );
	}

	public function test_exposes_gateway_data_to_checkout_blocks(): void {
		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => [
				'title'       => 'SEPA Direct Debit',
				'description' => 'Block checkout description',
			],
		];

		$type = new PaymentMethodType( Plugin::instance() );
		$type->initialize();

		$data = $type->get_payment_method_data();

		$this->assertSame( 'SEPA Direct Debit', $data['title'] );
		$this->assertSame( 'Block checkout description', $data['description'] );
		$this->assertArrayHasKey( 'supports', $data );
	}

	public function test_exposes_submit_validation_mode_to_checkout_blocks(): void {
		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => [
				'title'                   => 'SEPA Direct Debit',
				'iban_validation_trigger' => 'submit',
				'iban_validation_delay'   => 0,
			],
		];
		$this->reset_plugin_gateway();
		Plugin::instance()->replace_iban_service( new IbanService( [] ) );

		$type = new PaymentMethodType( Plugin::instance() );
		$type->initialize();

		$data = $type->get_payment_method_data();

		$this->assertSame( 'submit', $data['validationTrigger'] );
		$this->assertSame( 0, $data['validationDelay'] );
		$this->assertStringContainsString( 'ibantest_validate_iban', $data['validateIbanUrl'] );
		$this->assertSame( 'IBAN validation is currently not possible. Please try again later.', $this->unavailable_validation_message() );
	}

	public function test_enqueues_checkout_blocks_style_handle(): void {
		$type = new PaymentMethodType( Plugin::instance() );

		$this->assertSame( [ 'ibantest-checkout' ], $type->get_payment_method_style_handles() );
	}

	private function unavailable_validation_message(): string {
		return Plugin::instance()->iban_service()->validate( 'DE89 3704 0044 0532 0130 00' )['message'];
	}

	private function reset_plugin_gateway(): void {
		$property = new \ReflectionProperty( Plugin::class, 'gateway' );
		$property->setValue( Plugin::instance(), null );
	}
}
