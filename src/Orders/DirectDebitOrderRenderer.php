<?php

namespace Ibantest\WooCommerce\Orders;

use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DirectDebitOrderRenderer {
	public function __construct( private DirectDebitOrderMeta $meta ) {}

	public function render( \WC_Order $order ): void {
		if ( Plugin::GATEWAY_ID !== $order->get_payment_method() ) {
			return;
		}

		$data       = $this->meta->decrypted_payment_data( $order );
		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'ibantest_export_sepa',
					'order_id' => $order->get_id(),
				],
				admin_url( 'admin-post.php' )
			),
			'ibantest_export_sepa_' . $order->get_id()
		);
		?>
		<div class="order_data_column">
			<h4><?php esc_html_e( 'Direct debit', 'ibantest-for-woocommerce' ); ?></h4>
			<p>
				<?php echo esc_html__( 'IBAN', 'ibantest-for-woocommerce' ) . ': ' . esc_html( $this->meta->hide_iban( $data['iban'] ) ); ?><br>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Download SEPA XML', 'ibantest-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
