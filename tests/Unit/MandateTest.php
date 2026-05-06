<?php

namespace Ibantest\WooCommerce\Tests\Unit;

use Ibantest\WooCommerce\Support\Mandate;
use PHPUnit\Framework\TestCase;

final class MandateTest extends TestCase {
	public function test_replaces_mandate_placeholders(): void {
		$html = Mandate::render(
			[
				'mandate_text'  => 'Creditor [creditor_name], IBAN [iban], Holder [account_holder]',
				'creditor_name' => 'Example GmbH',
			],
			[
				'iban'           => 'DE89370400440532013000',
				'account_holder' => 'Jane Doe',
			]
		);

		$this->assertStringContainsString( 'Example GmbH', $html );
		$this->assertStringContainsString( 'DE89370400440532013000', $html );
		$this->assertStringContainsString( 'Jane Doe', $html );
	}
}
