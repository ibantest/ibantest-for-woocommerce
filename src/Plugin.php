<?php

namespace Ibantest\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Ibantest\WooCommerce\Admin\AdminAjax;
use Ibantest\WooCommerce\Blocks\PaymentMethodType;
use Ibantest\WooCommerce\Services\Encryption;
use Ibantest\WooCommerce\Services\IbanService;
use Ibantest\WooCommerce\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const GATEWAY_ID  = 'ibantest';
	public const TEXT_DOMAIN = 'ibantest-for-woocommerce';

	private static ?self $instance = null;

	private ?IbanService $iban_service = null;
	private ?Encryption $encryption = null;
	private ?Gateway $gateway = null;
	private ?Ajax $ajax = null;
	private ?AdminAjax $admin_ajax = null;
	private ?SepaExport $export = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function declare_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_IBANTEST_MAIN_FILE, true );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WC_IBANTEST_MAIN_FILE, true );
		}
	}

	public function init(): void {
		load_plugin_textdomain(
			'ibantest-for-woocommerce',
			false,
			plugin_basename( dirname( WC_IBANTEST_MAIN_FILE ) ) . '/languages/'
		);

		add_filter( 'plugin_action_links_' . plugin_basename( WC_IBANTEST_MAIN_FILE ), [ $this, 'plugin_action_links' ] );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'register_blocks_payment_method' ] );

		$this->admin_ajax()->init();
		$this->ajax()->init();
		$this->export()->init();
	}

	/**
	 * @param array<int, string> $methods
	 *
	 * @return array<int, string>
	 */
	public function add_gateway( array $methods ): array {
		$methods[] = Gateway::class;
		return $methods;
	}

	/**
	 * @param array<int, string> $links
	 *
	 * @return array<int, string>
	 */
	public function plugin_action_links( array $links ): array {
		$plugin_links = [
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->settings_url() ),
				esc_html__( 'Settings', 'ibantest-for-woocommerce' )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://www.ibantest.com' ),
				esc_html__( 'Support', 'ibantest-for-woocommerce' )
			),
		];

		return array_merge( $plugin_links, $links );
	}

	public function woocommerce_missing_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'IBANTEST for WooCommerce requires WooCommerce to be installed and active.', 'ibantest-for-woocommerce' )
		);
	}

	public function admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		if ( ! $this->encryption()->is_enabled() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				wp_kses_post(
					sprintf(
						/* translators: %s: wp-config.php constant name. */
						__( 'IBANTEST is inactive until a valid %s constant is configured. Bank account data is never stored without encryption.', 'ibantest-for-woocommerce' ),
						'<code>WC_IBANTEST_ENCRYPTION_KEY</code>'
					)
				)
			);
		}
	}

	public function register_blocks_payment_method( PaymentMethodRegistry $registry ): void {
		$registry->register( new PaymentMethodType( $this ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		$settings = get_option( 'woocommerce_' . self::GATEWAY_ID . '_settings', [] );
		return is_array( $settings ) ? $settings : [];
	}

	public function settings_url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . self::GATEWAY_ID );
	}

	public function gateway(): Gateway {
		if ( null === $this->gateway ) {
			$this->gateway = new Gateway( $this );
		}

		return $this->gateway;
	}

	public function iban_service(): IbanService {
		if ( null === $this->iban_service ) {
			$this->iban_service = new IbanService( $this->settings() );
		}

		return $this->iban_service;
	}

	public function replace_iban_service( IbanService $iban_service ): void {
		$this->iban_service = $iban_service;
	}

	public function encryption(): Encryption {
		if ( null === $this->encryption ) {
			$this->encryption = new Encryption();
		}

		return $this->encryption;
	}

	public function ajax(): Ajax {
		if ( null === $this->ajax ) {
			$this->ajax = new Ajax( $this );
		}

		return $this->ajax;
	}

	public function admin_ajax(): AdminAjax {
		if ( null === $this->admin_ajax ) {
			$this->admin_ajax = new AdminAjax( $this );
		}

		return $this->admin_ajax;
	}

	public function export(): SepaExport {
		if ( null === $this->export ) {
			$this->export = new SepaExport( $this );
		}

		return $this->export;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function gateway_fields(): array {
		return Settings::fields( $this->encryption() );
	}

	private function __construct() {}
}
