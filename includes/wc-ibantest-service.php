<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_IBANTEST_Service' ) ) {
	/**
	 * handles IBANTEST API calls
	 */
	class WC_IBANTEST_Service {

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
			$this->settings    = IBANTEST_For_WooCommerce()->get_settings();
			$this->ibantestApi = new \Ibantest\Ibantest();
			$this->ibantestApi->setToken( $this->settings['apikey'] );
		}

		/**
		 * get IBANTEST API
		 *
		 * @return \Ibantest\Ibantest
		 */
		public function get_ibantest_api() {
			return $this->ibantestApi;
		}

		/**
		 * get count of remaining credits
		 *
		 * @return array|mixed
		 */
		public function get_remaining_credits() {
			$res              = $this->ibantestApi->getRemainingCredits();
			$remainingCredits = 0;
			if ( $res && ! $res['errorCode'] && isset( $res['credits'] ) ) {
				$remainingCredits = $res['credits'];
			}

			return $remainingCredits;
		}

		/**
		 * check if this iban was already validated
		 *
		 * @param $iban
		 *
		 * @return bool|array
		 */
		public function already_checked( $iban ) {
			try {
				$checked_iban = WC()->session->get( 'iban_' . $iban );
				if ( ! empty( $checked_iban ) ) {
					if ( isset( $checked_iban['date'] ) && $checked_iban['date'] instanceof \DateTime ) {
						$endTime = clone $checked_iban['date'];
						$endTime->add( new \DateInterval( 'PT15M' ) );
						if ( $endTime > new \DateTime() ) {
							return $checked_iban['data'];
						}

					}
				}
			} catch ( \Exception $e ) {

			}

			return false;
		}

		/**
		 * validate IBAN
		 *
		 * @param $iban
		 *
		 * @return array
		 */
		public function validate_iban( $iban ) {
			$data = [];
			$iban = wc_clean( trim( $iban ) );
			if ( ! empty( $iban ) ) {

				if ( ! $res = $this->already_checked( $iban ) ) {
					$res = $this->ibantestApi->validateIban( $iban );
					WC()->session->set( 'iban_' . $iban, [ 'data' => $res, 'date' => new \DateTime() ] );
				}
				if ( $res['valid'] ) {
					$data = [
						'valid' => true,
					];
					if ( isset( $res['bankData'] ) && isset( $res['bankData']['bic'] ) ) {
						$data['bic']      = $res['bankData']['bic'];
						$data['bankName'] = $res['bankData']['description'];
					}
				} else {
					if ( isset( $res["errorCode"] ) ) {
						$data = [
							'error' => true,
						];
					} else {
						$data = [
							'valid' => false,
						];
						if ( isset( $res['checks'] ) ) {
							if ( isset( $res['checks']['ibanLength'] ) && false == $res['checks']['ibanLength'] ) {
								$data['message'] = __( 'IBAN has not the correct length', 'ibantest-for-woocommerce' );
							} elseif ( isset( $res['checks']['ibanChecksum'] ) && false == $res['checks']['ibanChecksum'] ) {
								$data['message'] = __( 'IBAN contains an incorrect checksum', 'ibantest-for-woocommerce' );
							} elseif ( isset( $res['checks']['ibanChecksum'] ) && false == $res['checks']['ibanChecksum'] ) {
								$data['message'] = __( 'IBAN structure is incorrect', 'ibantest-for-woocommerce' );
							} elseif ( isset( $res['checks']['bankAccountSyntaxVerify'] ) && false == $res['checks']['bankAccountSyntaxVerify'] ) {
								$data['message'] = __( 'The account number checksum is incorrect', 'ibantest-for-woocommerce' );
							} elseif ( isset( $res['checks']['bankExistVerify'] ) && false == $res['checks']['bankExistVerify'] ) {
								$data['message'] = __( 'This bank code does not exist', 'ibantest-for-woocommerce' );
							}
						}
					}
				}
			}

			return $data;
		}

		/**
		 * check ibantest API
		 */
		public function check_ibantest_api() {
			$message = null;

			if ( ! $this->settings['apikey'] || empty( $this->settings['apikey'] ) ) {
				$message = __( 'IBANTEST: API Key is not configured', 'ibantest-for-woocommerce' );
			} else {
				$res = $this->ibantestApi->getRemainingCredits();
				if ( isset( $res['errorCode'] ) ) {
					switch ( $res['errorCode'] ) {
						case "4000":
							$message = __( 'IBANTEST: No credits available', 'ibantest-for-woocommerce' );
							break;
						case "5000":
							$setting_link = $this->get_setting_link();
							$message      = sprintf(
								__( 'IBANTEST: verification failed. Please check your API Key %s', 'ibantest-for-woocommerce' ),
								'<a href="' . $setting_link . '">' . __( 'Settings', 'ibantest-for-woocommerce' ) . '</a>'
							);
							break;
						default:
							$message = 'IBANTEST: ' . $res['message'];
							break;
					}
				}
			}
			if ( $message ) {
				?>
                <div class="notice notice-error">
                    <p>
						<?php echo $message ?>
                    </p>
                </div>
				<?php
			}
		}
	}
}

/**
 * Returns the global instance of IBANTEST for WooCommerce
 */
function WC_IBANTEST_Service() {
	return WC_IBANTEST_Service::instance();
}

WC_IBANTEST_Service();


