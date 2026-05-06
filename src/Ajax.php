<?php

namespace Ibantest\WooCommerce;

use Ibantest\WooCommerce\Support\Mandate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ajax {
	public function __construct( private Plugin $plugin ) {}

	public function init(): void {
		foreach ( [ 'ibantest_validate_iban', 'ibantest_show_sepa_mandate' ] as $event ) {
			add_action( 'wp_ajax_woocommerce_' . $event, [ $this, $event ] );
			add_action( 'wp_ajax_nopriv_woocommerce_' . $event, [ $this, $event ] );
			add_action( 'wc_ajax_' . $event, [ $this, $event ] );
		}
	}

	public function ibantest_validate_iban(): void {
		if ( ! $this->verify_nonce( 'ibantest_validate_iban' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ibantest-for-woocommerce' ) ], 403 );
		}

		if ( $this->rate_limited() ) {
			wp_send_json_error( [ 'message' => __( 'Please wait before validating another IBAN.', 'ibantest-for-woocommerce' ) ], 429 );
		}

		$iban = isset( $_POST['iban'] ) ? wc_clean( wp_unslash( $_POST['iban'] ) ) : '';
		wp_send_json_success( $this->plugin->iban_service()->validate( (string) $iban ) );
	}

	public function ibantest_show_sepa_mandate(): void {
		if ( ! $this->verify_nonce( 'ibantest_show_sepa_mandate' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ibantest-for-woocommerce' ) ], 403 );
		}

		$_POST = $this->checkout_values();
		$gateway = $this->plugin->gateway();

		wp_send_json_success(
			[
				'html' => Mandate::render( $this->plugin->settings(), $gateway->mandate_preview_data_from_checkout() ),
			]
		);
	}

	private function verify_nonce( string $action ): bool {
		if ( ! isset( $_POST['nonce'] ) ) {
			return false;
		}

		return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $action );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function checkout_values(): array {
		$values = [];
		if ( isset( $_POST['checkout'] ) && is_string( $_POST['checkout'] ) ) {
			parse_str( wp_unslash( $_POST['checkout'] ), $values );
		} else {
			$values = $_POST;
		}

		if ( isset( $values['billing_country'] ) && function_exists( 'WC' ) && WC()->countries ) {
			$country = wc_clean( wp_unslash( $values['billing_country'] ) );
			if ( isset( WC()->countries->countries[ $country ] ) ) {
				$values['billing_country'] = WC()->countries->countries[ $country ];
			}
		}

		return array_map(
			static function ( $value ) {
				return is_scalar( $value ) ? wc_clean( wp_unslash( $value ) ) : '';
			},
			$values
		);
	}

	private function rate_limited(): bool {
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key      = 'ibantest_rate_' . md5( $ip . '|' . ( function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '' ) );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= 30 ) {
			return true;
		}

		set_transient( $key, $attempts + 1, MINUTE_IN_SECONDS );
		return false;
	}
}
