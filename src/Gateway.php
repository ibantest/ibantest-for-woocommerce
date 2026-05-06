<?php

namespace Ibantest\WooCommerce;

use Ibantest\WooCommerce\Admin\SettingsRenderer;
use Ibantest\WooCommerce\Checkout\CheckoutFields;
use Ibantest\WooCommerce\Emails\DirectDebitEmailRenderer;
use Ibantest\WooCommerce\Orders\DirectDebitOrderMeta;
use Ibantest\WooCommerce\Orders\DirectDebitOrderRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gateway extends \WC_Payment_Gateway {
	public const META_IBAN         = DirectDebitOrderMeta::META_IBAN;
	public const META_BIC          = DirectDebitOrderMeta::META_BIC;
	public const META_HOLDER       = DirectDebitOrderMeta::META_HOLDER;
	public const META_MANDATE_ID   = DirectDebitOrderMeta::META_MANDATE_ID;
	public const META_MANDATE_DATE = DirectDebitOrderMeta::META_MANDATE_DATE;
	public const META_MANDATE_MAIL = DirectDebitOrderMeta::META_MANDATE_MAIL;

	private Plugin $plugin;
	private ?CheckoutFields $checkout_fields = null;
	private ?DirectDebitOrderMeta $order_meta = null;
	private ?DirectDebitOrderRenderer $order_renderer = null;
	private ?DirectDebitEmailRenderer $email_renderer = null;
	private ?SettingsRenderer $settings_renderer = null;

	public function __construct( ?Plugin $plugin = null ) {
		$this->plugin             = $plugin ?? Plugin::instance();
		$this->id                 = Plugin::GATEWAY_ID;
		$this->icon               = WC_IBANTEST_PLUGIN_URL . '/assets/img/ibantest-icon.svg';
		$this->method_title       = __( 'SEPA Direct Debit (IBANTEST)', 'ibantest-for-woocommerce' );
		$this->method_description = __( 'Accept SEPA direct debit payments with IBAN validation.', 'ibantest-for-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
		];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'SEPA Direct Debit', 'ibantest-for-woocommerce' ) );
		$this->description = $this->get_option( 'description', '' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'show_thank_you' ] );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_detail_fields' ] );
		add_action( 'woocommerce_email_customer_details', [ $this, 'email_direct_debit' ], 15, 3 );
	}

	public function init_form_fields(): void {
		$this->form_fields = $this->plugin->gateway_fields();
	}

	public function is_available(): bool {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return $this->plugin->encryption()->is_enabled() && parent::is_available();
	}

	public function needs_setup(): bool {
		return ! $this->has_api_key();
	}

	public function is_account_connected(): bool {
		return $this->has_api_key();
	}

	public function is_onboarding_started(): bool {
		return $this->has_api_key();
	}

	public function is_onboarding_completed(): bool {
		return $this->has_api_key();
	}

	public function get_settings_url(): string {
		return $this->plugin->settings_url();
	}

	public function get_connection_url( string $return_url = '' ): string {
		return $this->plugin->settings_url() . '#ibantest-onboarding';
	}

	public function enqueue_scripts(): void {
		if ( ! is_checkout() || 'yes' !== $this->enabled ) {
			return;
		}

		wp_register_script(
			'ibantest-checkout',
			WC_IBANTEST_PLUGIN_URL . '/assets/js/classic-checkout.js',
			[ 'jquery', 'wc-checkout' ],
			WC_IBANTEST_VERSION,
			true
		);

		wp_enqueue_style(
			'ibantest-checkout',
			WC_IBANTEST_PLUGIN_URL . '/assets/css/checkout.css',
			[],
			WC_IBANTEST_VERSION
		);

		wp_localize_script(
			'ibantest-checkout',
			'ibantestCheckout',
			[
				'validateIbanUrl'     => \WC_AJAX::get_endpoint( 'ibantest_validate_iban' ),
				'validateIbanNonce'   => wp_create_nonce( 'ibantest_validate_iban' ),
				'showMandateUrl'      => \WC_AJAX::get_endpoint( 'ibantest_show_sepa_mandate' ),
				'showMandateNonce'    => wp_create_nonce( 'ibantest_show_sepa_mandate' ),
				'validationErrorText' => __( 'Please check your bank account data.', 'ibantest-for-woocommerce' ),
				'checkingText'        => __( 'Checking IBAN...', 'ibantest-for-woocommerce' ),
				'validText'           => __( 'IBAN valid.', 'ibantest-for-woocommerce' ),
				'enterIbanText'       => __( 'Enter your IBAN to start the check.', 'ibantest-for-woocommerce' ),
				'waitText'            => __( 'Please wait until the IBAN check is complete.', 'ibantest-for-woocommerce' ),
				'errorText'           => __( 'IBAN check could not be completed. Please try again.', 'ibantest-for-woocommerce' ),
				'bicText'             => __( 'BIC', 'ibantest-for-woocommerce' ),
				'ibanHelpText'        => __( 'Enter your IBAN, for example DE02 6005 0101 0002 0343 04.', 'ibantest-for-woocommerce' ),
				'validationTrigger'   => $this->iban_validation_trigger(),
				'validationDelay'     => $this->iban_validation_delay(),
			]
		);

		wp_enqueue_script( 'ibantest-checkout' );
	}

	public function admin_enqueue_scripts(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		wp_enqueue_style(
			'ibantest-admin',
			WC_IBANTEST_PLUGIN_URL . '/assets/css/admin.css',
			[],
			WC_IBANTEST_VERSION
		);

		wp_register_script(
			'ibantest-admin',
			WC_IBANTEST_PLUGIN_URL . '/assets/js/admin.js',
			[],
			WC_IBANTEST_VERSION,
			true
		);

		wp_localize_script(
			'ibantest-admin',
			'ibantestAdmin',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'refreshNonce'  => wp_create_nonce( 'ibantest_refresh_credits' ),
				'refreshAction' => 'ibantest_refresh_credits',
				'verifyNonce'   => wp_create_nonce( 'ibantest_verify_api_key' ),
				'verifyAction'  => 'ibantest_verify_api_key',
				'loadingText'   => __( 'Refreshing credits...', 'ibantest-for-woocommerce' ),
				'verifyText'    => __( 'Checking API key...', 'ibantest-for-woocommerce' ),
				'errorText'     => __( 'Credit balance could not be refreshed.', 'ibantest-for-woocommerce' ),
			]
		);

		wp_enqueue_script( 'ibantest-admin' );
	}

	public function admin_options(): void {
		$this->settings_renderer()->render();
	}

	public function payment_fields(): void {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}

		$this->checkout_fields()->render();
	}

	public function validate_fields(): bool {
		$data = $this->posted_payment_data();

		if ( isset( $_POST['payment_method'] ) ) {
			$payment_method = sanitize_key( wp_unslash( $_POST['payment_method'] ) );
			if ( $this->id !== $payment_method ) {
				return true;
			}
		}

		if ( ! $this->plugin->encryption()->is_enabled() ) {
			wc_add_notice( __( 'SEPA Direct Debit is not configured for encrypted storage.', 'ibantest-for-woocommerce' ), 'error' );
			return false;
		}

		if ( '' === $data['iban'] ) {
			wc_add_notice( __( 'Please insert your IBAN.', 'ibantest-for-woocommerce' ), 'error' );
			return false;
		}

		if ( $this->mandate_checkbox_required() && ! $data['mandate_accepted'] ) {
			wc_add_notice( __( 'Please agree to the SEPA mandate.', 'ibantest-for-woocommerce' ), 'error' );
			return false;
		}

		$iban_validation = $this->plugin->iban_service()->validate( $data['iban'] );
		if ( isset( $iban_validation['valid'] ) && false === $iban_validation['valid'] ) {
			wc_add_notice(
				isset( $iban_validation['message'] ) ? $iban_validation['message'] : __( 'Your IBAN is invalid.', 'ibantest-for-woocommerce' ),
				'error'
			);
			return false;
		}

		if ( ! empty( $iban_validation['bic'] ) ) {
			$_POST['ibantest_account_bic'] = (string) $iban_validation['bic'];
		}

		$data = $this->posted_payment_data();
		if ( '' !== $data['bic'] && ! $this->plugin->iban_service()->is_valid_bic( $data['bic'] ) ) {
			wc_add_notice( __( 'Your BIC is invalid.', 'ibantest-for-woocommerce' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * @param int|string $order_id
	 *
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return [
				'result'   => 'failure',
				'redirect' => '',
			];
		}

		$this->save_order_payment_data( $order, $this->posted_payment_data() );

		$order->update_status( 'on-hold', __( 'Awaiting SEPA Direct Debit payment.', 'ibantest-for-woocommerce' ) );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	public function show_thank_you(): void {
		printf(
			'<h3>%s</h3><p>%s</p>',
			esc_html__( 'Payment notice:', 'ibantest-for-woocommerce' ),
			esc_html__( 'The order amount will be debited directly from your bank account.', 'ibantest-for-woocommerce' )
		);
	}

	public function order_detail_fields( \WC_Order $order ): void {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		$this->order_renderer()->render( $order );
	}

	public function email_direct_debit( \WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		$this->email_renderer()->render( $order, $sent_to_admin, $plain_text );
	}

	/**
	 * @return array{iban: string, bic: string, holder: string, mandate_accepted: bool}
	 */
	public function posted_payment_data(): array {
		return $this->checkout_fields()->posted_payment_data();
	}

	/**
	 * @param array{iban: string, bic: string, holder: string, mandate_accepted: bool} $data
	 */
	public function save_order_payment_data( \WC_Order $order, array $data ): void {
		$this->order_meta()->save( $order, $data );
	}

	public function hide_iban( string $iban ): string {
		return $this->order_meta()->hide_iban( $iban );
	}

	public function mandate_checkbox_required(): bool {
		return $this->checkout_fields()->mandate_checkbox_required();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function block_data(): array {
		return [
			'title'                 => $this->title,
			'description'           => $this->description,
			'supports'              => $this->supports,
			'mandateCheckboxLabel'  => $this->get_option( 'mandate_checkbox_label', __( 'I hereby agree to the SEPA Direct Debit Mandate.', 'ibantest-for-woocommerce' ) ),
			'mandateRequired'       => $this->mandate_checkbox_required(),
			'ibanLabel'             => __( 'IBAN', 'ibantest-for-woocommerce' ),
			'validateIbanUrl'       => \WC_AJAX::get_endpoint( 'ibantest_validate_iban' ),
			'validateIbanNonce'     => wp_create_nonce( 'ibantest_validate_iban' ),
			'showMandateUrl'        => \WC_AJAX::get_endpoint( 'ibantest_show_sepa_mandate' ),
			'showMandateNonce'      => wp_create_nonce( 'ibantest_show_sepa_mandate' ),
			'showMandateLabel'      => __( 'Show SEPA mandate', 'ibantest-for-woocommerce' ),
			'validationErrorText'   => __( 'Please check your bank account data.', 'ibantest-for-woocommerce' ),
			'checkingText'          => __( 'Checking IBAN...', 'ibantest-for-woocommerce' ),
			'validText'             => __( 'IBAN valid.', 'ibantest-for-woocommerce' ),
			'enterIbanText'         => __( 'Enter your IBAN to start the check.', 'ibantest-for-woocommerce' ),
			'waitText'              => __( 'Please wait until the IBAN check is complete.', 'ibantest-for-woocommerce' ),
			'errorText'             => __( 'IBAN check could not be completed. Please try again.', 'ibantest-for-woocommerce' ),
			'bicText'               => __( 'BIC', 'ibantest-for-woocommerce' ),
			'ibanHelpText'          => __( 'Enter your IBAN, for example DE02 6005 0101 0002 0343 04.', 'ibantest-for-woocommerce' ),
			'validationTrigger'     => $this->iban_validation_trigger(),
			'validationDelay'       => $this->iban_validation_delay(),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function mandate_preview_data_from_checkout(): array {
		return $this->checkout_fields()->mandate_preview_data_from_checkout();
	}

	private function checkout_fields(): CheckoutFields {
		if ( null === $this->checkout_fields ) {
			$this->checkout_fields = new CheckoutFields( $this );
		}

		return $this->checkout_fields;
	}

	private function order_meta(): DirectDebitOrderMeta {
		if ( null === $this->order_meta ) {
			$this->order_meta = new DirectDebitOrderMeta( $this->plugin, $this );
		}

		return $this->order_meta;
	}

	private function order_renderer(): DirectDebitOrderRenderer {
		if ( null === $this->order_renderer ) {
			$this->order_renderer = new DirectDebitOrderRenderer( $this->order_meta() );
		}

		return $this->order_renderer;
	}

	private function email_renderer(): DirectDebitEmailRenderer {
		if ( null === $this->email_renderer ) {
			$this->email_renderer = new DirectDebitEmailRenderer( $this->order_meta() );
		}

		return $this->email_renderer;
	}

	private function settings_renderer(): SettingsRenderer {
		if ( null === $this->settings_renderer ) {
			$this->settings_renderer = new SettingsRenderer( $this->plugin, $this );
		}

		return $this->settings_renderer;
	}

	private function has_api_key(): bool {
		return '' !== trim( (string) $this->get_option( 'apikey', '' ) );
	}

	private function iban_validation_trigger(): string {
		$trigger = (string) $this->get_option( 'iban_validation_trigger', 'debounce' );

		return in_array( $trigger, [ 'debounce', 'focusout', 'submit' ], true ) ? $trigger : 'debounce';
	}

	private function iban_validation_delay(): int {
		$delay = (int) $this->get_option( 'iban_validation_delay', 450 );

		return min( 5000, max( 0, $delay ) );
	}
}
