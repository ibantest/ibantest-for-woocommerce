<?php

namespace Ibantest\WooCommerce\Tests\Unit;

use Ibantest\WooCommerce\Services\IbanService;
use PHPUnit\Framework\TestCase;

final class IbanServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['ibantest_test_options'] = [];
	}

	public function test_rejects_valid_iban_when_api_key_is_missing(): void {
		$service = new IbanService( [] );

		$result = $service->validate( 'DE89 3704 0044 0532 0130 00' );

		$this->assertFalse( $result['valid'] );
		$this->assertTrue( $result['error'] );
		$this->assertSame( 'IBAN validation is currently not possible. Please try again later.', $result['message'] );
	}

	public function test_rejects_invalid_checksum(): void {
		$service = new IbanService( [] );

		$result = $service->validate( 'DE89 3704 0044 0532 0130 01' );

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'IBAN contains an incorrect checksum.', $result['message'] );
	}

	public function test_validates_bic_format(): void {
		$service = new IbanService( [] );

		$this->assertTrue( $service->is_valid_bic( 'DEUTDEFF' ) );
		$this->assertTrue( $service->is_valid_bic( 'DEUTDEFF500' ) );
		$this->assertFalse( $service->is_valid_bic( 'INVALID' ) );
	}

	public function test_remaining_credits_are_unavailable_without_api_key(): void {
		$service = new IbanService( [] );

		$this->assertNull( $service->get_remaining_credits() );
	}

	public function test_remaining_credits_use_fresh_cache(): void {
		$GLOBALS['ibantest_test_options']['ibantest_credits_cache'] = [
			'credits'      => 1234,
			'last_updated' => time(),
		];

		$service  = new IbanService( [ 'apikey' => 'unused-in-fresh-cache' ] );
		$overview = $service->get_credit_overview();

		$this->assertSame( 1234, $overview['credits'] );
		$this->assertFalse( $overview['is_stale'] );
		$this->assertFalse( $overview['refreshed'] );
		$this->assertFalse( $overview['error'] );
	}

	public function test_stale_cache_without_api_key_is_reported_as_error(): void {
		$GLOBALS['ibantest_test_options']['ibantest_credits_cache'] = [
			'credits'      => 987,
			'last_updated' => time() - ( 2 * HOUR_IN_SECONDS ),
		];

		$service  = new IbanService( [] );
		$overview = $service->get_credit_overview();

		$this->assertSame( 987, $overview['credits'] );
		$this->assertTrue( $overview['is_stale'] );
		$this->assertFalse( $overview['refreshed'] );
		$this->assertTrue( $overview['error'] );
	}

	public function test_verify_api_key_fails_for_empty_key(): void {
		$service = new IbanService( [] );

		$result = $service->verify_api_key( '' );

		$this->assertTrue( $result['error'] );
		$this->assertNull( $result['credits'] );
		$this->assertNull( $result['last_updated'] );
	}

	public function test_maps_valid_remote_response_with_bank_data(): void {
		$service = new IbanService( [] );
		$result  = $this->map_remote_result( $service, $this->valid_response() );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'DE02600501010002034304', $result['iban'] );
		$this->assertSame( 'DE02 6005 0101 0002 0343 04', $result['ibanFormatted'] );
		$this->assertSame( 'SOLADEST600', $result['bic'] );
		$this->assertSame( 'Landesbank Baden-Württemberg/Baden-Württembergische Bank', $result['bankName'] );
		$this->assertSame( 'LBBW/BW-Bank Stuttgart', $result['bankNameShort'] );
		$this->assertSame( 'Stuttgart', $result['bankCity'] );
		$this->assertSame( '60050101', $result['bankCode'] );
		$this->assertSame( 'SOLADEST600', $result['bankData']['bic'] );
	}

	public function test_maps_invalid_remote_response_without_losing_bank_data(): void {
		$service = new IbanService( [] );
		$result  = $this->map_remote_result( $service, $this->invalid_response() );

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'IBAN contains an incorrect checksum.', $result['message'] );
		$this->assertSame( 'DE02600501010002034305', $result['iban'] );
		$this->assertSame( 'SOLADEST600', $result['bic'] );
		$this->assertSame( 'LBBW/BW-Bank Stuttgart', $result['bankNameShort'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function valid_response(): array {
		return [
			'valid'    => true,
			'checks'   => [
				'ibanLength'              => true,
				'ibanChecksum'            => true,
				'ibanSyntaxVerify'        => true,
				'bankAccountSyntaxVerify' => true,
				'bankExistVerify'         => true,
			],
			'ibanData' => [
				'iban'    => 'DE02600501010002034304',
				'ibanExt' => 'DE02 6005 0101 0002 0343 04',
			],
			'bankData' => [
				'bankCode'         => '60050101',
				'bic'              => 'SOLADEST600',
				'description'      => 'Landesbank Baden-Württemberg/Baden-Württembergische Bank',
				'descriptionShort' => 'LBBW/BW-Bank Stuttgart',
				'zip'              => '70144',
				'city'             => 'Stuttgart',
				'countryCode'      => 'DE',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function invalid_response(): array {
		$response                                   = $this->valid_response();
		$response['valid']                         = false;
		$response['checks']['ibanChecksum']        = false;
		$response['checks']['ibanSyntaxVerify']    = false;
		$response['ibanData']['iban']              = 'DE02600501010002034305';
		$response['ibanData']['ibanExt']           = 'DE02 6005 0101 0002 0343 05';

		return $response;
	}

	/**
	 * @param array<string, mixed> $response
	 *
	 * @return array<string, mixed>
	 */
	private function map_remote_result( IbanService $service, array $response ): array {
		$method = new \ReflectionMethod( $service, 'map_remote_result' );

		return $method->invoke( $service, $response );
	}
}
