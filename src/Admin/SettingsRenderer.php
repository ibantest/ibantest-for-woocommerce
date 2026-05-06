<?php

namespace Ibantest\WooCommerce\Admin;

use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsRenderer {
	public function __construct( private Plugin $plugin, private \WC_Payment_Gateway $gateway ) {}

	public function render(): void {
		echo '<div class="ibantest-wrapper">';
		echo '<div class="ibantest-main">';
		$this->render_onboarding_panel();
		$this->render_settings_sections();
		echo '</div>';
		$this->render_credit_overview();
		echo '</div>';
	}

	private function render_settings_sections(): void {
		$fields = $this->gateway->get_form_fields();

		foreach ( $this->settings_sections() as $section ) {
			$this->render_settings_section( $section, $fields );
		}
	}

	/**
	 * @param array{title: string, description: string, fields: string[], class?: string} $section
	 * @param array<string, array<string, mixed>> $fields
	 */
	private function render_settings_section( array $section, array $fields ): void {
		$section_fields = array_intersect_key( $fields, array_flip( $section['fields'] ) );
		if ( [] === $section_fields ) {
			return;
		}

		$is_encryption_section = in_array( 'encryption', $section['fields'], true );
		?>
		<section class="ibantest-settings-card <?php echo esc_attr( $section['class'] ?? '' ); ?>">
			<div class="ibantest-settings-card-header">
				<h3><?php echo esc_html( $section['title'] ); ?></h3>
				<p><?php echo esc_html( $section['description'] ); ?></p>
			</div>
			<?php if ( $is_encryption_section && isset( $fields['encryption']['description'] ) ) : ?>
				<div class="ibantest-encryption-note <?php echo $this->plugin->encryption()->is_enabled() ? 'is-ready' : 'needs-attention'; ?>">
					<?php echo wp_kses_post( (string) $fields['encryption']['description'] ); ?>
				</div>
			<?php else : ?>
				<table class="form-table ibantest-settings-table" role="presentation">
					<?php
					echo $this->gateway->generate_settings_html( $section_fields, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce renders trusted settings fields.
					?>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @return array<int, array{title: string, description: string, fields: string[], class?: string}>
	 */
	private function settings_sections(): array {
		return [
			[
				'title'       => __( 'Setup and API access', 'ibantest-for-woocommerce' ),
				'description' => __( 'Turn the payment method on and connect it with your IBANTEST account for live validation and credit tracking.', 'ibantest-for-woocommerce' ),
				'fields'      => [ 'enabled', 'apikey' ],
			],
			[
				'title'       => __( 'Checkout presentation', 'ibantest-for-woocommerce' ),
				'description' => __( 'Control the payment name, explanatory text and IBAN validation behavior shown to customers during checkout.', 'ibantest-for-woocommerce' ),
				'fields'      => [ 'title', 'description', 'iban_validation_trigger', 'iban_validation_delay' ],
			],
			[
				'title'       => __( 'Encrypted bank data storage', 'ibantest-for-woocommerce' ),
				'description' => __( 'IBAN and BIC values are stored encrypted. This section shows whether the required encryption key is available.', 'ibantest-for-woocommerce' ),
				'fields'      => [ 'encryption' ],
				'class'       => 'ibantest-settings-card-encryption',
			],
			[
				'title'       => __( 'Creditor and SEPA export', 'ibantest-for-woocommerce' ),
				'description' => __( 'Enter the creditor information used for mandate text and generated SEPA XML files.', 'ibantest-for-woocommerce' ),
				'fields'      => [ 'creditor_name', 'creditor_account_holder', 'creditor_account_iban', 'creditor_agent_bic', 'creditor_id', 'sepa_xml_format' ],
			],
			[
				'title'       => __( 'SEPA mandate', 'ibantest-for-woocommerce' ),
				'description' => __( 'Define the mandate wording, customer consent checkbox and how IBAN values are masked in admin and email output.', 'ibantest-for-woocommerce' ),
				'fields'      => [ 'mandate_text', 'enable_mandate_checkbox', 'mandate_checkbox_label', 'mandate_id_format', 'hide_iban_chars' ],
			],
		];
	}

	private function render_credit_overview(): void {
		$overview    = $this->plugin->iban_service()->get_credit_overview();
		$credits     = $overview['credits'];
		$updated_at  = $overview['last_updated'];
		$logo_url    = WC_IBANTEST_PLUGIN_URL . '/assets/img/ibantest-logo.svg';
		$refresh_url = wp_nonce_url(
			add_query_arg( 'action', 'ibantest_refresh_credits', admin_url( 'admin-post.php' ) ),
			'ibantest_refresh_credits'
		);
		?>
		<div class="ibantest-sidebar">
			<img class="ibantest-settings-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'IBANTEST', 'ibantest-for-woocommerce' ); ?>" width="260" height="64">
			<div class="ibantest-sidebar-panel">
				<h3><?php esc_html_e( 'Credit Overview', 'ibantest-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'Remaining credits', 'ibantest-for-woocommerce' ); ?></p>
				<?php if ( isset( $_GET['ibantest_credits'] ) && 'updated' === sanitize_key( wp_unslash( $_GET['ibantest_credits'] ) ) ) : ?>
					<p class="description ibantest-credit-status" id="ibantest-credit-status"><?php esc_html_e( 'Credit balance refreshed.', 'ibantest-for-woocommerce' ); ?></p>
				<?php elseif ( isset( $_GET['ibantest_credits'] ) && 'failed' === sanitize_key( wp_unslash( $_GET['ibantest_credits'] ) ) ) : ?>
					<p class="description ibantest-credit-status ibantest-credit-status-error" id="ibantest-credit-status"><?php esc_html_e( 'Credit balance could not be refreshed.', 'ibantest-for-woocommerce' ); ?></p>
				<?php elseif ( $overview['error'] && null !== $credits ) : ?>
					<p class="description ibantest-credit-status ibantest-credit-status-error" id="ibantest-credit-status"><?php esc_html_e( 'Automatic refresh failed. Showing cached credit balance.', 'ibantest-for-woocommerce' ); ?></p>
				<?php else : ?>
					<p class="description ibantest-credit-status" id="ibantest-credit-status" hidden></p>
				<?php endif; ?>
				<?php if ( null === $credits ) : ?>
					<p class="remaining-credits remaining-credits-unavailable" id="ibantest-remaining-credits"><?php esc_html_e( 'Not available', 'ibantest-for-woocommerce' ); ?></p>
					<p class="description">
						<?php esc_html_e( 'Add a valid IBANTEST API key and save the settings to show the current credit balance.', 'ibantest-for-woocommerce' ); ?>
					</p>
				<?php else : ?>
					<p class="remaining-credits" id="ibantest-remaining-credits"><?php echo esc_html( number_format_i18n( $credits ) ); ?></p>
				<?php endif; ?>
				<p class="description" id="ibantest-credits-last-updated">
					<?php
					if ( $updated_at ) {
						printf(
							/* translators: %s: localized date and time. */
							esc_html__( 'Last updated: %s', 'ibantest-for-woocommerce' ),
							esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), $updated_at ) )
						);
					} else {
						esc_html_e( 'Last updated: never', 'ibantest-for-woocommerce' );
					}
					?>
				</p>
				<p>
					<a class="button" id="ibantest-refresh-credits" href="<?php echo esc_url( $refresh_url ); ?>">
						<?php esc_html_e( 'Refresh credits now', 'ibantest-for-woocommerce' ); ?>
					</a>
				</p>
				<a class="ibantest-button" href="https://www.ibantest.com/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Buy new credits', 'ibantest-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private function render_onboarding_panel(): void {
		if ( '' !== trim( (string) $this->gateway->get_option( 'apikey', '' ) ) ) {
			return;
		}

		$register_url = 'https://www.ibantest.com/';
		$logo_url     = WC_IBANTEST_PLUGIN_URL . '/assets/img/ibantest-logo.svg';
		?>
		<div class="ibantest-onboarding" id="ibantest-onboarding">
			<div class="ibantest-onboarding-header">
				<img class="ibantest-onboarding-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'IBANTEST', 'ibantest-for-woocommerce' ); ?>" width="260" height="64">
				<p class="ibantest-eyebrow"><?php esc_html_e( 'Setup guide', 'ibantest-for-woocommerce' ); ?></p>
				<h3><?php esc_html_e( 'Connect your IBANTEST account', 'ibantest-for-woocommerce' ); ?></h3>
				<p>
					<?php esc_html_e( 'Add your API key to enable live IBAN validation and show your current credit balance.', 'ibantest-for-woocommerce' ); ?>
				</p>
			</div>
			<ol class="ibantest-setup-steps">
				<li>
					<strong><?php esc_html_e( 'Do you already have an IBANTEST account?', 'ibantest-for-woocommerce' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: %s: IBANTEST registration link. */
							wp_kses_post( __( 'No account yet? Register at IBANTEST and receive <strong>100 free credits</strong>: %s', 'ibantest-for-woocommerce' ) ),
							'<a href="' . esc_url( $register_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Create IBANTEST account', 'ibantest-for-woocommerce' ) . '</a>'
						);
						?>
					</p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Paste your API key', 'ibantest-for-woocommerce' ); ?></strong>
					<p><?php esc_html_e( 'You can find the API key in your IBANTEST account. We will verify it before saving.', 'ibantest-for-woocommerce' ); ?></p>
					<div class="ibantest-api-key-row">
						<input type="password" id="ibantest-onboarding-api-key" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'IBANTEST API key', 'ibantest-for-woocommerce' ); ?>">
						<button type="button" class="button button-primary" id="ibantest-verify-api-key">
							<?php esc_html_e( 'Check and save API key', 'ibantest-for-woocommerce' ); ?>
						</button>
					</div>
					<p class="description ibantest-credit-status" id="ibantest-onboarding-status" hidden></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Start validating IBANs', 'ibantest-for-woocommerce' ); ?></strong>
					<p><?php esc_html_e( 'After a successful check, your key is saved below and the credit overview updates automatically.', 'ibantest-for-woocommerce' ); ?></p>
				</li>
			</ol>
		</div>
		<?php
	}
}
