<?php

namespace Ibantest\WooCommerce\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mandate {
	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, string> $data
	 */
	public static function render( array $settings, array $data ): string {
		$text = isset( $settings['mandate_text'] ) && '' !== trim( (string) $settings['mandate_text'] )
			? (string) $settings['mandate_text']
			: Settings::default_mandate_text();

		$values = wp_parse_args(
			$data,
			[
				'creditor_name'  => (string) ( $settings['creditor_name'] ?? '' ),
				'creditor_id'    => (string) ( $settings['creditor_id'] ?? '' ),
				'mandate_id'     => __( 'Will be notified separately', 'ibantest-for-woocommerce' ),
				'account_holder' => '',
				'street'         => '',
				'postcode'       => '',
				'city'           => '',
				'country'        => '',
				'iban'           => '',
				'bic'            => '',
				'date'           => date_i18n( wc_date_format() ),
			]
		);

		foreach ( $values as $key => $value ) {
			$text = str_replace( '[' . $key . ']', (string) $value, $text );
		}

		return wp_kses_post( wpautop( $text ) );
	}
}
