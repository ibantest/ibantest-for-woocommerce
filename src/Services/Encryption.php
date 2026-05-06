<?php

namespace Ibantest\WooCommerce\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Encryption {
	private ?Key $key = null;
	private bool $key_loaded = false;

	public function generate_random_key(): string {
		return Key::createNewRandomKey()->saveToAsciiSafeString();
	}

	public function is_enabled(): bool {
		return $this->key() instanceof Key;
	}

	public function encrypt( string $value ): string {
		if ( '' === trim( $value ) ) {
			return '';
		}

		$key = $this->key();
		if ( ! $key ) {
			return '';
		}

		return Crypto::encrypt( $value, $key );
	}

	public function decrypt( string $value ): string {
		if ( '' === trim( $value ) ) {
			return '';
		}

		$key = $this->key();
		if ( ! $key ) {
			return '';
		}

		try {
			return Crypto::decrypt( $value, $key );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	private function key(): ?Key {
		if ( $this->key_loaded ) {
			return $this->key;
		}

		$this->key_loaded = true;

		if ( ! defined( 'WC_IBANTEST_ENCRYPTION_KEY' ) ) {
			return null;
		}

		$key = trim( (string) WC_IBANTEST_ENCRYPTION_KEY );
		if ( '' === $key ) {
			return null;
		}

		try {
			$this->key = Key::loadFromAsciiSafeString( $key );
		} catch ( \Throwable $e ) {
			$this->key = null;
		}

		return $this->key;
	}
}
