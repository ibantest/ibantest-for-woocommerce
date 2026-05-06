<?php

namespace Ibantest\WooCommerce\Emails;

use Ibantest\WooCommerce\Orders\DirectDebitOrderMeta;
use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DirectDebitEmailRenderer {
	public function __construct( private DirectDebitOrderMeta $meta ) {}

	public function render( \WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
		if ( Plugin::GATEWAY_ID !== $order->get_payment_method() ) {
			return;
		}

		$data   = $this->meta->decrypted_payment_data( $order );
		$fields = [
			__( 'Account Holder', 'ibantest-for-woocommerce' ) => $data['holder'],
			__( 'IBAN', 'ibantest-for-woocommerce' )           => $this->meta->hide_iban( $data['iban'] ),
			__( 'BIC/SWIFT', 'ibantest-for-woocommerce' )      => $data['bic'],
		];

		if ( $sent_to_admin ) {
			$fields[ __( 'Mandate Reference ID', 'ibantest-for-woocommerce' ) ] = (string) $order->get_meta( DirectDebitOrderMeta::META_MANDATE_ID );
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'SEPA direct debit data', 'ibantest-for-woocommerce' ) . "\n";
			foreach ( $fields as $label => $value ) {
				echo esc_html( $label . ': ' . $value ) . "\n";
			}
			return;
		}

		echo '<h2>' . esc_html__( 'SEPA direct debit data', 'ibantest-for-woocommerce' ) . '</h2><p>';
		foreach ( $fields as $label => $value ) {
			echo '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '<br>';
		}
		echo '</p>';
	}
}
