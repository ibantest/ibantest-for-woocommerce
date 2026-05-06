<?php

namespace {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
	define( 'WC_IBANTEST_VERSION', '2.0.0' );
	define( 'WC_IBANTEST_MAIN_FILE', dirname( __DIR__ ) . '/ibantest-for-woocommerce.php' );
	define( 'WC_IBANTEST_PLUGIN_PATH', dirname( __DIR__ ) );
	define( 'WC_IBANTEST_PLUGIN_URL', 'https://example.test/wp-content/plugins/ibantest-for-woocommerce' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );

	$vendor = WC_IBANTEST_PLUGIN_PATH . '/vendor/autoload.php';
	if ( file_exists( $vendor ) ) {
		require_once $vendor;
	}

	if ( ! defined( 'WC_IBANTEST_ENCRYPTION_KEY' ) && class_exists( \Defuse\Crypto\Key::class ) ) {
		define( 'WC_IBANTEST_ENCRYPTION_KEY', \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString() );
	}

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		class WC_Payment_Gateway {
			public string $id = '';
			public string $title = '';
			public string $description = '';
			public string $enabled = 'yes';
			public string $icon = '';
			public array $supports = [];
			public array $settings = [];
			public array $form_fields = [];
			public bool $has_fields = false;
			public string $method_title = '';
			public string $method_description = '';

			public function init_settings(): void {
				$this->settings = get_option( 'woocommerce_' . $this->id . '_settings', [] );
			}

			public function get_option( string $key, $default = '' ) {
				return $this->settings[ $key ] ?? $default;
			}

			public function get_form_fields() {
				return $this->form_fields;
			}

			public function generate_settings_html( $form_fields = [], $echo = true ) {
				$html = '';
				foreach ( $form_fields as $key => $field ) {
					$html .= '<tr><th>' . ( $field['title'] ?? $key ) . '</th><td><input id="woocommerce_' . $this->id . '_' . $key . '"></td></tr>';
				}

				if ( $echo ) {
					echo $html;
				}

				return $html;
			}

			public function is_available(): bool {
				return true;
			}

			public function get_return_url( $order ): string {
				return 'https://example.test/order/' . $order->get_id();
			}
		}
	}

	if ( ! class_exists( 'WC_Order' ) ) {
		class WC_Order {
			private array $meta = [];

			public function __construct( private int $id = 123 ) {}

			public function get_id(): int {
				return $this->id;
			}

			public function get_order_number(): string {
				return (string) $this->id;
			}

			public function update_meta_data( string $key, $value ): void {
				$this->meta[ $key ] = $value;
			}

			public function get_meta( string $key ) {
				return $this->meta[ $key ] ?? '';
			}

			public function get_billing_email(): string {
				return 'customer@example.test';
			}

			public function save(): void {}

			public function get_payment_method(): string {
				return 'ibantest';
			}

			public function get_total(): string {
				return '12.34';
			}

			public function get_order_received_url(): string {
				return 'https://example.test/order-received';
			}
		}
	}

	if ( ! class_exists( 'WC_Test_Session' ) ) {
		class WC_Test_Session {
			private array $data = [];

			public function get( string $key ) {
				return $this->data[ $key ] ?? false;
			}

			public function set( string $key, $value ): void {
				$this->data[ $key ] = $value;
			}

			public function reset(): void {
				$this->data = [];
			}
		}
	}

	if ( ! class_exists( 'WC_Test_Runtime' ) ) {
		class WC_Test_Runtime {
			public WC_Test_Session $session;

			public function __construct() {
				$this->session = new WC_Test_Session();
			}
		}
	}
}

namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
	if ( ! class_exists( AbstractPaymentMethodType::class ) ) {
		abstract class AbstractPaymentMethodType {
			protected $name = '';
			protected $settings = [];

			public function get_name() {
				return $this->name;
			}

			public function get_supported_features() {
				return [ 'products' ];
			}
		}
	}
}

namespace {
	function __( $text, $domain = null ) {
		return $text;
	}

	function esc_html__( $text, $domain = null ) {
		return $text;
	}

	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, $args );
	}

	function date_i18n( $format, $timestamp = false ) {
		return date( $format, $timestamp ?: time() );
	}

	function wc_date_format() {
		return 'Y-m-d';
	}

	function wc_time_format() {
		return 'H:i';
	}

	function wp_kses_post( $value ) {
		return $value;
	}

	function wpautop( $value ) {
		return '<p>' . str_replace( "\n\n", '</p><p>', $value ) . '</p>';
	}

	function get_option( $key, $default = [] ) {
		return $GLOBALS['ibantest_test_options'][ $key ] ?? $default;
	}

	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['ibantest_test_options'][ $key ] = $value;
		return true;
	}

	function add_action() {}
	function add_filter() {}
	function apply_filters( $hook, $value ) { return $value; }
	function load_plugin_textdomain() {}
	function plugin_basename( $file ) { return basename( $file ); }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
	function add_query_arg( $key, $value = null, $url = null ) {
		if ( is_array( $key ) ) {
			$args = $key;
			$url  = $value;
		} else {
			$args = [ $key => $value ];
		}
		return ( $url ?: '' ) . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
	function esc_url( $value ) { return $value; }
	function esc_html( $value ) { return $value; }
	function esc_attr( $value ) { return $value; }
	function esc_html_e( $value, $domain = null ) { echo $value; }
	function esc_attr_e( $value, $domain = null ) { echo $value; }
	function wp_kses( $value ) { return $value; }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	function wp_unslash( $value ) { return $value; }
	function wc_clean( $value ) { return is_string( $value ) ? trim( $value ) : $value; }
	function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['ibantest_test_notices'][] = [ 'message' => $message, 'type' => $type ]; }
	function WC() {
		if ( ! isset( $GLOBALS['ibantest_test_wc'] ) ) {
			$GLOBALS['ibantest_test_wc'] = new \WC_Test_Runtime();
		}
		return $GLOBALS['ibantest_test_wc'];
	}
	function wp_create_nonce( $action ) { return 'nonce-' . $action; }
	function wp_nonce_url( $url, $action ) { return add_query_arg( '_wpnonce', wp_create_nonce( $action ), $url ); }
	function wp_register_script() {}
	function wp_register_style() {}
	function wp_set_script_translations() {}

	class WC_AJAX {
		public static function get_endpoint( $endpoint ) {
			return 'https://example.test/?wc-ajax=' . $endpoint;
		}
	}

	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Services/Encryption.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Services/IbanService.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Support/Settings.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Support/Mandate.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Admin/AdminAjax.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Admin/SettingsRenderer.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Checkout/CheckoutFields.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Orders/DirectDebitOrderMeta.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Orders/DirectDebitOrderRenderer.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Emails/DirectDebitEmailRenderer.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Plugin.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Gateway.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/SepaExport.php';
	require_once WC_IBANTEST_PLUGIN_PATH . '/src/Blocks/PaymentMethodType.php';
}
