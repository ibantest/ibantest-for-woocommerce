<?php

use Defuse\Crypto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_IBANTEST_Encryption' ) ) {
	/**
	 * handles encryption
	 */
	class WC_IBANTEST_Encryption {

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
		 * WC_IBANTEST_Encryption constructor.
		 */
		public function __construct() {
		}

		/**
		 * generate random key
		 *
		 * @return string
		 */
		public function generate_random_key() {
			$key = Crypto\Key::createNewRandomKey();

			return $key->saveToAsciiSafeString();
		}

		/**
		 * get encryption key
		 *
		 * @return bool|Crypto\Key
		 */
		private function get_key() {
			if ( defined( 'WC_IBANTEST_ENCRYPTION_KEY' ) ) {
				$key = trim( WC_IBANTEST_ENCRYPTION_KEY );
				if ( ! empty( $key ) ) {
					return Crypto\Key::loadFromAsciiSafeString( $key );
				}
			}

			return false;
		}

		/**
		 * check if key is set
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return ( $this->get_key() ? true : false );
		}


		/**
		 * encrypt string
		 *
		 * @param $string
		 *
		 * @return string
		 */
		public function encrypt( $string ) {
			if ( ! $this->is_enabled() || empty( trim( $string ) ) ) {
				return $string;
			}

			return Crypto\Crypto::encrypt( $string, $this->get_key() );
		}

		/**
		 * descript string
		 *
		 * @param $string
		 *
		 * @return string
		 */
		public function decrypt( $string ) {

			if ( ! $this->is_enabled() || empty( trim( $string ) ) ) {
				return $string;
			}
			try {
				$string = Crypto\Crypto::decrypt( $string, $this->get_key() );
			} catch ( \Exception $e ) {

			}

			return $string;
		}
	}
}

/**
 * Returns the global instance of IBANTEST for WooCommerce
 */
function WC_IBANTEST_Encryption() {
	return WC_IBANTEST_Encryption::instance();
}

WC_IBANTEST_Encryption();


