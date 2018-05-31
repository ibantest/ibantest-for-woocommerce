<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_IBANTEST_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_IBANTEST_Gateway extends WC_Payment_Gateway {

	/**
	 * WC_IBANTEST_Gateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'ibantest';
		$this->method_title       = __( 'SEPA Direct Debit (IBANTEST)', 'ibantest-for-woocommerce' );
		$this->method_description = __( 'add SEPA Direct Debit as payment method', 'ibantest-for-woocommerce' );
		$this->has_fields         = false;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->enabled  = $this->get_option( 'enabled' );
		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		$this->logging  = 'yes' === $this->get_option( 'logging' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options',
		) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_order_meta' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'show_thank_you' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_detail_fields' ), 10, 1 );
		add_action( 'woocommerce_email_customer_details', array( $this, 'email_direct_debit' ), 15, 3 );

		// Remove WooCommerce footer text from our settings page.
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );
	}

	/**
	 * Initialise settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = WC_IBANTEST_Settings::fields();
	}

	/**
	 * Checks if method should be available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Add sidebar to the settings page.
	 */
	public function admin_options() {
		ob_start();
		parent::admin_options();
		$parent_options = ob_get_contents();
		ob_end_clean();

		WC_IBANTEST_Sidebar::settings_sidebar( $parent_options );
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'ibantest',
			plugins_url( 'files/js/ibantest-for-woocommerce' . $suffix . '.js', WC_IBANTEST_MAIN_FILE ),
			array( 'jquery', 'wc-cart' ),
			WC_IBANTEST_VERSION,
			true
		);

		$checkout_localize_params = array(
			'validate_iban_url'       => WC_AJAX::get_endpoint( 'ibantest_validate_iban' ),
			'validate_iban_nonce'     => wp_create_nonce( 'ibantest_validate_iban' ),
			'show_sepa_mandate_url'   => WC_AJAX::get_endpoint( 'ibantest_show_sepa_mandate' ),
			'show_sepa_mandate_nonce' => wp_create_nonce( 'ibantest_show_sepa_mandate' ),
		);

		wp_localize_script( 'ibantest', 'ibantest_params', $checkout_localize_params );
		wp_enqueue_script( 'ibantest' );


		if ( is_checkout() ) {
			wp_enqueue_script( 'prettyPhoto', wc()->plugin_url() . '/files/js/prettyPhoto/jquery.prettyPhoto' . $suffix . '.js', array( 'jquery' ), wc()->version, true );
			wp_enqueue_script( 'prettyPhoto-init', wc()->plugin_url() . '/files/js/prettyPhoto/jquery.prettyPhoto.init' . $suffix . '.js', array( 'jquery' ), wc()->version, true );
			wp_enqueue_style( 'woocommerce_prettyPhoto_css', wc()->plugin_url() . '/files/css/prettyPhoto.css' );
		}
	}


	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts( $hook ) {

	}

	/**
	 * Renders Direct Debit form.
	 */
	public function form() {

		$data    = [];
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$data['iban']   = WC_IBANTEST_Encryption()->decrypt( get_user_meta( $user_id, 'direct_debit_iban', true ) );
			$data['bic']    = WC_IBANTEST_Encryption()->decrypt( get_user_meta( $user_id, 'direct_debit_bic', true ) );
			$data['holder'] = WC_IBANTEST_Encryption()->decrypt( get_user_meta( $user_id, 'direct_debit_holder', true ) );
		}

		$fields = array(
			'account-iban'   => '<p class="form-row form-row-wide account-iban">
				<label for="' . esc_attr( $this->id ) . '-account-iban">' . __( 'IBAN', 'ibantest-for-woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-account-iban" class="input-text wc-' . esc_attr( $this->id ) . '-account-iban transform-uppercase" type="text" value="' . ( isset( $data['iban'] ) ? esc_attr( $data['iban'] ) : '' ) . '" autocomplete="off" placeholder="" name="' . esc_attr( $this->id ) . '_account_iban' . '" />
				<span id="' . esc_attr( $this->id ) . '-account-iban-error" class="error-message" style="display: none;"></span>
			</p>',
			'account-bic'    => '<p class="form-row form-row-wide account-bic" style="' . ( isset( $data['bic'] ) ? '' : 'display: none;' ) . '">
				<label for="' . esc_attr( $this->id ) . '-account-bic">' . __( 'BIC/SWIFT', 'ibantest-for-woocommerce' ) . '</label>
				<input id="' . esc_attr( $this->id ) . '-account-bic" class="input-text wc-' . esc_attr( $this->id ) . '-account-bic transform-uppercase" type="text" value="' . ( isset( $data['bic'] ) ? esc_attr( $data['bic'] ) : '' ) . '" autocomplete="off" placeholder="" name="' . esc_attr( $this->id ) . '_account_bic' . '" />
				<span id="' . esc_attr( $this->id ) . '-account-bank" style="display: none;"></span>
			</p>',
			'account-holder' => '<p class="form-row form-row-wide account-holder">
				<label for="' . esc_attr( $this->id ) . '-account-holder">' . __( 'Account Holder', 'ibantest-for-woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-account-holder" class="input-text wc-' . esc_attr( $this->id ) . '-account-holder" value="' . ( isset( $data['holder'] ) ? esc_attr( $data['holder'] ) : '' ) . '" type="text" autocomplete="off" placeholder="" name="' . esc_attr( $this->id ) . '_account_holder' . '" />
			</p>',
		);
		?>
        <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form">
			<?php do_action( 'ibantest_for_woocommerce_form_start', $this->id ); ?>
			<?php
			foreach ( $fields as $field ) {
				echo $field;
			}
			?>
			<?php
			$ajax_url       = wp_nonce_url( add_query_arg( array( 'action' => 'woocommerce_show_sepa_mandate' ), admin_url( 'admin-ajax.php' ) ), 'show_sepa_mandate' );
			$checkbox_label = str_replace( array(
				'[link]',
				'[/link]'
			), array(
				'<a href="' . $ajax_url . '" id="show-sepa-mandate-trigger" rel="sepa-mandate">',
				'</a>'
			), $this->settings['mandate_checkbox_label'] );
			echo '<p class="form-row legal direct-debit-checkbox terms-sepa">
	                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="direct-debit-checkbox">';
			if ( 'yes' == $this->settings['enable_mandate_checkbox'] ) {
				echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="' . esc_attr( $this->id ) . '_mandate_checkbox' . '" id="direct-debit-checkbox" />';
			}
			echo '<span class="' . esc_attr( $this->id ) . '-sepa-terms-checkbox-text">' . $checkbox_label . '</span>
                    </label>
                    <a href="#show-sepa-mandate-pretty-content" rel="prettyPhoto" id="show-sepa-mandate-pretty" style="display: none"></a>
                    <div id="show-sepa-mandate-pretty-content"  style="display: none"></div>
                </p>';

			?>
			<?php do_action( 'ibantest_for_woocommerce_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
		<?php
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		$this->form();
	}

	/**
	 * @return bool|void
	 */
	public function validate_fields() {

		if ( ! $this->is_available() || ! isset( $_POST['payment_method'] ) || $_POST['payment_method'] != $this->id ) {
			return;
		}

		if ( 'yes' == $this->settings['enable_mandate_checkbox'] && ! isset( $_POST['ibantest_mandate_checkbox'] ) ) {
			wc_add_notice( __( 'Please agree to SEPA mandate.', 'ibantest-for-woocommerce' ), 'error' );

			return false;
		}

		$iban   = ( isset( $_POST['ibantest_account_iban'] ) ? wc_clean( $_POST['ibantest_account_iban'] ) : '' );
		$bic    = ( isset( $_POST['ibantest_account_bic'] ) ? wc_clean( $_POST['ibantest_account_bic'] ) : '' );
		$holder = ( isset( $_POST['ibantest_account_holder'] ) ? wc_clean( $_POST['ibantest_account_holder'] ) : '' );

		if ( empty( $iban ) ) {
			wc_add_notice( __( 'Please insert your IBAN.', 'ibantest-for-woocommerce' ), 'error' );

			return false;
		}

		if ( empty( $bic ) ) {
			wc_add_notice( __( 'Please insert your IBAN.', 'ibantest-for-woocommerce' ), 'error' );

			return false;
		}

		if ( empty( $holder ) ) {
			wc_add_notice( __( 'Please insert the account holder.', 'ibantest-for-woocommerce' ), 'error' );

			return false;
		}

		$data = WC_IBANTEST_Service()->validate_iban( $iban );
		if (
			isset( $data['valid'] ) && false == $data['valid'] &&
			isset( $data['message'] ) && false == $data['message']
		) {
			wc_add_notice( $data['message'], 'error' );
		}

		// Validate BIC
		if ( ! preg_match( '/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/', $bic ) ) {
			wc_add_notice( __( 'Your BIC is invalid.', 'ibantest-for-woocommerce' ), 'error' );
		}

		WC()->session->set( 'ibantest', serialize( [ 'iban' => $iban, 'bic' => $bic, 'holder' => $holder ] ) );
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status( 'on-hold', __( 'Awaiting Direct Debit Payment', 'ibantest-for-woocommerce' ) );

		// Check if cart instance exists (frontend request only)
		if ( WC()->cart ) {
			// Remove cart
			WC()->cart->empty_cart();
		}

		// Return thankyou redirect
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);
	}

	/**
	 * add meta data to order
	 *
	 * @param $order_id
	 */
	public function add_order_meta( $order_id ) {

		$order = wc_get_order( $order_id );

		$iban   = isset( $_POST['ibantest_account_iban'] ) ? WC_IBANTEST_Encryption()->encrypt( wc_clean( $_POST['ibantest_account_iban'] ) ) : '';
		$bic    = isset( $_POST['ibantest_account_bic'] ) ? WC_IBANTEST_Encryption()->encrypt( wc_clean( $_POST['ibantest_account_bic'] ) ) : '';
		$holder = isset( $_POST['ibantest_account_holder'] ) ? WC_IBANTEST_Encryption()->encrypt( wc_clean( $_POST['ibantest_account_holder'] ) ) : '';

		$order->update_meta_data( '_direct_debit_iban', $iban );
		$order->update_meta_data( '_direct_debit_bic', $bic );
		$order->update_meta_data( '_direct_debit_holder', $holder );

		$mandate_id = str_replace( '[id]', $order->get_order_number(), $this->settings['mandate_id_format'] );
		$order->update_meta_data( '_direct_debit_mandate_id', $mandate_id );
		$order->update_meta_data( '_direct_debit_mandate_date', current_time( 'timestamp', true ) );
		$order->update_meta_data( '_direct_debit_mandate_mail', $order->get_billing_email() );

		$user_id = $order->get_user_id();
		if ( WC_IBANTEST_Encryption()->is_enabled() && 'yes' == $this->settings['remember'] && $user_id && ! empty( $iban ) ) {
			update_user_meta( $user_id, 'direct_debit_iban', $iban );
			update_user_meta( $user_id, 'direct_debit_bic', $bic );
			update_user_meta( $user_id, 'direct_debit_holder', $holder );
		}

		$order->save();
	}

	/**
	 * show text on order thank you page
	 */
	public function show_thank_you() {
		?>
        <h3><?php esc_html_e( 'Payment notice:', 'ibantest-for-woocommerce' ); ?></h3>
        <p><?php esc_html_e( 'The order amount will be debited directly from your bank account.', 'ibantest-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * show iban in order
	 *
	 * @param WC_Order $order WooCommerce order object.
	 */
	public function order_detail_fields( $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			$iban = WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_iban' ) );
			?>
            <p class="form-field form-field-wide">
                <br/>
            <h3>
				<?php _e( 'Direct debit', 'ibantest-for-woocommerce' ); ?>:
                <a href="<?php echo add_query_arg( array(
					'content'       => 'ibantest-sepa',
					'sepa_order_id' => $order->get_id(),
					'download'      => true
				), admin_url( 'export.php' ) ); ?>" target="_blank"
                   class="download_sepa_xml"><?php _e( 'SEPA XML Download', 'ibantest-for-woocommerce' ); ?></a>
            </h3>
            <p><?php echo __( 'IBAN', 'ibantest-for-woocommerce' ) . ': ' . $this->hide_chars( $iban ) ?> <br/></p>
			<?php
		}
	}

	/**
	 * hide chars
	 *
	 * @param $string
	 * @param string $replacement
	 *
	 * @return string
	 */
	public function hide_chars( $string, $replacement = '*' ) {
		if ( isset( $this->settings['hide_iban_chars'] ) ) {
			switch ( $this->settings['hide_iban_chars'] ) {
				case 'last3':
					$string = str_repeat( $replacement, strlen( $string ) - 3 ) . substr( $string, - 3 );
					break;
				case 'last4':
					$string = str_repeat( $replacement, strlen( $string ) - 4 ) . substr( $string, - 4 );
					break;
				case 'firstlast3':
					$string = substr( $string, 0, 3 ) . str_repeat( $replacement, strlen( $string ) - 6 ) . substr( $string, - 3 );
					break;
				case 'firstlast4':
					$string = substr( $string, 0, 4 ) . str_repeat( $replacement, strlen( $string ) - 8 ) . substr( $string, - 4 );
					break;
			}
		}

		return $string;
	}

	/**
	 * @param $order WC_Order
	 * @param $plain_text
	 * @param $email
	 */
	public function email_direct_debit( $order, $sent_to_admin, $plain_text ) {

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		if ( in_array( $order->get_status(), [ 'completed', 'processing', 'cancelled', 'refunded', 'failed' ] ) ) {
			return;
		}

		$sepa_fields = array(
			__( 'Account Holder', 'ibantest-for-woocommerce' ) => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_holder' ) ),
			__( 'IBAN', 'ibantest-for-woocommerce' )           => $this->hide_chars( WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_iban' ) ) ),
			__( 'BIC/SWIFT', 'ibantest-for-woocommerce' )      => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_bic' ) )
		);

		if ( $sent_to_admin ) {
			$sepa_fields[ __( 'Mandate Reference ID', 'ibantest-for-woocommerce' ) ] = $order->get_meta( '_direct_debit_mandate_id' );
		}
		$debit_date            = $order->get_meta( '_direct_debit_mandate_date' );
		$pre_notification_text = sprintf( __( 'We will debit %s from your account by direct debit on or shortly after %s.', 'ibantest-for-woocommerce' ), wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ), date_i18n( wc_date_format(), $debit_date ) );
		$mandate               = $this->generate_mandate( $order );

		wc_get_template( 'emails/email-sepa-data.php', array(
			'fields'                => $sepa_fields,
			'pre_notification_text' => $pre_notification_text,
			'mandate'               => $mandate,
		), WC_IBANTEST_TEMPLATE_PATH, WC_IBANTEST_TEMPLATE_PATH );
	}

	/**
	 * @param $order WC_Order
	 *
	 * @return string
	 */
	public function generate_mandate( $order ) {
		$params = array(
			'creditor_account_holder' => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_holder' ) ),
			'creditor_account_iban'   => $this->hide_chars( WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_iban' ) ) ),
			'creditor_agent_bic'      => WC_IBANTEST_Encryption()->decrypt( $order->get_meta( '_direct_debit_bic' ) ),
			'street'                  => $order->get_billing_address_1(),
			'postcode'                => $order->get_billing_postcode(),
			'city'                    => $order->get_billing_city(),
			'country'                 => $order->get_billing_country(),
			'mandate_id'              => $order->get_meta( '_direct_debit_mandate_id' ),
		);

		$args = wp_parse_args( $params, array(
			'creditor_name' => $this->settings['creditor_name'],
			'company_info'  => $this->settings['mandate_text'],
			'creditor_id'   => $this->settings['creditor_id'],
			'date'          => $order->get_meta( '_direct_debit_mandate_date' ),
		) );

		$text = __( $this->settings['mandate_text'], 'ibantest-for-woocommerce' );

		foreach ( $args as $key => $val ) {
			$text = str_replace( '[' . $key . ']', $val, $text );
		}

		return nl2br( $text );
	}

	/**
	 * Changes footer text in ibantest settings page.
	 *
	 * @param string $text Footer text.
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ) {
		if ( isset( $_GET['section'] ) && 'ibantest' === $_GET['section'] ) {
			$text = sprintf( __( 'If you like <b>IBANTEST for Woocommerce</b> please leave us a %1$s rating. A huge thanks in advance!', 'ibantest-for-woocommerce' ),
				'<a href="https://wordpress.org/support/plugin/ibantest-for-woocommerce/reviews?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="Thanks">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );
		}

		return $text;
	}

}
