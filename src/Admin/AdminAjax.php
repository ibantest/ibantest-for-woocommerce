<?php

namespace Ibantest\WooCommerce\Admin;

use Ibantest\WooCommerce\Plugin;
use Ibantest\WooCommerce\Services\IbanService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminAjax {
	public function __construct( private Plugin $plugin ) {}

	public function init(): void {
		add_action( 'wp_ajax_ibantest_refresh_credits', [ $this, 'ajax_refresh_credits' ] );
		add_action( 'wp_ajax_ibantest_verify_api_key', [ $this, 'ajax_verify_api_key' ] );
		add_action( 'admin_post_ibantest_refresh_credits', [ $this, 'refresh_credits' ] );
	}

	public function refresh_credits(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to refresh IBANTEST credits.', 'ibantest-for-woocommerce' ), 403 );
		}

		check_admin_referer( 'ibantest_refresh_credits' );

		$overview = $this->plugin->iban_service()->get_credit_overview( true );
		$status   = $overview['error'] ? 'failed' : 'updated';

		wp_safe_redirect(
			add_query_arg(
				'ibantest_credits',
				$status,
				$this->plugin->settings_url()
			)
		);
		exit;
	}

	public function ajax_refresh_credits(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to refresh IBANTEST credits.', 'ibantest-for-woocommerce' ) ],
				403
			);
		}

		check_ajax_referer( 'ibantest_refresh_credits', 'nonce' );

		$overview = $this->plugin->iban_service()->get_credit_overview( true );
		if ( $overview['error'] ) {
			wp_send_json_error(
				[
					'message'      => __( 'Credit balance could not be refreshed.', 'ibantest-for-woocommerce' ),
					'credits'      => $overview['credits'],
					'lastUpdated'   => $this->format_credit_timestamp( $overview['last_updated'] ),
					'lastUpdatedAt' => $overview['last_updated'],
				],
				502
			);
		}

		wp_send_json_success(
			[
				'message'       => __( 'Credit balance refreshed.', 'ibantest-for-woocommerce' ),
				'credits'       => $overview['credits'],
				'creditsLabel'  => null === $overview['credits'] ? __( 'Not available', 'ibantest-for-woocommerce' ) : number_format_i18n( $overview['credits'] ),
				'lastUpdated'   => $this->format_credit_timestamp( $overview['last_updated'] ),
				'lastUpdatedAt' => $overview['last_updated'],
			]
		);
	}

	public function ajax_verify_api_key(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to update the IBANTEST API key.', 'ibantest-for-woocommerce' ) ],
				403
			);
		}

		check_ajax_referer( 'ibantest_verify_api_key', 'nonce' );

		$api_key = isset( $_POST['apiKey'] ) ? sanitize_text_field( wp_unslash( $_POST['apiKey'] ) ) : '';
		if ( '' === trim( $api_key ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Please enter your IBANTEST API key.', 'ibantest-for-woocommerce' ) ],
				400
			);
		}

		$overview = ( new IbanService( [ 'apikey' => $api_key ] ) )->verify_api_key( $api_key );
		if ( $overview['error'] ) {
			wp_send_json_error(
				[ 'message' => __( 'We could not verify this API key. Please check the key and try again.', 'ibantest-for-woocommerce' ) ],
				400
			);
		}

		$settings           = $this->plugin->settings();
		$settings['apikey'] = $api_key;
		$this->plugin->replace_iban_service( new IbanService( $settings ) );
		update_option( 'woocommerce_' . Plugin::GATEWAY_ID . '_settings', $settings );

		wp_send_json_success(
			[
				'message'       => __( 'API key verified and saved. IBANTEST validation is ready.', 'ibantest-for-woocommerce' ),
				'credits'       => $overview['credits'],
				'creditsLabel'  => number_format_i18n( (int) $overview['credits'] ),
				'lastUpdated'   => $this->format_credit_timestamp( $overview['last_updated'] ),
				'lastUpdatedAt' => $overview['last_updated'],
			]
		);
	}

	private function format_credit_timestamp( ?int $timestamp ): string {
		if ( ! $timestamp ) {
			return __( 'Last updated: never', 'ibantest-for-woocommerce' );
		}

		return sprintf(
			/* translators: %s: localized date and time. */
			__( 'Last updated: %s', 'ibantest-for-woocommerce' ),
			date_i18n( wc_date_format() . ' ' . wc_time_format(), $timestamp )
		);
	}
}
