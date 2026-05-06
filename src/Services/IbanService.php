<?php

namespace Ibantest\WooCommerce\Services;

use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IbanService {
	private const CREDITS_CACHE_OPTION = 'ibantest_credits_cache';
	private const CREDITS_CACHE_TTL    = HOUR_IN_SECONDS;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( private array $settings ) {}

	/**
	 * @return array{valid?: bool, error?: bool, message?: string, iban?: string, ibanFormatted?: string, bic?: string, bankName?: string, bankNameShort?: string, bankCity?: string, bankCode?: string, bankData?: array<string, string>}
	 */
	public function validate( string $iban ): array {
		$iban = $this->normalize_iban( $iban );
		if ( '' === $iban ) {
			return [
				'valid'   => false,
				'message' => __( 'Please insert your IBAN.', 'ibantest-for-woocommerce' ),
			];
		}

		if ( ! $this->passes_mod97( $iban ) ) {
			return [
				'valid'   => false,
				'message' => __( 'IBAN contains an incorrect checksum.', 'ibantest-for-woocommerce' ),
			];
		}

		if ( ! $this->can_validate_remotely() ) {
			return [
				'valid'   => false,
				'error'   => true,
				'message' => $this->validation_unavailable_message(),
			];
		}

		$remote = $this->remote_validate( $iban );
		if ( [] !== $remote ) {
			return $remote;
		}

		return [
			'valid'   => false,
			'error'   => true,
			'message' => $this->validation_unavailable_message(),
		];
	}

	public function normalize_iban( string $iban ): string {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $iban ) ?? '' );
	}

	public function is_valid_bic( string $bic ): bool {
		return (bool) preg_match( '/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper( $bic ) );
	}

	public function get_remaining_credits( bool $force_refresh = false ): ?int {
		return $this->get_credit_overview( $force_refresh )['credits'];
	}

	/**
	 * @return array{credits: ?int, last_updated: ?int, error: bool}
	 */
	public function verify_api_key( string $api_key ): array {
		$credits = $this->fetch_remaining_credits( $api_key );
		if ( null === $credits ) {
			return [
				'credits'      => null,
				'last_updated' => null,
				'error'        => true,
			];
		}

		$last_updated = time();
		update_option(
			self::CREDITS_CACHE_OPTION,
			[
				'credits'      => $credits,
				'last_updated' => $last_updated,
			],
			false
		);

		return [
			'credits'      => $credits,
			'last_updated' => $last_updated,
			'error'        => false,
		];
	}

	/**
	 * @return array{credits: ?int, last_updated: ?int, is_stale: bool, refreshed: bool, error: bool}
	 */
	public function get_credit_overview( bool $force_refresh = false ): array {
		$cache        = $this->read_credit_cache();
		$last_updated = $cache['last_updated'];
		$is_stale     = null === $last_updated || ( time() - $last_updated ) >= self::CREDITS_CACHE_TTL;

		if ( $force_refresh || $is_stale ) {
			$fetched = $this->fetch_remaining_credits();
			if ( null !== $fetched ) {
				$last_updated = time();
				$cache        = [
					'credits'      => $fetched,
					'last_updated' => $last_updated,
				];

				update_option( self::CREDITS_CACHE_OPTION, $cache, false );

				return [
					'credits'      => $fetched,
					'last_updated' => $last_updated,
					'is_stale'     => false,
					'refreshed'    => true,
					'error'        => false,
				];
			}

			return [
				'credits'      => $cache['credits'],
				'last_updated' => $last_updated,
				'is_stale'     => $is_stale,
				'refreshed'    => false,
				'error'        => true,
			];
		}

		return [
			'credits'      => $cache['credits'],
			'last_updated' => $last_updated,
			'is_stale'     => false,
			'refreshed'    => false,
			'error'        => false,
		];
	}

	/**
	 * @return array{credits: ?int, last_updated: ?int}
	 */
	private function read_credit_cache(): array {
		$cache = get_option( self::CREDITS_CACHE_OPTION, [] );
		if ( ! is_array( $cache ) ) {
			return [
				'credits'      => null,
				'last_updated' => null,
			];
		}

		return [
			'credits'      => isset( $cache['credits'] ) ? max( 0, (int) $cache['credits'] ) : null,
			'last_updated' => isset( $cache['last_updated'] ) ? max( 0, (int) $cache['last_updated'] ) : null,
		];
	}

	private function fetch_remaining_credits( ?string $api_key = null ): ?int {
		$api_key = null === $api_key ? ( $this->settings['apikey'] ?? '' ) : $api_key;
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key || ! class_exists( \Ibantest\Ibantest::class ) ) {
			return null;
		}

		try {
			$client = new \Ibantest\Ibantest();
			$client->setToken( $api_key );
			$result = $client->getRemainingCredits();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( ! is_array( $result ) || isset( $result['errorCode'] ) || ! isset( $result['credits'] ) ) {
			return null;
		}

		return max( 0, (int) $result['credits'] );
	}

	private function passes_mod97( string $iban ): bool {
		if ( ! preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $iban ) ) {
			return false;
		}

		$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$numeric    = '';
		$length     = strlen( $rearranged );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $rearranged[ $i ];
			if ( ctype_alpha( $char ) ) {
				$numeric .= (string) ( ord( $char ) - 55 );
			} else {
				$numeric .= $char;
			}
		}

		$checksum = 0;
		$digits   = strlen( $numeric );
		for ( $i = 0; $i < $digits; $i++ ) {
			$checksum = ( $checksum * 10 + (int) $numeric[ $i ] ) % 97;
		}

		return 1 === $checksum;
	}

	/**
	 * @return array{valid?: bool, error?: bool, message?: string, iban?: string, ibanFormatted?: string, bic?: string, bankName?: string, bankNameShort?: string, bankCity?: string, bankCode?: string, bankData?: array<string, string>}
	 */
	private function remote_validate( string $iban ): array {
		$api_key = $this->api_key();

		$cache_key = 'ibantest_' . md5( $iban );
		$cached    = $this->session_get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$client = new \Ibantest\Ibantest();
			$client->setToken( $api_key );
			$result = $client->validateIban( $iban );
		} catch ( \Throwable $e ) {
			return [
				'valid'   => false,
				'error'   => true,
				'message' => $this->validation_unavailable_message(),
			];
		}

		$mapped = $this->map_remote_result( is_array( $result ) ? $result : [] );
		$this->session_set( $cache_key, $mapped );

		return $mapped;
	}

	/**
	 * @param array<string, mixed> $result
	 *
	 * @return array{valid?: bool, error?: bool, message?: string, iban?: string, ibanFormatted?: string, bic?: string, bankName?: string, bankNameShort?: string, bankCity?: string, bankCode?: string, bankData?: array<string, string>}
	 */
	private function map_remote_result( array $result ): array {
		if ( isset( $result['errorCode'] ) ) {
			return [
				'valid'   => false,
				'error'   => true,
				'message' => $this->validation_unavailable_message(),
			];
		}

		$data = [
			'valid' => ! empty( $result['valid'] ),
		];

		if ( isset( $result['ibanData'] ) && is_array( $result['ibanData'] ) ) {
			if ( ! empty( $result['ibanData']['iban'] ) ) {
				$data['iban'] = (string) $result['ibanData']['iban'];
			}
			if ( ! empty( $result['ibanData']['ibanExt'] ) ) {
				$data['ibanFormatted'] = (string) $result['ibanData']['ibanExt'];
			}
		}

		if ( isset( $result['bankData'] ) && is_array( $result['bankData'] ) ) {
			$data = array_merge( $data, $this->map_bank_data( $result['bankData'] ) );
		}

		if ( ! empty( $result['valid'] ) ) {
			return $data;
		}

		$data['message'] = $this->remote_error_message( $result );

		return $data;
	}

	/**
	 * @param array<string, mixed> $bank_data
	 *
	 * @return array{bic?: string, bankName?: string, bankNameShort?: string, bankCity?: string, bankCode?: string, bankData?: array<string, string>}
	 */
	private function map_bank_data( array $bank_data ): array {
		$mapped = [];
		$raw    = [];

		foreach ( [ 'bankCode', 'bic', 'description', 'descriptionShort', 'zip', 'city', 'countryCode' ] as $key ) {
			if ( isset( $bank_data[ $key ] ) && is_scalar( $bank_data[ $key ] ) && '' !== (string) $bank_data[ $key ] ) {
				$raw[ $key ] = (string) $bank_data[ $key ];
			}
		}

		if ( isset( $raw['bic'] ) ) {
			$mapped['bic'] = strtoupper( $raw['bic'] );
		}
		if ( isset( $raw['description'] ) ) {
			$mapped['bankName'] = $raw['description'];
		}
		if ( isset( $raw['descriptionShort'] ) ) {
			$mapped['bankNameShort'] = $raw['descriptionShort'];
		}
		if ( isset( $raw['city'] ) ) {
			$mapped['bankCity'] = $raw['city'];
		}
		if ( isset( $raw['bankCode'] ) ) {
			$mapped['bankCode'] = $raw['bankCode'];
		}
		if ( [] !== $raw ) {
			$mapped['bankData'] = $raw;
		}

		return $mapped;
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function remote_error_message( array $result ): string {
		$checks = isset( $result['checks'] ) && is_array( $result['checks'] ) ? $result['checks'] : [];
		if ( isset( $checks['ibanLength'] ) && false === $checks['ibanLength'] ) {
			return __( 'IBAN has not the correct length.', 'ibantest-for-woocommerce' );
		}
		if ( isset( $checks['ibanChecksum'] ) && false === $checks['ibanChecksum'] ) {
			return __( 'IBAN contains an incorrect checksum.', 'ibantest-for-woocommerce' );
		}
		if ( isset( $checks['ibanStructure'] ) && false === $checks['ibanStructure'] ) {
			return __( 'IBAN structure is incorrect.', 'ibantest-for-woocommerce' );
		}
		if ( isset( $checks['bankAccountSyntaxVerify'] ) && false === $checks['bankAccountSyntaxVerify'] ) {
			return __( 'The account number checksum is incorrect.', 'ibantest-for-woocommerce' );
		}
		if ( isset( $checks['bankExistVerify'] ) && false === $checks['bankExistVerify'] ) {
			return __( 'This bank code does not exist.', 'ibantest-for-woocommerce' );
		}

		return __( 'Your IBAN is invalid.', 'ibantest-for-woocommerce' );
	}

	private function can_validate_remotely(): bool {
		return '' !== $this->api_key() && class_exists( \Ibantest\Ibantest::class );
	}

	private function api_key(): string {
		return isset( $this->settings['apikey'] ) ? trim( (string) $this->settings['apikey'] ) : '';
	}

	private function validation_unavailable_message(): string {
		return __( 'IBAN validation is currently not possible. Please try again later.', 'ibantest-for-woocommerce' );
	}

	/**
	 * @return mixed
	 */
	private function session_get( string $key ) {
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			return WC()->session->get( $key );
		}

		return false;
	}

	/**
	 * @param mixed $value
	 */
	private function session_set( string $key, $value ): void {
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			WC()->session->set( $key, $value );
		}
	}
}
