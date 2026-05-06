<?php

namespace Ibantest\WooCommerce\Tests\Unit;

use Ibantest\WooCommerce\Plugin;
use Ibantest\WooCommerce\Orders\DirectDebitOrderMeta;
use Ibantest\WooCommerce\SepaExport;
use PHPUnit\Framework\TestCase;

final class SepaExportTest extends TestCase {
	public function test_does_not_generate_xml_without_creditor_settings(): void {
		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => [],
		];

		$export = new SepaExport( Plugin::instance() );

		$this->assertSame( '', $export->build_xml( new \WC_Order() ) );
		$this->assertSame( 'Missing SEPA creditor setting: creditor_agent_bic', $export->last_error() );
	}

	public function test_does_not_generate_xml_without_decrypted_debtor_iban(): void {
		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => $this->creditor_settings(),
		];

		$order = new \WC_Order();
		$order->update_meta_data( DirectDebitOrderMeta::META_HOLDER, Plugin::instance()->encryption()->encrypt( 'Jane Doe' ) );
		$order->update_meta_data( DirectDebitOrderMeta::META_MANDATE_ID, 'MANDATE-123' );

		$export = new SepaExport( Plugin::instance() );

		$this->assertSame( '', $export->build_xml( $order ) );
		$this->assertSame( 'SEPA XML could not be generated because the debtor IBAN is missing or cannot be decrypted.', $export->last_error() );
	}

	#[\PHPUnit\Framework\Attributes\IgnoreDeprecations]
	public function test_generates_xml_without_optional_debtor_bic(): void {
		$GLOBALS['ibantest_test_options'] = [
			'woocommerce_ibantest_settings' => $this->creditor_settings(),
		];

		$order = new \WC_Order();
		$order->update_meta_data( DirectDebitOrderMeta::META_IBAN, Plugin::instance()->encryption()->encrypt( 'DE89370400440532013000' ) );
		$order->update_meta_data( DirectDebitOrderMeta::META_HOLDER, Plugin::instance()->encryption()->encrypt( 'Jane Doe' ) );
		$order->update_meta_data( DirectDebitOrderMeta::META_BIC, '' );
		$order->update_meta_data( DirectDebitOrderMeta::META_MANDATE_ID, 'MANDATE-123' );
		$order->update_meta_data( DirectDebitOrderMeta::META_MANDATE_DATE, time() );

		$export = new SepaExport( Plugin::instance() );
		$xml    = $export->build_xml( $order );

		$this->assertNotSame( '', $xml, $export->last_error() );
		$this->assertStringContainsString( 'DE89370400440532013000', $xml );
		$this->assertStringContainsString( 'Jane Doe', $xml );
	}

	/**
	 * @return array<string, string>
	 */
	private function creditor_settings(): array {
		return [
			'creditor_agent_bic'       => 'COBADEFFXXX',
			'creditor_name'            => 'Example GmbH',
			'sepa_xml_format'          => 'pain.008.001.02',
			'creditor_account_holder'  => 'Example GmbH',
			'creditor_account_iban'    => 'DE89370400440532013000',
			'creditor_id'              => 'DE98ZZZ09999999999',
		];
	}
}
