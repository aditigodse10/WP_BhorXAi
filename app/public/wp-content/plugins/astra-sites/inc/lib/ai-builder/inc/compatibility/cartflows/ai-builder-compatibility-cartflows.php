<?php
/**
 * AI Builder Compatibility for 'Cartflows'
 *
 * @package AI Builder
 * @since 3.4.6
 */

namespace AiBuilder\Inc\Compatibility\Cartflows;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Ai_Builder_Compatibility_Cartflows' ) ) {

	/**
	 * Cartflows Compatibility
	 *
	 * @since 3.4.6
	 */
	class Ai_Builder_Compatibility_Cartflows {
		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 3.4.6
		 */
		private static $instance = null;

		/**
		 * Constructor
		 *
		 * @since 3.4.6
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'disable_cartflows_redirect' ), 2 );
		}

		/**
		 * Initiator
		 *
		 * @since 3.4.6
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Disable Cartflows redirect.
		 *
		 * @param string               $plugin_init Plugin init file used for activation.
		 * @param array<string, mixed> $data data.
		 * @return void
		 */
		public function disable_cartflows_redirect( $plugin_init, $data = array() ) {
			if ( 'cartflows/cartflows.php' === $plugin_init ) {
				update_option( 'wcf_setup_skipped', true );
				delete_option( 'wcf_start_onboarding' );
			}
		}
	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Ai_Builder_Compatibility_Cartflows::get_instance();

}
