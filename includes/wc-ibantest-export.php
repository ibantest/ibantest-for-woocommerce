<?php

use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Digitick\Sepa\PaymentInformation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_IBANTEST_Export' ) ) {
	/**
	 * handles IBANTEST exports
	 */
	class WC_IBANTEST_Export {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * @var \Ibantest\Ibantest
		 */
		public $ibantestApi;

		/**
		 * Settings
		 *
		 * @var $settings
		 */
		public $settings = array();

		/**
		 * WC_IBANTEST_Service constructor.
		 */
		public function __construct() {
			include_once WC_IBANTEST_PLUGIN_PATH . '/vendor/autoload.php';
			$this->settings = IBANTEST_For_WooCommerce()->get_settings();
		}

		/**
		 * @param array $args
		 *
		 * @return array
		 */
		public function export_args( $args = array() ) {
			if ( 'ibantest-sepa' === $_GET['content'] ) {
				$args['content']  = 'ibantest-sepa';
				$args['order_id'] = absint( $_GET['sepa_order_id'] );
			}

			return $args;
		}

		/**
		 * @param array $args
		 */
		public function export( $args = array() ) {
			if (
				$args['content'] != 'ibantest-sepa' ||
				empty( $args['order_id'] )
			) {
				return;
			}

			$order = wc_get_order( $args['order_id'] );

			// Set the initial information
			// third parameter 'pain.008.003.02' is optional would default to 'pain.008.002.02' if not changed
			$directDebit = TransferFileFacadeFactory::createDirectDebit(
				$this->settings['creditor_agent_bic'] . '-' . date( 'Ymd-His' ),
				$this->settings['creditor_name'],
				$this->settings['sepa_xml_format']
			);

			$paymentName = 'PMT-ID-' . date( 'Ymd-His' );
			$directDebit->addPaymentInfo(
				$paymentName,
				array(
					'id'                  => $paymentName,
					'dueDate'             => new \DateTime( 'now + 0 days' ),
					'creditorName'        => $this->settings['creditor_account_holder'],
					'creditorAccountIBAN' => $this->settings['creditor_account_iban'],
					'creditorAgentBIC'    => $this->settings['creditor_agent_bic'],
					'creditorId'          => $this->settings['creditor_id'],
					'seqType'             => PaymentInformation::S_ONEOFF,
					'localInstrumentCode' => 'CORE' // default. optional.
				)
			);
			$directDebit->addTransfer(
				$paymentName,
				array(
					'amount'                => $order->get_total(),
					'debtorIban'            => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_iban' ) ),
					'debtorBic'             => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_bic' ) ),
					'debtorName'            => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_holder' ) ),
					'debtorMandate'         => $order->get_meta( '_direct_debit_mandate_id' ) ,
					'debtorMandateSignDate' => new \DateTime( date('Y-m-d', $order->get_meta( '_direct_debit_mandate_date' ) ) ),
					'remittanceInformation' => sprintf( __( 'Order %s', 'ibantest-for-woocommerce' ), $order->get_order_number() ),
				)
			);

			$filename    = 'SEPA-Export-order-' . (int)$args['order_id'] . '.xml';

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $directDebit->asXML();

			exit();
		}

	}
}

/**
 * Returns the global instance of IBANTEST for WooCommerce
 */
function WC_IBANTEST_Export() {
	return WC_IBANTEST_Export::instance();
}

WC_IBANTEST_Export();


