<?php
/**
 * Plugin Name: IBANTEST for WooCommerce
 * Plugin URI: https://www.ibantest.com/
 * Description: Provides direct debit payment with IBAN and BIC validation for WooCommerce
 * Version: 1.0.0
 * Author: IBANTEST
 * Author URI: https://www.ibantest.com
 * Requires at least: 3.8
 * Requires PHP: 5.6.0
 * Tested up to: 4.9
 * WC requires at least: 3.0
 * WC tested up to: 3.3
 * Requires at least WooCommerce: 3.0
 * Tested up to WooCommerce: 3.3
 *
 * Text Domain: woocommerce-ibantest
 * Domain Path: /languages/
 *
 * @author IBANTEST
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Required minimums and constants
 */
define( 'WC_IBANTEST_VERSION', '1.0.0' );
define( 'WC_IBANTEST_MIN_PHP_VER', '5.6.0' );
define( 'WC_IBANTEST_MIN_WC_VER', '3.0.0' );
define( 'WC_IBANTEST_MAIN_FILE', __FILE__ );
define( 'WC_IBANTEST_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_IBANTEST_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WC_IBANTEST_ASSETS_URL', untrailingslashit( WC_IBANTEST_PLUGIN_URL . '/files' ) );
define( 'WC_IBANTEST_TEMPLATE_PATH', WC_IBANTEST_PLUGIN_PATH . '/templates/' );

if ( ! class_exists( 'WooCommerce_IBANTEST' ) ) {

	class WooCommerce_IBANTEST {

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
		 * Settings
		 *
		 * @var $settings
		 */
		public $settings = array();

		/**
		 * WooCommerce_IBANTEST constructor.
		 */
		public function __construct() {
			$this->settings = get_option( 'woocommerce_ibantest_settings' );
			add_action( 'init', array( $this, 'init' ), 0 );

			// Loaded action
			do_action( 'woocommerce_ibantest_loaded' );
		}

		/**
		 * Init
		 */
		public function init() {
			do_action( 'before_woocommerce_ibantest_init' );
			include_once( 'includes/wc-ibantest-ajax.php' );
			include_once( 'includes/wc-ibantest-encryption.php' );
			include_once( 'includes/wc-ibantest-export.php' );
			include_once( 'includes/wc-ibantest-service.php' );
			include_once( 'includes/wc-ibantest-settings.php' );
			include_once( 'includes/wc-ibantest-sidebar.php' );
			include_once( 'includes/wc-ibantest-gateway.php' );

			add_action( 'admin_notices', array( WC_IBANTEST_Service(), 'check_ibantest_api' ), 0 );

			add_action( 'export_wp', array( WC_IBANTEST_Export(), 'export' ), 0, 1 );
			add_filter( 'export_args', array( WC_IBANTEST_Export(), 'export_args' ) );

			load_plugin_textdomain( 'woocommerce-ibantest', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			// Payment gateways
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			// Init action
			do_action( 'woocommerce_ibantest_init' );
		}

		/**
		 * getter for settings
		 *
		 * @return array|
		 */
		public function get_settings() {
			return $this->settings;
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-ibantest' ) . '</a>',
				'<a href="https://www.ibantest.com">' . __( 'Support', 'woocommerce-ibantest' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$section_slug = 'ibantest';

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @param  array $methods Payment methods.
		 *
		 * @return array $methods Payment methods.
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_IBANTEST_Gateway';

			return $methods;
		}
	}
}

/**
 * Returns the global instance of WooCommerce IBANTEST
 */
function WooCommerce_IBANTEST() {
	return WooCommerce_IBANTEST::instance();
}

WooCommerce_IBANTEST();

