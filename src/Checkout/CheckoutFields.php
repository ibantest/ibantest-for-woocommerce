<?php

namespace Ibantest\WooCommerce\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckoutFields {
	public function __construct( private \WC_Payment_Gateway $gateway ) {}

	public function render(): void {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->gateway->id ); ?>-form" class="wc-payment-form wc-<?php echo esc_attr( $this->gateway->id ); ?>-form">
			<p class="form-row form-row-wide">
				<label for="<?php echo esc_attr( $this->gateway->id ); ?>-account-iban">
					<?php esc_html_e( 'IBAN', 'ibantest-for-woocommerce' ); ?> <span class="required">*</span>
				</label>
				<input id="<?php echo esc_attr( $this->gateway->id ); ?>-account-iban" class="input-text transform-uppercase" name="<?php echo esc_attr( $this->gateway->id ); ?>_account_iban" type="text" inputmode="text" autocomplete="off" aria-describedby="ibantest-account-status ibantest-account-iban-description" required>
				<span id="ibantest-account-iban-description" class="description"><?php esc_html_e( 'Enter your IBAN, for example DE02 6005 0101 0002 0343 04.', 'ibantest-for-woocommerce' ); ?></span>
			</p>
			<input id="<?php echo esc_attr( $this->gateway->id ); ?>-account-bic" name="<?php echo esc_attr( $this->gateway->id ); ?>_account_bic" type="hidden">
			<input id="<?php echo esc_attr( $this->gateway->id ); ?>-account-holder" name="<?php echo esc_attr( $this->gateway->id ); ?>_account_holder" type="hidden">
			<input id="ibantest-iban-validated" name="ibantest_iban_validated" type="hidden" value="">
			<input id="ibantest-account-bank" name="ibantest_account_bank" type="hidden" value="">
			<div id="ibantest-account-status" class="ibantest-validation-status" role="status" aria-live="polite" hidden></div>
			<div id="ibantest-bank-details" class="ibantest-bank-details" hidden></div>
			<?php if ( $this->mandate_checkbox_required() ) : ?>
				<p class="form-row form-row-wide legal">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="<?php echo esc_attr( $this->gateway->id ); ?>_mandate_checkbox" value="1">
						<span><?php echo esc_html( $this->gateway->get_option( 'mandate_checkbox_label', __( 'I hereby agree to the SEPA Direct Debit Mandate.', 'ibantest-for-woocommerce' ) ) ); ?></span>
					</label>
				</p>
			<?php endif; ?>
			<p class="form-row form-row-wide">
				<button type="button" class="button" id="ibantest-show-mandate"><?php esc_html_e( 'Show SEPA mandate', 'ibantest-for-woocommerce' ); ?></button>
			</p>
			<div id="ibantest-mandate-preview" class="woocommerce-info" style="display:none"></div>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	public function mandate_checkbox_required(): bool {
		return 'yes' === $this->gateway->get_option( 'enable_mandate_checkbox', 'yes' );
	}

	/**
	 * @return array{iban: string, bic: string, holder: string, mandate_accepted: bool}
	 */
	public function posted_payment_data(): array {
		return [
			'iban'             => $this->clean_posted_string( 'ibantest_account_iban' ),
			'bic'              => strtoupper( $this->clean_posted_string( 'ibantest_account_bic' ) ),
			'holder'           => $this->posted_account_holder(),
			'mandate_accepted' => $this->posted_bool( 'ibantest_mandate_checkbox' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function mandate_preview_data_from_checkout(): array {
		return [
			'account_holder' => $this->posted_account_holder(),
			'iban'           => $this->clean_posted_string( 'ibantest_account_iban' ),
			'bic'            => $this->clean_posted_string( 'ibantest_account_bic' ),
			'street'         => $this->clean_posted_string( 'billing_address_1' ),
			'postcode'       => $this->clean_posted_string( 'billing_postcode' ),
			'city'           => $this->clean_posted_string( 'billing_city' ),
			'country'        => $this->clean_posted_string( 'billing_country' ),
		];
	}

	private function posted_account_holder(): string {
		$holder = $this->clean_posted_string( 'ibantest_account_holder' );
		if ( '' !== $holder ) {
			return $holder;
		}

		$company = $this->clean_posted_string( 'billing_company' );
		if ( '' !== $company ) {
			return $company;
		}

		return trim( $this->clean_posted_string( 'billing_first_name' ) . ' ' . $this->clean_posted_string( 'billing_last_name' ) );
	}

	private function clean_posted_string( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		$value = wp_unslash( $_POST[ $key ] );
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return (string) wc_clean( $value );
	}

	private function posted_bool( string $key ): bool {
		if ( ! isset( $_POST[ $key ] ) ) {
			return false;
		}

		$value = wc_clean( wp_unslash( $_POST[ $key ] ) );
		return in_array( $value, [ '1', 'yes', 'true', 'on' ], true );
	}
}
