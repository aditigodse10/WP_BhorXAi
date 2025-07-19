<?php
/**
 * AI Builder Compatibility for 'SureMail'
 *
 * @see  https://wordpress.org/plugins/sureMail/
 *
 * @package AI Builder
 * @since 1.2.26
 */

namespace AiBuilder\Inc\Compatibility\SureCart;

if ( ! class_exists( 'Ai_Builder_Compatibility_SureMail' ) ) {

	/**
	 * SureMail Compatibility
	 *
	 * @since 1.2.26
	 */
	class Ai_Builder_Compatibility_SureMail {
		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.2.26
		 */
		private static $instance = null;

		/**
		 * Constructor
		 *
		 * @since 1.2.26
		 */
		public function __construct() {
			add_action( 'astra_sites_after_plugin_activation', array( $this, 'activation' ), 10 );
		}

		/**
		 * Initiator
		 *
		 * @since 1.2.26
		 * @return object initialized object of class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Stop the plugin activation redirection.
		 *
		 * @since 1.2.26
		 * @param string $plugin_init Plugin init file.
		 * @return void
		 */
		public function activation( $plugin_init ) {
			if ( 'suremails/suremails.php' === $plugin_init ) {
				update_option( 'suremails_do_redirect', false );
			}
		}
	}

	/**
	 * Kicking this off by calling 'instance()' method
	 */
	Ai_Builder_Compatibility_SureMail::instance();

}
