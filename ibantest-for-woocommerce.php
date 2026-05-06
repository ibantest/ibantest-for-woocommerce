<?php
/**
 * Plugin Name: IBANTEST for WooCommerce
 * Plugin URI: https://www.ibantest.com/
 * Description: Provides SEPA direct debit payments with IBAN and BIC validation for WooCommerce.
 * Version: 2.0.0
 * Author: IBANTEST
 * Author URI: https://www.ibantest.com
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Tested up to: 6.9
 * WC requires at least: 10.0
 * WC tested up to: 10.7
 * Requires Plugins: woocommerce
 *
 * Text Domain: ibantest-for-woocommerce
 * Domain Path: /languages/
 */

use Ibantest\WooCommerce\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_IBANTEST_VERSION', '2.0.0' );
define( 'WC_IBANTEST_MAIN_FILE', __FILE__ );
define( 'WC_IBANTEST_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_IBANTEST_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

$ibantest_autoload = WC_IBANTEST_PLUGIN_PATH . '/vendor/autoload.php';
if ( file_exists( $ibantest_autoload ) ) {
	require_once $ibantest_autoload;
}

// Prefer Composer's optimized autoloader; keep this fallback for source installs without vendor/autoload.php.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Ibantest\\WooCommerce\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = WC_IBANTEST_PLUGIN_PATH . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'before_woocommerce_init',
	static function (): void {
		Plugin::declare_compatibility();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->init();
	}
);
