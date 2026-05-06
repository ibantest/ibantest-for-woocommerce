<?php

namespace Ibantest\WooCommerce\Orders;

use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DirectDebitOrderMeta {
	public const META_IBAN         = '_ibantest_iban';
	public const META_BIC          = '_ibantest_bic';
	public const META_HOLDER       = '_ibantest_account_holder';
	public const META_MANDATE_ID   = '_ibantest_mandate_id';
	public const META_MANDATE_DATE = '_ibantest_mandate_date';
	public const META_MANDATE_MAIL = '_ibantest_mandate_email';

	public function __construct( private Plugin $plugin, private \WC_Payment_Gateway $gateway ) {}

	/**
	 * @param array{iban: string, bic: string, holder: string, mandate_accepted: bool} $data
	 */
	public function save( \WC_Order $order, array $data ): void {
		$mandate_id = str_replace(
			'[id]',
			$order->get_order_number(),
			$this->gateway->get_option( 'mandate_id_format', 'MANDATE[id]' )
		);

		$order->update_meta_data( self::META_IBAN, $this->plugin->encryption()->encrypt( $this->plugin->iban_service()->normalize_iban( $data['iban'] ) ) );
		$order->update_meta_data( self::META_BIC, $this->plugin->encryption()->encrypt( strtoupper( $data['bic'] ?? '' ) ) );
		$order->update_meta_data( self::META_HOLDER, $this->plugin->encryption()->encrypt( $this->account_holder_from_data_or_order( $data, $order ) ) );
		$order->update_meta_data( self::META_MANDATE_ID, $mandate_id );
		$order->update_meta_data( self::META_MANDATE_DATE, time() );
		$order->update_meta_data( self::META_MANDATE_MAIL, $order->get_billing_email() );
		$order->save();
	}

	/**
	 * @param array{holder?: string} $data
	 */
	private function account_holder_from_data_or_order( array $data, \WC_Order $order ): string {
		$holder = trim( (string) ( $data['holder'] ?? '' ) );
		if ( '' !== $holder ) {
			return $holder;
		}

		if ( method_exists( $order, 'get_billing_company' ) && '' !== trim( (string) $order->get_billing_company() ) ) {
			return trim( (string) $order->get_billing_company() );
		}

		$first_name = method_exists( $order, 'get_billing_first_name' ) ? (string) $order->get_billing_first_name() : '';
		$last_name  = method_exists( $order, 'get_billing_last_name' ) ? (string) $order->get_billing_last_name() : '';

		return trim( $first_name . ' ' . $last_name );
	}

	public function hide_iban( string $iban ): string {
		$mode   = $this->gateway->get_option( 'hide_iban_chars', 'last4' );
		$length = strlen( $iban );

		if ( $length <= 4 || 'full' === $mode ) {
			return $iban;
		}

		return match ( $mode ) {
			'last3'      => str_repeat( '*', max( 0, $length - 3 ) ) . substr( $iban, -3 ),
			'firstlast3' => substr( $iban, 0, 3 ) . str_repeat( '*', max( 0, $length - 6 ) ) . substr( $iban, -3 ),
			'firstlast4' => substr( $iban, 0, 4 ) . str_repeat( '*', max( 0, $length - 8 ) ) . substr( $iban, -4 ),
			default      => str_repeat( '*', max( 0, $length - 4 ) ) . substr( $iban, -4 ),
		};
	}

	/**
	 * @return array{holder: string, iban: string, bic: string}
	 */
	public function decrypted_payment_data( \WC_Order $order ): array {
		return [
			'holder' => $this->plugin->encryption()->decrypt( (string) $order->get_meta( self::META_HOLDER ) ),
			'iban'   => $this->plugin->encryption()->decrypt( (string) $order->get_meta( self::META_IBAN ) ),
			'bic'    => $this->plugin->encryption()->decrypt( (string) $order->get_meta( self::META_BIC ) ),
		];
	}
}
