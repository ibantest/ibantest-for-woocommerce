<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WC_IBANTEST_Sidebar' ) ) {
	/**
	 * Displays information in the backend.
	 */
	class WC_IBANTEST_Sidebar {
		/**
		 * WC_IBANTEST_Sidebar constructor.
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_css' ) );
		}

		/**
		 * Loads admin CSS file, has to be done here instead of gateway class, because
		 * it is required in all admin pages.
		 */
		public function load_admin_css() {
			wp_enqueue_style(
				'ibantest_admin',
				plugins_url( 'files/css/ibantest-admin.css?v=846724456', WC_IBANTEST_MAIN_FILE )
			);
		}

		/**
		 * @param $parent_options
		 */
		public static function settings_sidebar( $parent_options ) {
			$remainingCredits = WC_IBANTEST_Service()->get_remaining_credits();
			?>
            <div class="ibantest-wrapper">
                <div class="ibantest-main">
					<?php echo $parent_options; ?>
                </div>
                <div class="ibantest-sidebar">
                    <img class="ibantest-settings-logo"
                         src="<?php echo esc_url( WC_IBANTEST_ASSETS_URL ); ?>/img/ibantest_logo.gif" height="45"/>
                    <div class="ibantest-sidebar-panel">
                        <h3><?php echo __( 'Remaining credits', 'ibantest-for-woocommerce' ) ?></h3>
                        <p class="remaining-credits"><?php echo number_format( $remainingCredits, 0, ',', '.' ) ?></p>
                        <a class="ibantest-button" href="https://www.ibantest.com/" target="_blank">Buy new credits</a>
                    </div>
                </div>
            </div>

			<?php
		}
	}

	new WC_IBANTEST_Sidebar();
}

