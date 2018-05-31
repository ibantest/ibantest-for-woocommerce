<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_IBANTEST_AJAX class.
 *
 * Registers AJAX actions for IBANTEST for WooCommerce.
 *
 * @extends WC_AJAX
 */
class WC_IBANTEST_AJAX extends WC_AJAX {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'ibantest_validate_iban'     => true,
			'ibantest_show_sepa_mandate' => true,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Cart quantity update function.
	 */
	public static function ibantest_validate_iban() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ibantest_validate_iban' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$data = [];
		if ( isset( $_POST['iban'] ) && ! empty( $_POST['iban'] ) ) {
			$data = WC_IBANTEST_Service()->validate_iban( $_POST['iban'] );
		}

		echo json_encode( $data );

		wp_die();
	}

	/**
	 * Cart quantity update function.
	 */
	public static function ibantest_show_sepa_mandate() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ibantest_show_sepa_mandate' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$settings = IBANTEST_For_WooCommerce()->get_settings();
		$values   = array();
		parse_str( $_POST['checkout'], $values );

		$params = array(
			'creditor_account_holder' => wc_clean( isset( $values['ibantest_account_holder'] ) ? $values['ibantest_account_holder'] : '' ),
			'creditor_account_iban'   => wc_clean( isset( $values['ibantest_account_iban'] ) ? $values['ibantest_account_iban'] : '' ),
			'creditor_agent_bic'      => wc_clean( isset( $values['ibantest_account_bic'] ) ? $values['ibantest_account_bic'] : '' ),
			'street'                  => wc_clean( isset( $values['billing_address_1'] ) ? $values['billing_address_1'] : '' ),
			'postcode'                => wc_clean( isset( $values['billing_postcode'] ) ? $values['billing_postcode'] : '' ),
			'city'                    => wc_clean( isset( $values['billing_city'] ) ? $values['billing_city'] : '' ),
			'country'                 => ( isset( $values['billing_country'] ) && isset( WC()->countries->countries[ $values['billing_country'] ] ) ? WC()->countries->countries[ $values['billing_country'] ] : '' ),
			'mandate_id'              => isset( $values['order_id'] ) ? wc_clean( $values['order_id'] ) : __( 'Will be notified separately', 'ibantest-for-woocommerce' ),
		);

		$args = wp_parse_args( $params, array(
			'creditor_name' => $settings['creditor_name'],
			'company_info'  => $settings['mandate_text'],
			'creditor_id'   => $settings['creditor_id'],
			'date'          => date_i18n( wc_date_format(), strtotime( "now" ) ),
		) );

		$text = __( $settings['mandate_text'], 'ibantest-for-woocommerce' );

		foreach ( $args as $key => $val ) {
			$text = str_replace( '[' . $key . ']', $val, $text );
		}

		$content = apply_filters( 'the_content', $text );

		echo json_encode( $content );
		wp_die();
	}
}

WC_IBANTEST_AJAX::init();
